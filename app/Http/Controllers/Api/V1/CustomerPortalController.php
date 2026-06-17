<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalVideo;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\CustomerStatement;
use App\Models\CustomerPortalAccess;
use App\Models\FinalUserPatientAssignment;
use App\Models\Note;
use App\Models\NoteDetail;
use App\Models\PortalNotification;
use App\Models\RadiologyImage;
use App\Models\RadiologyStudy;
use App\Models\VaccinationLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CustomerPortalController extends Controller
{
    public function bootstrap(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $since = isset($data['since']) ? Carbon::parse($data['since']) : null;
        $access = $this->portalAccess($request);
        $user = $request->user()->loadMissing('tenant');

        $assignments = $this->activeAssignments($access)
            ->with(['animal.animalType', 'animal.club'])
            ->get();

        $patientIds = $assignments
            ->pluck('animal_id')
            ->filter()
            ->values()
            ->all();

        $visibilityByAnimal = $this->visibilityByAnimal($access, $patientIds);

        return response()->json([
            'server_time' => now()->toISOString(),
            'since' => $since?->toISOString(),
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'tenant' => [
                    'id' => $user->tenant_id,
                    'name' => $user->tenant?->name,
                    'business_name' => $user->tenant?->business_name,
                    'email' => $user->tenant?->email,
                    'phone' => $user->tenant?->phone,
                ],
                'customer' => [
                    'id' => $access->customer->id,
                    'name' => $access->customer->name,
                    'last_name' => $access->customer->last_name,
                    'full_name' => $access->customer->full_name,
                    'email' => $access->customer->email,
                    'phone' => $access->customer->phone,
                ],
                'account_summary' => $this->accountSummary($access),
                'portal_access' => [
                    'status' => $access->status,
                    'billing_mode' => $access->billing_mode,
                    'access_starts_at' => $access->access_starts_at?->toISOString(),
                    'access_ends_at' => $access->access_ends_at?->toISOString(),
                ],
                'patients' => $assignments
                    ->filter(fn (FinalUserPatientAssignment $assignment) => $assignment->animal)
                    ->map(fn (FinalUserPatientAssignment $assignment) => $this->serializePatient(
                        $assignment->animal,
                        $visibilityByAnimal[$assignment->animal_id] ?? null
                    ))
                    ->values(),
                'note_summaries' => $this->noteSummaries($access, $patientIds, $visibilityByAnimal, $since),
                'statement_summaries' => $this->statementSummaries($access, $since),
                'notifications' => $this->notificationSummaries($access, $since),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $access = $this->portalAccess($request);
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'tenant' => [
                    'id' => $user->tenant_id,
                    'name' => $user->tenant?->name,
                    'business_name' => $user->tenant?->business_name,
                    'email' => $user->tenant?->email,
                    'phone' => $user->tenant?->phone,
                ],
                'customer' => [
                    'id' => $access->customer->id,
                    'name' => $access->customer->name,
                    'last_name' => $access->customer->last_name,
                    'full_name' => $access->customer->full_name,
                    'email' => $access->customer->email,
                    'phone' => $access->customer->phone,
                ],
                'portal_access' => [
                    'status' => $access->status,
                    'billing_mode' => $access->billing_mode,
                    'access_starts_at' => $access->access_starts_at?->toISOString(),
                    'access_ends_at' => $access->access_ends_at?->toISOString(),
                ],
            ],
        ]);
    }

    public function patients(Request $request)
    {
        $access = $this->portalAccess($request);

        $assignments = $this->activeAssignments($access)
            ->with(['animal.animalType', 'animal.club'])
            ->get();

        $visibilityByAnimal = $this->visibilityByAnimal($access, $assignments->pluck('animal_id')->all());

        return response()->json([
            'data' => $assignments
                ->filter(fn (FinalUserPatientAssignment $assignment) => $assignment->animal)
                ->map(fn (FinalUserPatientAssignment $assignment) => $this->serializePatient(
                    $assignment->animal,
                    $visibilityByAnimal[$assignment->animal_id] ?? null
                ))
                ->values(),
        ]);
    }

    public function patient(Request $request, Animal $patient)
    {
        $access = $this->portalAccess($request);
        $this->authorizePatient($access, $patient);

        $patient->loadMissing(['animalType', 'club', 'customer']);

        $visibility = AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->where('animal_id', $patient->id)
            ->first();

        return response()->json([
            'data' => $this->serializePatient($patient, $visibility, detailed: true),
        ]);
    }

    public function patientNotes(Request $request, Animal $patient)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $access = $this->portalAccess($request);
        $visibility = $this->authorizePatientSection($access, $patient, 'show_notes');

        $notes = Note::with(['details.catalogItem', 'details.animal'])
            ->where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->whereHas('details', fn (Builder $query) => $query->where('animal_id', $patient->id))
            ->latest('date_at')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $notes->getCollection()
                ->map(fn (Note $note) => $this->serializeNote($note, [$patient->id]))
                ->values(),
            'meta' => [
                'patient_visibility' => $this->serializeVisibility($visibility),
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
            ],
        ]);
    }

    public function note(Request $request, Note $note)
    {
        $access = $this->portalAccess($request);
        abort_unless($note->tenant_id === $access->tenant_id, 404);
        abort_unless($note->customer_id === $access->customer_id, 404);

        $assignedPatientIds = $this->activeAssignments($access)->pluck('animal_id')->all();
        $note->loadMissing(['details.catalogItem', 'details.animal', 'payments.paymentMethod']);

        $visiblePatientIds = $note->details
            ->pluck('animal_id')
            ->filter()
            ->intersect($assignedPatientIds)
            ->values()
            ->all();

        return response()->json([
            'data' => $this->serializeNote($note, $assignedPatientIds, detailed: true),
        ]);
    }

    public function patientHistory(Request $request, Animal $patient)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $access = $this->portalAccess($request);
        $visibility = $this->authorizePatientSection($access, $patient, 'show_history');

        $details = NoteDetail::with(['note', 'catalogItem'])
            ->where('tenant_id', $access->tenant_id)
            ->where('animal_id', $patient->id)
            ->whereHas('note', fn (Builder $query) => $query
                ->where('customer_id', $access->customer_id))
            ->latest('id')
            ->paginate($data['per_page'] ?? 30);

        return response()->json([
            'data' => $details->getCollection()
                ->map(fn (NoteDetail $detail) => [
                    'id' => $detail->id,
                    'type' => $detail->catalogItem?->type,
                    'title' => $detail->catalogItem?->name ?? 'Movimiento',
                    'note_id' => $detail->note_id,
                    'folio' => $detail->note?->folio,
                    'date_at' => $detail->note?->date_at?->toDateString(),
                    'quantity' => (float) $detail->quantity,
                    'subtotal' => (float) $detail->subtotal,
                    'note_status' => $detail->note?->status,
                    'note_balance' => $detail->note?->balance,
                ])
                ->values(),
            'meta' => [
                'patient_visibility' => $this->serializeVisibility($visibility),
                'current_page' => $details->currentPage(),
                'last_page' => $details->lastPage(),
                'per_page' => $details->perPage(),
                'total' => $details->total(),
            ],
        ]);
    }

    public function patientVideos(Request $request, Animal $patient)
    {
        $access = $this->portalAccess($request);
        $visibility = $this->authorizePatientSection($access, $patient, 'show_videos');

        $videos = AnimalVideo::where('tenant_id', $access->tenant_id)
            ->where('animal_id', $patient->id)
            ->latest('video_date')
            ->get();

        return response()->json([
            'data' => $videos->map(fn (AnimalVideo $video) => [
                'id' => $video->id,
                'video_date' => $video->video_date?->toDateString(),
                'original_name' => $video->original_name,
                'mime_type' => $video->mime_type,
                'size' => $video->size,
                'notes' => $video->notes,
                'url' => $this->temporaryStorageUrl($video->disk, $video->path),
                'published_at' => $video->published_at?->toISOString(),
                'updated_at' => $video->updated_at?->toISOString(),
            ])->values(),
            'meta' => [
                'patient_visibility' => $this->serializeVisibility($visibility),
            ],
        ]);
    }

    public function patientRadiology(Request $request, Animal $patient)
    {
        $access = $this->portalAccess($request);
        $visibility = $this->authorizePatientSection($access, $patient, 'show_radiology');

        $studies = RadiologyStudy::with(['images' => fn ($query) => $query->orderBy('id')])
            ->where('tenant_id', $access->tenant_id)
            ->where('animal_id', $patient->id)
            ->latest('study_date')
            ->get();

        return response()->json([
            'data' => $studies->map(fn (RadiologyStudy $study) => [
                'id' => $study->id,
                'name' => $study->name,
                'study_date' => $study->study_date?->toDateString(),
                'notes' => $study->notes,
                'images' => $study->images->map(fn (RadiologyImage $image) => [
                    'id' => $image->id,
                    'label' => $image->label,
                    'original_name' => $image->original_name,
                    'mime_type' => $image->mime_type,
                    'size' => $image->size,
                    'notes' => $image->notes,
                    'url' => $this->temporaryStorageUrl($image->disk, $image->path),
                    'published_at' => $image->published_at?->toISOString(),
                ])->values(),
                'published_at' => $study->published_at?->toISOString(),
                'updated_at' => $study->updated_at?->toISOString(),
            ])->values(),
            'meta' => [
                'patient_visibility' => $this->serializeVisibility($visibility),
            ],
        ]);
    }

    public function patientVaccines(Request $request, Animal $patient)
    {
        $access = $this->portalAccess($request);
        $visibility = $this->authorizePatientSection($access, $patient, 'show_vaccines');

        $letters = VaccinationLetter::where('tenant_id', $access->tenant_id)
            ->where('animal_id', $patient->id)
            ->latest('date')
            ->get();

        return response()->json([
            'data' => $letters->map(fn (VaccinationLetter $letter) => [
                'id' => $letter->id,
                'date' => $letter->date?->toDateString(),
                'image_url' => $this->publicStorageUrl($letter->image_path),
                'pdf_url' => $this->vaccinationPdfUrl($letter),
                'published_at' => $letter->published_at?->toISOString(),
                'updated_at' => $letter->updated_at?->toISOString(),
            ])->values(),
            'meta' => [
                'patient_visibility' => $this->serializeVisibility($visibility),
            ],
        ]);
    }

    public function vaccinationLetterPdf(Request $request, VaccinationLetter $vaccinationLetter)
    {
        $access = $this->portalAccess($request);

        abort_unless($vaccinationLetter->tenant_id === $access->tenant_id, 404);
        abort_unless($vaccinationLetter->animal, 404);

        $this->authorizePatientSection($access, $vaccinationLetter->animal, 'show_vaccines');
        abort_unless($this->publicStorageFileExists($vaccinationLetter->image_path), 404);

        $vaccinationLetter->loadMissing(['tenant', 'animal.customer', 'animal.animalType']);

        $pdf = Pdf::loadView('client.animals.vaccination-letter-pdf', [
            'letter' => $vaccinationLetter,
            'animal' => $vaccinationLetter->animal,
            'customer' => $vaccinationLetter->animal->customer,
            'tenant' => $vaccinationLetter->tenant,
            'imageDataUri' => $this->publicStorageImageAsDataUri($vaccinationLetter->image_path),
            'tenantLogoDataUri' => $this->publicStorageImageAsDataUri($vaccinationLetter->tenant?->logo),
            'generatedDate' => Carbon::now()->format('Y-m-d'),
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'carta-vacunacion-' . str($vaccinationLetter->animal->name)->slug() . '-' . $vaccinationLetter->date->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }

    public function statements(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $access = $this->portalAccess($request);
        abort_unless($this->hasAnyVisibleSection($access, 'show_statement'), 404);

        $statements = CustomerStatement::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->latest('period_end')
            ->paginate($data['per_page'] ?? 12);

        return response()->json([
            'data' => $statements->getCollection()
                ->map(fn (CustomerStatement $statement) => $this->serializeStatement($statement))
                ->values(),
            'meta' => [
                'current_page' => $statements->currentPage(),
                'last_page' => $statements->lastPage(),
                'per_page' => $statements->perPage(),
                'total' => $statements->total(),
            ],
        ]);
    }

    public function statementPdf(Request $request, CustomerStatement $statement)
    {
        $access = $this->portalAccess($request);
        abort_unless($this->hasAnyVisibleSection($access, 'show_statement'), 404);
        abort_unless($statement->tenant_id === $access->tenant_id, 404);
        abort_unless($statement->customer_id === $access->customer_id, 404);
        abort_unless($statement->pdf_path && Storage::disk('local')->exists($statement->pdf_path), 404);

        return response()->file(storage_path('app/' . $statement->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($statement->pdf_path) . '"',
        ]);
    }

    public function notifications(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $access = $this->portalAccess($request);

        $baseQuery = PortalNotification::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id);

        $notifications = (clone $baseQuery)
            ->when($request->boolean('unread_only'), fn (Builder $query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($data['per_page'] ?? 30);

        return response()->json([
            'data' => $notifications->getCollection()
                ->map(fn (PortalNotification $notification) => $this->serializeNotification($notification))
                ->values(),
            'meta' => [
                'unread_count' => (clone $baseQuery)->whereNull('read_at')->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markNotificationRead(Request $request, PortalNotification $notification)
    {
        $access = $this->portalAccess($request);
        $this->authorizeNotification($access, $notification);

        $notification->markAsRead();

        return response()->json([
            'data' => $this->serializeNotification($notification->fresh()),
            'meta' => [
                'unread_count' => PortalNotification::where('tenant_id', $access->tenant_id)
                    ->where('customer_id', $access->customer_id)
                    ->where('user_id', $access->user_id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        $access = $this->portalAccess($request);

        PortalNotification::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'meta' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function portalAccess(Request $request): CustomerPortalAccess
    {
        return $request->attributes->get('customer_portal_access');
    }

    private function activeAssignments(CustomerPortalAccess $access)
    {
        return FinalUserPatientAssignment::query()
            ->where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->whereNull('revoked_at');
    }

    private function authorizePatient(CustomerPortalAccess $access, Animal $patient): void
    {
        abort_unless($patient->tenant_id === $access->tenant_id, 404);
        abort_unless($patient->customer_id === $access->customer_id, 404);

        $assigned = $this->activeAssignments($access)
            ->where('animal_id', $patient->id)
            ->exists();

        abort_unless($assigned, 404);
    }

    private function authorizePatientSection(CustomerPortalAccess $access, Animal $patient, string $section): AnimalPortalVisibilitySetting
    {
        $this->authorizePatient($access, $patient);

        $visibility = AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->where('animal_id', $patient->id)
            ->first();

        abort_unless($visibility && $visibility->{$section}, 404);

        return $visibility;
    }

    private function visibilityByAnimal(CustomerPortalAccess $access, array $animalIds)
    {
        return AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->whereIn('animal_id', $animalIds)
            ->get()
            ->keyBy('animal_id');
    }

    private function noteSummaries(CustomerPortalAccess $access, array $patientIds, $visibilityByAnimal, ?Carbon $since)
    {
        return Note::with(['details.catalogItem', 'details.animal'])
            ->where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->when($since, function (Builder $query) use ($since) {
                $query->where(function (Builder $query) use ($since) {
                    $query->where('updated_at', '>=', $since)
                        ->orWhere('published_at', '>=', $since);
                });
            })
            ->latest('date_at')
            ->limit(50)
            ->get()
            ->map(function (Note $note) use ($patientIds) {
                $details = $this->customerVisibleNoteDetails($note, $patientIds);

                return [
                    'id' => $note->id,
                    'folio' => $note->folio,
                    'date_at' => $note->date_at?->toDateString(),
                    'status' => $note->status,
                    'total' => (float) $note->total,
                    'paid' => $note->amount_paid,
                    'balance' => $note->balance,
                    'payable' => $note->balance > 0 && $note->status === 'PENDIENTE',
                    'animal_ids' => $details->pluck('animal_id')->filter()->unique()->values(),
                    'items_count' => $details->count(),
                    'items_preview' => $details
                        ->take(3)
                        ->map(fn ($detail) => [
                            'id' => $detail->id,
                            'animal_id' => $detail->animal_id,
                            'name' => $detail->catalogItem?->name,
                            'type' => $detail->catalogItem?->type,
                            'quantity' => (float) $detail->quantity,
                            'subtotal' => (float) $detail->subtotal,
                        ])
                        ->values(),
                    'published_at' => $note->published_at?->toISOString(),
                    'updated_at' => $note->updated_at?->toISOString(),
                ];
            });
    }

    private function statementSummaries(CustomerPortalAccess $access, ?Carbon $since)
    {
        return CustomerStatement::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->when($since, function (Builder $query) use ($since) {
                $query->where(function (Builder $query) use ($since) {
                    $query->where('updated_at', '>=', $since)
                        ->orWhere('published_at', '>=', $since)
                        ->orWhere('generated_at', '>=', $since);
                });
            })
            ->latest('period_end')
            ->limit(12)
            ->get()
            ->map(fn (CustomerStatement $statement) => [
                'id' => $statement->id,
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'cutoff_day' => $statement->cutoff_day,
                'previous_balance' => (float) $statement->previous_balance,
                'period_charges' => (float) $statement->period_charges,
                'period_payments' => (float) $statement->period_payments,
                'ending_balance' => (float) $statement->ending_balance,
                'status' => $statement->status,
                'pdf_available' => (bool) $statement->pdf_path,
                'generated_at' => $statement->generated_at?->toISOString(),
                'published_at' => $statement->published_at?->toISOString(),
            ]);
    }

    private function accountSummary(CustomerPortalAccess $access): array
    {
        $notes = Note::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->get();

        $pendingNotes = $notes->filter(fn (Note $note) => $note->balance > 0 && $note->status !== 'CANCELADA');

        return [
            'total_notes' => $notes->count(),
            'pending_notes' => $pendingNotes->count(),
            'total_charges' => (float) $notes->sum('total'),
            'amount_paid' => (float) $notes->sum(fn (Note $note) => $note->amount_paid),
            'outstanding_balance' => (float) $pendingNotes->sum(fn (Note $note) => max($note->balance, 0)),
            'credit_balance' => (float) $access->customer->credit_balance,
        ];
    }

    private function notificationSummaries(CustomerPortalAccess $access, ?Carbon $since)
    {
        return PortalNotification::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->when($since, function (Builder $query) use ($since) {
                $query->where(function (Builder $query) use ($since) {
                    $query->where('created_at', '>=', $since)
                        ->orWhere('updated_at', '>=', $since)
                        ->orWhere('read_at', '>=', $since);
                });
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (PortalNotification $notification) => $this->serializeNotification($notification));
    }

    private function authorizeNotification(CustomerPortalAccess $access, PortalNotification $notification): void
    {
        abort_unless($notification->tenant_id === $access->tenant_id, 404);
        abort_unless($notification->customer_id === $access->customer_id, 404);
        abort_unless($notification->user_id === $access->user_id, 404);
    }

    private function serializeNotification(PortalNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'animal_id' => $notification->animal_id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'updated_at' => $notification->updated_at?->toISOString(),
        ];
    }

    private function hasAnyVisibleSection(CustomerPortalAccess $access, string $section): bool
    {
        return AnimalPortalVisibilitySetting::where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $access->user_id)
            ->where($section, true)
            ->whereHas('animal.finalUserPatientAssignments', fn (Builder $query) => $query
                ->where('tenant_id', $access->tenant_id)
                ->where('customer_id', $access->customer_id)
                ->where('user_id', $access->user_id)
                ->whereNull('revoked_at'))
            ->exists();
    }

    private function temporaryStorageUrl(?string $disk, ?string $path): ?string
    {
        if (!$disk || !$path || !Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (!$path || !$this->publicStorageFileExists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function publicStorageImageAsDataUri(?string $path): ?string
    {
        if (!$path || !$this->publicStorageFileExists($path)) {
            return null;
        }

        $fullPath = $this->publicStorageFilePath($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }

    private function publicStorageFileExists(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return Storage::disk('public')->exists($path)
            || is_file(public_path('storage/' . ltrim($path, '/')));
    }

    private function publicStorageFilePath(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return public_path('storage/' . ltrim($path, '/'));
    }

    private function vaccinationPdfUrl(VaccinationLetter $letter): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'public.vaccination-letters.print',
            now()->addMinutes(30),
            ['vaccinationLetter' => $letter],
            false
        );

        return request()->getSchemeAndHttpHost() . $relativeUrl;
    }

    private function serializeStatement(CustomerStatement $statement): array
    {
        return [
            'id' => $statement->id,
            'period_start' => $statement->period_start?->toDateString(),
            'period_end' => $statement->period_end?->toDateString(),
            'cutoff_day' => $statement->cutoff_day,
            'previous_balance' => (float) $statement->previous_balance,
            'period_charges' => (float) $statement->period_charges,
            'period_payments' => (float) $statement->period_payments,
            'ending_balance' => (float) $statement->ending_balance,
            'status' => $statement->status,
            'pdf_available' => (bool) $statement->pdf_path,
            'pdf_url' => route('api.v1.portal.statements.pdf', $statement),
            'generated_at' => $statement->generated_at?->toISOString(),
            'published_at' => $statement->published_at?->toISOString(),
            'updated_at' => $statement->updated_at?->toISOString(),
        ];
    }

    private function serializePatient(Animal $animal, ?AnimalPortalVisibilitySetting $visibility, bool $detailed = false): array
    {
        $data = [
            'id' => $animal->id,
            'name' => $animal->name,
            'animal_type' => $animal->animalType ? [
                'id' => $animal->animalType->id,
                'name' => $animal->animalType->name,
            ] : null,
            'club' => $animal->club ? [
                'id' => $animal->club->id,
                'name' => $animal->club->name,
            ] : null,
            'status' => $animal->status,
            'photo_path' => $animal->photo_path,
            'birthdate' => $animal->birthdate?->toDateString(),
            'sex' => $animal->sex,
            'visibility' => $this->serializeVisibility($visibility),
            'updated_at' => $animal->updated_at?->toISOString(),
        ];

        if ($detailed && ($visibility?->show_profile ?? false)) {
            $data['profile'] = [
                'color' => $animal->color,
                'weight' => $animal->weight,
                'microchip' => $animal->microchip,
                'notes' => $animal->notes,
            ];
        }

        return $data;
    }

    private function serializeNote(Note $note, array $visiblePatientIds, bool $detailed = false): array
    {
        $details = $this->customerVisibleNoteDetails($note, $visiblePatientIds);

        $data = [
            'id' => $note->id,
            'folio' => $note->folio,
            'date_at' => $note->date_at?->toDateString(),
            'status' => $note->status,
            'total' => (float) $note->total,
            'paid' => $note->amount_paid,
            'balance' => $note->balance,
            'payable' => $note->balance > 0 && $note->status === 'PENDIENTE',
            'animal_ids' => $details->pluck('animal_id')->filter()->unique()->values(),
            'items_count' => $details->count(),
            'published_at' => $note->published_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];

        if ($detailed) {
            $data['items'] = $details
                ->map(fn ($detail) => [
                    'id' => $detail->id,
                    'animal_id' => $detail->animal_id,
                    'animal_name' => $detail->animal?->name,
                    'catalog_item_id' => $detail->catalog_item_id,
                    'name' => $detail->catalogItem?->name,
                    'type' => $detail->catalogItem?->type,
                    'quantity' => (float) $detail->quantity,
                    'price_at_sale' => (float) $detail->price_at_sale,
                    'tax_at_sale' => (float) $detail->tax_at_sale,
                    'subtotal' => (float) $detail->subtotal,
                ])
                ->values();

            $data['payments'] = $note->payments
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'payment_method' => $payment->paymentMethod?->name,
                    'amount' => (float) $payment->amount,
                    'amount_applied' => (float) $payment->pivot?->amount_applied,
                    'reference' => $payment->reference,
                    'created_at' => $payment->created_at?->toISOString(),
                ])
                ->values();
        } else {
            $data['items_preview'] = $details
                ->take(3)
                ->map(fn ($detail) => [
                    'id' => $detail->id,
                    'animal_id' => $detail->animal_id,
                    'name' => $detail->catalogItem?->name,
                    'type' => $detail->catalogItem?->type,
                    'quantity' => (float) $detail->quantity,
                    'subtotal' => (float) $detail->subtotal,
                ])
                ->values();
        }

        return $data;
    }

    private function customerVisibleNoteDetails(Note $note, array $visiblePatientIds)
    {
        $details = $note->details->filter(fn ($detail) => !$detail->animal_id
            || in_array($detail->animal_id, $visiblePatientIds, true));

        return $details->isNotEmpty() ? $details : $note->details;
    }

    private function serializeVisibility(?AnimalPortalVisibilitySetting $visibility): array
    {
        return [
            'profile' => (bool) ($visibility?->show_profile ?? false),
            'history' => (bool) ($visibility?->show_history ?? false),
            'notes' => (bool) ($visibility?->show_notes ?? false),
            'services' => (bool) ($visibility?->show_services ?? false),
            'products' => (bool) ($visibility?->show_products ?? false),
            'files' => (bool) ($visibility?->show_files ?? false),
            'videos' => (bool) ($visibility?->show_videos ?? false),
            'radiology' => (bool) ($visibility?->show_radiology ?? false),
            'statement' => (bool) ($visibility?->show_statement ?? false),
            'vaccines' => (bool) ($visibility?->show_vaccines ?? false),
            'appointments' => (bool) ($visibility?->show_appointments ?? false),
        ];
    }
}
