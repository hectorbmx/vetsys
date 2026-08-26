@extends('layouts.client')

@section('title', $clubNote ? 'Editar nota de club' : 'Servicios del club')

@section('content')
@php
    $customNotesHtml = app(\App\Services\RichTextSanitizer::class)
        ->sanitize(old('notes_html', $clubNote?->notes_html ?? ''));
@endphp

<div x-data="clubServicesPOS()" class="-mt-6 px-6 pb-6 max-w-7xl mx-auto space-y-6">
    <div x-show="isSubmitting" x-cloak x-transition.opacity class="fixed inset-0 z-[140] flex items-center justify-center theme-overlay px-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 theme-border-primary-soft theme-spinner-primary"></div>
            <p class="mt-4 text-sm font-black uppercase tracking-widest theme-text-heading" x-text="isEditing ? 'Actualizando nota de club' : 'Guardando nota de club'"></p>
            <p class="mt-2 text-xs font-semibold text-slate-500">Procesando la informacion. No cierres esta ventana.</p>
        </div>
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-black theme-text-heading uppercase tracking-widest">{{ $clubNote ? 'Editar nota de club' : 'Agregar servicios al club' }}</h1>
            <p class="text-xs text-slate-400 font-medium mt-0.5">{{ $club->name }} | Captura cargos libres sin cliente ni caballo obligatorio.</p>
        </div>
        <a href="{{ route('client.clubes.edit', ['clube' => $club, 'tab' => 'servicios']) }}"
           @click="confirmClose($event)"
           class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide transition-all">
            ← Volver
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-xs font-bold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ $clubNote ? route('client.clubes.services.update', [$club, $clubNote]) : route('client.clubes.services.store', $club) }}" method="POST" @submit="handleSubmit($event)" @keydown.enter="if (!$event.target.closest('[data-club-note-quill-editor]')) $event.preventDefault()">
        @csrf
        @if($clubNote)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Club</label>
                        <div class="w-full text-xs font-black theme-text-heading bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                            {{ $club->name }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Fecha de la nota</label>
                        <input type="date" name="date_at" x-model="noteDate" required class="w-full text-xs font-bold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors">
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-4">
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Anadir productos o servicios al detalle</label>
                        <input type="text" x-model="itemQuery" @input.debounce.300ms="searchItems()" placeholder="Escribe el nombre del servicio o escanea el SKU del producto..." class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors">

                        <div x-show="itemSuggestions.length > 0" x-cloak class="absolute z-40 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden divide-y divide-slate-100">
                            <template x-for="item in itemSuggestions" :key="item.id">
                                <button type="button" @click="addItemToTable(item)" class="w-full p-3 hover:bg-slate-50 cursor-pointer transition-colors flex justify-between items-center text-left">
                                    <div>
                                        <span class="text-xs font-bold theme-text-heading block" x-text="item.name"></span>
                                        <span class="text-[10px] text-slate-400 font-mono uppercase" x-text="item.type === 'service' ? 'Servicio' : 'Producto'"></span>
                                    </div>
                                    <span class="text-xs font-black theme-text-heading" x-text="'$' + parseFloat(item.price).toFixed(2)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-slate-100 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Concepto</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest w-24">Cant.</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest w-32">Precio U.</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right w-32">Subtotal</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(row, index) in basket" :key="row.uid">
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="text-xs font-bold theme-text-heading block" x-text="row.name"></span>
                                            <span x-show="row.has_inventory" class="text-[10px] text-slate-400 font-semibold block" x-text="'Disponible: ' + parseFloat(row.stock_actual).toFixed(2)"></span>
                                            <span x-show="stockMessage(row)" class="text-[10px] font-bold block mt-1"
                                                  :class="stockState(row) === 'blocked' || stockState(row) === 'negative' ? 'text-rose-600' : 'text-amber-600'"
                                                  x-text="stockMessage(row)"></span>
                                            <input type="hidden" :name="'items['+index+'][id]'" :value="row.id">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" step="1" min="1" :name="'items['+index+'][quantity]'" x-model.number="row.quantity" @input="row.quantity = normalizeQuantity(row.quantity); calculateTotals()" class="w-full text-xs font-bold text-center theme-text-heading bg-slate-50 border border-slate-200 rounded-lg py-1 px-1.5 theme-input">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="relative flex items-center">
                                                <span class="absolute left-1.5 text-xs text-slate-400 font-bold">$</span>
                                                <input type="number" step="0.01" min="0" :name="'items['+index+'][price]'" x-model.number="row.price" @input="calculateTotals()" class="w-full text-xs font-bold text-right theme-text-heading bg-slate-50 border border-slate-200 rounded-lg py-1 pr-1.5 pl-4 theme-input">
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-black theme-text-heading" x-text="'$' + rowSubtotal(row).toFixed(2)"></td>
                                        <td class="px-4 py-3 text-center">
                                            <button type="button" @click="removeItem(index)" class="text-slate-400 hover:text-rose-600 font-bold transition-colors">x</button>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="basket.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-xs font-bold text-slate-400">
                                            No has agregado ningun articulo a la nota del club todavia.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Notas personalizadas para imprimir</label>
                        <div data-club-note-quill-editor class="hidden min-h-[180px] bg-white">{!! $customNotesHtml !!}</div>
                        <textarea name="notes_html" data-club-note-quill-content rows="6" placeholder="Agrega indicaciones, observaciones o recomendaciones para esta nota..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none theme-input">{{ $customNotesHtml }}</textarea>
                        <p class="text-[10px] font-semibold text-slate-400">Este texto se imprimira despues del desglose de productos y servicios.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-6">
                    <div>
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Resumen financiero</h3>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Conceptos:</span>
                                <span class="text-sm font-black theme-text-heading" x-text="basket.length"></span>
                            </div>
                            <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Total de nota:</span>
                                <span class="text-2xl font-black theme-text-heading" x-text="'$' + noteTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Abonos</p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">Quedan fuera de esta primera version. Esta captura solo registra cargos del club.</p>
                    </div>

                    <button type="submit" class="w-full theme-button-dark p-4 rounded-xl font-black text-xs uppercase tracking-widest shadow-md transition-all text-center disabled:opacity-40 disabled:pointer-events-none" :disabled="isSubmitting || basket.length === 0 || hasBlockingStock()">
                        <span x-text="isEditing ? 'Actualizar nota de club' : 'Procesar y guardar nota de club'"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function clubServicesPOS() {
    return {
        searchItemUrl: "{{ route('client.api.buscar-articulos') }}",
        initialSaleState: @js($initialSaleState),
        isEditing: @js((bool) $clubNote),
        itemQuery: '',
        itemSuggestions: [],
        noteDate: new Date().toISOString().split('T')[0],
        basket: [],
        noteTotal: 0.00,
        isSubmitting: false,

        init() {
            if (!this.initialSaleState) {
                return;
            }

            this.noteDate = this.initialSaleState.noteDate || this.noteDate;
            this.basket = (this.initialSaleState.basket || []).map(row => ({
                uid: `${row.id}-${Date.now()}-${Math.random()}`,
                ...row,
                quantity: this.normalizeQuantity(row.quantity),
                price: parseFloat(row.price || 0),
            }));
            this.calculateTotals();
        },

        searchItems() {
            if (this.itemQuery.length < 2) {
                this.itemSuggestions = [];
                return;
            }

            fetch(`${this.searchItemUrl}?q=${encodeURIComponent(this.itemQuery)}`)
                .then(res => res.json())
                .then(data => { this.itemSuggestions = data; })
                .catch(() => { this.itemSuggestions = []; });
        },

        addItemToTable(item) {
            const existing = this.basket.find(row => row.id === item.id);
            if (existing) {
                existing.quantity = this.normalizeQuantity(existing.quantity) + 1;
            } else {
                this.basket.push({
                    uid: `${item.id}-${Date.now()}-${Math.random()}`,
                    id: item.id,
                    name: item.name,
                    quantity: 1,
                    price: parseFloat(item.price),
                    has_inventory: Boolean(item.has_inventory),
                    stock_actual: parseFloat(item.stock_actual ?? 0),
                    stock_minimo: parseFloat(item.stock_minimo ?? 0),
                    allow_negative_stock: Boolean(item.allow_negative_stock)
                });
            }

            this.itemQuery = '';
            this.itemSuggestions = [];
            this.calculateTotals();
        },

        removeItem(index) {
            this.basket.splice(index, 1);
            this.calculateTotals();
        },

        calculateTotals() {
            this.noteTotal = this.basket.reduce((total, row) => total + this.rowSubtotal(row), 0);
        },

        rowSubtotal(row) {
            return this.normalizeQuantity(row.quantity) * (parseFloat(row.price) || 0);
        },

        normalizeQuantity(value) {
            return Math.max(1, Math.floor(Number(value) || 1));
        },

        requiredQuantity(row) {
            return this.normalizeQuantity(row.quantity);
        },

        stockState(row) {
            if (!row.has_inventory) {
                return 'normal';
            }

            const resultingStock = parseFloat(row.stock_actual || 0) - this.requiredQuantity(row);
            if (resultingStock < 0) {
                return row.allow_negative_stock ? 'negative' : 'blocked';
            }

            if (resultingStock <= parseFloat(row.stock_minimo || 0)) {
                return 'low';
            }

            return 'normal';
        },

        stockMessage(row) {
            const state = this.stockState(row);
            const resultingStock = parseFloat(row.stock_actual || 0) - this.requiredQuantity(row);

            if (state === 'blocked') {
                return `Existencias insuficientes. La nota requiere ${this.requiredQuantity(row).toFixed(2)} y quedaria en ${resultingStock.toFixed(2)}.`;
            }

            if (state === 'negative') {
                return `Advertencia: esta nota dejara el inventario en ${resultingStock.toFixed(2)}.`;
            }

            if (state === 'low') {
                return `El inventario quedara en ${resultingStock.toFixed(2)}, igual o debajo del minimo.`;
            }

            return '';
        },

        hasBlockingStock() {
            return this.basket.some(row => this.stockState(row) === 'blocked');
        },

        handleSubmit(event) {
            this.basket = this.basket.map(row => ({
                ...row,
                quantity: this.normalizeQuantity(row.quantity),
            }));
            this.calculateTotals();

            if (!event.target.checkValidity()) {
                this.isSubmitting = false;
                return;
            }

            if (this.hasBlockingStock()) {
                event.preventDefault();
                this.isSubmitting = false;
                return;
            }

            this.isSubmitting = true;
        },

        confirmClose(event) {
            if (this.isSubmitting || this.basket.length === 0) {
                return;
            }

            if (!confirm('Estas seguro que quieres cerrar la nota del club? Los cambios no guardados se perderan.')) {
                event.preventDefault();
            }
        }
    }
}
</script>

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
        <style>
            [data-club-note-quill-editor].ql-container.ql-snow { border-color: #e2e8f0; border-radius: 0 0 0.75rem 0.75rem; font-family: inherit; font-size: 0.75rem; }
            .ql-toolbar.ql-snow { border-color: #e2e8f0; border-radius: 0.75rem 0.75rem 0 0; }
            [data-club-note-quill-editor] .ql-editor { min-height: 180px; font-weight: 600; color: #0f172a; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Quill === 'undefined') return;

                document.querySelectorAll('[data-club-note-quill-editor]').forEach((editorElement) => {
                    const form = editorElement.closest('form');
                    const contentInput = form?.querySelector('[data-club-note-quill-content]');
                    if (!form || !contentInput || editorElement.dataset.ready) return;

                    editorElement.dataset.ready = 'true';
                    editorElement.classList.remove('hidden');
                    contentInput.classList.add('hidden');

                    const quill = new Quill(editorElement, {
                        theme: 'snow',
                        placeholder: 'Agrega indicaciones, observaciones o recomendaciones para esta nota...',
                        modules: {
                            toolbar: [
                                ['bold', 'italic', 'underline'],
                                [{ color: [] }],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                [{ align: [] }],
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
