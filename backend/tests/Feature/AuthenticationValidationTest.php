<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationValidationTest extends TestCase
{
    public static function invalidEmails(): array
    {
        $cases = [];
        foreach (['register', 'login', 'forgot-password', 'reset-password'] as $endpoint) {
            foreach (['array' => ['person@example.test'], 'nested' => ['address' => 'person@example.test'], 'number' => 123, 'boolean' => true, 'null' => null, 'invalid string' => 'not-an-email'] as $label => $email) {
                $cases[$endpoint.' '.$label] = [$endpoint, $email];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidEmails')]
    public function test_invalid_email_returns_validation_errors_without_server_errors(string $endpoint, mixed $email): void
    {
        $this->postJson('/api/v1/'.$endpoint, [
            'name' => 'Ana Reyes', 'email' => $email, 'role' => 'customer',
            'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!', 'token' => 'invalid-reset-token',
        ])->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
