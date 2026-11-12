<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CmsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SmtpDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ?Process $smtp = null;

    private ?string $readyFile = null;

    private array $messages = [];

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires the guarded disposable PostgreSQL test database.');
        }
        parent::setUp();
        $node = (new ExecutableFinder)->find('node');
        $this->assertNotNull($node, 'Node is required for the loopback SMTP fixture.');
        $this->readyFile = tempnam(sys_get_temp_dir(), 'erepair-smtp-');
        $this->smtp = new Process([$node, base_path('tests/Fixtures/smtp-server.mjs'), $this->readyFile], base_path());
        $this->smtp->start();
        $port = null;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $metadata = json_decode(file_get_contents($this->readyFile), true);
            if (isset($metadata['port'])) { $port = $metadata['port']; break; }
            if (! $this->smtp->isRunning()) { break; }
            usleep(50000);
        }
        $this->assertIsInt($port, 'The local SMTP fixture must start before sending test mail.');
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'erepair@example.test',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1', 'port' => $port, 'username' => null, 'password' => null, 'timeout' => 5],
            'sanctum.stateful' => ['127.0.0.1:5173'],
        ]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        Event::listen(MessageSent::class, function (MessageSent $event) {
            $this->messages[] = $event->message->getTextBody();
        });
    }

    protected function tearDown(): void
    {
        if ($this->smtp) {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (method_exists($transport, 'stop')) { $transport->stop(); }
            $metadata = $this->readyFile && is_file($this->readyFile) ? json_decode(file_get_contents($this->readyFile), true) : null;
            if (isset($metadata['port'], $metadata['control']) && $this->smtp->isRunning()) {
                $socket = @stream_socket_client('tcp://127.0.0.1:'.$metadata['port'], $errorCode, $errorMessage, 1);
                if ($socket) {
                    stream_set_timeout($socket, 1);
                    fgets($socket); // Drain the greeting before closing, avoiding a TCP reset.
                    fwrite($socket, 'XEREPAIR-STOP '.$metadata['control']."\r\n");
                    fgets($socket);
                    fclose($socket);
                }
            }
            for ($attempt = 0; $attempt < 350 && $this->smtp->isRunning(); $attempt++) { usleep(50000); }
            $this->assertFalse($this->smtp->isRunning(), 'The owned SMTP fixture must shut down without an OS taskkill.');
        }
        if ($this->readyFile && is_file($this->readyFile)) { unlink($this->readyFile); }
        parent::tearDown();
    }

    private function resetLink(string $pathPrefix): string
    {
        $this->assertSame(1, count($this->messages));
        $metadata = json_decode(file_get_contents($this->readyFile), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $metadata['accepted'], 'The SMTP server must accept the actual message.');
        preg_match_all('~https?://[^\s<>]+~', (string) $this->messages[0], $matches);
        foreach ($matches[0] as $candidate) {
            $candidate = html_entity_decode(rtrim($candidate, '.)]'));
            if (str_starts_with((string) parse_url($candidate, PHP_URL_PATH), $pathPrefix)) { return $candidate; }
        }
        $this->fail('The delivered email did not contain the expected reset form path.');
    }

    public function test_customer_reset_email_is_delivered_over_smtp_and_its_link_resets_only_that_account(): void
    {
        $user = User::factory()->create(['email' => 'smtp-customer@example.test']);
        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        $url = $this->resetLink('/reset-password');
        $this->assertSame('/reset-password', parse_url($url, PHP_URL_PATH));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($user->email, $query['email']);
        $this->postJson('/api/v1/reset-password', [...$query, 'password' => 'NewCustomerPassword123!', 'password_confirmation' => 'NewCustomerPassword123!'])->assertOk();
        $this->assertTrue(Hash::check('NewCustomerPassword123!', $user->fresh()->password));
    }

    public function test_core_reset_email_is_delivered_over_smtp_with_an_independent_native_form(): void
    {
        $operator = User::factory()->create(['role' => 'admin', 'super' => true, 'email' => 'smtp-editor@example.test']);
        $cms = app(CmsAccount::class)->provision($operator);
        $this->postJson('/cp/auth/password/email', ['email' => $cms->email])->assertOk();
        $url = $this->resetLink('/cp/auth/password/reset/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($cms->email, $query['email']);
        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $this->postJson('/cp/auth/password/reset', ['email' => $cms->email, 'token' => $token, 'password' => 'NewEditorPassword123!', 'password_confirmation' => 'NewEditorPassword123!'])->assertOk();
        $this->assertTrue(Hash::check('NewEditorPassword123!', $cms->fresh()->password));
        $this->assertTrue(Hash::check('password', $operator->fresh()->password));
    }
}
