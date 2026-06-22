<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookableServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-appointment-configuration') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_bookable' => ['nullable', 'boolean'],
            'appointment_duration_minutes' => [
                'nullable',
                'required_if:is_bookable,1',
                'integer',
                'min:5',
                'max:480',
            ],
            'appointment_buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'booking_description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
