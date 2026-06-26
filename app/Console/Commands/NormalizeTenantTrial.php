<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeTenantTrial extends Command
{
    protected $signature = 'tenants:normalize-trial
        {tenant_id : ID del tenant a normalizar}
        {--starts= : Fecha inicio YYYY-MM-DD; por defecto created_at del tenant}
        {--ends= : Fecha fin YYYY-MM-DD; por defecto subscription_ends_at del tenant}
        {--created-by= : ID del usuario admin que quedara como autor}
        {--apply : Escribe cambios; sin esta bandera solo muestra dry-run}';

    protected $description = 'Crea suscripcion y pago trial $0 para normalizar un tenant existente.';

    public function handle(): int
    {
        $tenant = Tenant::with(['plan', 'subscriptions', 'payments'])->findOrFail((int) $this->argument('tenant_id'));

        if (! $tenant->plan_id || ! $tenant->plan) {
            $this->error('El tenant no tiene plan asignado.');

            return self::FAILURE;
        }

        if ($tenant->subscriptions()->where('status', 'active')->exists()) {
            $this->error('El tenant ya tiene una suscripcion activa.');

            return self::FAILURE;
        }

        if ($tenant->payments()->where('status', 'paid')->exists()) {
            $this->error('El tenant ya tiene un pago pagado.');

            return self::FAILURE;
        }

        $startsAt = $this->option('starts')
            ? Carbon::parse($this->option('starts'))->startOfDay()
            : $tenant->created_at->copy()->startOfDay();
        $endsAt = $this->option('ends')
            ? Carbon::parse($this->option('ends'))->endOfDay()
            : $tenant->subscription_ends_at?->copy()->endOfDay();

        if (! $endsAt) {
            $this->error('Debe indicar --ends o el tenant debe tener subscription_ends_at.');

            return self::FAILURE;
        }

        $payload = [
            'tenant_id' => $tenant->id,
            'tenant' => $tenant->name,
            'plan_id' => $tenant->plan_id,
            'plan' => $tenant->plan->name,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'amount' => '0.00',
            'payment_method' => 'trial',
            'apply' => (bool) $this->option('apply'),
        ];

        $this->table(['Campo', 'Valor'], collect($payload)->map(fn ($value, $key) => [$key, (string) $value])->all());

        if (! $this->option('apply')) {
            $this->warn('Dry-run: agrega --apply para escribir cambios.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenant, $startsAt, $endsAt) {
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $tenant->plan_id,
                'provider' => 'manual',
                'status' => 'active',
                'starts_at' => $startsAt,
                'trial_ends_at' => $endsAt,
                'ends_at' => $endsAt,
                'created_by' => $this->option('created-by') ? (int) $this->option('created-by') : null,
                'notes' => 'Trial historico normalizado por comando tenants:normalize-trial.',
            ]);

            TenantPayment::create([
                'tenant_id' => $tenant->id,
                'tenant_subscription_id' => $subscription->id,
                'plan_id' => $tenant->plan_id,
                'provider' => 'manual',
                'amount' => 0,
                'currency' => $tenant->plan->currency ?? 'MXN',
                'status' => 'paid',
                'payment_method' => 'trial',
                'paid_at' => now(),
                'period_starts_at' => $startsAt,
                'period_ends_at' => $endsAt,
                'created_by' => $this->option('created-by') ? (int) $this->option('created-by') : null,
                'notes' => 'Trial sin cargo normalizado para respaldar acceso vigente.',
            ]);

            $tenant->update([
                'trial_ends_at' => $endsAt,
                'subscription_ends_at' => $endsAt,
            ]);
        });

        $this->info('Tenant normalizado con trial $0.');

        return self::SUCCESS;
    }
}
