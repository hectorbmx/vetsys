<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\CustomerAppointmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\AppointmentAvailabilityRequest;
use App\Http\Requests\Api\Customer\AppointmentIndexRequest;
use App\Http\Requests\Api\Customer\AppointmentProposalResponseRequest;
use App\Http\Requests\Api\Customer\CancelAppointmentRequest;
use App\Http\Requests\Api\Customer\StoreAppointmentRequest;
use App\Http\Resources\Api\Customer\AppointmentResource;
use App\Http\Resources\Api\Customer\BookableServiceResource;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\CatalogItem;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentConfigurationService;
use App\Services\AppointmentService;
use App\Services\CustomerAppointmentContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomerAppointmentController extends Controller
{
    public function __construct(
        private CustomerAppointmentContextResolver $contexts,
        private AppointmentAvailabilityService $availability,
        private AppointmentService $appointments,
    ) {}

    public function bootstrap(Request $request)
    {
        $context = $this->contexts->resolve($request);
        $setting = $context->tenant->appointmentSetting()->with('doctor.veterinarianProfile')->first();
        $readiness = app(AppointmentConfigurationService::class)->readiness($context->tenant, $setting);
        $request->attributes->set('appointment_default_duration', $setting?->default_duration_minutes ?? 30);

        $patients = Animal::query()
            ->whereIn('id', $context->visibleAnimalIds)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return response()->json([
            'data' => [
                'enabled' => (bool) ($setting?->is_customer_booking_enabled && $readiness['ready']),
                'timezone' => $setting?->timezone,
                'booking_window_days' => $setting?->booking_window_days,
                'minimum_notice_minutes' => $setting?->minimum_notice_minutes,
                'cancellation_notice_minutes' => $setting?->customer_cancellation_notice_minutes,
                'cancellation_policy' => $setting?->cancellation_policy?->value,
                'doctor' => $setting?->doctor ? [
                    'id' => $setting->doctor->id,
                    'name' => $setting->doctor->veterinarianProfile?->professional_name
                        ?: $setting->doctor->name,
                ] : null,
                'patients' => $patients->map(fn (Animal $animal) => [
                    'id' => $animal->id,
                    'name' => $animal->name,
                    'status' => $animal->status,
                ])->values(),
                'services' => BookableServiceResource::collection(
                    $this->bookableServices($context->tenant->id)->get(),
                )->resolve($request),
            ],
        ]);
    }

    public function services(Request $request)
    {
        $context = $this->contexts->resolve($request);
        $setting = $context->tenant->appointmentSetting()->first();
        $request->attributes->set('appointment_default_duration', $setting?->default_duration_minutes ?? 30);

        return BookableServiceResource::collection(
            $this->bookableServices($context->tenant->id)->get(),
        );
    }

    public function availability(AppointmentAvailabilityRequest $request)
    {
        $context = $this->contexts->resolve($request);
        $animal = Animal::where('tenant_id', $context->tenant->id)->findOrFail($request->integer('animal_id'));
        $this->contexts->authorizeAnimal($context, $animal);
        $service = $this->bookableServices($context->tenant->id)
            ->findOrFail($request->integer('service_id'));
        $from = $request->string('from')->toString();
        $to = $request->filled('to') ? $request->string('to')->toString() : $from;
        $slots = $this->availability->slotsForRange($context->tenant, $service, $from, $to);

        return response()->json([
            'data' => $slots->map(fn ($dateSlots) => $dateSlots
                ->map(fn ($slot) => $slot->toArray())
                ->values()),
            'meta' => [
                'from' => $from,
                'to' => $to,
                'timezone' => $context->tenant->appointmentSetting?->timezone,
            ],
        ]);
    }

    public function index(AppointmentIndexRequest $request)
    {
        $context = $this->contexts->resolve($request);
        $timezone = $context->tenant->appointmentSetting?->timezone ?: 'UTC';
        $fromUtc = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString(), $timezone)->startOfDay()->utc()
            : null;
        $toUtc = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString(), $timezone)->addDay()->startOfDay()->utc()
            : null;
        $appointments = $this->appointmentQuery($context)
            ->with('pendingProposal')
            ->when($request->filled('status'), fn (Builder $query) => $query
                ->where('status', $request->string('status')->toString()))
            ->when($fromUtc, fn (Builder $query) => $query->where('starts_at', '>=', $fromUtc))
            ->when($toUtc, fn (Builder $query) => $query->where('starts_at', '<', $toUtc))
            ->orderByDesc('starts_at')
            ->paginate($request->integer('per_page', 20));

        return AppointmentResource::collection($appointments);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $context = $this->contexts->resolve($request);
        $appointment = $this->scopedAppointment($context, $appointment->id);

        return new AppointmentResource($appointment);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $context = $this->contexts->resolve($request);
        $animal = Animal::where('tenant_id', $context->tenant->id)->findOrFail($request->integer('animal_id'));
        $this->contexts->authorizeAnimal($context, $animal);
        $service = $this->bookableServices($context->tenant->id)
            ->findOrFail($request->integer('service_id'));
        $appointment = $this->appointments->request(
            $context->tenant,
            $context->user,
            $context->customer,
            $animal,
            $service,
            CarbonImmutable::parse($request->string('starts_at')->toString()),
            $request->input('customer_reason'),
            $request->string('idempotency_key')->toString(),
        );

        return (new AppointmentResource($appointment->load('pendingProposal')))
            ->response()
            ->setStatusCode(201);
    }

    public function acceptProposal(
        AppointmentProposalResponseRequest $request,
        Appointment $appointment,
        AppointmentProposal $proposal,
    ) {
        $context = $this->contexts->resolve($request);
        $appointment = $this->scopedAppointment($context, $appointment->id, false);
        $proposal = $this->scopedProposal($appointment, $proposal);
        $appointment = $this->appointments->acceptProposal(
            $appointment,
            $proposal,
            $context->user,
            $request->string('idempotency_key')->toString(),
        );

        return new AppointmentResource($appointment->load('pendingProposal'));
    }

    public function rejectProposal(
        AppointmentProposalResponseRequest $request,
        Appointment $appointment,
        AppointmentProposal $proposal,
    ) {
        $context = $this->contexts->resolve($request);
        $appointment = $this->scopedAppointment($context, $appointment->id, false);
        $proposal = $this->scopedProposal($appointment, $proposal);
        $appointment = $this->appointments->rejectProposal(
            $appointment,
            $proposal,
            $context->user,
            $request->input('response_message'),
            $request->string('idempotency_key')->toString(),
        );

        return new AppointmentResource($appointment->load('pendingProposal'));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment)
    {
        $context = $this->contexts->resolve($request);
        $appointment = $this->scopedAppointment($context, $appointment->id, false);
        $appointment = $this->appointments->cancel(
            $appointment,
            $context->user,
            $request->input('reason'),
            $request->string('idempotency_key')->toString(),
        );

        return new AppointmentResource($appointment->load('pendingProposal'));
    }

    private function bookableServices(int $tenantId): Builder
    {
        return CatalogItem::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'service')
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->orderBy('name');
    }

    private function appointmentQuery(CustomerAppointmentContext $context): Builder
    {
        return Appointment::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('customer_id', $context->customer->id)
            ->whereIn('animal_id', $context->visibleAnimalIds);
    }

    private function scopedAppointment(
        CustomerAppointmentContext $context,
        int $appointmentId,
        bool $withDetail = true,
    ): Appointment {
        $query = $this->appointmentQuery($context)->whereKey($appointmentId);

        if ($withDetail) {
            $query->with([
                'pendingProposal',
                'proposals' => fn ($query) => $query->latest('id'),
                'events' => fn ($query) => $query->oldest('id'),
            ]);
        }

        return $query->firstOrFail();
    }

    private function scopedProposal(Appointment $appointment, AppointmentProposal $proposal): AppointmentProposal
    {
        abort_unless(
            (int) $proposal->tenant_id === (int) $appointment->tenant_id
            && (int) $proposal->appointment_id === (int) $appointment->id,
            404,
        );

        return $proposal;
    }
}
