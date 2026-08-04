<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerStatement;
use App\Models\NoteDetail;
use App\Models\Payment;
use App\Services\CustomerStatementGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CustomerStatementController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->tenant?->usesMonthlyCutoffBilling(), 404);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $statements = CustomerStatement::query()
            ->with('customer:id,name,last_name')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->whereHas('customer', function ($customerQuery) use ($term) {
                    $customerQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $statements->getCollection()->map(fn (CustomerStatement $statement) => [
                'id' => $statement->id,
                'customer_id' => $statement->customer_id,
                'customer_name' => $statement->customer?->full_name,
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'period_charges' => (float) $statement->period_charges,
                'period_payments' => (float) $statement->period_payments,
                'ending_balance' => (float) $statement->ending_balance,
                'status' => $statement->status,
                'generated_at' => $statement->generated_at?->toISOString(),
                'published_at' => $statement->published_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $statements->currentPage(),
                'last_page' => $statements->lastPage(),
                'per_page' => $statements->perPage(),
                'total' => $statements->total(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer, CustomerStatement $statement, CustomerStatementGenerator $generator)
    {
        $this->authorizeCustomer($customer);
        abort_unless($statement->tenant_id === auth()->user()->tenant_id && $statement->customer_id === $customer->id, 404);

        $statementData = $generator->dataForRange(
            $customer,
            $statement->period_start->copy()->startOfDay(),
            $statement->period_end->copy()->endOfDay()
        );

        return response()->json([
            'data' => array_merge($this->serializeStatement($statement), [
                'services_by_month' => $statementData['serviceDetailsByMonth']
                    ->map(fn ($details, string $month) => [
                        'month' => $month,
                        'items' => $details->map(fn (NoteDetail $detail) => [
                            'id' => $detail->id,
                            'note_id' => $detail->note_id,
                            'note_folio' => $detail->note?->folio,
                            'animal_id' => $detail->animal_id,
                            'animal_name' => $detail->animal?->name,
                            'name' => $detail->catalogItem?->name ?? 'Servicio eliminado',
                            'type' => $detail->catalogItem?->type,
                            'quantity' => (float) $detail->quantity,
                            'price_at_sale' => (float) $detail->price_at_sale,
                            'subtotal' => (float) $detail->subtotal,
                            'date_at' => $detail->note?->date_at?->toDateString(),
                        ])->values(),
                    ])
                    ->values(),
                'payments' => $statementData['payments']
                    ->map(fn (Payment $payment) => [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_method_name' => $payment->paymentMethod?->name,
                        'reference' => $payment->reference,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at?->toISOString(),
                    ])
                    ->values(),
            ]),
        ]);
    }

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
            'generated_at' => $statement->generated_at?->toISOString(),
            'published_at' => $statement->published_at?->toISOString(),
        ];
    }
}
