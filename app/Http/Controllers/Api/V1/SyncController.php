<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'notes' => ['nullable', 'array'],
            'notes.*' => ['array'],
            'payments' => ['nullable', 'array'],
            'payments.*' => ['array'],
        ]);

        $results = [
            'customers' => [],
            'animals' => [],
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

        foreach ($payload['notes'] ?? [] as $item) {
            $item = $this->resolveCustomerReference($request, $item, $customerMap);
            $item = $this->resolveAnimalReferences($request, $item, $animalMap);
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
