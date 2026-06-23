<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DashboardServicePerformanceService
{
    public function forYear(Tenant $tenant, int $year): array
    {
        $paidByNote = DB::table('note_payments')
            ->join('payments', 'payments.id', '=', 'note_payments.payment_id')
            ->where('payments.tenant_id', $tenant->id)
            ->where('payments.status', 'paid')
            ->groupBy('note_payments.note_id')
            ->selectRaw('note_payments.note_id, SUM(note_payments.amount_applied) as amount_paid');

        $rows = DB::table('note_details as details')
            ->join('notes', 'notes.id', '=', 'details.note_id')
            ->join('catalog_items', 'catalog_items.id', '=', 'details.catalog_item_id')
            ->leftJoinSub($paidByNote, 'paid_by_note', fn ($join) => $join
                ->on('paid_by_note.note_id', '=', 'notes.id'))
            ->where('details.tenant_id', $tenant->id)
            ->where('notes.tenant_id', $tenant->id)
            ->where('catalog_items.tenant_id', $tenant->id)
            ->where('catalog_items.type', 'service')
            ->where('notes.status', '!=', 'CANCELADA')
            ->whereNull('notes.deleted_at')
            ->whereBetween('notes.date_at', ["{$year}-01-01", "{$year}-12-31"])
            ->groupBy('notes.id', 'notes.date_at', 'notes.total', 'paid_by_note.amount_paid')
            ->selectRaw('notes.date_at, notes.total as note_total, COALESCE(paid_by_note.amount_paid, 0) as amount_paid')
            ->selectRaw('SUM(details.quantity) as service_count, SUM(details.subtotal) as service_value')
            ->get();

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $months = collect(range(1, 12))->map(fn (int $month) => [
            'month' => $month,
            'label' => $labels[$month - 1],
            'service_count' => 0.0,
            'service_value' => 0.0,
            'collected' => 0.0,
            'debt' => 0.0,
        ])->keyBy('month');

        foreach ($rows as $row) {
            $month = (int) substr((string) $row->date_at, 5, 2);
            $serviceValue = max((float) $row->service_value, 0);
            $noteTotal = (float) $row->note_total;
            $amountPaid = max((float) $row->amount_paid, 0);
            $collected = $noteTotal > 0
                ? min($serviceValue, $serviceValue * min($amountPaid, $noteTotal) / $noteTotal)
                : 0;
            $current = $months->get($month);
            $current['service_count'] += (float) $row->service_count;
            $current['service_value'] += $serviceValue;
            $current['collected'] += $collected;
            $months->put($month, $current);
        }

        $months = $months->map(function (array $month) {
            $month['service_count'] = round($month['service_count'], 2);
            $month['service_value'] = round($month['service_value'], 2);
            $month['collected'] = round($month['collected'], 2);
            $month['debt'] = round(max($month['service_value'] - $month['collected'], 0), 2);

            return $month;
        })->values();
        $maxValue = max((float) $months->max('service_value'), 1);

        return [
            'year' => $year,
            'months' => $months->map(function (array $month) use ($maxValue) {
                $month['service_value_percent'] = round(($month['service_value'] / $maxValue) * 100, 2);
                $month['collected_percent'] = round(($month['collected'] / $maxValue) * 100, 2);

                return $month;
            })->all(),
            'totals' => [
                'service_count' => round((float) $months->sum('service_count'), 2),
                'service_value' => round((float) $months->sum('service_value'), 2),
                'collected' => round((float) $months->sum('collected'), 2),
                'debt' => round((float) $months->sum('debt'), 2),
            ],
        ];
    }
}
