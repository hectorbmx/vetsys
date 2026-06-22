<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTenantAppointmentRequest;
use App\Http\Requests\ConfirmAppointmentRequest;
use App\Http\Requests\FinishAppointmentRequest;
use App\Http\Requests\ProposeAppointmentRequest;
use App\Http\Requests\RejectAppointmentRequest;
use App\Http\Requests\StoreManualAppointmentRequest;
use App\Http\Requests\TenantAppointmentAvailabilityRequest;
use App\Http\Requests\TenantAppointmentIndexRequest;
use App\Http\Resources\Api\Tenant\TenantAppointmentResource;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentConfigurationService;
use App\Services\AppointmentService;
use App\Services\TenantAppointmentQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TenantAppointmentController extends Controller
{
    public function __construct(
        private TenantAppointmentQueryService $queries,
        private AppointmentAvailabilityService $availability,
        private AppointmentService $appointments,
    ) {}

    public function bootstrap(Request $request)
    {
        $this->authorize('view-appointments');
        $tenant = $request->user()->tenant;
        $options = $this->queries->formOptions($tenant, $request->user());
        $readiness = app(AppointmentConfigurationService::class)
            ->readiness($tenant, $options['setting']);

        return response()->json([
            'data' => [
                'timezone' => $options['setting']?->timezone,
                'ready' => $readiness['ready'],
                'readiness' => $readiness,
                'doctor' => $options['setting']?->doctor ? [
                    'id' => $options['setting']->doctor->id,
                    'name' => $options['setting']->doctor->veterinarianProfile?->professional_name
                        ?: $options['setting']->doctor->name,
                ] : null,
                'customers' => $options['customers']->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'animals' => $customer->animals->map(fn (Animal $animal) => [
                        'id' => $animal->id,
                        'name' => $animal->name,
                    ])->values(),
                ])->values(),
                'services' => $options['services']->map(fn (CatalogItem $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => (int) ($service->appointment_duration_minutes
                        ?: $options['setting']?->default_duration_minutes),
                    'buffer_minutes' => (int) ($service->appointment_buffer_minutes ?: 0),
                ])->values(),
            ],
        ]);
    }

    public function availability(TenantAppointmentAvailabilityRequest $request)
    {
        $tenant = $request->user()->tenant;
        $service = $tenant->catalogItems()
            ->where('type', 'service')
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->findOrFail($request->integer('service_id'));
        $from = $request->string('from')->toString();
        $to = $request->filled('to') ? $request->string('to')->toString() : $from;
        $slots = $this->availability->slotsForRange(
            $tenant,
            $service,
            $from,
            $to,
            applyCustomerRules: false,
        );

        return response()->json([
            'data' => $slots->map(fn ($dateSlots) => $dateSlots
                ->map(fn ($slot) => $slot->toArray())
                ->values()),
            'meta' => ['from' => $from, 'to' => $to, 'timezone' => $tenant->appointmentSetting?->timezone],
        ]);
    }

    public function index(TenantAppointmentIndexRequest $request)
    {
        $tenant = $request->user()->tenant;
        $timezone = $tenant->appointmentSetting?->timezone ?: 'UTC';
        $from = $request->input('from', CarbonImmutable::now($timezone)->toDateString());
        $to = $request->input('to', CarbonImmutable::parse($from, $timezone)->addDays(6)->toDateString());
        $appointments = $this->queries->queryForRange($tenant, $request->user(), $from, $to, [
            'statuses' => $request->input('statuses', []),
            'customer_id' => $request->integer('customer_id') ?: null,
            'animal_id' => $request->integer('animal_id') ?: null,
        ])->paginate($request->integer('per_page', 30));

        return TenantAppointmentResource::collection($appointments)->additional([
            'range' => ['from' => $from, 'to' => $to, 'timezone' => $timezone],
        ]);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->authorize('view-appointments');

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    public function storeManual(StoreManualAppointmentRequest $request)
    {
        $tenant = $request->user()->tenant;
        $customer = $tenant->customers()->findOrFail($request->integer('customer_id'));
        $animal = $tenant->animals()
            ->where('customer_id', $customer->id)
            ->findOrFail($request->integer('animal_id'));
        $service = $tenant->catalogItems()->findOrFail($request->integer('service_id'));
        $appointment = $this->appointments->createManual(
            $tenant,
            $request->user(),
            $customer,
            $animal,
            $service,
            CarbonImmutable::parse($request->string('starts_at')->toString()),
            $request->integer('duration_minutes') ?: null,
            $request->input('customer_reason'),
            $request->input('internal_notes'),
            $request->string('idempotency_key')->toString(),
        );

        return (new TenantAppointmentResource(
            $this->queries->detail($tenant, $request->user(), $appointment->id),
        ))->response()->setStatusCode(201);
    }

    public function confirm(ConfirmAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->confirm(
            $this->detail($request, $appointment, false),
            $request->user(),
            $request->integer('duration_minutes') ?: null,
            $request->input('internal_notes'),
            $request->string('idempotency_key')->toString(),
        );

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    public function reject(RejectAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->reject(
            $this->detail($request, $appointment, false),
            $request->user(),
            $request->string('reason')->toString(),
            $request->string('idempotency_key')->toString(),
        );

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    public function propose(ProposeAppointmentRequest $request, Appointment $appointment)
    {
        $proposal = $this->appointments->propose(
            $this->detail($request, $appointment, false),
            $request->user(),
            CarbonImmutable::parse($request->string('starts_at')->toString()),
            $request->integer('duration_minutes') ?: null,
            $request->input('message'),
            $request->string('idempotency_key')->toString(),
        );

        return (new TenantAppointmentResource($this->detail($request, $proposal->appointment)))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(CancelTenantAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->cancel(
            $this->detail($request, $appointment, false),
            $request->user(),
            $request->string('reason')->toString(),
            $request->string('idempotency_key')->toString(),
        );

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    public function complete(FinishAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->complete(
            $this->detail($request, $appointment, false),
            $request->user(),
            $request->string('idempotency_key')->toString(),
        );

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    public function noShow(FinishAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->markNoShow(
            $this->detail($request, $appointment, false),
            $request->user(),
            $request->string('idempotency_key')->toString(),
        );

        return new TenantAppointmentResource($this->detail($request, $appointment));
    }

    private function detail(Request $request, Appointment $appointment, bool $withRelations = true): Appointment
    {
        $result = $this->queries->detail(
            $request->user()->tenant,
            $request->user(),
            $appointment->id,
        );

        return $withRelations ? $result : $result->withoutRelations();
    }
}
