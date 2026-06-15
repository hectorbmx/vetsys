@extends('layouts.client')

@section('title', 'Primera Venta')
@section('contextual-tour', 'first-sale')

@section('content')
<div x-data="salesPOS()" x-init="initPOS()" class="p-6 max-w-7xl mx-auto space-y-6">

    {{-- MARQUESINA DE RECUPERACIÓN (SALVAVIDAS CONTRA ACCIDENTES) --}}
    <div x-show="showRecoveryAlert" x-cloak x-collapse class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <span class="text-xl">💾</span>
            <div>
                <p class="text-xs font-bold text-amber-900">¿Tuviste un percance?</p>
                <p class="text-[11px] text-amber-700 font-medium">Detectamos una nota de venta incompleta guardada en este navegador. ¿Deseas recuperarla?</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="recoverBackup()" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded-lg text-[11px] font-black uppercase tracking-wide transition-all shadow-sm">
                Recuperar Datos
            </button>
            <button @click="clearBackup()" class="text-amber-600 hover:text-amber-800 px-2 py-1.5 text-[11px] font-bold">
                Descartar
            </button>
        </div>
    </div>

    {{-- ENCABEZADO --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-black theme-text-heading uppercase tracking-widest">Nueva Nota de Venta</h1>
            <p class="text-xs text-slate-400 font-medium mt-0.5">Genera cargos a clientes, asigna conceptos a pacientes y procesa pagos al instante.</p>
        </div>
        <a href="{{ route('client.ventas.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide transition-all">
            ← Volver al Historial
        </a>
    </div>

    <form :action="storeRoute" method="POST" @submit="handleSubmit($event)">
        @csrf
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            {{-- SECCIÓN IZQUIERDA: CONFIGURACIÓN GENERAL Y BÚSQUEDAS (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- PASO 1: BÚSQUEDA DEL CLIENTE Y FECHA --}}
                <div data-tour="sale-customer" class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    {{-- Buscador de Cliente con Autocomplete --}}
                    <div class="md:col-span-2 relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">1. Buscar Cliente (Propietario)</label>
                        
                        <div class="relative">
                            <input type="text" x-model="customerQuery" @input.debounce.300ms="searchCustomers()" placeholder="Escribe nombre, apellido o teléfono..." class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors" :disabled="selectedCustomer !== null">
                            
                            {{-- Botón para remover cliente seleccionado --}}
                            <template x-if="selectedCustomer !== null">
                                <button type="button" @click="removeCustomer()" class="absolute right-3 top-2.5 text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg transition-colors">
                                    Cambiar Cliente
                                </button>
                            </template>
                        </div>

                        {{-- Input Oculto para enviar el ID real al Backend --}}
                        <input type="hidden" name="customer_id" :value="selectedCustomer ? selectedCustomer.id : ''">

                        {{-- Lista Desplegable de Sugerencias --}}
                        <div x-show="customerSuggestions.length > 0" x-cloak class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden divide-y divide-slate-100">
                            <template x-for="customer in customerSuggestions" :key="customer.id">
                                <div @click="selectCustomer(customer)" class="p-3 hover:bg-slate-50 cursor-pointer transition-colors flex justify-between items-center">
                                    <div>
                                        <span class="text-xs font-bold theme-text-heading block" x-text="customer.full_name"></span>
                                        <span class="text-[10px] text-slate-400 font-medium" x-text="'📞 ' + (customer.phone ?? 'Sin teléfono')"></span>
                                    </div>
                                    <span class="text-[9px] theme-bg-primary-soft theme-text-primary-strong font-black px-2 py-1 rounded-full uppercase tracking-wider" x-text="customer.animals.length + ' mascotas'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Campo de Fecha --}}
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Fecha de la Nota</label>
                        <input type="date" name="date_at" x-model="noteDate" required class="w-full text-xs font-bold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors">
                    </div>
                </div>

                {{-- PASO 3: BUSCADOR DE ARTICULOS (Habilitado solo si ya elegiste cliente y pacientes) --}}
                {{-- PASO 2: SELECCIONAR MASCOTAS A LAS QUE SE APLICARA LA NOTA --}}
                <div data-tour="sale-animals" class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-4" :class="{'opacity-50 pointer-events-none': selectedCustomer === null}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">2. Seleccionar Pacientes</label>
                            <p class="text-[11px] font-semibold text-slate-500">Cada producto o servicio agregado se aplicara a todas las mascotas seleccionadas.</p>
                        </div>
                        <span class="text-[10px] theme-bg-primary-soft theme-text-primary-strong font-black px-3 py-1.5 rounded-full uppercase tracking-wider" x-text="selectedAnimalIds.length + ' seleccionadas'"></span>
                    </div>

                    <template x-if="selectedCustomer && selectedCustomer.animals.length > 0">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <template x-for="animal in selectedCustomer.animals" :key="animal.id">
                                <label class="flex items-center gap-3 border rounded-xl px-3 py-3 cursor-pointer transition-all"
                                       :class="isAnimalSelected(animal.id) ? 'theme-border-primary theme-bg-primary-soft theme-text-heading' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                                    <input type="checkbox" class="rounded border-slate-300 theme-text-primary theme-focus-ring-primary" :checked="isAnimalSelected(animal.id)" @change="toggleAnimal(animal.id)">
                                    <span class="text-xs font-black" x-text="animal.name"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                    <template x-if="selectedCustomer && selectedCustomer.animals.length === 0">
                        <div class="border border-amber-200 bg-amber-50 rounded-xl px-4 py-3 text-xs font-bold text-amber-800">
                            Este cliente no tiene mascotas registradas. Para generar la nota primero registra al menos una mascota.
                        </div>
                    </template>

                    <template x-for="(animalId, index) in selectedAnimalIds" :key="animalId">
                        <input type="hidden" :name="'animal_ids['+index+']'" :value="animalId">
                    </template>
                </div>

                <div data-tour="sale-items" class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-4" :class="{'opacity-50 pointer-events-none': selectedCustomer === null || selectedAnimalIds.length === 0}">
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">3. Anadir Productos o Servicios al Detalle</label>
                        <input type="text" x-model="itemQuery" @input.debounce.300ms="searchItems()" placeholder="Escribe el nombre del servicio o escanea el SKU del producto..." class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors">
                        
                        {{-- Lista Desplegable de Artículos --}}
                        <div x-show="itemSuggestions.length > 0" x-cloak class="absolute z-40 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden divide-y divide-slate-100">
                            <template x-for="item in itemSuggestions" :key="item.id">
                                <div @click="addItemToTable(item)" class="p-3 hover:bg-slate-50 cursor-pointer transition-colors flex justify-between items-center">
                                    <div>
                                        <span class="text-xs font-bold theme-text-heading block" x-text="item.name"></span>
                                        <span class="text-[10px] text-slate-400 font-mono uppercase" x-text="item.type === 'service' ? '⚙️ Servicio' : '📦 Producto'"></span>
                                    </div>
                                    <span class="text-xs font-black theme-text-heading" x-text="'$' + parseFloat(item.price).toFixed(2)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- TABLA REACTIVA DE DETALLES --}}
                    <div class="overflow-hidden border border-slate-100 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Concepto</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest w-20">Cant.</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest w-28">Precio U.</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right w-28">Subtotal Unit.</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(row, index) in basket" :key="index">
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        {{-- Nombre e Inputs Ocultos del Renglón para Laravel --}}
                                        <td class="px-4 py-3">
                                            <span class="text-xs font-bold theme-text-heading block" x-text="row.name"></span>
                                            <span x-show="row.has_inventory" class="text-[10px] text-slate-400 font-semibold block" x-text="'Disponible: ' + parseFloat(row.stock_actual).toFixed(2)"></span>
                                            <span x-show="stockMessage(row)" class="text-[10px] font-bold block mt-1"
                                                  :class="stockState(row) === 'blocked' || stockState(row) === 'negative' ? 'text-rose-600' : 'text-amber-600'"
                                                  x-text="stockMessage(row)"></span>
                                            <input type="hidden" :name="'items['+index+'][id]'" :value="row.id">
                                        </td>
                                        

                                        {{-- Cantidad Editable --}}
                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" min="0.01" :name="'items['+index+'][quantity]'" x-model.number="row.quantity" @input="calculateTotals()" class="w-full text-xs font-bold text-center theme-text-heading bg-slate-50 border border-slate-200 rounded-lg py-1 px-1.5 theme-input">
                                        </td>

                                        {{-- Precio Editable --}}
                                        <td class="px-4 py-3">
                                            <div class="relative flex items-center">
                                                <span class="absolute left-1.5 text-xs text-slate-400 font-bold">$</span>
                                                <input type="number" step="0.01" min="0" :name="'items['+index+'][price]'" x-model.number="row.price" @input="calculateTotals()" class="w-full text-xs font-bold text-right theme-text-heading bg-slate-50 border border-slate-200 rounded-lg py-1 pr-1.5 pl-4 theme-input">
                                            </div>
                                        </td>

                                        {{-- Subtotal Calculado --}}
                                        <td class="px-4 py-3 text-right text-xs font-black theme-text-heading" x-text="'$' + (row.quantity * row.price).toFixed(2)">
                                        </td>

                                        {{-- Botón Eliminar Fila --}}
                                        <td class="px-4 py-3 text-center">
                                            <button type="button" @click="removeItem(index)" class="text-slate-400 hover:text-rose-600 font-bold transition-colors">
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                {{-- Fila Vacía --}}
                                <template x-if="basket.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-xs font-bold text-slate-400">
                                            No has agregado ningún artículo a la nota todavía.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN DERECHA: CAJA FINANCIERA Y ACCIONES (1/3) --}}
            <div class="space-y-6">
                <div data-tour="sale-checkout" class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-6">
                    
                    {{-- TOTALES DE LA COMPRA --}}
                    <div>
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Resumen Financiero</h3>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Subtotal por mascota:</span>
                                <span class="text-sm font-black theme-text-heading" x-text="'$' + basketSubtotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center text-[11px] font-bold text-slate-500">
                                <span>Mascotas seleccionadas:</span>
                                <span x-text="selectedAnimalIds.length"></span>
                            </div>
                            <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Total de Nota:</span>
                                <span class="text-2xl font-black theme-text-heading" x-text="'$' + noteTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-100">

                    
                    {{-- MÓDULO DE LIQUIDACIÓN / CAJA (Contado o Crédito) --}}
<div class="space-y-4" :class="{'opacity-50 pointer-events-none': basket.length === 0 || selectedAnimalIds.length === 0}">
    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Método de Operación</label>
    
    {{-- Selector de tipo tipo Toggle con Botones Estilizados --}}
    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl">
        <button type="button" 
                @click="paymentType = 'credito'; amountReceived = 0;" 
                :class="paymentType === 'credito' ? 'bg-white theme-text-heading shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                class="py-2 text-xs font-black uppercase tracking-wider rounded-lg transition-all text-center">
            🕒 Crédito
        </button>
        <button type="button" 
                @click="paymentType = 'contado'; amountReceived = noteTotal; handlePaymentMethodChange();" 
                :class="paymentType === 'contado' ? 'theme-button-primary shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                class="py-2 text-xs font-black uppercase tracking-wider rounded-lg transition-all text-center">
            💰 Contado
        </button>
    </div>
    <input type="hidden" name="operation_type" :value="paymentType">

    {{-- Campos condicionales si se selecciona CONTADO --}}
    <div x-show="paymentType === 'contado'" x-transition class="space-y-4 pt-2">
        
        {{-- Monto Recibido (Se auto-llena con el total, pero es editable si deja cambio) --}}
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Monto Recibido ($)</label>
            <div class="relative flex items-center">
                <span class="absolute left-4 text-xs font-black text-slate-400">$</span>
                <input type="number" step="0.01" min="0" name="amount_received" x-model.number="amountReceived" :disabled="isStripeCardPayment()" class="w-full text-xs font-black theme-text-heading bg-white border border-slate-200 rounded-xl py-2.5 pr-4 pl-8 theme-input transition-colors disabled:bg-slate-100 disabled:text-slate-400">
            </div>
            <p x-show="isStripeCardPayment()" x-cloak class="text-[11px] font-semibold text-[#635BFF] mt-2">El cobro con tarjeta se confirmara por Stripe. La nota quedara pendiente hasta recibir el pago.</p>
        </div>

        {{-- Forma de Pago --}}
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Forma de Pago</label>
            <select name="payment_method_id" x-model="paymentMethodId" @change="handlePaymentMethodChange()" :required="paymentType === 'contado'" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 theme-input transition-colors">
                <option value="">-- Selecciona Método --</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}" data-slug="{{ $method->slug }}">💵 {{ $method->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Alerta visual de Cambio a entregar si paga con más del total --}}
        <div x-show="amountReceived > noteTotal" x-transition class="p-3 bg-teal-50 border border-teal-100 rounded-xl flex justify-between items-center">
            <span class="text-[11px] font-bold text-teal-800">Cambio para el cliente:</span>
            <span class="text-xs font-black text-teal-900" x-text="'$' + (amountReceived - noteTotal).toFixed(2)"></span>
        </div>
    </div>
