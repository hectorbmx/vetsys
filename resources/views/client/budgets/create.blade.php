@extends('layouts.client')

@section('title', 'Nuevo Presupuesto')

@section('content')
<div x-data="budgetBuilder()" class="-mt-6 mx-auto max-w-7xl space-y-6 px-6 pb-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black uppercase tracking-widest theme-text-heading">Nuevo presupuesto</h1>
            <p class="mt-0.5 text-xs font-medium text-slate-400">Selecciona un cliente, separa los trabajos por caballo y ajusta precios solo para esta cotizacion.</p>
        </div>
        <a href="{{ route('client.budgets.index') }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold tracking-wide text-slate-700 transition-all hover:bg-slate-200">
            &larr; Volver
        </a>
    </div>

    <form action="{{ route('client.budgets.store') }}" method="POST" @submit="handleSubmit($event)" @keydown.enter="if (!$event.target.closest('[data-budget-quill-editor]')) $event.preventDefault()">
        @csrf
        <input type="hidden" name="customer_id" :value="selectedCustomer ? selectedCustomer.id : ''">

        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-xs font-bold text-rose-700">
                <p class="font-black uppercase tracking-widest">Revisa el presupuesto</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
                        <div class="relative md:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">1. Buscar cliente</label>
                            <div class="relative">
                                <input type="text"
                                       x-model="customerQuery"
                                       @input.debounce.300ms="searchCustomers()"
                                       :disabled="selectedCustomer !== null"
                                       placeholder="Nombre, correo o telefono..."
                                       class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none transition-colors theme-input disabled:bg-slate-50">
                                <template x-if="selectedCustomer">
                                    <button type="button" @click="clearCustomer()" class="absolute right-3 top-2.5 rounded-lg bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100">
                                        Cambiar
                                    </button>
                                </template>
                            </div>

                            <div x-show="customerSuggestions.length > 0" x-cloak class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                                <template x-for="customer in customerSuggestions" :key="customer.id">
                                    <button type="button" @click="selectCustomer(customer)" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 p-3 text-left transition-colors last:border-b-0 hover:bg-slate-50">
                                        <span>
                                            <span class="block text-xs font-black theme-text-heading" x-text="customer.full_name"></span>
                                            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400" x-text="customer.phone || customer.email || 'Sin contacto'"></span>
                                        </span>
                                        <span class="rounded-full theme-bg-primary-soft px-2.5 py-1 text-[9px] font-black uppercase tracking-widest theme-text-primary" x-text="customer.animals_count + ' caballos'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha</label>
                            <input type="date" name="budget_date" x-model="budgetDate" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold theme-text-heading outline-none theme-input">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Vigencia</label>
                            <input type="date" name="valid_until" x-model="validUntil" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold theme-text-heading outline-none theme-input">
                        </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Notas generales</label>
                            <div data-budget-quill-editor class="hidden min-h-[190px] bg-white">{!! old('notes') !!}</div>
                            <textarea name="notes" x-model="notes" data-budget-quill-content rows="4" placeholder="Observaciones generales del presupuesto..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none theme-input"></textarea>
                        </div>
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm" :class="{ 'opacity-60': !selectedCustomer }">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-black theme-text-heading">2. Caballos del cliente</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-400">Activa los caballos que llevaran trabajos en este presupuesto.</p>
                        </div>
                        <span class="rounded-full theme-bg-primary-soft px-3 py-1.5 text-[10px] font-black uppercase tracking-widest theme-text-primary" x-text="selectedAnimals.length + ' seleccionados'"></span>
                    </div>

                    <template x-if="!selectedCustomer">
                        <div class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center">
                            <p class="text-sm font-black theme-text-heading">Selecciona un cliente</p>
                            <p class="mt-2 text-xs font-semibold text-slate-400">Al elegirlo se cargaran sus caballos activos.</p>
                        </div>
                    </template>

                    <template x-if="selectedCustomer && animals.length === 0 && !animalsLoading">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-6 text-center">
                            <p class="text-sm font-black text-amber-800">Cliente sin caballos activos</p>
                            <p class="mt-2 text-xs font-semibold text-amber-700">Registra al menos un caballo para poder crear el presupuesto.</p>
                        </div>
                    </template>

                    <template x-if="animalsLoading">
                        <div class="rounded-2xl border border-slate-200 px-6 py-10 text-center">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Cargando caballos...</p>
                        </div>
                    </template>

                    <div x-show="animals.length > 0" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="animal in animals" :key="animal.id">
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-3 transition-all"
                                   :class="isAnimalSelected(animal.id) ? 'theme-border-primary theme-bg-primary-soft' : 'border-slate-200 bg-white hover:border-slate-300'">
                                <input type="checkbox" class="rounded border-slate-300 theme-text-primary focus:ring-0" :checked="isAnimalSelected(animal.id)" @change="toggleAnimal(animal)">
                                <span class="text-xs font-black theme-text-heading" x-text="animal.name"></span>
                            </label>
                        </template>
                    </div>
                </section>

                <template x-for="(animal, animalIndex) in selectedAnimals" :key="animal.id">
                    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-black theme-text-heading" x-text="animal.name"></p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-400">Servicios especificos para este caballo.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-slate-500 ring-1 ring-slate-200" x-text="'Subtotal $' + animalSubtotal(animal).toFixed(2)"></span>
                        </div>

                        <div class="space-y-4 p-5">
                            <input type="hidden" :name="'animals[' + animalIndex + '][animal_id]'" :value="animal.id">

                            <div class="relative">
                                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Agregar servicio</label>
                                <input type="text"
                                       x-model="animal.serviceQuery"
                                       @focus="activeAnimalId = animal.id"
                                       @input.debounce.300ms="searchServices(animal)"
                                       placeholder="Buscar servicio o producto del catalogo..."
                                       class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none theme-input">

                                <div x-show="activeAnimalId === animal.id && serviceSuggestions.length > 0" x-cloak class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                                    <template x-for="service in serviceSuggestions" :key="service.id">
                                        <button type="button" @click="addService(animal, service)" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 p-3 text-left transition-colors last:border-b-0 hover:bg-slate-50">
                                            <span>
                                                <span class="block text-xs font-black theme-text-heading" x-text="service.name"></span>
                                                <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-widest text-slate-400" x-text="service.type === 'service' ? 'Servicio' : 'Producto'"></span>
                                            </span>
                                            <span class="text-xs font-black theme-text-heading" x-text="'$' + parseFloat(service.price).toFixed(2)"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-slate-100">
                                <table class="w-full border-collapse text-left">
                                    <thead>
                                        <tr class="bg-slate-50">
                                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Servicio</th>
                                            <th class="w-24 px-4 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">Cant.</th>
                                            <th class="w-32 px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Precio base</th>
                                            <th class="w-36 px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Precio presupuesto</th>
                                            <th class="w-32 px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Subtotal</th>
                                            <th class="w-12 px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(item, itemIndex) in animal.items" :key="item.uid">
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="px-4 py-3">
                                                    <p class="text-xs font-black theme-text-heading" x-text="item.name"></p>
                                                    <input type="hidden" :name="'animals[' + animalIndex + '][items][' + itemIndex + '][catalog_item_id]'" :value="item.id">
                                                    <input type="hidden" :name="'animals[' + animalIndex + '][items][' + itemIndex + '][service_name_snapshot]'" :value="item.name">
                                                    <input type="hidden" :name="'animals[' + animalIndex + '][items][' + itemIndex + '][base_price]'" :value="item.base_price">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" min="1" step="1" x-model.number="item.quantity" :name="'animals[' + animalIndex + '][items][' + itemIndex + '][quantity]'" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-center text-xs font-bold theme-text-heading outline-none theme-input">
                                                </td>
                                                <td class="px-4 py-3 text-right text-xs font-bold text-slate-400" x-text="'$' + parseFloat(item.base_price).toFixed(2)"></td>
                                                <td class="px-4 py-3">
                                                    <div class="relative">
                                                        <span class="absolute left-2 top-1.5 text-xs font-bold text-slate-400">$</span>
                                                        <input type="number" min="0" step="0.01" x-model.number="item.price_at_budget" :name="'animals[' + animalIndex + '][items][' + itemIndex + '][price_at_budget]'" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-1.5 pl-5 pr-2 text-right text-xs font-bold theme-text-heading outline-none theme-input">
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-xs font-black theme-text-heading" x-text="'$' + itemSubtotal(item).toFixed(2)"></td>
                                                <td class="px-4 py-3 text-center">
                                                    <button type="button" @click="removeService(animal, itemIndex)" class="font-black text-slate-400 hover:text-rose-600">x</button>
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-if="animal.items.length === 0">
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-xs font-bold text-slate-400">
                                                    Sin servicios para este caballo.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <textarea :name="'animals[' + animalIndex + '][notes]'" x-model="animal.notes" rows="2" placeholder="Notas especificas para este caballo..." class="w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold theme-text-heading outline-none theme-input"></textarea>
                        </div>
                    </section>
                </template>
            </div>

            <aside class="space-y-6">
                <section class="sticky top-24 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Resumen</h2>
                    <div class="mt-4 space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>Cliente</span>
                            <span class="max-w-[150px] truncate theme-text-heading" x-text="selectedCustomer ? selectedCustomer.full_name : 'Sin seleccionar'"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>Caballos</span>
                            <span x-text="selectedAnimals.length"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>Servicios</span>
                            <span x-text="itemsCount()"></span>
                        </div>
                        <div class="border-t border-slate-200 pt-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-widest text-slate-500">Total</span>
                                <span class="text-2xl font-black theme-text-heading" x-text="'$' + grandTotal().toFixed(2)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <button type="submit" class="w-full rounded-xl theme-button-primary px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em]">
                            Guardar presupuesto
                        </button>
                        <p class="text-[10px] font-semibold leading-5 text-slate-400">Se guardara como borrador con precios congelados solo para este presupuesto.</p>
                    </div>
                </section>
            </aside>
        </div>
    </form>
