<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    public function push(Request $request)
    {
        $payload = $request->validate([
            'customers' => ['nullable', 'array'],
            'customers.*' => ['array'],
            'animals' => ['nullable', 'array'],
            'animals.*' => ['array'],
            'services' => ['nullable', 'array'],
            'services.*' => ['array'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['array'],
            'payments' => ['nullable', 'array'],
            'payments.*' => ['array'],
        ]);

        $results = [
            'customers' => [],
            'animals' => [],
            'services' => [],
            'notes' => [],
            'payments' => [],
        ];

        $customerMap = [];
        $animalMap = [];

        foreach ($payload['customers'] ?? [] as $item) {
            $result = $this->runItem($request, CustomerController::class, 'store', $item);
            $results['customers'][] = $result;

            if (($result['status'] ?? null) === 'synced' && !empty($result['data']['client_uuid'])) {
                $customerMap[$result['data']['client_uuid']] = $result['data']['id'];
            }
        }

        foreach ($payload['animals'] ?? [] as $item) {
            $item = $this->resolveCustomerReference($request, $item, $customerMap);
            $result = $this->runItem($request, AnimalController::class, 'store', $item);
            $results['animals'][] = $result;

            if (($result['status'] ?? null) === 'synced' && !empty($result['data']['client_uuid'])) {
                $animalMap[$result['data']['client_uuid']] = $result['data']['id'];
            }
        }

        $failedServices = [];
        foreach ($payload['services'] ?? [] as $item) {
            $item['type'] = 'service';
            $item['from_sale'] = true;
            if (empty($item['client_uuid'])) {
                $results['services'][] = ['status' => 'error', 'client_uuid' => null, 'message' => 'Falta el identificador del servicio.', 'http_status' => 422];
                continue;
            }
            $result = $this->runItem($request, CatalogItemController::class, 'store', $item);
            $results['services'][] = $result;
            if ($result['status'] !== 'synced') {
                $failedServices[] = $item['client_uuid'];
            }
        }

        foreach ($payload['notes'] ?? [] as $item) {
            $item = $this->resolveCustomerReference($request, $item, $customerMap);
            $item = $this->resolveAnimalReferences($request, $item, $animalMap);
            if (! $this->resolveServiceReferences($request, $item, $failedServices)) {
                $results['notes'][] = ['status' => 'error', 'client_uuid' => $item['client_uuid'] ?? null, 'message' => 'La nota depende de un servicio pendiente o no disponible.', 'http_status' => 422];
                continue;
            }
            $result = $this->runItem($request, NoteController::class, 'store', $item);
            $results['notes'][] = $result;
        }

        foreach ($payload['payments'] ?? [] as $item) {
            $item = $this->resolveCustomerReference($request, $item, $customerMap);
            $result = $this->runItem($request, PaymentController::class, 'store', $item);
            $results['payments'][] = $result;
        }

        return response()->json([
            'server_time' => now()->toISOString(),
            'results' => $results,
        ]);
    }

    private function resolveServiceReferences(Request $request, array &$item, array $failedServices): bool
    {
        if (! isset($item['items']) || ! is_array($item['items'])) {
            return true;
        }
        foreach ($item['items'] as &$detail) {
            if (! is_array($detail) || ! array_key_exists('catalog_client_uuid', $detail)) {
                continue;
            }
            $uuid = $detail['catalog_client_uuid'];
            if (! is_string($uuid) || in_array($uuid, $failedServices, true)) {
                return false;
            }
            $id = DB::table('quick_service_operations as ops')
                ->join('catalog_items as catalog', 'catalog.id', '=', 'ops.catalog_item_id')
                ->where('ops.tenant_id', $request->user()->tenant_id)
                ->where('catalog.tenant_id', $request->user()->tenant_id)
                ->where('ops.client_uuid', $uuid)
                ->where('catalog.type', 'service')->where('catalog.is_active', true)
                ->whereNull('catalog.deleted_at')->value('catalog.id');
            if (! $id) {
                return false;
            }
            $detail['id'] = $id;
            unset($detail['catalog_client_uuid']);
        }
        return true;
    }

    private function runItem(Request $parentRequest, string $controllerClass, string $method, array $item): array
    {
        try {
            $childRequest = Request::create($parentRequest->path(), 'POST', $item);
            $childRequest->setUserResolver(fn () => $parentRequest->user());

            $response = app($controllerClass)->{$method}($childRequest);
            $statusCode = $response->getStatusCode();
            $data = $response->getData(true);

            return [
                'status' => in_array($statusCode, [Response::HTTP_OK, Response::HTTP_CREATED], true) ? 'synced' : 'error',
                'client_uuid' => $item['client_uuid'] ?? null,
                'idempotent' => $data['idempotent'] ?? false,
                'data' => $data['data'] ?? $data,
                'http_status' => $statusCode,
            ];
        } catch (ValidationException $exception) {
            return [
                'status' => 'error',
                'client_uuid' => $item['client_uuid'] ?? null,
                'message' => 'Validacion fallida.',
                'errors' => $exception->errors(),
                'http_status' => 422,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'status' => 'error',
                'client_uuid' => $item['client_uuid'] ?? null,
                'message' => $exception->getMessage(),
                'http_status' => 500,
            ];
        }
    }

    private function resolveCustomerReference(Request $request, array $item, array $customerMap): array
    {
        if (!empty($item['customer_id'])) {
            return $item;
        }

        $clientUuid = $item['customer_client_uuid'] ?? null;
        if (!$clientUuid) {
            return $item;
        }

        $item['customer_id'] = $customerMap[$clientUuid]
            ?? Customer::where('tenant_id', $request->user()->tenant_id)
                ->where('client_uuid', $clientUuid)
                ->value('id');

        return $item;
    }

    private function resolveAnimalReferences(Request $request, array $item, array $animalMap): array
    {
        if (!empty($item['animal_ids'])) {
            return $item;
        }

        $clientUuids = $item['animal_client_uuids'] ?? null;
        if (!$clientUuids || !is_array($clientUuids)) {
            return $item;
        }

        $item['animal_ids'] = collect($clientUuids)
            ->map(fn ($clientUuid) => $animalMap[$clientUuid]
                ?? Animal::where('tenant_id', $request->user()->tenant_id)
                    ->where('client_uuid', $clientUuid)
                    ->value('id'))
            ->filter()
            ->values()
            ->all();

        return $item;
    }
}
