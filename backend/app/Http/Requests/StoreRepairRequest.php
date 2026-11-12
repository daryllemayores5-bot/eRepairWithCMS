<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        return [
            'technician_profile_id' => ['required', 'integer', 'exists:technician_profiles,id'],
            'device_type_id' => ['required', 'integer', Rule::exists('device_types', 'id')->where('active', true)],
            'device_brand_id' => ['required', 'integer', Rule::exists('device_brands', 'id')->where('active', true)],
            'repair_category_id' => ['required', 'integer', Rule::exists('repair_categories', 'id')->where('active', true)],
            'model' => ['required', 'string', 'max:150'], 'serial_number' => ['nullable', 'string', 'max:150'],
            'problem_description' => ['required', 'string', 'min:15', 'max:5000'],
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'service_location' => ['required', 'string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:5'], 'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
