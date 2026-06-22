<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-appointment-configuration') ?? false;
    }

    public function rules(): array
    {
        return [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ];
    }
}
