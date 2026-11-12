<?php

namespace App\Http\Requests;

class LoginRequest extends AuthenticationRequest
{
    public function rules(): array
    {
        return ['email' => ['bail', 'required', 'string', 'email', 'max:255'], 'password' => ['required', 'string']];
    }
}
