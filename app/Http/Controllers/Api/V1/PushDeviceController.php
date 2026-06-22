<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushDeviceRequest;
use App\Http\Resources\Api\PushDeviceResource;
use App\Models\PushDevice;
use App\Services\PushDeviceRegistrar;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function __construct(private PushDeviceRegistrar $registrar) {}

    public function store(StorePushDeviceRequest $request)
    {
        $device = $this->registrar->register($request->user(), $request->validated());

        return new PushDeviceResource($device);
    }

    public function destroy(Request $request, PushDevice $pushDevice)
    {
        abort_unless(
            (int) $pushDevice->tenant_id === (int) $request->user()->tenant_id
            && (int) $pushDevice->user_id === (int) $request->user()->id,
            404,
        );

        return new PushDeviceResource($this->registrar->revoke($pushDevice));
    }
}
