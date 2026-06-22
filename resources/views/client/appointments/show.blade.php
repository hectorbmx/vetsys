@extends('layouts.client')

@section('title', 'Detalle de cita')

@php
    $status = $appointment->status->value;
    $statusLabels = [
        'pending_tenant' => 'Pendiente de confirmacion',
        'pending_customer' => 'Esperando respuesta del customer',
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
    $timezone = $appointment->timezone;
    $startsLocal = $appointment->starts_at->setTimezone($timezone);
    $endsLocal = $appointment->ends_at->setTimezone($timezone);
    $openStatuses = ['pending_tenant', 'pending_customer', 'confirmed'];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('client.agenda.index', ['date' => $startsLocal->toDateString(), 'view' => 'day']) }}"
               class="text-[10px] font-black uppercase tracking-widest theme-link-primary">Volver a agenda</a>
            <h1 class="mt-2 text-3xl font-black tracking-tight theme-text-heading">{{ $appointment->animal_name_snapshot }}</h1>
            <p class="mt-1 text-sm font-semibold text-slate-500">{{ $appointment->service_name_snapshot }}</p>
        </div>
        <span class="w-fit rounded-full border px-4 py-2 text-xs font-black {{ $statusClasses[$status] ?? 'bg-slate-100' }}">
            {{ $statusLabels[$status] ?? $status }}
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha y hora</p>
                    <p class="mt-2 text-lg font-black text-slate-900">{{ $startsLocal->format('d/m/Y H:i') }}</p>
                    <p class="text-xs font-semibold text-slate-500">Hasta {{ $endsLocal->format('H:i') }} · {{ $appointment->duration_minutes }} min</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Veterinario</p>
                    <p class="mt-2 text-lg font-black text-slate-900">{{ $appointment->doctor_name_snapshot }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $timezone }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</p>
                    <p class="mt-2 text-sm font-black text-slate-900">{{ $appointment->customer?->full_name ?? 'Customer eliminado' }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $appointment->customer?->phone }} {{ $appointment->customer?->email }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Paciente</p>
                    <p class="mt-2 text-sm font-black text-slate-900">{{ $appointment->animal_name_snapshot }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $appointment->animal?->animalType?->name }}</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Motivo visible</p>
                    <p class="mt-2 whitespace-pre-line text-sm font-semibold text-slate-700">{{ $appointment->customer_reason ?: 'Sin motivo registrado.' }}</p>
                </div>
                <div class="rounded-xl border border-violet-100 bg-violet-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-violet-500">Nota interna</p>
                    <p class="mt-2 whitespace-pre-line text-sm font-semibold text-violet-900">{{ $appointment->internal_notes ?: 'Sin nota interna.' }}</p>
                </div>
            </div>

            @if($appointment->rejection_reason || $appointment->cancellation_reason)
                <div class="mt-4 rounded-xl border border-rose-100 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                    {{ $appointment->rejection_reason ?: $appointment->cancellation_reason }}
                </div>
            @endif

            @if($appointment->is_late_cancellation)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-black text-amber-900">Cancelacion tardia · {{ $appointment->cancellation_fee_status->value }}</p>
                    @if($appointment->cancellation_fee_amount !== null)
                        <p class="mt-1 text-sm font-semibold text-amber-800">Monto sugerido: ${{ number_format((float) $appointment->cancellation_fee_amount, 2) }}</p>
                    @endif
                </div>
            @endif
        </section>

        <aside class="space-y-3">
            @if($status === 'pending_tenant')
                <details open class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <summary class="cursor-pointer text-sm font-black text-emerald-900">Confirmar cita</summary>
                    <form method="POST" action="{{ route('client.agenda.confirm', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <textarea name="internal_notes" rows="3" class="w-full rounded-xl border-emerald-200 text-sm theme-input" placeholder="Nota interna opcional">{{ $appointment->internal_notes }}</textarea>
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-xs font-black text-white">Confirmar</button>
                    </form>
                </details>

                <details class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <summary class="cursor-pointer text-sm font-black text-rose-900">Rechazar solicitud</summary>
                    <form method="POST" action="{{ route('client.agenda.reject', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <textarea name="reason" rows="3" required class="w-full rounded-xl border-rose-200 text-sm theme-input" placeholder="Motivo visible para el customer"></textarea>
                        <button class="w-full rounded-xl bg-rose-700 px-4 py-3 text-xs font-black text-white">Rechazar</button>
                    </form>
                </details>
            @endif

            @if(in_array($status, $openStatuses, true))
                <details class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <summary class="cursor-pointer text-sm font-black text-violet-900">Proponer otro horario</summary>
                    <form method="POST" action="{{ route('client.agenda.propose', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="datetime-local" name="starts_at" required class="w-full rounded-xl border-violet-200 text-sm theme-input">
                        <textarea name="message" rows="2" class="w-full rounded-xl border-violet-200 text-sm theme-input" placeholder="Mensaje para el customer"></textarea>
                        <button class="w-full rounded-xl bg-violet-700 px-4 py-3 text-xs font-black text-white">Enviar contrapropuesta</button>
                    </form>
                </details>

                <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <summary class="cursor-pointer text-sm font-black text-slate-800">Cancelar cita</summary>
                    <form method="POST" action="{{ route('client.agenda.cancel', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <textarea name="reason" rows="2" required class="w-full rounded-xl border-slate-200 text-sm theme-input" placeholder="Motivo visible"></textarea>
                        <button class="w-full rounded-xl bg-slate-800 px-4 py-3 text-xs font-black text-white">Cancelar cita</button>
                    </form>
                </details>
            @endif

            @if($status === 'confirmed' && $appointment->starts_at->isPast())
                <div class="grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('client.agenda.complete', $appointment) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="w-full rounded-xl bg-blue-700 px-3 py-3 text-[10px] font-black uppercase tracking-wider text-white">Completar</button>
                    </form>
                    <form method="POST" action="{{ route('client.agenda.no-show', $appointment) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="w-full rounded-xl bg-orange-600 px-3 py-3 text-[10px] font-black uppercase tracking-wider text-white">No asistio</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>

    @if($appointment->proposals->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black theme-text-heading">Contrapropuestas</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($appointment->proposals as $proposal)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-black text-slate-900">{{ $proposal->starts_at->setTimezone($timezone)->format('d/m/Y H:i') }}</p>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">{{ $proposal->status->value }}</span>
                        </div>
                        <p class="mt-2 text-xs font-semibold text-slate-600">{{ $proposal->message ?: 'Sin mensaje.' }}</p>
                        <p class="mt-2 text-[10px] font-bold text-slate-400">Vence: {{ $proposal->expires_at->setTimezone($timezone)->format('d/m/Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black theme-text-heading">Historial</h2>
        <div class="mt-5 space-y-0">
            @forelse($appointment->events as $event)
                <div class="relative border-l-2 border-slate-200 pb-6 pl-6 last:pb-0">
                    <span class="absolute -left-[7px] top-0 h-3 w-3 rounded-full theme-bg-primary"></span>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ str($event->event_type->value)->after('appointment.')->replace('_', ' ')->headline() }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ $event->actor?->name ?: 'Sistema' }}</p>
                            @if($event->metadata)
                                <p class="mt-2 break-words rounded-lg bg-slate-50 p-2 font-mono text-[10px] text-slate-500">{{ json_encode($event->metadata, JSON_UNESCAPED_UNICODE) }}</p>
                            @endif
                        </div>
                        <time class="text-[10px] font-bold text-slate-400">{{ $event->created_at->setTimezone($timezone)->format('d/m/Y H:i') }}</time>
                    </div>
                </div>
            @empty
                <p class="text-sm font-semibold text-slate-500">Sin eventos registrados.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
