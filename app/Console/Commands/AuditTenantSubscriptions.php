<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditTenantSubscriptions extends Command
{
    protected $signature = 'tenants:audit-subscriptions
        {--tenant= : Audita solo un tenant por ID}
        {--json : Imprime el resultado completo en JSON}';

    protected $description = 'Audita tenants, suscripciones y pagos SaaS sin modificar datos.';

    public function handle(): int
    {
        $query = Tenant::query()
            ->with([
                'plan',
                'subscriptions' => fn ($query) => $query->latest(),
                'payments' => fn ($query) => $query->latest(),
            ])
            ->orderBy('id');

        if ($this->option('tenant')) {
            $query->whereKey((int) $this->option('tenant'));
        }

        $tenants = $query->get();
        $rows = $tenants->map(fn (Tenant $tenant) => $this->auditTenant($tenant));
        $summary = $this->summarize($rows);

        if ($this->option('json')) {
            $this->line(json_encode([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'tenants' => $rows->values(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Auditoria de suscripciones tenant');
        $this->line('Generado: '.now()->toDateTimeString());
        $this->line('');

        $this->table(
            ['Categoria', 'Total'],
            collect($summary)->map(fn ($count, $category) => [$category, $count])->values()->all()
        );

        $attentionRows = $rows
            ->filter(fn (array $row) => $row['requires_attention'])
            ->values();

        if ($attentionRows->isEmpty()) {
            $this->info('No se detectaron tenants que requieran atencion.');

            return self::SUCCESS;
        }

        $this->warn('Tenants que requieren atencion: '.$attentionRows->count());
        $this->table(
            ['ID', 'Tenant', 'Status', 'Plan', 'Billing status', 'Issues'],
            $attentionRows
                ->map(fn (array $row) => [
                    $row['id'],
                    $row['name'],
                    $row['tenant_status'],
                    $row['plan'],
                    $row['billing_status'],
                    implode(', ', $row['issues']),
                ])
                ->all()
        );

        return self::SUCCESS;
    }

    private function auditTenant(Tenant $tenant): array
    {
        $subscriptions = $tenant->subscriptions;
        $payments = $tenant->payments;
        $activeSubscriptions = $subscriptions->where('status', 'active');
        $pendingSubscriptions = $subscriptions->where('status', 'pending');
        $paidPayments = $payments->where('status', 'paid');
        $pendingPayments = $payments->where('status', 'pending');
        $activeSubscription = $activeSubscriptions
            ->sortByDesc(fn ($subscription) => $subscription->starts_at ?? $subscription->created_at)
            ->first();
        $paidPayment = $paidPayments
            ->sortByDesc(fn ($payment) => $payment->period_ends_at ?? $payment->paid_at ?? $payment->created_at)
            ->first();

        $issues = [];
        $billingStatus = 'unknown';
        $now = now();

        if (! $tenant->plan_id) {
            $billingStatus = 'no_plan';
            $issues[] = 'sin_plan';
        } elseif (! $tenant->plan) {
            $billingStatus = 'missing_plan_record';
            $issues[] = 'plan_no_existe';
        } elseif (! $tenant->plan->is_active) {
            $billingStatus = 'inactive_plan';
            $issues[] = 'plan_inactivo';
        }

        if ($tenant->plan_id && $subscriptions->isEmpty()) {
            $issues[] = 'sin_suscripciones';
        }

        if ($tenant->plan_id && $payments->isEmpty()) {
            $issues[] = 'sin_pagos';
        }

        if ($activeSubscriptions->count() > 1) {
            $issues[] = 'multiples_suscripciones_activas';
        }

        if ($tenant->subscription_ends_at && $activeSubscription && $activeSubscription->ends_at && ! $tenant->subscription_ends_at->equalTo($activeSubscription->ends_at)) {
            $issues[] = 'vencimiento_tenant_difiere_de_suscripcion';
        }

        if ($activeSubscription && $this->isExpired($activeSubscription->ends_at)) {
            $issues[] = 'suscripcion_activa_vencida';
        }

        if ($paidPayment && $this->isExpired($paidPayment->period_ends_at)) {
            $issues[] = 'ultimo_pago_pagado_vencido';
        }

        if ($pendingSubscriptions->isNotEmpty() || $pendingPayments->isNotEmpty()) {
            $billingStatus = 'pending_payment';
        }

        if ($activeSubscription && $paidPayment && ! $this->isExpired($activeSubscription->ends_at) && ! $this->isExpired($paidPayment->period_ends_at)) {
            $billingStatus = $this->isTrialPayment($paidPayment) ? 'trial_active' : 'paid_active';
        }

        if ($tenant->trial_ends_at && $tenant->trial_ends_at->greaterThanOrEqualTo($now) && $paidPayments->contains(fn ($payment) => $this->isTrialPayment($payment))) {
            $billingStatus = 'trial_active';
        }

        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast() && ! $this->hasCurrentPaidPayment($paidPayments)) {
            $billingStatus = 'trial_expired';
            $issues[] = 'trial_vencido';
        }

        if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast() && ! $this->hasCurrentPaidPayment($paidPayments)) {
            $billingStatus = 'subscription_expired';
            $issues[] = 'suscripcion_vencida';
        }

        if ($tenant->plan_id && ! $activeSubscription && $pendingSubscriptions->isEmpty()) {
            $issues[] = 'sin_suscripcion_activa_o_pendiente';
        }

        if ($tenant->plan_id && ! $paidPayment && $pendingPayments->isEmpty()) {
            $issues[] = 'sin_pago_pagado_o_pendiente';
        }

        if ($tenant->status !== 'active' || ! $tenant->is_active) {
            $issues[] = 'tenant_no_activo';
        }

        if ($billingStatus === 'unknown' && $issues === []) {
            $billingStatus = 'ok';
        } elseif ($billingStatus === 'unknown') {
            $billingStatus = 'needs_review';
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'tenant_status' => $tenant->status,
            'is_active' => (bool) $tenant->is_active,
            'plan_id' => $tenant->plan_id,
            'plan' => $tenant->plan?->name ?? 'N/A',
            'trial_ends_at' => $tenant->trial_ends_at?->toDateTimeString(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toDateTimeString(),
            'subscriptions_count' => $subscriptions->count(),
            'active_subscriptions_count' => $activeSubscriptions->count(),
            'pending_subscriptions_count' => $pendingSubscriptions->count(),
            'payments_count' => $payments->count(),
            'paid_payments_count' => $paidPayments->count(),
            'pending_payments_count' => $pendingPayments->count(),
            'billing_status' => $billingStatus,
            'issues' => array_values(array_unique($issues)),
            'requires_attention' => $this->requiresAttention($tenant, $billingStatus, $issues),
        ];
    }

    private function summarize(Collection $rows): array
    {
        $summary = [
            'total' => $rows->count(),
            'requires_attention' => $rows->where('requires_attention', true)->count(),
        ];

        foreach ($rows->groupBy('billing_status') as $status => $group) {
            $summary[$status] = $group->count();
        }

        return $summary;
    }

    private function isTrialPayment($payment): bool
    {
        return $payment->status === 'paid'
            && $payment->payment_method === 'trial'
            && (float) $payment->amount === 0.0;
    }

    private function hasCurrentPaidPayment(Collection $paidPayments): bool
    {
        return $paidPayments->contains(fn ($payment) => ! $this->isExpired($payment->period_ends_at));
    }

    private function isExpired($date): bool
    {
        return $date && now()->greaterThan($date);
    }

    private function requiresAttention(Tenant $tenant, string $billingStatus, array $issues): bool
    {
        if ($tenant->status !== 'active' || ! $tenant->is_active) {
            return true;
        }

        if (in_array($billingStatus, ['paid_active', 'trial_active'], true)) {
            return false;
        }

        return $issues !== [] || in_array($billingStatus, [
            'no_plan',
            'pending_payment',
            'trial_expired',
            'subscription_expired',
            'needs_review',
        ], true);
    }
}
