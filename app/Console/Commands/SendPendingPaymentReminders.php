<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PendingPaymentReminderService;
use Illuminate\Console\Command;

class SendPendingPaymentReminders extends Command
{
    protected $signature = 'tenants:send-pending-payment-reminders';

    protected $description = 'Crea una notificación diaria para tenants con pago pendiente.';

    public function handle(PendingPaymentReminderService $reminders): int
    {
        $created = 0;

        Tenant::query()
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereHas('subscriptions', function ($subscriptionQuery) {
                        $subscriptionQuery
                            ->where('status', 'pending')
                            ->where(function ($dateQuery) {
                                $dateQuery->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                            });
                    })
                    ->orWhereHas('payments', function ($paymentQuery) {
                        $paymentQuery
                            ->where('status', 'pending')
                            ->where(function ($dateQuery) {
                                $dateQuery->whereNull('period_ends_at')->orWhere('period_ends_at', '>=', now());
                            });
                    });
            })
            ->chunkById(100, function ($tenants) use ($reminders, &$created) {
                foreach ($tenants as $tenant) {
                    if ($reminders->notifyIfNeeded($tenant)) {
                        $created++;
                    }
                }
            });

        $this->info("Notificaciones creadas: {$created}");

        return self::SUCCESS;
    }
}
