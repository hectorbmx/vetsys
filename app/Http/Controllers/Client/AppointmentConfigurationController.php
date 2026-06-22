<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorScheduleRequest;
use App\Http\Requests\StoreScheduleBlockRequest;
use App\Http\Requests\UpdateAppointmentSettingsRequest;
use App\Http\Requests\UpdateBookableServiceRequest;
use App\Models\CatalogItem;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Services\AppointmentConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentConfigurationController extends Controller
{
    public function updateSettings(
        UpdateAppointmentSettingsRequest $request,
        AppointmentConfigurationService $service
    ) {
        $service->updateSettings($request->user()->tenant, $request->user(), $request->validated());

        return $this->success('Configuracion de agenda actualizada.');
    }

    public function storeSchedule(
        StoreDoctorScheduleRequest $request,
        AppointmentConfigurationService $service
    ) {
        $service->storeSchedule($request->user()->tenant, $request->validated());

        return $this->success('Bloque semanal agregado.');
    }

    public function destroySchedule(
        Request $request,
        DoctorSchedule $doctorSchedule,
        AppointmentConfigurationService $service
    ) {
        Gate::authorize('manage-appointment-configuration');
        $service->deleteSchedule($request->user()->tenant, $doctorSchedule);

        return $this->success('Bloque semanal eliminado.');
    }

    public function storeBlock(
        StoreScheduleBlockRequest $request,
        AppointmentConfigurationService $service
    ) {
        $service->storeBlock($request->user()->tenant, $request->user(), $request->validated());

        return $this->success('Ausencia agregada a la agenda.');
    }

    public function destroyBlock(
        Request $request,
        ScheduleBlock $scheduleBlock,
        AppointmentConfigurationService $service
    ) {
        Gate::authorize('manage-appointment-configuration');
        $service->deleteBlock($request->user()->tenant, $scheduleBlock);

        return $this->success('Ausencia eliminada.');
    }

    public function updateService(
        UpdateBookableServiceRequest $request,
        CatalogItem $catalogItem,
        AppointmentConfigurationService $service
    ) {
        $service->updateBookableService($request->user()->tenant, $catalogItem, $request->validated());

        return $this->success('Servicio de agenda actualizado.');
    }

    private function success(string $message)
    {
        return redirect()
            ->route('client.mi-configuracion.index', ['tab' => 'agenda'])
            ->with('activeTab', 'agenda')
            ->with('success', $message);
    }
}
