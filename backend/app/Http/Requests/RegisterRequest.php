<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends AuthenticationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'email' => ['bail', 'required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'role' => ['required', Rule::in(['customer', 'technician'])],
            'city' => ['required_if:role,technician', 'nullable', 'string', 'max:100'],
            'province' => ['required_if:role,technician', 'nullable', 'string', 'max:100'],
            'super' => ['prohibited'], 'permissions' => ['prohibited'], 'account_status' => ['prohibited'],
        ];
    }
}
