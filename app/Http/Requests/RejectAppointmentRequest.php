<?php

namespace App\Http\Requests;

class RejectAppointmentRequest extends TenantAppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIdempotencyKey();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => $this->idempotencyRules(),
        ];
    }
}
