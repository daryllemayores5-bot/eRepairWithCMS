<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindTechniciansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'device_type' => ['nullable', 'integer'],
            'specialization' => ['nullable', 'integer'],
            'repair_category' => ['nullable', 'integer', Rule::exists('repair_categories', 'id')->where('active', true)],
            'availability' => ['nullable', 'in:available,busy,unavailable'],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'sort' => ['nullable', 'in:recommended,rating,experience,distance'],
            'page' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['required_if:sort,distance', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:sort,distance', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
