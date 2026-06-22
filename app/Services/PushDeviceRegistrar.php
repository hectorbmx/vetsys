<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PushDeviceRegistrar
{
    public function register(User $user, array $attributes): PushDevice
    {
        $token = $attributes['token'];
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($user, $attributes, $token, $tokenHash) {
            $byDevice = PushDevice::query()
                ->where('user_id', $user->id)
                ->where('platform', $attributes['platform'])
                ->where('device_uuid', $attributes['device_uuid'])
                ->lockForUpdate()
                ->first();
            $byToken = PushDevice::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($byDevice && $byToken && ! $byDevice->is($byToken)) {
                $byToken->delete();
                $byToken = null;
            }

            $device = $byDevice ?: $byToken ?: new PushDevice;
            $device->fill([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'platform' => $attributes['platform'],
                'token' => $token,
                'token_hash' => $tokenHash,
                'device_uuid' => $attributes['device_uuid'],
                'device_name' => $attributes['device_name'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ])->save();

            return $device->fresh();
        }, 3);
    }

    public function revoke(PushDevice $device): PushDevice
    {
        if (! $device->revoked_at) {
            $device->forceFill(['revoked_at' => now()])->save();
        }

        return $device->fresh();
    }
}
