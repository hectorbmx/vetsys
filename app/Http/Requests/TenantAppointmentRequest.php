<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class TenantAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate-appointments') ?? false;
    }

    protected function prepareIdempotencyKey(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key') ?: $this->input('idempotency_key'),
        ]);
    }

    protected function idempotencyRules(): array
    {
        return ['required', 'string', 'max:120'];
    }
}
