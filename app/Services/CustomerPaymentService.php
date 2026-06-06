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

            if (($note->balance - $apply) <= 0) {
                $note->update(['status' => 'PAGADA']);
            }

            $remaining -= $apply;
        }

        return $payment;
    }
}
