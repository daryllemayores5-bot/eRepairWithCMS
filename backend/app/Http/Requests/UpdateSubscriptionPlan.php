<?php

namespace App\Http\Requests;

use App\Services\CmsAccount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlan extends FormRequest
{
    public function authorize(): bool
    {
        $operator = CmsAccount::identity($this->user('cp'))?->operator;

        return $operator?->role === 'admin' && $operator->super && $operator->account_status === 'active';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/D'],
            'features' => ['required', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
