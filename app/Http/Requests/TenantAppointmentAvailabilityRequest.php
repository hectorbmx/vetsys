<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class TenantAppointmentAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-appointments') ?? false;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('from') || ! $this->filled('to')) {
                return;
            }

            if (CarbonImmutable::parse($this->string('from'))->diffInDays(
                CarbonImmutable::parse($this->string('to')),
            ) + 1 > 31) {
                $validator->errors()->add('to', 'El rango no puede superar 31 dias.');
            }
        });
    }
}
