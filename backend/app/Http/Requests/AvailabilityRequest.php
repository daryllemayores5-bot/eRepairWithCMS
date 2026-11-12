<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'technician';
    }

    public function rules(): array
    {
        return ['availability' => ['required', Rule::in(['available', 'busy', 'unavailable'])]];
    }
}
