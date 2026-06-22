<?php

namespace App\Http\Requests;

class FinishAppointmentRequest extends TenantAppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIdempotencyKey();
    }

    public function rules(): array
    {
        return ['idempotency_key' => $this->idempotencyRules()];
    }
}
