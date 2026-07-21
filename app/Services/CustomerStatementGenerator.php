<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerStatement;
use App\Models\Note;
use App\Models\NoteDetail;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CustomerStatementGenerator
{
    public function previewRange(Customer $customer, Carbon $from, Carbon $to): array
    {
        $chargesByAnimal = $this->unstatementedServiceDetailsForRange($customer, $from, $to)
            ->groupBy(fn (NoteDetail $detail) => $detail->animal?->name ?? 'Sin paciente')
            ->map(fn ($details, string $animalName) => [
                'animal' => $animalName,
                'services_count' => (int) $details->count(),
                'total' => round((float) $details->sum('subtotal'), 2),
            ])
            ->values();

        return [
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'animals_count' => $chargesByAnimal->count(),
            'services_count' => (int) $chargesByAnimal->sum('services_count'),
            'total' => round((float) $chargesByAnimal->sum('total'), 2),
            'rows' => $chargesByAnimal,
        ];
    }

    public function generateStoredForRange(Customer $customer, Carbon $from, Carbon $to): CustomerStatement
    {
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
            $statementData['serviceDetailsByMonth'],
            $statementData['payments'],
            $statementData['period_charges'],
            $statementData['period_payments'],
            $statementData['ending_balance'],
            $statementData['previous_balance'],
            $tenant->usesMonthlyCutoffBilling()
        );

        Storage::disk('local')->put($path, $pdf->output());

        $setting = $customer->accountSetting;

        $statement = CustomerStatement::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ],
            [
                'cutoff_day' => $setting?->cutoff_day ?? (int) $to->day,
                'previous_balance' => $statementData['previous_balance'],
                'period_charges' => $statementData['period_charges'],
                'period_payments' => $statementData['period_payments'],
                'ending_balance' => $statementData['ending_balance'],
                'pdf_path' => $path,
                'generated_at' => now(),
                'status' => $existingStatement ? 'regenerated' : 'generated',
                'visible_to_customer' => true,
                'published_at' => now(),
            ]
        );

        app(PortalNotificationService::class)->statementGenerated($statement->fresh('customer'));

        return $statement;
    }

    public function generateStored(Customer $customer): CustomerStatement
    {
        $setting = $customer->accountSetting;

        if (!$setting) {
            throw new \RuntimeException('El cliente no tiene configuracion contable.');
        }

        [$from, $to] = $this->closedPeriodForCutoff($setting->cutoff_day);
        return $this->generateStoredForRange($customer, $from, $to);
    }

    public function calculateRangeTotals(Customer $customer, Carbon $from, Carbon $to): array
    {
        $statementData = $this->buildStatementData($customer, $from, $to);

        return [
            'previous_balance' => round((float) $statementData['previous_balance'], 2),
            'period_charges' => round((float) $statementData['period_charges'], 2),
            'period_payments' => round((float) $statementData['period_payments'], 2),
            'ending_balance' => round((float) $statementData['ending_balance'], 2),
        ];
    }

    public function dataForRange(Customer $customer, Carbon $from, Carbon $to): array
    {
        return $this->buildStatementData($customer, $from, $to);
    }

    public function makePdfForRange(Customer $customer, Carbon $from, Carbon $to, ?bool $usesMonthlyCutoffBilling = null)
    {
        $tenant = $customer->tenant;
        $statementData = $this->buildStatementData($customer, $from, $to);

        return $this->makeStatementPdf(
            $customer,
            $tenant,
            $from,
            $to,
            $statementData['notesByMonth'],
            $statementData['serviceDetailsByMonth'],
            $statementData['payments'],
            $statementData['period_charges'],
            $statementData['period_payments'],
            $statementData['ending_balance'],
            $statementData['previous_balance'],
            $usesMonthlyCutoffBilling ?? $tenant->usesMonthlyCutoffBilling()
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
        $serviceDetailsByMonth,
        $payments,
        float $totalInvoiced,
        float $totalPaid,
        float $totalDebt,
        ?float $previousBalance = null,
        bool $usesMonthlyCutoffBilling = false
    ) {
        return Pdf::loadView('client.customers.statement', [
            'customer' => $customer,
            'tenant' => $tenant,
            'from' => $from,
            'to' => $to,
            'notesByMonth' => $notesByMonth,
            'serviceDetailsByMonth' => $serviceDetailsByMonth,
            'payments' => $payments,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalDebt' => $totalDebt,
            'previousBalance' => $previousBalance,
            'usesMonthlyCutoffBilling' => $usesMonthlyCutoffBilling,
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
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELADA')
            ->whereBetween('date_at', [$from, $to])
            ->orderBy('date_at', 'asc')
            ->get();

        $payments = Payment::with('paymentMethod')
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $serviceDetails = $this->unstatementedServiceDetailsForRange($customer, $from, $to);

        $previousCharges = (float) NoteDetail::where('note_details.tenant_id', $customer->tenant_id)
            ->whereHas('note', fn ($query) => $query
                ->where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->where('status', '!=', 'CANCELADA')
                ->whereDate('date_at', '<', $from->toDateString()))
            ->sum('subtotal');

        $previousPayments = (float) Payment::where('customer_id', $customer->id)
            ->where('tenant_id', $customer->tenant_id)
            ->where('created_at', '<', $from)
            ->sum('amount');

        $previousBalance = max($previousCharges - $previousPayments, 0);
        $periodCharges = (float) $serviceDetails->sum('subtotal');
        $periodPayments = (float) $payments->sum('amount');
        $endingBalance = max($previousBalance + $periodCharges - $periodPayments, 0);

        return [
            'notesByMonth' => $notes->groupBy(fn ($note) => ucfirst($note->date_at->translatedFormat('F Y'))),
            'serviceDetailsByMonth' => $serviceDetails->groupBy(fn (NoteDetail $detail) => ucfirst($detail->note->date_at->translatedFormat('F Y'))),
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

    private function unstatementedServiceDetailsForRange(Customer $customer, Carbon $from, Carbon $to)
    {
        $coveredRanges = CustomerStatement::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where(function ($query) use ($from, $to) {
                $query
                    ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('period_end', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($query) use ($from, $to) {
                        $query
                            ->whereDate('period_start', '<=', $from->toDateString())
                            ->whereDate('period_end', '>=', $to->toDateString());
                    });
            })
            ->where(function ($query) use ($from, $to) {
                $query
                    ->whereDate('period_start', '!=', $from->toDateString())
                    ->orWhereDate('period_end', '!=', $to->toDateString());
            })
            ->get(['period_start', 'period_end']);

        return NoteDetail::query()
            ->with(['note', 'animal', 'catalogItem'])
            ->where('note_details.tenant_id', $customer->tenant_id)
            ->join('notes', 'notes.id', '=', 'note_details.note_id')
            ->where('notes.tenant_id', $customer->tenant_id)
            ->where('notes.customer_id', $customer->id)
            ->where('notes.status', '!=', 'CANCELADA')
            ->whereNull('notes.deleted_at')
            ->whereBetween('notes.date_at', [$from, $to])
            ->select('note_details.*')
            ->orderBy('notes.date_at', 'asc')
            ->orderBy('note_details.id', 'asc')
            ->get()
            ->reject(function (NoteDetail $detail) use ($coveredRanges) {
                $date = $detail->note?->date_at;

                if (!$date) {
                    return false;
                }

                return $coveredRanges->contains(fn (CustomerStatement $statement) => $date->betweenIncluded(
                    $statement->period_start->copy()->startOfDay(),
                    $statement->period_end->copy()->endOfDay()
                ));
            })
            ->values();
    }

    private function dateForCutoff(Carbon $date, int $cutoffDay): Carbon
    {
        return $date->copy()->day(min($cutoffDay, $date->daysInMonth))->startOfDay();
    }
}
