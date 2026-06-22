<?php

namespace App\Http\Requests;

class ConfirmAppointmentRequest extends TenantAppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIdempotencyKey();
    }

    public function rules(): array
    {
        return [
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => $this->idempotencyRules(),
        ];
    }
}
