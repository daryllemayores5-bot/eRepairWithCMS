<?php

namespace App\Http\Requests;

class ResetPasswordRequest extends AuthenticationRequest
{
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'string', 'min:10', 'regex:/[a-zA-Z]/', 'regex:/[0-9]/'],
        ];
    }
}
