<?php

namespace App\Http\Requests;

use App\Models\TechnicianSubscription;
use Illuminate\Foundation\Http\FormRequest;

class RequestDemoSubscription extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('request', TechnicianSubscription::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'expected_price' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/D'],
            'confirm_demo' => ['required', 'accepted'],
            'plan_slug' => ['sometimes', 'required', 'in:pro,premium'],
            'technician_id' => ['prohibited'], 'status' => ['prohibited'],
            'starts_at' => ['prohibited'], 'expires_at' => ['prohibited'],
        ];
    }
}
