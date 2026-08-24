@extends('layouts.client')

@section('title', 'Presupuesto ' . $budget->folio)

@section('content')
<div x-data="budgetShowEditor()" class="-mt-4 space-y-6">
    <div x-show="deletingItem"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-[120] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm"
         role="dialog"
         aria-modal="true">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl">
            <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-slate-100 border-t-teal-500"></div>
            <p class="mt-5 text-sm font-black uppercase tracking-widest theme-text-heading">Eliminando registro</p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Actualizando el presupuesto.</p>
        </div>
    </div>

    <div x-show="toast.show"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-4 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="translate-y-4 opacity-0"
         class="fixed bottom-5 right-5 z-[130] rounded-2xl theme-surface-dark px-6 py-4 shadow-2xl">
        <div class="flex items-center gap-3">
            <span class="grid h-6 w-6 place-items-center rounded-full theme-bg-primary text-xs font-black text-white">&#10003;</span>
            <p class="text-[10px] font-black uppercase tracking-widest text-white" x-text="toast.message"></p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-xs font-bold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-xs font-bold text-rose-700">
            <p class="font-black uppercase tracking-widest">Revisa la partida</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tighter theme-text-heading">{{ $budget->folio }}</h1>
            <p class="mt-1 text-xs font-bold uppercase tracking-widest text-slate-400">
                {{ $budget->customer?->full_name ?? 'Cliente no disponible' }} &middot; {{ $budget->budget_date?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('client.budgets.pdf', $budget) }}" target="_blank" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 hover:text-rose-700" title="PDF presupuesto">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M6 2h8l4 4v16H6V2Zm7 1.5V7h3.5L13 3.5ZM8.5 12.5h1.25c1.2 0 2 .7 2 1.75s-.8 1.75-2 1.75H9v2H7.5v-5.5h1Zm.5 2.25h.65c.38 0 .62-.18.62-.5s-.24-.5-.62-.5H9v1Zm3.25-2.25h1.4c1.72 0 2.85 1.03 2.85 2.75S15.37 18 13.65 18h-1.4v-5.5Zm1.5 4.2c.78-.02 1.25-.52 1.25-1.45s-.47-1.43-1.25-1.45v2.9Zm3.25-4.2h3.5v1.3H18.5v.9h1.75V16H18.5v2H17v-5.5Z"/>
                </svg>
            </a>
            <form action="{{ route('client.budgets.destroy', $budget) }}"
                  method="POST"
                  @submit="if (!confirm('Eliminar este presupuesto? Esta accion no se puede deshacer.')) { $event.preventDefault(); return; } deletingItem = true">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 hover:text-rose-700" title="Eliminar presupuesto">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18"/>
                        <path d="M8 6V4h8v2"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v5"/>
                        <path d="M14 11v5"/>
                    </svg>
                </button>
            </form>
            <a href="{{ route('client.budgets.index') }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200">
                Volver
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Estatus</p>
            <p class="mt-2 text-sm font-black theme-text-heading">{{ $statusLabels[$budget->status] ?? $budget->status }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Caballos</p>
            <p class="mt-2 text-sm font-black theme-text-heading">{{ $budget->animals->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Vigencia</p>
            <p class="mt-2 text-sm font-black theme-text-heading">{{ $budget->valid_until?->format('d/m/Y') ?? 'Sin vigencia' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total</p>
            <p class="mt-2 text-xl font-black theme-text-heading" x-text="'$' + formatMoney(budgetTotal)">${{ number_format((float) $budget->total, 2) }}</p>
        </div>
    </div>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50/50 p-5">
            <p class="text-sm font-black theme-text-heading">Notas generales</p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">Observaciones que apareceran en el presupuesto y su PDF.</p>
        </div>
        <form action="{{ route('client.budgets.update', $budget) }}" method="POST" data-budget-notes-form class="p-5">
            @csrf
            @method('PATCH')
            <div data-budget-show-quill-editor class="hidden min-h-[190px] bg-white">{!! old('notes', $budget->notes) !!}</div>
            <textarea name="notes" data-budget-show-quill-content rows="6" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none theme-input">{{ old('notes', $budget->notes) }}</textarea>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="rounded-xl theme-button-primary px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em]">
                    Guardar notas
                </button>
            </div>
        </form>
    </section>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50/50 p-5">
            <p class="text-sm font-black theme-text-heading">Detalle por caballo</p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">Vista inicial del presupuesto guardado.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach($budget->animals as $budgetAnimal)
                <section class="p-5">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-black theme-text-heading">{{ $budgetAnimal->animal?->name ?? 'Caballo no disponible' }}</p>
                            @if($budgetAnimal->notes)
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $budgetAnimal->notes }}</p>
                            @endif
                        </div>
                        <span class="rounded-full theme-bg-primary-soft px-3 py-1.5 text-[10px] font-black theme-text-primary"
                              x-text="'$' + formatMoney(budgetAnimalSubtotals[{{ $budgetAnimal->id }}] ?? {{ (float) $budgetAnimal->subtotal }})">
                            ${{ number_format((float) $budgetAnimal->subtotal, 2) }}
                        </span>
                    </div>

                    <div class="mb-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                        <div class="relative">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Agregar servicio</label>
                            <input type="text"
                                   x-model="serviceQueries[{{ $budgetAnimal->id }}]"
                                   @focus="activeBudgetAnimalId = {{ $budgetAnimal->id }}"
                                   @input.debounce.300ms="searchServices({{ $budgetAnimal->id }})"
                                   placeholder="Buscar servicio del catalogo..."
                                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold theme-text-heading outline-none theme-input">

                            <div x-show="activeBudgetAnimalId === {{ $budgetAnimal->id }} && serviceSuggestions.length > 0" x-cloak class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                                <template x-for="service in serviceSuggestions" :key="service.id">
                                    <button type="button"
                                            @click="addService({{ $budgetAnimal->id }}, @js(route('client.budgets.animals.items.store', [$budget, $budgetAnimal])), service)"
                                            class="flex w-full items-center justify-between gap-4 border-b border-slate-100 p-3 text-left transition-colors last:border-b-0 hover:bg-slate-50">
                                        <span>
                                            <span class="block text-xs font-black theme-text-heading" x-text="service.name"></span>
                                            <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-widest text-slate-400" x-text="service.type === 'service' ? 'Servicio' : 'Producto'"></span>
                                        </span>
                                        <span class="text-xs font-black theme-text-heading" x-text="'$' + formatMoney(service.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-100">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Servicio</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Cantidad</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Precio base</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Precio presupuesto</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Subtotal</th>
                                    <th class="w-12 px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($budgetAnimal->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-xs font-black theme-text-heading">{{ $item->service_name_snapshot }}</td>
                                        <td class="px-4 py-3 text-right text-xs font-bold text-slate-500">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-xs font-bold text-slate-400">${{ number_format((float) $item->base_price, 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="number"
                                                   min="0"
                                                   step="0.01"
                                                   value="{{ number_format((float) $item->price_at_budget, 2, '.', '') }}"
                                                   @blur="autosaveItemPrice($event, @js(route('client.budgets.items.update', [$budget, $item])), {{ $item->id }}, {{ $budgetAnimal->id }})"
                                                   class="w-28 rounded-xl border border-transparent bg-transparent px-3 py-2 text-right text-xs font-black theme-text-heading outline-none transition focus:border-slate-200 focus:bg-white focus:shadow-sm">
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-black theme-text-heading"
                                            x-text="'$' + formatMoney(itemSubtotals[{{ $item->id }}] ?? {{ (float) $item->subtotal }})">
                                            ${{ number_format((float) $item->subtotal, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="{{ route('client.budgets.items.destroy', [$budget, $item]) }}"
                                                  method="POST"
                                                  @submit="if (!confirm('Eliminar esta partida del presupuesto?')) { $event.preventDefault(); return; } deletingItem = true">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-lg font-black leading-none text-rose-500 hover:text-rose-700" title="Eliminar partida">&times;</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
function budgetShowEditor() {
    return {
        activeBudgetAnimalId: null,
        serviceSuggestions: [],
        serviceQueries: {},
        itemSubtotals: {},
        budgetAnimalSubtotals: {},
        budgetTotal: {{ (float) $budget->total }},
        deletingItem: false,
        toast: {
            show: false,
            message: '',
            timeout: null,
        },
        csrfToken: @js(csrf_token()),
        serviceSearchUrl: @js(route('client.budgets.services.search')),

        formatMoney(value) {
            return Number.parseFloat(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        showToast(message) {
            this.toast.message = message;
            this.toast.show = true;

            if (this.toast.timeout) {
                clearTimeout(this.toast.timeout);
            }

            this.toast.timeout = setTimeout(() => {
                this.toast.show = false;
            }, 2600);
        },

        searchServices(budgetAnimalId) {
            const query = this.serviceQueries[budgetAnimalId] || '';
            this.activeBudgetAnimalId = budgetAnimalId;

            if (query.length < 2) {
                this.serviceSuggestions = [];
                return;
            }

            fetch(`${this.serviceSearchUrl}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => { this.serviceSuggestions = data; })
                .catch(() => { this.serviceSuggestions = []; });
        },

        addService(budgetAnimalId, url, service) {
            this.serviceQueries[budgetAnimalId] = service.name;
            this.serviceSuggestions = [];
            this.activeBudgetAnimalId = null;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    catalog_item_id: service.id,
                }),
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No se pudo agregar el servicio.');
                    }

                    window.location.reload();
                })
                .catch(() => {
                    alert('No se pudo agregar el servicio al presupuesto.');
                });
        },

        autosaveItemPrice(event, url, itemId, budgetAnimalId) {
            const input = event.target;
            const value = Number.parseFloat(input.value || 0);

            if (Number.isNaN(value) || value < 0) {
                input.value = '0.00';
                return;
            }

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    price_at_budget: value,
                }),
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No se pudo actualizar el precio.');
                    }

                    return response.json();
                })
                .then(data => {
                    input.value = Number.parseFloat(data.item.price_at_budget || 0).toFixed(2);
                    this.itemSubtotals[itemId] = data.item.subtotal;
                    this.budgetAnimalSubtotals[budgetAnimalId] = data.budget_animal.subtotal;
                    this.budgetTotal = data.budget.total;
                    this.showToast('Precio guardado');
                })
                .catch(() => {
                    alert('No se pudo guardar el precio del presupuesto.');
                });
        },
    };
}
</script>
@endpush

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
        <style>
            [data-budget-show-quill-editor].ql-container.ql-snow { border-color: #e2e8f0; border-radius: 0 0 0.75rem 0.75rem; font-family: inherit; font-size: 0.75rem; }
            .ql-toolbar.ql-snow { border-color: #e2e8f0; border-radius: 0.75rem 0.75rem 0 0; }
            [data-budget-show-quill-editor] .ql-editor { min-height: 190px; font-weight: 600; color: #0f172a; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Quill === 'undefined') return;

                document.querySelectorAll('[data-budget-notes-form]').forEach((form) => {
                    const editorElement = form.querySelector('[data-budget-show-quill-editor]');
                    const contentInput = form.querySelector('[data-budget-show-quill-content]');
                    if (!editorElement || !contentInput || editorElement.dataset.ready) return;

                    editorElement.dataset.ready = 'true';
                    editorElement.classList.remove('hidden');
                    contentInput.classList.add('hidden');

                    const quill = new Quill(editorElement, {
                        theme: 'snow',
                        placeholder: 'Observaciones generales del presupuesto...',
                        modules: {
                            toolbar: [
                                ['bold', 'italic', 'underline'],
                                [{ color: [] }],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['blockquote', 'link'],
                                ['clean']
                            ]
                        }
                    });

                    const syncContent = () => {
                        const html = quill.root.innerHTML;
                        contentInput.value = quill.getText().trim() === '' ? '' : html;
                    };

                    quill.on('text-change', syncContent);
                    form.addEventListener('submit', syncContent);
                    syncContent();
                });
            });
        </script>
    @endpush
@endonce
@endsection