</div>

                    {{-- BOTÓN PRINCIPAL DE ENVÍO --}}
                    <button type="submit" class="w-full theme-button-dark p-4 rounded-xl font-black text-xs uppercase tracking-widest shadow-md transition-all text-center disabled:opacity-40 disabled:pointer-events-none" :disabled="basket.length === 0 || selectedCustomer === null || selectedAnimalIds.length === 0 || hasBlockingStock()">
                        <span x-text="isStripeCardPayment() ? 'Guardar nota y generar link Stripe' : '🛒 Procesar y Guardar Nota'"></span>
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

{{-- MOTOR REACTIVO CON ALPINE.JS --}}
<script>
function salesPOS() {
    return {
        // Rutas y configuración
        storeRoute: "{{ route('client.ventas.store') }}",
        searchCustomerUrl: "{{ route('client.api.buscar-clientes') }}",
        searchItemUrl: "{{ route('client.api.buscar-articulos') }}",
        prefilledCustomer: @js($prefilledCustomer),
        
        // Estados de búsqueda
        customerQuery: '',
        itemQuery: '',
        customerSuggestions: [],
        itemSuggestions: [],
        
        // Datos de la Nota
        selectedCustomer: null,
        selectedAnimalIds: [],
        noteDate: new Date().toISOString().split('T')[0], // Fecha del día actual nativa
        basket: [],
        basketSubtotal: 0.00,
        noteTotal: 0.00,
        amountReceived: 0.00,
        paymentType: 'credito',
        paymentMethodId: '',

        // Estado del Backup
        showRecoveryAlert: false,

        // Inicializador del componente
        initPOS() {
            if (this.prefilledCustomer) {
                localStorage.removeItem('vet_pos_backup');
                this.selectedCustomer = this.prefilledCustomer;
                this.customerQuery = this.prefilledCustomer.full_name;
                this.customerSuggestions = [];
                this.selectedAnimalIds = [];
                return;
            }

            // Verificar si hay una sesión previa accidentada en el LocalStorage
            if (localStorage.getItem('vet_pos_backup')) {
                this.showRecoveryAlert = true;
            }
        },

        // Buscar clientes asíncronamente
        searchCustomers() {
            if (this.customerQuery.length < 2) {
                this.customerSuggestions = [];
                return;
            }
            fetch(`${this.searchCustomerUrl}?q=${encodeURIComponent(this.customerQuery)}`)
                .then(res => res.json())
                .then(data => { this.customerSuggestions = data; });
        },

        // Seleccionar cliente y guardarlo en el flujo
        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerQuery = customer.full_name;
            this.customerSuggestions = [];
            this.selectedAnimalIds = [];
            this.saveToLocalStorage();
        },

        // Remover cliente para elegir otro
        removeCustomer() {
            this.selectedCustomer = null;
            this.customerQuery = '';
            this.selectedAnimalIds = [];
            this.basket = []; // Limpiamos tabla para evitar discrepancias de mascotas
            this.calculateTotals();
        },

        isAnimalSelected(animalId) {
            return this.selectedAnimalIds.includes(String(animalId));
        },

        toggleAnimal(animalId) {
            const normalizedId = String(animalId);
            if (this.isAnimalSelected(normalizedId)) {
                this.selectedAnimalIds = this.selectedAnimalIds.filter(id => id !== normalizedId);
            } else {
                this.selectedAnimalIds.push(normalizedId);
            }
            this.calculateTotals();
        },

        // Buscar artículos de forma asíncrona
        searchItems() {
            if (this.itemQuery.length < 2) {
                this.itemSuggestions = [];
                return;
            }
            fetch(`${this.searchItemUrl}?q=${encodeURIComponent(this.itemQuery)}`)
                .then(res => res.json())
                .then(data => { this.itemSuggestions = data; });
        },

        // Agregar artículo a la tabla reactiva
        addItemToTable(item) {
            // Evaluamos si el artículo ya se encuentra en la tabla para solo sumarle uno
            let existing = this.basket.find(row => row.id === item.id);
            if (existing) {
                existing.quantity += 1;
            } else {
                    this.basket.push({
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

        // Eliminar fila de la nota
        removeItem(index) {
            this.basket.splice(index, 1);
            this.calculateTotals();
        },

        // Recalcular la suma de la nota al vuelo
        calculateTotals() {
            let runningTotal = 0;
            this.basket.forEach(row => {
                runningTotal += (row.quantity * row.price);
            });
            this.basketSubtotal = runningTotal;
            this.noteTotal = runningTotal * this.selectedAnimalIds.length;
            this.saveToLocalStorage();
        },

        requiredQuantity(row) {
            return parseFloat(row.quantity || 0) * this.selectedAnimalIds.length;
        },

        stockState(row) {
            if (!row.has_inventory || this.selectedAnimalIds.length === 0) {
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
                return `Existencias insuficientes. La venta requiere ${this.requiredQuantity(row).toFixed(2)} y quedaría en ${resultingStock.toFixed(2)}.`;
            }

            if (state === 'negative') {
                return `Advertencia: esta venta dejará el inventario en ${resultingStock.toFixed(2)}.`;
            }

            if (state === 'low') {
                return `El inventario quedará en ${resultingStock.toFixed(2)}, igual o debajo del mínimo.`;
            }

            return '';
        },

        hasBlockingStock() {
            return this.basket.some(row => this.stockState(row) === 'blocked');
        },

        // Persistencia local (Guarda el progreso de la sesión ante apagones/accidentes)
        saveToLocalStorage() {
            if (this.selectedCustomer || this.basket.length > 0) {
                let state = {
                    customer: this.selectedCustomer,
                    selectedAnimalIds: this.selectedAnimalIds,
                    basket: this.basket,
                    noteDate: this.noteDate
                };
                localStorage.setItem('vet_pos_backup', JSON.stringify(state));
            }
        },

        // Recuperar los datos salvados por LocalStorage
        recoverBackup() {
            let backup = JSON.parse(localStorage.getItem('vet_pos_backup'));
            if (backup) {
                this.selectedCustomer = backup.customer;
                this.customerQuery = backup.customer ? backup.customer.full_name : '';
                this.selectedAnimalIds = (backup.selectedAnimalIds || []).map(id => String(id));
                this.basket = backup.basket;
                this.noteDate = backup.noteDate;
                this.calculateTotals();
            }
            this.showRecoveryAlert = false;
        },

        // Limpiar el LocalStorage manualmente
        clearBackup() {
            localStorage.removeItem('vet_pos_backup');
            this.showRecoveryAlert = false;
        },

        handlePaymentMethodChange() {
            if (this.isStripeCardPayment()) {
                this.amountReceived = 0;
                return;
            }

            if (this.paymentType === 'contado') {
                this.amountReceived = this.noteTotal;
            }
        },

        isStripeCardPayment() {
            if (this.paymentType !== 'contado' || !this.paymentMethodId) {
                return false;
            }

            const option = document.querySelector(`select[name="payment_method_id"] option[value="${this.paymentMethodId}"]`);
            const value = `${option?.textContent ?? ''} ${option?.dataset?.slug ?? ''}`.toLowerCase();

            return value.includes('tarjeta') || value.includes('card') || value.includes('stripe');
        },

        // Limpieza de seguridad al procesar exitosamente la venta
        handleSubmit(event) {
            if (this.hasBlockingStock()) {
                event.preventDefault();
            }
        }
    }
}

</script>
@endsection
