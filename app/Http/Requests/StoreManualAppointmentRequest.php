<?php

namespace App\Http\Requests;

class StoreManualAppointmentRequest extends TenantAppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIdempotencyKey();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'animal_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'customer_reason' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => $this->idempotencyRules(),
        ];
    }
}
