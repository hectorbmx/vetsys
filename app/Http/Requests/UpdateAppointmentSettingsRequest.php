<?php

namespace App\Http\Requests;

use App\Enums\AppointmentCancellationPolicy;
use App\Enums\AppointmentLateFeeCollectionMethod;
use App\Enums\AppointmentLateFeeType;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAppointmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-appointment-configuration') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Convertir campos de horas a minutos para la BD
        if ($this->has('minimum_notice_hours')) {
            $this->merge([
                'minimum_notice_minutes' => (int) $this->input('minimum_notice_hours', 0) * 60,
            ]);
        }

        if ($this->has('customer_cancellation_notice_hours')) {
            $this->merge([
                'customer_cancellation_notice_minutes' => (int) $this->input('customer_cancellation_notice_hours', 0) * 60,
            ]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'doctor_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'slot_interval_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'minimum_notice_minutes' => ['required', 'integer', 'min:0'],
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'customer_cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'customer_cancellation_notice_minutes' => ['required', 'integer', 'min:0'],
            'proposal_hold_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'reminder_hours_before' => ['required', 'integer', 'min:1', 'max:168'],
            'cancellation_policy' => ['required', new Enum(AppointmentCancellationPolicy::class)],
            'late_fee_type' => ['nullable', new Enum(AppointmentLateFeeType::class)],
            'late_fee_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'late_fee_collection_method' => ['nullable', new Enum(AppointmentLateFeeCollectionMethod::class)],
            'late_fee_catalog_item_id' => [
                'nullable',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'service')),
            ],
            'is_customer_booking_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('late_fee_type') && ! $this->filled('late_fee_value')) {
                $validator->errors()->add('late_fee_value', 'Indica el valor sugerido del cargo tardio.');
            }

            if (
                $this->input('late_fee_type') === AppointmentLateFeeType::Percentage->value
                && (float) $this->input('late_fee_value', 0) > 100
            ) {
                $validator->errors()->add('late_fee_value', 'El porcentaje no puede superar 100.');
            }
        });
    }
}
