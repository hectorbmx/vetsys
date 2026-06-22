<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'animal_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer_reason' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
