<?php

namespace App\Http\Requests;

class ProposeAppointmentRequest extends TenantAppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIdempotencyKey();
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'message' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => $this->idempotencyRules(),
        ];
    }
}
