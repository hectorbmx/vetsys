<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Note;
use App\Models\NotePayment;
use App\Models\Payment;

class CustomerPaymentService
{
    public function apply(
        Customer $customer,
        int $paymentMethodId,
        float $amount,
        array $paymentData = []
    ): Payment {
        $payment = Payment::create(array_merge([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
        ], $paymentData));

        $pending = Note::where('customer_id', $customer->id)
            ->where('tenant_id', $customer->tenant_id)
            ->orderBy('date_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (Note $note) => $note->balance > 0);

        $remaining = $amount;

        foreach ($pending as $note) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $note->balance);

            NotePayment::create([
                'note_id' => $note->id,
                'payment_id' => $payment->id,
                'amount_applied' => $apply,
            ]);

            if ($note->balance <= 0) {
                $note->update(['status' => 'PAGADA']);
            }

            $remaining -= $apply;
        }

        return $payment;
    }

    public function applyAvailableCredit(Customer $customer, Note $note): float
    {
        $remainingBalance = max((float) $note->balance, 0);
        $totalApplied = 0;

        if ($remainingBalance <= 0) {
            return 0;
        }

        $payments = Payment::where('customer_id', $customer->id)
            ->where('tenant_id', $customer->tenant_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $alreadyApplied = (float) NotePayment::where('payment_id', $payment->id)->sum('amount_applied');
            $available = max((float) $payment->amount - $alreadyApplied, 0);

            if ($available <= 0) {
                continue;
            }

            $apply = min($available, $remainingBalance);

            NotePayment::create([
                'note_id' => $note->id,
                'payment_id' => $payment->id,
                'amount_applied' => $apply,
            ]);

            $remainingBalance -= $apply;
            $totalApplied += $apply;

            if ($remainingBalance <= 0) {
                break;
            }
        }

        $note->refresh();

        if ($note->balance <= 0) {
            $note->update(['status' => 'PAGADA']);
        }

        return $totalApplied;
    }
}
