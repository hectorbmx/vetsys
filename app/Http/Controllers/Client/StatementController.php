<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerStatement;
use App\Models\Note;
use App\Models\Payment;
use App\Services\CustomerStatementGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StatementController extends Controller
{
    public function preview(Request $request, Customer $customer, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);

        [$from, $to] = $this->validatedPeriod($request);

        return response()->json($generator->previewRange($customer, $from, $to));
    }

    public function storeManual(Request $request, Customer $customer, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);

        [$from, $to] = $this->validatedPeriod($request);

        $statement = $generator->generateStoredForRange($customer, $from, $to);

        return redirect()
            ->route('client.customers.show', ['customer' => $customer->id, 'tab' => 'notas'])
            ->with('activeCustomerTab', 'notas')
            ->with('success', "Corte {$statement->period_start->format('d/m/Y')} - {$statement->period_end->format('d/m/Y')} generado correctamente.");
    }

    public function generate(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        [$from, $to] = $this->validatedPeriod($request);

        // Notas del período con detalles → agrupadas por mes
        $notes = Note::with([
                'details.catalogItem',
                'details.animal',
            ])
            ->where('customer_id', $customer->id)
            ->whereBetween('date_at', [$from, $to])
            ->orderBy('date_at', 'asc')
            ->get();

        // Pagos del período
        $payments = Payment::with('paymentMethod')
            ->where('customer_id', $customer->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        // Agrupar notas por "Mes Año" (ej: "Mayo 2026")
        $notesByMonth = $notes->groupBy(function ($note) {
            return ucfirst($note->date_at->translatedFormat('F Y'));
        });

        // Saldo pendiente total al final del período
        $totalDebt     = $notes->sum(fn($n) => $n->balance);
        $totalPaid     = $payments->sum('amount');
        $totalInvoiced = $notes->sum('total');

        // Datos del tenant (la vet)
        $tenant = $customer->tenant;

        $pdf = Pdf::loadView('client.customers.statement', [
            'customer'      => $customer,
            'tenant'        => $tenant,
            'from'          => $from,
            'to'            => $to,
            'notesByMonth'  => $notesByMonth,
            'payments'      => $payments,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid'     => $totalPaid,
            'totalDebt'     => $totalDebt,
        ])
        ->setPaper('letter', 'portrait')
        ->setOption('defaultFont', 'sans-serif')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', true);

        $filename = 'estado-cuenta-' . str($customer->full_name)->slug() . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }

    public function storeGenerated(Customer $customer, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);

        if (!$customer->accountSetting) {
            return back()->with('error', 'Configura la cuenta contable antes de generar estados guardados.');
        }

        $statement = $generator->generateStored($customer);

        return redirect()
            ->route('client.customers.show', ['customer' => $customer->id, 'tab' => 'notas'])
            ->with('activeCustomerTab', 'notas')
            ->with('success', 'Corte automatico ' . $statement->period_start->format('d/m/Y') . ' - ' . $statement->period_end->format('d/m/Y') . ' generado correctamente.');
    }

    public function show(Customer $customer, CustomerStatement $statement, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);
        abort_unless($statement->tenant_id === auth()->user()->tenant_id && $statement->customer_id === $customer->id, 404);

        $tenant = $customer->tenant;
        $from = $statement->period_start->copy()->startOfDay();
        $to = $statement->period_end->copy()->endOfDay();
        $statementData = $generator->dataForRange($customer, $from, $to);
        $usesMonthlyCutoffBilling = auth()->user()->tenant?->usesMonthlyCutoffBilling()
            || $tenant?->usesMonthlyCutoffBilling();

        return view('client.customers.statement-show', [
            'customer' => $customer,
            'tenant' => $tenant,
            'statement' => $statement,
            'from' => $from,
            'to' => $to,
            'notesByMonth' => $statementData['notesByMonth'],
            'serviceDetailsByMonth' => $statementData['serviceDetailsByMonth'],
            'payments' => $statementData['payments'],
            'previousBalance' => $statementData['previous_balance'],
            'totalInvoiced' => $statementData['period_charges'],
            'totalPaid' => $statementData['period_payments'],
            'totalDebt' => $statementData['ending_balance'],
            'usesMonthlyCutoffBilling' => $usesMonthlyCutoffBilling,
        ]);
    }

    public function showStored(Customer $customer, CustomerStatement $statement, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);
        abort_unless($statement->tenant_id === auth()->user()->tenant_id && $statement->customer_id === $customer->id, 404);

        $usesMonthlyCutoffBilling = auth()->user()->tenant?->usesMonthlyCutoffBilling()
            || $customer->tenant?->usesMonthlyCutoffBilling();

        if ($usesMonthlyCutoffBilling) {
            $from = $statement->period_start->copy()->startOfDay();
            $to = $statement->period_end->copy()->endOfDay();
            $filename = 'estado-cuenta-' . str($customer->full_name)->slug() . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';

            return $generator->makePdfForRange($customer, $from, $to, true)
                ->stream($filename, [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);
        }

        abort_unless($statement->pdf_path && Storage::disk('local')->exists($statement->pdf_path), 404);

        return response()->file(storage_path('app/' . $statement->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($statement->pdf_path) . '"',
        ]);
    }

    public function recalculate(Customer $customer, CustomerStatement $statement, CustomerStatementGenerator $generator)
    {
        $this->authorizeTenant($customer);
        abort_unless($statement->tenant_id === auth()->user()->tenant_id && $statement->customer_id === $customer->id, 404);

        $generator->generateStoredForRange(
            $customer,
            $statement->period_start->copy()->startOfDay(),
            $statement->period_end->copy()->endOfDay()
        );

        return redirect()
            ->route('client.customers.statements.show', [$customer, $statement])
            ->with('success', 'Corte recalculado correctamente.');
    }

    private function authorizeTenant(Customer $customer): void
    {
        abort_unless($customer->tenant_id === auth()->user()->tenant_id, 404);
    }

    private function validatedPeriod(Request $request): array
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ], [
            'date_from.required'       => 'La fecha inicial es obligatoria.',
            'date_to.required'         => 'La fecha final es obligatoria.',
            'date_to.after_or_equal'   => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        return [
            Carbon::parse($request->date_from)->startOfDay(),
            Carbon::parse($request->date_to)->endOfDay(),
        ];
    }
}
