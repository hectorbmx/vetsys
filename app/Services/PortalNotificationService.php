<?php

namespace App\Services;

use App\Models\AnimalPortalVisibilitySetting;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerStatement;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PortalNotification;
use Illuminate\Database\Eloquent\Collection;

class PortalNotificationService
{
    public function statementGenerated(CustomerStatement $statement): void
    {
        $customer = $statement->customer;

        if (!$customer) {
            return;
        }

        foreach ($this->activeAccesses($customer) as $access) {
            if (!$this->hasVisibleSection($access, 'show_statement')) {
                continue;
            }

            $this->createOnce($access, [
                'type' => 'portal.statement.generated',
                'title' => 'Estado de cuenta disponible',
                'body' => 'Tu estado de cuenta del periodo '
                    . $statement->period_start?->format('d/m/Y')
                    . ' - '
                    . $statement->period_end?->format('d/m/Y')
                    . ' esta disponible.',
                'url' => '/portal/statements/' . $statement->id,
                'data' => [
                    'statement_id' => $statement->id,
                    'period_start' => $statement->period_start?->toDateString(),
                    'period_end' => $statement->period_end?->toDateString(),
                    'ending_balance' => (float) $statement->ending_balance,
                ],
            ]);
        }
    }

    public function notePaymentConfirmed(Note $note, Payment $payment, float $amount): void
    {
        $customer = $note->customer;

        if (!$customer) {
            return;
        }

        $note->loadMissing('details');
        $animalIds = $note->details->pluck('animal_id')->filter()->unique()->values();

        foreach ($this->activeAccesses($customer) as $access) {
            $visibleAnimalIds = $animalIds
                ->filter(fn ($animalId) => $this->hasVisibleSection($access, 'show_notes', (int) $animalId));

            if ($visibleAnimalIds->isEmpty()) {
                continue;
            }

            $this->createOnce($access, [
                'animal_id' => $visibleAnimalIds->first(),
                'type' => 'portal.payment.succeeded',
                'title' => 'Pago confirmado',
                'body' => 'Se confirmo el pago de la nota ' . $note->folio
                    . ' por $' . number_format($amount, 2) . ' MXN.',
                'url' => '/portal/notes/' . $note->id,
                'data' => [
                    'note_id' => $note->id,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'balance' => $note->balance,
                ],
            ]);
        }
    }

    public function customerPaymentConfirmed(Customer $customer, Payment $payment, float $amount): void
    {
        foreach ($this->activeAccesses($customer) as $access) {
            if (!$this->hasVisibleSection($access, 'show_statement')) {
                continue;
            }

            $this->createOnce($access, [
                'type' => 'portal.payment.succeeded',
                'title' => 'Pago confirmado',
                'body' => 'Se confirmo un pago por $' . number_format($amount, 2) . ' MXN.',
                'url' => '/portal/statements',
                'data' => [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                ],
            ]);
        }
    }

    public function notePublished(Note $note): void
    {
        $customer = $note->customer;

        if (!$customer) {
            return;
        }

        $note->loadMissing('details');
        $animalIds = $note->details->pluck('animal_id')->filter()->unique()->values();

        foreach ($this->activeAccesses($customer) as $access) {
            $visibleAnimalIds = $animalIds
                ->filter(fn ($animalId) => $this->hasVisibleSection($access, 'show_notes', (int) $animalId));

            if ($visibleAnimalIds->isEmpty()) {
                continue;
            }

            $this->createOnce($access, [
                'animal_id' => $visibleAnimalIds->first(),
                'type' => $note->balance > 0 ? 'portal.note.payment_pending' : 'portal.note.created',
                'title' => $note->balance > 0 ? 'Nota pendiente de pago' : 'Nota publicada',
                'body' => 'La nota ' . $note->folio . ' esta disponible en tu portal.',
                'url' => '/portal/notes/' . $note->id,
                'data' => [
                    'note_id' => $note->id,
                    'folio' => $note->folio,
                    'total' => (float) $note->total,
                    'balance' => $note->balance,
                ],
            ]);
        }
    }

    private function activeAccesses(Customer $customer): Collection
    {
        return CustomerPortalAccess::with('customer')
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('access_ends_at')
                    ->orWhere('access_ends_at', '>=', now());
            })
            ->get();
    }

    private function hasVisibleSection(CustomerPortalAccess $access, string $section, ?int $animalId = null): bool
    {
        return AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->when($animalId, fn ($query) => $query->where('animal_id', $animalId))
            ->where($section, true)
            ->whereHas('animal.finalUserPatientAssignments', fn ($query) => $query
                ->where('tenant_id', $access->tenant_id)
                ->where('customer_id', $access->customer_id)
                ->where('user_id', $access->user_id)
                ->whereNull('revoked_at'))
            ->exists();
    }

    private function createOnce(CustomerPortalAccess $access, array $payload): void
    {
        $data = $payload['data'] ?? [];

        $exists = PortalNotification::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->where('type', $payload['type'])
            ->where('data->note_id', $data['note_id'] ?? null)
            ->where('data->statement_id', $data['statement_id'] ?? null)
            ->where('data->payment_id', $data['payment_id'] ?? null)
            ->exists();

        if ($exists) {
            return;
        }

        PortalNotification::create([
            'tenant_id' => $access->tenant_id,
            'customer_id' => $access->customer_id,
            'user_id' => $access->user_id,
            'animal_id' => $payload['animal_id'] ?? null,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'url' => $payload['url'] ?? null,
            'data' => $data,
        ]);
    }
}