</div>

@push('scripts')
<script>
function budgetBuilder() {
    return {
        customerQuery: '',
        customerSuggestions: [],
        selectedCustomer: null,
        animals: [],
        selectedAnimals: [],
        animalsLoading: false,
        activeAnimalId: null,
        serviceSuggestions: [],
        budgetDate: @js(now()->format('Y-m-d')),
        validUntil: @js(now()->addDays(15)->format('Y-m-d')),
        notes: @js(old('notes', '')),
        customerSearchUrl: @js(route('client.budgets.customers.search')),
        customerAnimalsUrl: @js(url('/client/presupuestos/customers')),
        serviceSearchUrl: @js(route('client.budgets.services.search')),

        searchCustomers() {
            if (this.selectedCustomer || this.customerQuery.length < 2) {
                this.customerSuggestions = [];
                return;
            }

            fetch(`${this.customerSearchUrl}?q=${encodeURIComponent(this.customerQuery)}`)
                .then(response => response.json())
                .then(data => { this.customerSuggestions = data; })
                .catch(() => { this.customerSuggestions = []; });
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerQuery = customer.full_name;
            this.customerSuggestions = [];
            this.loadAnimals(customer.id);
        },

        clearCustomer() {
            this.selectedCustomer = null;
            this.customerQuery = '';
            this.customerSuggestions = [];
            this.animals = [];
            this.selectedAnimals = [];
            this.serviceSuggestions = [];
            this.activeAnimalId = null;
        },

        loadAnimals(customerId) {
            this.animalsLoading = true;
            fetch(`${this.customerAnimalsUrl}/${customerId}/animals`)
                .then(response => response.json())
                .then(data => { this.animals = data; })
                .catch(() => { this.animals = []; })
                .finally(() => { this.animalsLoading = false; });
        },

        isAnimalSelected(animalId) {
            return this.selectedAnimals.some(animal => animal.id === animalId);
        },

        toggleAnimal(animal) {
            if (this.isAnimalSelected(animal.id)) {
                this.selectedAnimals = this.selectedAnimals.filter(selected => selected.id !== animal.id);
                return;
            }

            this.selectedAnimals.push({
                ...animal,
                serviceQuery: '',
                notes: '',
                items: [],
            });
        },

        searchServices(animal) {
            this.activeAnimalId = animal.id;

            if (animal.serviceQuery.length < 2) {
                this.serviceSuggestions = [];
                return;
            }

            fetch(`${this.serviceSearchUrl}?q=${encodeURIComponent(animal.serviceQuery)}`)
                .then(response => response.json())
                .then(data => { this.serviceSuggestions = data; })
                .catch(() => { this.serviceSuggestions = []; });
        },

        addService(animal, service) {
            animal.items.push({
                uid: `${service.id}-${Date.now()}-${Math.random()}`,
                id: service.id,
                name: service.name,
                quantity: 1,
                base_price: parseFloat(service.price || 0),
                price_at_budget: parseFloat(service.price || 0),
            });

            animal.serviceQuery = '';
            this.serviceSuggestions = [];
            this.activeAnimalId = null;
        },

        removeService(animal, index) {
            animal.items.splice(index, 1);
        },

        itemSubtotal(item) {
            return Number(item.quantity || 0) * Number(item.price_at_budget || 0);
        },

        animalSubtotal(animal) {
            return animal.items.reduce((sum, item) => sum + this.itemSubtotal(item), 0);
        },

        grandTotal() {
            return this.selectedAnimals.reduce((sum, animal) => sum + this.animalSubtotal(animal), 0);
        },

        itemsCount() {
            return this.selectedAnimals.reduce((sum, animal) => sum + animal.items.length, 0);
        },

        handleSubmit(event) {
            if (!this.selectedCustomer) {
                event.preventDefault();
                alert('Selecciona un cliente antes de guardar.');
                return;
            }

            if (this.selectedAnimals.length === 0) {
                event.preventDefault();
                alert('Selecciona al menos un caballo.');
                return;
            }

            if (this.itemsCount() === 0) {
                event.preventDefault();
                alert('Agrega al menos un servicio al presupuesto.');
            }
        },
    };
}
</script>
@endpush

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
        <style>
            [data-budget-quill-editor].ql-container.ql-snow { border-color: #e2e8f0; border-radius: 0 0 0.75rem 0.75rem; font-family: inherit; font-size: 0.75rem; }
            .ql-toolbar.ql-snow { border-color: #e2e8f0; border-radius: 0.75rem 0.75rem 0 0; }
            [data-budget-quill-editor] .ql-editor { min-height: 190px; font-weight: 600; color: #0f172a; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Quill === 'undefined') return;

                document.querySelectorAll('[data-budget-quill-editor]').forEach((editorElement) => {
                    const form = editorElement.closest('form');
                    const contentInput = form?.querySelector('[data-budget-quill-content]');
                    if (!form || !contentInput || editorElement.dataset.ready) return;

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

                        const root = form.closest('[x-data]');
                        if (root && window.Alpine) {
                            Alpine.$data(root).notes = contentInput.value;
                        }
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
