<?php

namespace App\Http\Requests;

use App\Enums\PushPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('platform')) {
            $this->merge(['platform' => strtolower((string) $this->input('platform'))]);
        }
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', new Enum(PushPlatform::class)],
            'token' => ['required', 'string', 'max:4096'],
            'device_uuid' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
