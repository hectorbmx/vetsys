<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTenantAppointmentRequest;
use App\Http\Requests\ConfirmAppointmentRequest;
use App\Http\Requests\FinishAppointmentRequest;
use App\Http\Requests\ProposeAppointmentRequest;
use App\Http\Requests\RejectAppointmentRequest;
use App\Http\Requests\StoreManualAppointmentRequest;
use App\Http\Requests\TenantAppointmentAvailabilityRequest;
use App\Http\Requests\TenantAppointmentIndexRequest;
use App\Models\Appointment;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentConfigurationService;
use App\Services\AppointmentService;
use App\Services\TenantAppointmentQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private TenantAppointmentQueryService $queries,
        private AppointmentAvailabilityService $availability,
        private AppointmentService $appointments,
    ) {}

    public function index(TenantAppointmentIndexRequest $request)
    {
        $tenant = $request->user()->tenant;
        $options = $this->queries->formOptions($tenant, $request->user());
        $timezone = $options['setting']?->timezone ?: 'UTC';
        $viewMode = $request->input('view', 'week');
        $selectedDate = CarbonImmutable::parse(
            $request->input('date', CarbonImmutable::now($timezone)->toDateString()),
            $timezone,
        )->startOfDay();
        $from = $viewMode === 'day' ? $selectedDate : $selectedDate->startOfWeek();
        $to = $viewMode === 'day' ? $selectedDate : $selectedDate->endOfWeek();
        $filters = [
            'statuses' => $request->input('statuses', []),
            'customer_id' => $request->integer('customer_id') ?: null,
            'animal_id' => $request->integer('animal_id') ?: null,
        ];
        $appointments = $this->queries
            ->queryForRange($tenant, $request->user(), $from, $to, $filters)
            ->get();
        $appointmentsByDate = $appointments->groupBy(fn (Appointment $appointment) => $appointment->starts_at->setTimezone($timezone)->toDateString());
        $readiness = app(AppointmentConfigurationService::class)
            ->readiness($tenant, $options['setting']);
        $previousDate = $viewMode === 'day' ? $selectedDate->subDay() : $selectedDate->subWeek();
        $nextDate = $viewMode === 'day' ? $selectedDate->addDay() : $selectedDate->addWeek();

        return view('client.appointments.index', [
            ...$options,
            'appointments' => $appointments,
            'appointmentsByDate' => $appointmentsByDate,
            'readiness' => $readiness,
            'timezone' => $timezone,
            'viewMode' => $viewMode,
            'selectedDate' => $selectedDate,
            'from' => $from,
            'to' => $to,
            'previousDate' => $previousDate->toDateString(),
            'nextDate' => $nextDate->toDateString(),
        ]);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->authorize('view-appointments');

        return view('client.appointments.show', [
            'appointment' => $this->queries->detail(
                $request->user()->tenant,
                $request->user(),
                $appointment->id,
            ),
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

        return response()->json([
            'data' => $this->availability
                ->slotsForRange($tenant, $service, $from, $to, applyCustomerRules: false)
                ->map(fn ($slots) => $slots->map(fn ($slot) => $slot->toArray())->values()),
        ]);
    }

    public function storeManual(StoreManualAppointmentRequest $request)
    {
        $tenant = $request->user()->tenant;
        $customer = $tenant->customers()->findOrFail($request->integer('customer_id'));
        $animal = $tenant->animals()->where('customer_id', $customer->id)
            ->findOrFail($request->integer('animal_id'));
        $service = $tenant->catalogItems()->findOrFail($request->integer('service_id'));
        $appointment = $this->appointments->createManual(
            $tenant,
            $request->user(),
            $customer,
            $animal,
            $service,
            $this->tenantDateTime($request, $request->string('starts_at')->toString()),
            $request->integer('duration_minutes') ?: null,
            $request->input('customer_reason'),
            $request->input('internal_notes'),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Cita manual creada y confirmada.');
    }

    public function confirm(ConfirmAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->confirm(
            $this->scoped($request, $appointment),
            $request->user(),
            $request->integer('duration_minutes') ?: null,
            $request->input('internal_notes'),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Cita confirmada.');
    }

    public function reject(RejectAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->reject(
            $this->scoped($request, $appointment),
            $request->user(),
            $request->string('reason')->toString(),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Solicitud rechazada.');
    }

    public function propose(ProposeAppointmentRequest $request, Appointment $appointment)
    {
        $proposal = $this->appointments->propose(
            $this->scoped($request, $appointment),
            $request->user(),
            $this->tenantDateTime($request, $request->string('starts_at')->toString()),
            $request->integer('duration_minutes') ?: null,
            $request->input('message'),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($proposal->appointment, 'Contrapropuesta enviada.');
    }

    public function cancel(CancelTenantAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->cancel(
            $this->scoped($request, $appointment),
            $request->user(),
            $request->string('reason')->toString(),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Cita cancelada.');
    }

    public function complete(FinishAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->complete(
            $this->scoped($request, $appointment),
            $request->user(),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Cita completada.');
    }

    public function noShow(FinishAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointments->markNoShow(
            $this->scoped($request, $appointment),
            $request->user(),
            $request->string('idempotency_key')->toString(),
        );

        return $this->success($appointment, 'Cita marcada como no asistio.');
    }

    private function scoped(Request $request, Appointment $appointment): Appointment
    {
        return $this->queries->detail($request->user()->tenant, $request->user(), $appointment->id)
            ->withoutRelations();
    }

    private function success(Appointment $appointment, string $message)
    {
        return redirect()->back()
            ->with('success', $message)
            ->with('appointmentId', $appointment->id);
    }

    private function tenantDateTime(Request $request, string $value): CarbonImmutable
    {
        $timezone = $request->user()->tenant->appointmentSetting?->timezone ?: 'UTC';

        return CarbonImmutable::parse($value, $timezone);
    }
}
