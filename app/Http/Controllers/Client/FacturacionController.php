<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use App\Models\Invoice;


class FacturacionController extends Controller
{
   public function index()
{
    $user = auth()->user();
    $tenantId = $user->tenant_id;

    $tenant = $user->tenant()->with('billingProfile')->first();

    $billingProfile = $tenant?->billingProfile;

    $stats = [
        'notasPendientes' => Note::where('tenant_id', $tenantId)
            ->where('status', 'PAGADA')
            ->count(),

        'facturasEmitidas' => Invoice::where('tenant_id', $tenantId)
            ->where('status', 'issued')
            ->count(),

        'facturasCanceladas' => Invoice::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->count(),

        'facturacionActiva' => (bool) $billingProfile?->is_active,
    ];

    return view('client.facturacion.index', compact('stats', 'billingProfile'));
}

   public function notas(Request $request)
{
    $tenantId = auth()->user()->tenant_id;

    $notes = Note::query()
        ->where('tenant_id', $tenantId)
        ->where('status', 'PAGADA')
        ->when($request->filled('q'), function ($query) use ($request) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        })
        ->latest('date_at')
        ->paginate(15)
        ->withQueryString();

    return view('client.facturacion.notas', compact('notes'));
}

    public function create(Note $note)
    {
        $this->authorizeTenantNote($note);

        if ($note->status !== 'PAGADA') {
            return back()->with('error', 'Solo se pueden facturar notas pagadas.');
        }

        return view('client.facturacion.create', compact('note'));
    }

    public function store(Request $request, Note $note)
    {
        $this->authorizeTenantNote($note);

        // Aquí después conectaremos FacturapiService
        return back()->with('info', 'Aquí se emitirá la factura con Facturapi.');
    }

    public function show($invoice)
    {
        // Después lo cambiaremos a Invoice $invoice cuando creemos el modelo.
        return view('client.facturacion.show', compact('invoice'));
    }

    private function authorizeTenantNote(Note $note): void
    {
        if ($note->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes permiso para acceder a esta nota.');
        }
    }
}