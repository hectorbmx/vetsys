<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NotePayment;
use App\Models\Payment;
use App\Models\PaymentEditHistory;
use Illuminate\Support\Facades\DB;

class CustomerPaymentEditService
{
    public function update(Payment $payment, array $data): Payment
    {
        abort_if($payment->provider === 'stripe', 422, 'Los pagos de Stripe no se pueden editar.');

        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $originalApplications = NotePayment::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->get();

            $originalNoteIds = $originalApplications->pluck('note_id')->unique()->values();
            $before = $this->snapshot($payment, $originalApplications);
            $payment->update([
                'amount' => $data['amount'],
                'payment_method_id' => $data['payment_method_id'],
                'reference' => $data['reference'] ?? null,
                'created_at' => $data['created_at'],
                'updated_at' => now(),
            ]);

            NotePayment::query()->where('payment_id', $payment->id)->delete();

            if ($originalApplications->count() === 1) {
                $this->applyToOriginalNote($payment, (int) $originalApplications->first()->note_id);
            } else {
                $this->applyGlobally($payment);
            }

            $affectedNoteIds = $originalNoteIds
                ->merge(NotePayment::query()->where('payment_id', $payment->id)->pluck('note_id'))
                ->unique()
                ->values();

            $this->refreshNoteStatuses($affectedNoteIds);

            $actor = auth()->user();
            PaymentEditHistory::create([
                'payment_id' => $payment->id,
                'user_id' => $actor?->id,
                'user_name' => $actor?->name,
                'before' => $before,
                'after' => $this->snapshot($payment->fresh(), NotePayment::where('payment_id', $payment->id)->get()),
                'created_at' => now(),
            ]);

            return $payment->fresh(['customer', 'paymentMethod', 'notes']);
        });
    }

    private function snapshot(Payment $payment, $applications): array
    {
        return [
            'amount' => (string) $payment->amount,
            'paid_at' => $payment->created_at->format('Y-m-d'),
            'payment_method_id' => $payment->payment_method_id,
            'payment_method' => $payment->paymentMethod?->name,
            'reference' => $payment->reference,
            'applications' => $applications->map(fn ($item) => [
                'note_id' => $item->note_id,
                'amount' => (string) $item->amount_applied,
            ])->values()->all(),
        ];
    }

    private function applyToOriginalNote(Payment $payment, int $noteId): void
    {
        $note = Note::query()->lockForUpdate()->find($noteId);

        if (! $note || $note->status === 'CANCELADA') {
            return;
        }

        $otherApplied = (int) round((float) NotePayment::query()
            ->where('note_id', $note->id)
            ->where('payment_id', '!=', $payment->id)
            ->sum('amount_applied') * 100);
        $available = max((int) round((float) $note->total * 100) - $otherApplied, 0);
        $amount = min((int) round((float) $payment->amount * 100), $available);

        if ($amount > 0) {
            NotePayment::query()->create([
                'note_id' => $note->id,
                'payment_id' => $payment->id,
                'amount_applied' => number_format($amount / 100, 2, '.', ''),
            ]);
        }
    }

    private function applyGlobally(Payment $payment): void
    {
        $notes = Note::query()
            ->where('tenant_id', $payment->tenant_id)
            ->where('customer_id', $payment->customer_id)
            ->where('status', '!=', 'CANCELADA')
            ->orderBy('date_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = (int) round((float) $payment->amount * 100);

        foreach ($notes as $note) {
            if ($remaining <= 0) {
                break;
            }

            $otherApplied = (int) round((float) NotePayment::query()
                ->where('note_id', $note->id)
                ->where('payment_id', '!=', $payment->id)
                ->sum('amount_applied') * 100);
            $available = max((int) round((float) $note->total * 100) - $otherApplied, 0);
            $amount = min($remaining, $available);

            if ($amount <= 0) {
                continue;
            }

            NotePayment::query()->create([
                'note_id' => $note->id,
                'payment_id' => $payment->id,
                'amount_applied' => number_format($amount / 100, 2, '.', ''),
            ]);

            $remaining -= $amount;
        }
    }

    private function refreshNoteStatuses($noteIds): void
    {
        Note::query()
            ->whereIn('id', $noteIds)
            ->where('status', '!=', 'CANCELADA')
            ->lockForUpdate()
            ->get()
            ->each(function (Note $note): void {
                $applied = (float) NotePayment::query()
                    ->where('note_id', $note->id)
                    ->sum('amount_applied');

                $note->update([
                    'status' => $applied >= (float) $note->total ? 'PAGADA' : 'PENDIENTE',
                ]);
            });
    }
}
