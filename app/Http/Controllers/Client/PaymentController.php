<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Note;
use App\Models\NotePayment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Devuelve un JSON con el preview FIFO de cómo se distribuiría el pago.
     * Llamado via fetch desde Alpine.js cada vez que el usuario cambia el monto.
     */
    public function preview(Request $request, Customer $customer)
    {
        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json(['distribution' => [], 'leftover' => 0]);
        }

        $pending = Note::where('customer_id', $customer->id)
            ->orderBy('date_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn($n) => $n->balance > 0);

        $remaining  = $amount;
        $distribution = [];

        foreach ($pending as $note) {
            if ($remaining <= 0) break;

            $apply = min($remaining, $note->balance);

            $distribution[] = [
                'folio'       => $note->folio,
                'balance'     => $note->balance,
                'amount_applied' => $apply,
                'new_balance' => round($note->balance - $apply, 2),
            ];

            $remaining -= $apply;
        }

        return response()->json([
            'distribution' => $distribution,
            'leftover'     => round($remaining, 2), // sobraría si paga de más
        ]);
    }

    /**
     * Guarda el pago y aplica FIFO sobre las notas pendientes.
     */
    public function store(Request $request, Customer $customer)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'reference'         => 'nullable|string|max:255',
        ], [
            'amount.required'            => 'El monto es obligatorio.',
            'amount.min'                 => 'El monto debe ser mayor a cero.',
            'payment_method_id.required' => 'Selecciona un método de pago.',
            'payment_method_id.exists'   => 'Método de pago no válido.',
        ]);

        DB::transaction(function () use ($request, $customer) {

            // 1. Crear el pago cabecera
            $payment = Payment::create([
                'tenant_id'         => $customer->tenant_id,
                'customer_id'       => $customer->id,
                'payment_method_id' => $request->payment_method_id,
                'amount'            => $request->amount,
                'reference'         => $request->reference,
            ]);

            // 2. FIFO: notas con saldo, de más antigua a más nueva
            $pending = Note::where('customer_id', $customer->id)
                ->orderBy('date_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->filter(fn($n) => $n->balance > 0);

            $remaining = (float) $request->amount;

            foreach ($pending as $note) {
                if ($remaining <= 0) break;

                $apply = min($remaining, $note->balance);

                NotePayment::create([
                    'note_id'        => $note->id,
                    'payment_id'     => $payment->id,
                    'amount_applied' => $apply,
                ]);

                // Si el saldo queda en 0, marcar la nota como PAGADA
                if (($note->balance - $apply) <= 0) {
                    $note->update(['status' => 'PAGADA']);
                }

                $remaining -= $apply;
            }
        });

        return redirect()
            ->route('client.customers.show', $customer)
            ->with('success', 'Pago registrado correctamente.');
    }
}