<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class TenantAppointmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-appointments') ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'view' => ['nullable', Rule::in(['day', 'week'])],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [new Enum(AppointmentStatus::class)],
            'customer_id' => ['nullable', 'integer'],
            'animal_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
