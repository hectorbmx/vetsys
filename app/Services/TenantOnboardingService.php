<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

class TenantOnboardingService
{
    public function reconcileSafely(Tenant $tenant): ?array
    {
        try {
            return $this->reconcile($tenant);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function reconcile(Tenant $tenant): array
    {
        $completedSteps = $tenant->onboardingSteps()
            ->whereIn('step', TenantOnboardingStep::STEPS)
            ->pluck('step')
            ->flip();

        foreach (TenantOnboardingStep::STEPS as $step) {
            if ($completedSteps->has($step)) {
                continue;
            }

            $evidence = $this->detectEvidence($tenant, $step);

            if ($evidence) {
                $this->markCompleted($tenant, $step, $evidence);
            }
        }

        return $this->statusFor($tenant);
    }

    public function markCompleted(Tenant $tenant, string $step, ?Model $evidence = null): TenantOnboardingStep
    {
        if (!TenantOnboardingStep::isValidStep($step)) {
            throw new InvalidArgumentException("Unsupported onboarding step: {$step}");
        }

        return $tenant->onboardingSteps()->firstOrCreate(
            ['step' => $step],
            [
                'completed_at' => now(),
                'evidence_type' => $evidence?->getMorphClass(),
                'evidence_id' => $evidence?->getKey(),
            ]
        );
    }

    public function statusFor(Tenant $tenant): array
    {
        $completed = $tenant->onboardingSteps()
            ->whereIn('step', TenantOnboardingStep::STEPS)
            ->get()
            ->keyBy('step');
        $total = count(TenantOnboardingStep::STEPS);
        $completedCount = $completed->count();
        $steps = collect(TenantOnboardingStep::STEPS)
            ->map(function (string $step) use ($completed) {
                $record = $completed->get($step);

                return [
                    'key' => $step,
                    'completed' => (bool) $record,
                    'completed_at' => $record?->completed_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return [
            'completed' => $completedCount,
            'total' => $total,
            'percentage' => (int) round(($completedCount / $total) * 100),
            'is_completed' => $completedCount === $total,
            'next_step' => collect($steps)->firstWhere('completed', false)['key'] ?? null,
            'steps' => $steps,
        ];
    }

    private function detectEvidence(Tenant $tenant, string $step): ?Model
    {
        return match ($step) {
            TenantOnboardingStep::FIRST_ANIMAL_TYPE_CREATED => AnimalType::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->oldest('id')
                ->first(),
            TenantOnboardingStep::FIRST_PAYMENT_METHOD_CREATED => PaymentMethod::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->oldest('id')
                ->first(),
            TenantOnboardingStep::FIRST_SERVICE_CREATED => CatalogItem::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', 'service')
                ->where('is_active', true)
                ->oldest('id')
                ->first(),
            TenantOnboardingStep::FIRST_CUSTOMER_CREATED => Customer::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->oldest('id')
                ->first(),
            TenantOnboardingStep::FIRST_PET_CREATED => Animal::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->whereHas('customer', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active'))
                ->oldest('id')
                ->first(),
            TenantOnboardingStep::FIRST_NOTE_CREATED => $this->eligibleNotes($tenant)
                ->oldest('notes.id')
                ->first(),
            default => null,
        };
    }

    private function eligibleNotes(Tenant $tenant)
    {
        return Note::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', 'CANCELADA')
            ->where('total', '>', 0)
            ->whereHas('details', fn ($query) => $query->where('tenant_id', $tenant->id));
    }
}
