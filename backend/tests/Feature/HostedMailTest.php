<?php

namespace Tests\Feature;

use App\Mail\BrevoHttpTransport;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class HostedMailTest extends TestCase
{
    public function test_password_reset_notification_keeps_the_canonical_frontend_link(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<reset-message>'], 201)]);
        config(['mail.default' => 'brevo-http', 'mail.mailers.brevo-http.key' => 'private-test-key', 'mail.from.address' => 'hello@erepair.test', 'erepair.frontend_url' => 'https://erepair.school.edu.ph']);
        $user = new User(['name' => 'Customer', 'email' => 'customer@erepair.test']);
        $user->sendPasswordResetNotification('test-reset-token');
        Http::assertSent(function ($request) {
            $body = html_entity_decode($request['htmlContent']);

            return str_contains($body, 'https://erepair.school.edu.ph/reset-password?token=test-reset-token')
                && str_contains($body, 'customer%40erepair.test');
        });
    }

    public function test_real_mail_manager_sends_a_message_through_https_without_an_extra_sdk(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<test-message>'], 201)]);
        config(['mail.default' => 'brevo-http', 'mail.mailers.brevo-http.key' => 'private-test-key', 'mail.from.address' => 'hello@erepair.test']);
        Mail::raw('Reset your password securely.', fn ($mail) => $mail->to('customer@erepair.test')->subject('Reset password'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'private-test-key')
            && $request['to'][0]['email'] === 'customer@erepair.test'
            && $request['textContent'] === 'Reset your password securely.');
        Http::assertSentCount(1);
    }

    public function test_provider_failure_does_not_expose_secrets_or_retry_a_send(): void
    {
        Http::fake(['*' => Http::response(['error' => 'private-test-key'], 429)]);
        $transport = new BrevoHttpTransport('private-test-key');
        try {
            $transport->send((new Email)->from('hello@erepair.test')->to('user@erepair.test')->subject('Reset')->text('private reset token'));
            $this->fail('Provider failure accepted.');
        } catch (TransportException $error) {
            $this->assertSame('Transactional email could not be sent.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        Http::assertSentCount(1);
    }
}
