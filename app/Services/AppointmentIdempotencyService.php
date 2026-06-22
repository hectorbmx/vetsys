<?php

namespace App\Services;

use App\Exceptions\AppointmentDomainException;
use App\Models\Appointment;
use App\Models\AppointmentIdempotencyKey;
use App\Models\AppointmentProposal;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AppointmentIdempotencyService
{
    public function execute(
        Tenant $tenant,
        User $actor,
        string $operation,
        ?string $idempotencyKey,
        array $payload,
        Closure $callback,
    ): Model {
        return DB::transaction(function () use (
            $tenant,
            $actor,
            $operation,
            $idempotencyKey,
            $payload,
            $callback,
        ) {
            if (blank($idempotencyKey)) {
                return $this->ensureModel($callback());
            }

            if (mb_strlen($idempotencyKey) > 120) {
                throw new AppointmentDomainException(
                    'APPOINTMENT_IDEMPOTENCY_KEY_INVALID',
                    'La clave de idempotencia no puede superar 120 caracteres.',
                    422,
                );
            }

            $requestHash = hash('sha256', json_encode(
                $this->normalize($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            AppointmentIdempotencyKey::query()->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'user_id' => $actor->id,
                'operation' => $operation,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = AppointmentIdempotencyKey::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $actor->id)
                ->where('operation', $operation)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($record->request_hash, $requestHash)) {
                throw new AppointmentDomainException(
                    'APPOINTMENT_IDEMPOTENCY_PAYLOAD_MISMATCH',
                    'La clave de idempotencia ya fue usada con datos diferentes.',
                );
            }

            if ($record->status === 'completed') {
                return $this->resolveResult($record);
            }

            $result = $this->ensureModel($callback());

            $record->update([
                'status' => 'completed',
                'result_type' => $result::class,
                'result_id' => $result->getKey(),
                'response_data' => ['id' => $result->getKey()],
            ]);

            return $result;
        }, 3);
    }

    private function resolveResult(AppointmentIdempotencyKey $record): Model
    {
        if (! in_array($record->result_type, [Appointment::class, AppointmentProposal::class], true)) {
            throw new AppointmentDomainException(
                'APPOINTMENT_IDEMPOTENCY_RESULT_INVALID',
                'El resultado idempotente guardado no es valido.',
            );
        }

        return $record->result_type::query()->findOrFail($record->result_id);
    }

    private function ensureModel(mixed $result): Model
    {
        if (! $result instanceof Model || ! $result->exists) {
            throw new AppointmentDomainException(
                'APPOINTMENT_OPERATION_RESULT_INVALID',
                'La operacion de cita no produjo un resultado persistido.',
                500,
            );
        }

        return $result;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn ($item) => $this->normalize($item), $value);
    }
}
