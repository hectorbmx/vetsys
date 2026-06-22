<?php

namespace App\Data;

use Carbon\CarbonImmutable;

class AppointmentSlot
{
    public function __construct(
        public CarbonImmutable $startsAtUtc,
        public CarbonImmutable $endsAtUtc,
        public CarbonImmutable $localStartsAt,
        public CarbonImmutable $localEndsAt,
        public string $timezone,
        public int $durationMinutes,
        public int $bufferMinutes,
    ) {}

    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAtUtc->toIso8601String(),
            'ends_at' => $this->endsAtUtc->toIso8601String(),
            'local_starts_at' => $this->localStartsAt->toIso8601String(),
            'local_ends_at' => $this->localEndsAt->toIso8601String(),
            'timezone' => $this->timezone,
            'duration_minutes' => $this->durationMinutes,
            'buffer_minutes' => $this->bufferMinutes,
        ];
    }
}
