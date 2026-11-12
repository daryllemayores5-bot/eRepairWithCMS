<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'technician';
    }

    public function rules(): array
    {
        return [
            'biography' => ['required', 'string', 'min:30', 'max:4000'], 'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'city' => ['required', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'],
            'availability' => ['required', Rule::in(['available', 'busy', 'unavailable'])],
            'specialization_ids' => ['required', 'array', 'min:1'], 'specialization_ids.*' => ['integer', 'distinct', Rule::exists('specializations', 'id')->where('active', true)],
            'device_type_ids' => ['required', 'array', 'min:1'], 'device_type_ids.*' => ['integer', 'distinct', Rule::exists('device_types', 'id')->where('active', true)],
            'service_areas' => ['nullable', 'array', 'max:10'], 'service_areas.*.city' => ['required', 'string', 'max:100'],
            'service_areas.*.province' => ['required', 'string', 'max:100'],
            'verification_status' => ['prohibited'], 'verified_by' => ['prohibited'], 'latitude' => ['prohibited'], 'longitude' => ['prohibited'],
        ];
    }
}
