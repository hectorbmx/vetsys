<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalVideo;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerStatement;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PortalNotification;
use App\Models\RadiologyImage;
use App\Models\RadiologyStudy;
use App\Models\VaccinationLetter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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
            if (!$this->noteIsVisibleForAccess($access, $animalIds)) {
                continue;
            }

            $this->createOnce($access, [
                'animal_id' => $this->firstVisibleAnimalId($access, $animalIds, 'show_notes'),
                'type' => 'portal.payment.succeeded',
                'title' => 'Pago confirmado',
                'body' => 'Se confirmo el pago de la nota ' . $note->folio
                    . ' por $' . number_format($amount, 2) . ' MXN.',
                'url' => '/portal/notas/' . $note->id,
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
            $this->createOnce($access, [
                'type' => 'portal.payment.succeeded',
                'title' => 'Pago confirmado',
                'body' => 'Se confirmo un pago por $' . number_format($amount, 2) . ' MXN.',
                'url' => '/portal/pagos',
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
            if (!$this->noteIsVisibleForAccess($access, $animalIds)) {
                continue;
            }

            $this->createOnce($access, [
                'animal_id' => $this->firstVisibleAnimalId($access, $animalIds, 'show_notes'),
                'type' => $note->balance > 0 ? 'portal.note.payment_pending' : 'portal.note.created',
                'title' => $note->balance > 0 ? 'Nota pendiente de pago' : 'Nota publicada',
                'body' => 'La nota ' . $note->folio . ' esta disponible en tu portal.',
                'url' => '/portal/notas/' . $note->id,
                'data' => [
                    'note_id' => $note->id,
                    'folio' => $note->folio,
                    'total' => (float) $note->total,
                    'balance' => $note->balance,
                ],
            ]);
        }
    }

    public function videoPublished(AnimalVideo $video): void
    {
        $video->loadMissing('animal.customer');
        $animal = $video->animal;

        if (!$animal || !$animal->customer) {
            return;
        }

        $this->notifyAnimalResource($animal, 'show_videos', [
            'type' => 'portal.video.created',
            'title' => 'Video disponible',
            'body' => 'Se agrego un video al expediente de ' . $animal->name . '.',
            'url' => '/portal/mascotas/' . $animal->id,
            'data' => [
                'animal_id' => $animal->id,
                'video_id' => $video->id,
                'video_date' => $video->video_date?->toDateString(),
            ],
        ]);
    }

    public function radiologyStudyPublished(RadiologyStudy $study): void
    {
        $study->loadMissing('animal.customer');
        $animal = $study->animal;

        if (!$animal || !$animal->customer) {
            return;
        }

        $this->notifyAnimalResource($animal, 'show_radiology', [
            'type' => 'portal.radiology.study_created',
            'title' => 'Estudio RX disponible',
            'body' => 'Se agrego el estudio ' . $study->name . ' al expediente de ' . $animal->name . '.',
            'url' => '/portal/mascotas/' . $animal->id,
            'data' => [
                'animal_id' => $animal->id,
                'radiology_study_id' => $study->id,
                'study_date' => $study->study_date?->toDateString(),
            ],
        ]);
    }

    public function radiologyImagesPublished(RadiologyStudy $study, int $imageCount, ?int $lastImageId = null): void
    {
        $study->loadMissing('animal.customer');
        $animal = $study->animal;

        if (!$animal || !$animal->customer || $imageCount <= 0) {
            return;
        }

        $this->notifyAnimalResource($animal, 'show_radiology', [
            'type' => 'portal.radiology.images_created',
            'title' => 'Imagenes RX disponibles',
            'body' => 'Se agregaron ' . $imageCount . ' imagen(es) RX al expediente de ' . $animal->name . '.',
            'url' => '/portal/mascotas/' . $animal->id,
            'data' => [
                'animal_id' => $animal->id,
                'radiology_study_id' => $study->id,
                'radiology_image_id' => $lastImageId,
                'image_count' => $imageCount,
            ],
        ]);
    }

    public function radiologyImagePublished(RadiologyImage $image): void
    {
        $image->loadMissing('study.animal.customer');

        if (!$image->study) {
            return;
        }

        $this->radiologyImagesPublished($image->study, 1, $image->id);
    }

    public function vaccinationLetterPublished(VaccinationLetter $letter): void
    {
        $letter->loadMissing('animal.customer');
        $animal = $letter->animal;

        if (!$animal || !$animal->customer) {
            return;
        }

        $this->notifyAnimalResource($animal, 'show_vaccines', [
            'type' => 'portal.vaccination_letter.created',
            'title' => 'Carta de vacunacion disponible',
            'body' => 'Se agrego una carta de vacunacion al expediente de ' . $animal->name . '.',
            'url' => '/portal/mascotas/' . $animal->id,
            'data' => [
                'animal_id' => $animal->id,
                'vaccination_letter_id' => $letter->id,
                'date' => $letter->date?->toDateString(),
            ],
        ]);
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

    private function noteIsVisibleForAccess(CustomerPortalAccess $access, SupportCollection $animalIds): bool
    {
        if ($animalIds->isEmpty()) {
            return $this->hasVisibleSection($access, 'show_notes')
                || $this->hasAssignedPatient($access);
        }

        return $animalIds
            ->contains(fn ($animalId) => $this->hasVisibleSection($access, 'show_notes', (int) $animalId));
    }

    private function firstVisibleAnimalId(CustomerPortalAccess $access, SupportCollection $animalIds, string $section): ?int
    {
        return $animalIds
            ->first(fn ($animalId) => $this->hasVisibleSection($access, $section, (int) $animalId));
    }

    private function notifyAnimalResource(Animal $animal, string $section, array $payload): void
    {
        $customer = $animal->customer;

        if (!$customer) {
            return;
        }

        foreach ($this->activeAccesses($customer) as $access) {
            if (!$this->hasVisibleSection($access, $section, $animal->id)) {
                continue;
            }

            $this->createOnce($access, [
                ...$payload,
                'animal_id' => $animal->id,
            ]);
        }
    }

    private function hasAssignedPatient(CustomerPortalAccess $access): bool
    {
        return AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
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

        $existsQuery = PortalNotification::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->where('type', $payload['type']);

        foreach ([
            'note_id',
            'statement_id',
            'payment_id',
            'video_id',
            'radiology_study_id',
            'radiology_image_id',
            'vaccination_letter_id',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $existsQuery->where('data->' . $key, $data[$key]);
            }
        }

        $exists = $existsQuery->exists();

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
