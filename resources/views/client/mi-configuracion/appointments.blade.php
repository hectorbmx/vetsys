<div x-show="currentTab === 'agenda'" x-transition:enter="transition duration-200" class="space-y-6" x-cloak>
    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Estado de preparacion</p>
                <h2 class="mt-2 text-xl font-black theme-text-heading">
                    {{ $appointmentReadiness['ready'] ? 'Agenda lista para clientes' : 'Configuracion incompleta' }}
                </h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Las reservas solo pueden habilitarse cuando exista veterinario, horario y al menos un servicio agendable.
                </p>
            </div>
            <span class="inline-flex rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-widest {{ $appointmentReadiness['ready'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                {{ $appointmentReadiness['ready'] ? 'Lista' : 'Requiere atencion' }}
            </span>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach([
                ['key' => 'doctor', 'label' => 'Veterinario activo'],
                ['key' => 'schedule', 'label' => 'Horario semanal'],
                ['key' => 'service', 'label' => 'Servicio agendable'],
            ] as $requirement)
                <div class="rounded-2xl border p-4 {{ $appointmentReadiness[$requirement['key']] ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }}">
                    <p class="text-xs font-black {{ $appointmentReadiness[$requirement['key']] ? 'text-emerald-700' : 'text-slate-500' }}">
                        {{ $appointmentReadiness[$requirement['key']] ? 'Completo' : 'Pendiente' }}
                    </p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $requirement['label'] }}</p>
                </div>
            @endforeach
        </div>

        @unless($canManageAgenda)
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold text-amber-800">
                Solo un administrador del tenant puede modificar la configuracion de agenda.
            </div>
        @endunless
    </div>

    <form action="{{ route('client.mi-configuracion.agenda.update') }}" method="POST" class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        @csrf
        @method('PATCH')

        <div class="border-b border-slate-100 bg-slate-50/50 p-6">
            <h3 class="text-sm font-black uppercase tracking-widest theme-text-heading">Reglas generales</h3>
            <p class="mt-1 text-[11px] font-medium text-slate-400">Configura quien atiende y las politicas usadas para solicitudes.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Veterinario agendable</span>
                <select name="doctor_user_id" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    <option value="">Selecciona un veterinario</option>
                    @foreach($appointmentDoctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((int) old('doctor_user_id', $appointmentSetting->doctor_user_id) === $doctor->id)>
                            {{ $doctor->veterinarianProfile?->professional_name ?: $doctor->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Zona horaria</span>
                <select name="timezone" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    @foreach($appointmentTimezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $appointmentSetting->timezone) === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Intervalo de slots</span>
                <select name="slot_interval_minutes" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    @foreach([5, 10, 15, 20, 30, 60] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('slot_interval_minutes', $appointmentSetting->slot_interval_minutes) === $minutes)>{{ $minutes }} minutos</option>
                    @endforeach
                </select>
            </label>

            @foreach([
                ['name' => 'default_duration_minutes', 'label' => 'Duracion predeterminada (min)', 'min' => 5, 'max' => 480],
                ['name' => 'booking_window_days', 'label' => 'Ventana maxima (dias)', 'min' => 1, 'max' => 365],
                ['name' => 'proposal_hold_hours', 'label' => 'Vigencia de propuesta (horas)', 'min' => 1, 'max' => 72],
                ['name' => 'reminder_hours_before', 'label' => 'Recordatorio (horas antes)', 'min' => 1, 'max' => 168],
            ] as $field)
                <label class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">{{ $field['label'] }}</span>
                    <input type="number" name="{{ $field['name'] }}" min="{{ $field['min'] }}" max="{{ $field['max'] }}"
                           value="{{ old($field['name'], $appointmentSetting->{$field['name']}) }}" @disabled(!$canManageAgenda)
                           class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                </label>
            @endforeach

            {{-- Anticipacion minima: BD en minutos, UI en horas --}}
            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Anticipacion minima (horas)</span>
                <input type="number" name="minimum_notice_hours" min="0" max="168"
                       value="{{ old('minimum_notice_hours', (int) round($appointmentSetting->minimum_notice_minutes / 60)) }}"
                       @disabled(!$canManageAgenda)
                       class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
            </label>

            {{-- Cancelacion gratuita: BD en minutos, UI en horas --}}
            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Cancelacion gratuita (horas)</span>
                <input type="number" name="customer_cancellation_notice_hours" min="0" max="168"
                       value="{{ old('customer_cancellation_notice_hours', (int) round($appointmentSetting->customer_cancellation_notice_minutes / 60)) }}"
                       @disabled(!$canManageAgenda)
                       class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Politica de cancelacion</span>
                <select name="cancellation_policy" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    <option value="no_penalty" @selected(old('cancellation_policy', $appointmentSetting->cancellation_policy?->value) === 'no_penalty')>Sin penalizacion</option>
                    <option value="late_fee_review" @selected(old('cancellation_policy', $appointmentSetting->cancellation_policy?->value) === 'late_fee_review')>Revisar cargo tardio</option>
                </select>
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Tipo de cargo sugerido</span>
                <select name="late_fee_type" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    <option value="">Sin monto sugerido</option>
                    <option value="fixed" @selected(old('late_fee_type', $appointmentSetting->late_fee_type?->value) === 'fixed')>Monto fijo</option>
                    <option value="percentage" @selected(old('late_fee_type', $appointmentSetting->late_fee_type?->value) === 'percentage')>Porcentaje</option>
                </select>
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Valor sugerido</span>
                <input type="number" step="0.01" min="0" name="late_fee_value" value="{{ old('late_fee_value', $appointmentSetting->late_fee_value) }}"
                       @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Forma de cobro</span>
                <select name="late_fee_collection_method" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    <option value="">Sin definir</option>
                    <option value="account" @selected(old('late_fee_collection_method', $appointmentSetting->late_fee_collection_method?->value) === 'account')>Agregar a cuenta</option>
                    <option value="next_visit" @selected(old('late_fee_collection_method', $appointmentSetting->late_fee_collection_method?->value) === 'next_visit')>Siguiente consulta</option>
                </select>
            </label>

            <label class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Servicio para cargo tardio</span>
                <select name="late_fee_catalog_item_id" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-sm font-semibold theme-focus-primary">
                    <option value="">Sin servicio asociado</option>
                    @foreach($appointmentServices->where('is_active', true) as $service)
                        <option value="{{ $service->id }}" @selected((int) old('late_fee_catalog_item_id', $appointmentSetting->late_fee_catalog_item_id) === $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/50 p-6 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-center gap-3 text-xs font-black uppercase tracking-widest theme-text-heading">
                <input type="hidden" name="is_customer_booking_enabled" value="0">
                <input type="checkbox" name="is_customer_booking_enabled" value="1"
                       @checked(old('is_customer_booking_enabled', $appointmentSetting->is_customer_booking_enabled))
                       @disabled(!$canManageAgenda || !$appointmentReadiness['ready'])
                       class="rounded border-slate-300 theme-focus-primary">
                Permitir solicitudes de customers
            </label>
            @if($canManageAgenda)
                <button class="theme-button-dark rounded-xl px-5 py-3 text-xs font-black uppercase tracking-widest">Guardar reglas</button>
            @endif
        </div>
    </form>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/50 p-6">
                <h3 class="text-sm font-black uppercase tracking-widest theme-text-heading">Horario semanal</h3>
                <p class="mt-1 text-[11px] text-slate-400">Puedes crear varios bloques por dia.</p>
            </div>

            @if($canManageAgenda)
                <form action="{{ route('client.mi-configuracion.agenda.schedules.store') }}" method="POST" class="grid grid-cols-1 gap-3 border-b border-slate-100 p-5 sm:grid-cols-4">
                    @csrf
                    <select name="weekday" class="rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                        @foreach($appointmentWeekdays as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="time" name="starts_at" value="09:00" class="rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    <input type="time" name="ends_at" value="17:00" class="rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    <button class="theme-button-dark rounded-xl px-3 py-2 text-[10px] font-black uppercase tracking-widest">Agregar</button>
                </form>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($appointmentSchedules as $schedule)
                    <div class="flex items-center justify-between gap-4 p-5">
                        <div>
                            <p class="text-xs font-black theme-text-heading">{{ $appointmentWeekdays[$schedule->weekday] }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ substr($schedule->starts_at, 0, 5) }} - {{ substr($schedule->ends_at, 0, 5) }}</p>
                        </div>
                        @if($canManageAgenda)
                            <form action="{{ route('client.mi-configuracion.agenda.schedules.destroy', $schedule) }}" method="POST" onsubmit="return confirm('Eliminar este bloque semanal?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[10px] font-black uppercase tracking-widest text-red-600">Eliminar</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="p-8 text-center text-xs font-semibold text-slate-400">No hay horarios semanales configurados.</p>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/50 p-6">
                <h3 class="text-sm font-black uppercase tracking-widest theme-text-heading">Ausencias y bloqueos</h3>
                <p class="mt-1 text-[11px] text-slate-400">Las fechas se capturan en {{ $appointmentSetting->timezone }}.</p>
            </div>

            @if($canManageAgenda)
                <form action="{{ route('client.mi-configuracion.agenda.blocks.store') }}" method="POST" class="space-y-3 border-b border-slate-100 p-5">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input type="datetime-local" name="starts_at" class="rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                        <input type="datetime-local" name="ends_at" class="rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    </div>
                    <div class="flex gap-3">
                        <input type="text" name="reason" maxlength="255" placeholder="Motivo opcional" class="min-w-0 flex-1 rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                        <button class="theme-button-dark rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-widest">Bloquear</button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($appointmentBlocks as $block)
                    @php
                        $localStart = $block->starts_at->copy()->setTimezone($appointmentSetting->timezone);
                        $localEnd = $block->ends_at->copy()->setTimezone($appointmentSetting->timezone);
                    @endphp
                    <div class="flex items-center justify-between gap-4 p-5">
                        <div>
                            <p class="text-xs font-black theme-text-heading">{{ $localStart->format('d/m/Y H:i') }} - {{ $localEnd->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $block->reason ?: 'Sin motivo' }}</p>
                        </div>
                        @if($canManageAgenda)
                            <form action="{{ route('client.mi-configuracion.agenda.blocks.destroy', $block) }}" method="POST" onsubmit="return confirm('Eliminar esta ausencia?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[10px] font-black uppercase tracking-widest text-red-600">Eliminar</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="p-8 text-center text-xs font-semibold text-slate-400">No hay ausencias futuras registradas.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50/50 p-6">
            <h3 class="text-sm font-black uppercase tracking-widest theme-text-heading">Servicios agendables</h3>
            <p class="mt-1 text-[11px] text-slate-400">Solo servicios activos y habilitados apareceran al customer.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($appointmentServices as $service)
                <form action="{{ route('client.mi-configuracion.agenda.services.update', $service) }}" method="POST" class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-[1.2fr_150px_130px_2fr_auto] lg:items-end">
                    @csrf
                    @method('PATCH')
                    <div>
                        <p class="text-xs font-black theme-text-heading">{{ $service->name }}</p>
                        <p class="mt-1 text-[10px] font-bold uppercase tracking-widest {{ $service->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $service->is_active ? 'Activo' : 'Inactivo' }}</p>
                        <label class="mt-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
                            <input type="hidden" name="is_bookable" value="0">
                            <input type="checkbox" name="is_bookable" value="1" @checked($service->is_bookable) @disabled(!$canManageAgenda || !$service->is_active) class="rounded border-slate-300 theme-focus-primary">
                            Disponible en agenda
                        </label>
                    </div>
                    <label class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Duracion (min)</span>
                        <input type="number" name="appointment_duration_minutes" min="5" max="480" value="{{ $service->appointment_duration_minutes ?: 30 }}" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    </label>
                    <label class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Margen (min)</span>
                        <input type="number" name="appointment_buffer_minutes" min="0" max="120" value="{{ $service->appointment_buffer_minutes }}" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    </label>
                    <label class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Descripcion para reserva</span>
                        <input type="text" name="booking_description" maxlength="1000" value="{{ $service->booking_description }}" @disabled(!$canManageAgenda) class="w-full rounded-xl border-slate-200 text-xs font-semibold theme-focus-primary">
                    </label>
                    @if($canManageAgenda)
                        <button class="theme-button-dark rounded-xl px-4 py-3 text-[10px] font-black uppercase tracking-widest">Guardar</button>
                    @endif
                </form>
            @empty
                <p class="p-10 text-center text-xs font-semibold text-slate-400">No existen servicios en el catalogo.</p>
            @endforelse
        </div>
    </div>
</div>
