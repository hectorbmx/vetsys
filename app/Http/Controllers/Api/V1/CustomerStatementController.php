<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerStatementGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CustomerStatementController extends Controller
{
    public function preview(Request $request, Customer $customer, CustomerStatementGenerator $generator)
    {
        $this->authorizeCustomer($customer);

        [$from, $to] = $this->validatedPeriod($request);

        return response()->json($generator->previewRange($customer, $from, $to));
    }

    public function storeManual(Request $request, Customer $customer, CustomerStatementGenerator $generator)
    {
        $this->authorizeCustomer($customer);

        [$from, $to] = $this->validatedPeriod($request);
        $preview = $generator->previewRange($customer, $from, $to);

        if (($preview['services_count'] ?? 0) <= 0) {
            return response()->json([
                'message' => 'No hay servicios disponibles para generar este corte.',
                'preview' => $preview,
            ], 422);
        }

        $statement = $generator->generateStoredForRange($customer, $from, $to);

        return response()->json([
            'data' => [
                'id' => $statement->id,
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'period_charges' => (float) $statement->period_charges,
                'period_payments' => (float) $statement->period_payments,
                'ending_balance' => (float) $statement->ending_balance,
                'status' => $statement->status,
                'generated_at' => $statement->generated_at?->toISOString(),
            ],
        ], 201);
    }

    private function authorizeCustomer(Customer $customer): void
    {
        abort_unless($customer->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless(auth()->user()->tenant?->usesMonthlyCutoffBilling(), 404);
    }

    private function validatedPeriod(Request $request): array
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ], [
            'date_from.required' => 'La fecha inicial es obligatoria.',
            'date_to.required' => 'La fecha final es obligatoria.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        return [
            Carbon::parse($request->date_from)->startOfDay(),
            Carbon::parse($request->date_to)->endOfDay(),
        ];
    }
}
