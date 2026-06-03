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
    public function generate(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ], [
            'date_from.required'       => 'La fecha inicial es obligatoria.',
            'date_to.required'         => 'La fecha final es obligatoria.',
            'date_to.after_or_equal'   => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        $from = Carbon::parse($request->date_from)->startOfDay();
        $to   = Carbon::parse($request->date_to)->endOfDay();

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

        $generator->generateStored($customer);

        return back()->with('success', 'Estado de cuenta guardado correctamente.');
    }

    public function showStored(Customer $customer, CustomerStatement $statement)
    {
        $this->authorizeTenant($customer);
        abort_unless($statement->tenant_id === auth()->user()->tenant_id && $statement->customer_id === $customer->id, 404);
        abort_unless($statement->pdf_path && Storage::disk('local')->exists($statement->pdf_path), 404);

        return response()->file(storage_path('app/' . $statement->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($statement->pdf_path) . '"',
        ]);
    }

    private function authorizeTenant(Customer $customer): void
    {
        abort_unless($customer->tenant_id === auth()->user()->tenant_id, 404);
    }
}
