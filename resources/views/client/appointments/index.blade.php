@extends('layouts.client')

@section('title', 'Agenda')

@php
    $statusLabels = [
        'pending_tenant' => 'Pendiente',
        'pending_customer' => 'Respuesta customer',
        'confirmed' => 'Confirmada',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
        'completed' => 'Completada',
        'no_show' => 'No asistio',
    ];
    $statusClasses = [
        'pending_tenant' => 'bg-amber-100 text-amber-800 border-amber-200',
        'pending_customer' => 'bg-violet-100 text-violet-800 border-violet-200',
        'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
        'cancelled' => 'bg-slate-100 text-slate-600 border-slate-200',
        'completed' => 'bg-blue-100 text-blue-800 border-blue-200',
        'no_show' => 'bg-orange-100 text-orange-800 border-orange-200',
    ];
    $customerOptions = $customers->map(fn ($customer) => [
        'id' => $customer->id,
        'name' => $customer->full_name,
        'animals' => $customer->animals->map(fn ($animal) => [
            'id' => $animal->id,
            'name' => $animal->name,
        ])->values(),
    ])->values();
    $dateCursor = $from;
@endphp

@section('content')
<div class="space-y-6"
     x-data="appointmentPanel(@js($customerOptions), @js(route('client.agenda.availability')))">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.25em] theme-text-primary">Operacion tenant</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight theme-text-heading">Agenda</h1>
            <p class="mt-1 text-sm font-semibold text-slate-500">
                {{ $viewMode === 'day' ? $selectedDate->locale('es')->isoFormat('dddd D [de] MMMM') : $from->format('d/m/Y').' - '.$to->format('d/m/Y') }}
                <span class="ml-2 text-xs text-slate-400">{{ $timezone }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <a href="{{ route('client.agenda.index', array_merge(request()->except(['view', 'date']), ['view' => 'day', 'date' => $selectedDate->toDateString()])) }}"
                   class="rounded-lg px-4 py-2 text-xs font-black {{ $viewMode === 'day' ? 'theme-bg-primary theme-text-primary-ink' : 'text-slate-500 hover:bg-slate-50' }}">
                    Dia
                </a>
                <a href="{{ route('client.agenda.index', array_merge(request()->except(['view', 'date']), ['view' => 'week', 'date' => $selectedDate->toDateString()])) }}"
                   class="rounded-lg px-4 py-2 text-xs font-black {{ $viewMode === 'week' ? 'theme-bg-primary theme-text-primary-ink' : 'text-slate-500 hover:bg-slate-50' }}">
                    Semana
                </a>
            </div>
            @if($readiness['ready'])
                <button type="button" @click="manualOpen = true"
                        class="rounded-xl theme-surface-dark px-5 py-3 text-[10px] font-black uppercase tracking-widest shadow-lg">
                    + Cita manual
                </button>
            @endif
        </div>
    </div>

    @if(!$readiness['ready'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-black text-amber-900">Configuracion pendiente</p>
                <p class="mt-1 text-xs font-semibold text-amber-700">
                    Completa veterinario, horario y servicio reservable para operar la agenda.
                </p>
            </div>
            <a href="{{ route('client.mi-configuracion.index', ['tab' => 'agenda']) }}"
               class="rounded-xl bg-amber-900 px-4 py-2.5 text-center text-[10px] font-black uppercase tracking-widest text-white">
                Configurar agenda
            </a>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Pendientes</p>
            <p class="mt-2 text-2xl font-black text-amber-950">{{ $appointments->where('status.value', 'pending_tenant')->count() }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Confirmadas</p>
            <p class="mt-2 text-2xl font-black text-emerald-950">{{ $appointments->where('status.value', 'confirmed')->count() }}</p>
        </div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-violet-700">Respuesta customer</p>
            <p class="mt-2 text-2xl font-black text-violet-950">{{ $appointments->where('status.value', 'pending_customer')->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total periodo</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ $appointments->count() }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('client.agenda.index') }}"
          class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
        <input type="hidden" name="view" value="{{ $viewMode }}">
        <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Fecha</label>
            <input type="date" name="date" value="{{ $selectedDate->toDateString() }}"
                   class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
        </div>
        <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Estado</label>
            <select name="statuses[]" class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                <option value="">Todos</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(in_array($value, request('statuses', [])))>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Customer</label>
            <select name="customer_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                <option value="">Todos</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) request('customer_id') === $customer->id)>{{ $customer->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2 flex items-end gap-2">
            <button class="flex-1 rounded-xl theme-bg-primary px-4 py-3 text-[10px] font-black uppercase tracking-widest theme-text-primary-ink">Aplicar filtros</button>
            <a href="{{ route('client.agenda.index', ['view' => $viewMode]) }}"
               class="rounded-xl border border-slate-200 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500">Limpiar</a>
        </div>
    </form>

    <div class="flex items-center justify-between">
        <a href="{{ route('client.agenda.index', array_merge(request()->except('date'), ['date' => $previousDate])) }}"
           class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-600 shadow-sm">Anterior</a>
        <a href="{{ route('client.agenda.index', ['view' => $viewMode, 'date' => now($timezone)->toDateString()]) }}"
           class="text-[10px] font-black uppercase tracking-widest theme-link-primary">Hoy</a>
        <a href="{{ route('client.agenda.index', array_merge(request()->except('date'), ['date' => $nextDate])) }}"
           class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-600 shadow-sm">Siguiente</a>
    </div>

    <div class="overflow-x-auto pb-2">
        <div class="grid min-w-[900px] gap-4 {{ $viewMode === 'week' ? 'grid-cols-7' : 'grid-cols-1 min-w-0' }}">
            @for($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay())
                @php($dayAppointments = $appointmentsByDate->get($day->toDateString(), collect()))
                <section class="rounded-2xl border {{ $day->isToday() ? 'theme-border-primary bg-white' : 'border-slate-200 bg-slate-50/70' }} p-3 min-h-72">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $day->locale('es')->isoFormat('ddd') }}</p>
                            <p class="text-lg font-black theme-text-heading">{{ $day->format('d') }}</p>
                        </div>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-black text-slate-600">{{ $dayAppointments->count() }}</span>
                    </div>

                    <div class="space-y-2">
                        @forelse($dayAppointments as $appointment)
                            @php($status = $appointment->status->value)
                            <a href="{{ route('client.agenda.show', $appointment) }}"
                               class="block rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-black text-slate-900">{{ $appointment->starts_at->setTimezone($timezone)->format('H:i') }}</p>
                                    <span class="rounded-full border px-2 py-0.5 text-[9px] font-black {{ $statusClasses[$status] ?? 'bg-slate-100' }}">{{ $statusLabels[$status] ?? $status }}</span>
                                </div>
                                <p class="mt-2 truncate text-xs font-black text-slate-800">{{ $appointment->animal_name_snapshot }}</p>
                                <p class="truncate text-[11px] font-semibold text-slate-500">{{ $appointment->customer?->full_name }}</p>
                                <p class="mt-2 truncate text-[10px] font-bold text-slate-400">{{ $appointment->service_name_snapshot }}</p>
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 px-3 py-8 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">Sin citas</div>
                        @endforelse
                    </div>
                </section>
            @endfor
        </div>
    </div>

    <div x-show="manualOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" @keydown.escape.window="manualOpen = false">
        <div @click.outside="manualOpen = false" class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 p-6">
                <div>
                    <h2 class="text-xl font-black theme-text-heading">Nueva cita manual</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Se crea confirmada y ocupa inmediatamente el horario.</p>
                </div>
                <button type="button" @click="manualOpen = false" class="rounded-xl bg-slate-100 px-3 py-2 font-black text-slate-500">X</button>
            </div>

            <form method="POST" action="{{ route('client.agenda.manual.store') }}" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Customer</label>
                        <select name="customer_id" x-model="customerId" @change="animalId = ''" required class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                            <option value="">Selecciona</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Mascota</label>
                        <select name="animal_id" x-model="animalId" required class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                            <option value="">Selecciona</option>
                            <template x-for="animal in availableAnimals()" :key="animal.id">
                                <option :value="animal.id" x-text="animal.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Servicio</label>
                        <select name="service_id" x-model="serviceId" @change="loadSlots()" required class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                            <option value="">Selecciona</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->appointment_duration_minutes ?: $setting?->default_duration_minutes }} min)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Fecha</label>
                        <input type="date" x-model="date" @change="loadSlots()" min="{{ now($timezone)->toDateString() }}" required class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Horario disponible</label>
                    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                        <template x-if="loading"><p class="col-span-full text-xs font-semibold text-slate-500">Consultando horarios...</p></template>
                        <template x-for="slot in slots" :key="slot.starts_at">
                            <label class="cursor-pointer">
                                <input type="radio" name="starts_at" :value="slot.starts_at" required class="peer sr-only">
                                <span class="block rounded-xl border border-slate-200 px-3 py-2 text-center text-xs font-black text-slate-600 peer-checked:theme-bg-primary peer-checked:theme-text-primary-ink" x-text="slotTime(slot)"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="slotError" x-text="slotError" class="mt-2 text-xs font-semibold text-rose-600"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Motivo visible</label>
                    <textarea name="customer_reason" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input" placeholder="Motivo de la consulta"></textarea>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Nota interna</label>
                    <textarea name="internal_notes" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-sm theme-input" placeholder="Solo visible para admin y veterinario"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="manualOpen = false" class="rounded-xl border border-slate-200 px-5 py-3 text-xs font-black text-slate-500">Cancelar</button>
                    <button type="submit" class="rounded-xl theme-surface-dark px-5 py-3 text-xs font-black">Crear cita confirmada</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function appointmentPanel(customers, availabilityUrl) {
    return {
        manualOpen: false,
        customers,
        availabilityUrl,
        customerId: '',
        animalId: '',
        serviceId: '',
        date: '',
        slots: [],
        loading: false,
        slotError: '',
        availableAnimals() {
            return this.customers.find((customer) => String(customer.id) === String(this.customerId))?.animals ?? [];
        },
        async loadSlots() {
            this.slots = [];
            this.slotError = '';
            if (!this.serviceId || !this.date) return;
            this.loading = true;
            try {
                const query = new URLSearchParams({ service_id: this.serviceId, from: this.date });
                const response = await fetch(`${this.availabilityUrl}?${query}`, { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'No fue posible consultar horarios.');
                this.slots = payload.data?.[this.date] ?? [];
                if (!this.slots.length) this.slotError = 'No hay horarios disponibles para esta fecha.';
            } catch (error) {
                this.slotError = error.message;
            } finally {
                this.loading = false;
            }
        },
        slotTime(slot) {
            return new Date(slot.local_starts_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
@endpush
