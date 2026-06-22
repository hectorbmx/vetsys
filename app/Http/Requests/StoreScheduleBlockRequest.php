<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-appointment-configuration') ?? false;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
