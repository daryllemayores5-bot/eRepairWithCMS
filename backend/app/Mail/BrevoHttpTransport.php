<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/** Transactional HTTPS mail for hosts that block outbound SMTP ports. */
class BrevoHttpTransport extends AbstractTransport
{
    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo-http';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || $this->key === '' || count($email->getAttachments()) > 0 || count($email->getReplyTo()) > 1) {
            throw new TransportException('Unsupported email or missing transactional mail configuration.');
        }
        $address = fn (Address $item) => ['email' => $item->getAddress(), 'name' => $item->getName()];
        $payload = ['sender' => $address($email->getFrom()[0] ?? $message->getEnvelope()->getSender()), 'subject' => $email->getSubject() ?? ''];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $kind => $recipients) {
            if ($recipients !== []) {
                $payload[$kind] = array_map($address, $recipients);
            }
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }
        try {
            // No automatic retries: an ambiguous send must not duplicate a reset email.
            $response = Http::withHeaders(['api-key' => $this->key])->acceptJson()
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (Throwable $error) {
            $previous = $error->getPrevious();
            $context = $previous && method_exists($previous, 'getHandlerContext') ? $previous->getHandlerContext() : [];
            Log::warning('Transactional email request failed.', [
                'failure_class' => $error::class,
                'network_errno' => is_int($context['errno'] ?? null) ? $context['errno'] : null,
            ]);
            // Do not log provider bodies, recipients, reset URLs or authentication keys.
            throw new TransportException('Transactional email could not be sent.');
        }
        if ($response->status() !== 201 || ! is_string($response->json('messageId'))) {
            $code = $response->json('code');
            $knownCodes = ['invalid_parameter', 'missing_parameter', 'unauthorized', 'permission_denied', 'not_enough_credits', 'account_under_validation', 'account_not_enabled', 'bad_request'];
            $message = (string) $response->json('message', '');
            $category = match (true) {
                (bool) preg_match('/\bip\b|whitelist|allowlist/i', $message) => 'ip_authorization',
                (bool) preg_match('/sender|domain/i', $message) => 'sender',
                (bool) preg_match('/permission|resource|api.?key/i', $message) => 'authorization',
                (bool) preg_match('/quota|credit/i', $message) => 'quota',
                default => 'unclassified',
            };
            Log::warning('Transactional email provider rejected the request.', [
                'http_status' => $response->status(),
                'provider_code' => in_array($code, $knownCodes, true) ? $code : 'unclassified',
                'category' => $category,
            ]);
            throw new TransportException('Transactional email could not be sent.');
        }
    }
}
