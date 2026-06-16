<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserAccessSession;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAccessSessionManager
{
    public const WEB_SESSION_KEY = 'user_access_session_id';

    public function planAllows(User $user, string $platform): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($platform === 'web' && $user->hasRole('customer')) {
            return false;
        }

        $plan = $user->tenant?->plan;

        if (! $plan) {
            return false;
        }

        return $platform === 'web'
            ? $plan->web_access && $plan->max_web_sessions_per_user > 0
            : $plan->mobile_access && $plan->max_mobile_sessions_per_user > 0;
    }

    public function registerWeb(User $user, Request $request): UserAccessSession
    {
        return DB::transaction(function () use ($user, $request) {
            $user = User::query()->with('tenant.plan')->lockForUpdate()->findOrFail($user->id);

            $access = UserAccessSession::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'platform' => 'web',
                'session_id' => $request->session()->getId(),
                'device_name' => $this->webDeviceName($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity_at' => now(),
            ]);

            $this->enforceLimits($user, 'web', $access->id);
            $request->session()->put(self::WEB_SESSION_KEY, $access->id);

            return $access;
        });
    }

    public function registerMobile(User $user, int $tokenId, Request $request): UserAccessSession
    {
        return DB::transaction(function () use ($user, $tokenId, $request) {
            $user = User::query()->with('tenant.plan')->lockForUpdate()->findOrFail($user->id);

            $access = UserAccessSession::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'platform' => 'mobile',
                'token_id' => $tokenId,
                'device_name' => $request->input('device_name', 'mobile-app'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity_at' => now(),
            ]);

            $this->enforceLimits($user, 'mobile', $access->id);

            return $access;
        });
    }

    public function activeWebAccess(User $user, Request $request): ?UserAccessSession
    {
        $accessId = $request->session()->get(self::WEB_SESSION_KEY);

        if (! $accessId) {
            return null;
        }

        return UserAccessSession::query()
            ->whereKey($accessId)
            ->where('user_id', $user->id)
            ->where('platform', 'web')
            ->whereNull('revoked_at')
            ->first();
    }

    public function activeMobileAccess(User $user, ?int $tokenId): ?UserAccessSession
    {
        if (! $tokenId) {
            return null;
        }

        return UserAccessSession::query()
            ->where('user_id', $user->id)
            ->where('platform', 'mobile')
            ->where('token_id', $tokenId)
            ->whereNull('revoked_at')
            ->first();
    }

    public function touch(UserAccessSession $access): void
    {
        if (! $access->last_activity_at || $access->last_activity_at->lt(now()->subMinute())) {
            $access->forceFill(['last_activity_at' => now()])->save();
        }
    }

    public function revokeCurrentWeb(Request $request): void
    {
        $accessId = $request->session()->get(self::WEB_SESSION_KEY);

        if ($accessId) {
            UserAccessSession::whereKey($accessId)->update(['revoked_at' => now()]);
        }
    }

    public function revokeCurrentMobile(?int $tokenId): void
    {
        if ($tokenId) {
            UserAccessSession::where('token_id', $tokenId)->update(['revoked_at' => now()]);
        }
    }

    private function enforceLimits(User $user, string $platform, int $currentAccessId): void
    {
        $plan = $user->tenant?->plan;

        if ($platform === 'web') {
            $staleWebAccesses = $user->accessSessions()
                ->where('platform', 'web')
                ->whereNull('revoked_at')
                ->whereKeyNot($currentAccessId)
                ->where('last_activity_at', '<', now()->subMinutes(config('session.lifetime')))
                ->get();

            $this->revoke($staleWebAccesses);
        }

        if (! $user->hasRole('super-admin') && $plan && ! $plan->allow_cross_platform_sessions) {
            $otherPlatform = $platform === 'web' ? 'mobile' : 'web';
            $this->revoke($user->accessSessions()
                ->where('platform', $otherPlatform)
                ->whereNull('revoked_at')
                ->get());
        }

        $limit = $user->hasRole('super-admin')
            ? 1
            : (int) ($platform === 'web'
                ? $plan?->max_web_sessions_per_user
                : $plan?->max_mobile_sessions_per_user);

        $activeOtherSessions = $user->accessSessions()
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->whereKeyNot($currentAccessId)
            ->latest('id')
            ->get();

        $excess = $activeOtherSessions->slice(max(0, $limit - 1));

        $this->revoke($excess);
    }

    private function revoke(Collection $accesses): void
    {
        if ($accesses->isEmpty()) {
            return;
        }

        $webSessionIds = $accesses->where('platform', 'web')->pluck('session_id')->filter();
        $tokenIds = $accesses->where('platform', 'mobile')->pluck('token_id')->filter();

        UserAccessSession::whereIn('id', $accesses->pluck('id'))->update(['revoked_at' => now()]);

        if ($webSessionIds->isNotEmpty() && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->whereIn('id', $webSessionIds)->delete();
        }

        if ($tokenIds->isNotEmpty()) {
            DB::table('personal_access_tokens')->whereIn('id', $tokenIds)->delete();
        }
    }

    private function webDeviceName(Request $request): string
    {
        return str($request->userAgent() ?: 'Navegador web')->limit(120)->toString();
    }
}
