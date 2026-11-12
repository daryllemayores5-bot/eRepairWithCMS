<?php

namespace App\Http\Requests;

class ForgotPasswordRequest extends AuthenticationRequest
{
    public function rules(): array
    {
        return ['email' => ['bail', 'required', 'string', 'email', 'max:255']];
    }
}
