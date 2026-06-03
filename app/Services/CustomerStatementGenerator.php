<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerStatement;
use App\Models\Note;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CustomerStatementGenerator
{
    public function generateStored(Customer $customer): CustomerStatement
    {
        $setting = $customer->accountSetting;

        if (!$setting) {
            throw new \RuntimeException('El cliente no tiene configuracion contable.');
        }

        [$from, $to] = $this->closedPeriodForCutoff($setting->cutoff_day);
        $tenant = $customer->tenant;
        $statementData = $this->buildStatementData($customer, $from, $to);

        $existingStatement = CustomerStatement::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->whereDate('period_start', $from->toDateString())
            ->whereDate('period_end', $to->toDateString())
            ->first();

        $filename = 'estado-cuenta-' . str($customer->full_name)->slug() . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';
        $path = "statements/{$tenant->id}/{$customer->id}/{$filename}";

        $pdf = $this->makeStatementPdf(
            $customer,
            $tenant,
            $from,
            $to,
            $statementData['notesByMonth'],
            $statementData['payments'],
            $statementData['period_charges'],
            $statementData['period_payments'],
            $statementData['ending_balance'],
            $statementData['previous_balance']
        );

        Storage::disk('local')->put($path, $pdf->output());

        return CustomerStatement::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ],
            [
                'cutoff_day' => $setting->cutoff_day,
                'previous_balance' => $statementData['previous_balance'],
                'period_charges' => $statementData['period_charges'],
                'period_payments' => $statementData['period_payments'],
                'ending_balance' => $statementData['ending_balance'],
                'pdf_path' => $path,
                'generated_at' => now(),
                'status' => $existingStatement ? 'regenerated' : 'generated',
            ]
        );
    }

    public function shouldGenerateToday(Customer $customer, ?Carbon $date = null): bool
    {
        $setting = $customer->accountSetting;
        if (!$setting || !$setting->is_statement_enabled) {
            return false;
        }

        $date ??= now();
        return $date->day === min($setting->cutoff_day, $date->daysInMonth);
    }

    private function makeStatementPdf(
        Customer $customer,
        $tenant,
        Carbon $from,
        Carbon $to,
        $notesByMonth,
        $payments,
        float $totalInvoiced,
        float $totalPaid,
        float $totalDebt,
        ?float $previousBalance = null
    ) {
        return Pdf::loadView('client.customers.statement', [
            'customer' => $customer,
            'tenant' => $tenant,
            'from' => $from,
            'to' => $to,
            'notesByMonth' => $notesByMonth,
            'payments' => $payments,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalDebt' => $totalDebt,
            'previousBalance' => $previousBalance,
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    private function buildStatementData(Customer $customer, Carbon $from, Carbon $to): array
    {
        $notes = Note::with([
                'details.catalogItem',
                'details.animal',
            ])
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELADA')
            ->whereBetween('date_at', [$from, $to])
            ->orderBy('date_at', 'asc')
            ->get();

        $payments = Payment::with('paymentMethod')
            ->where('customer_id', $customer->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $previousCharges = (float) Note::where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELADA')
            ->whereDate('date_at', '<', $from->toDateString())
            ->sum('total');

        $previousPayments = (float) Payment::where('customer_id', $customer->id)
            ->where('created_at', '<', $from)
            ->sum('amount');

        $previousBalance = max($previousCharges - $previousPayments, 0);
        $periodCharges = (float) $notes->sum('total');
        $periodPayments = (float) $payments->sum('amount');
        $endingBalance = max($previousBalance + $periodCharges - $periodPayments, 0);

        return [
            'notesByMonth' => $notes->groupBy(fn ($note) => ucfirst($note->date_at->translatedFormat('F Y'))),
            'payments' => $payments,
            'previous_balance' => $previousBalance,
            'period_charges' => $periodCharges,
            'period_payments' => $periodPayments,
            'ending_balance' => $endingBalance,
        ];
    }

    private function closedPeriodForCutoff(int $cutoffDay): array
    {
        $today = now()->startOfDay();
        $periodEnd = $this->dateForCutoff($today->copy(), $cutoffDay);

        if ($periodEnd->greaterThan($today)) {
            $periodEnd = $this->dateForCutoff($today->copy()->subMonthNoOverflow(), $cutoffDay);
        }

        $previousEnd = $this->dateForCutoff($periodEnd->copy()->subMonthNoOverflow(), $cutoffDay);
        $periodStart = $previousEnd->copy()->addDay()->startOfDay();

        return [$periodStart, $periodEnd->endOfDay()];
    }

    private function dateForCutoff(Carbon $date, int $cutoffDay): Carbon
    {
        return $date->copy()->day(min($cutoffDay, $date->daysInMonth))->startOfDay();
    }
}
