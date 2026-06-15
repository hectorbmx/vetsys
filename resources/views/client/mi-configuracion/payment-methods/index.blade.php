{{-- TAB 4: MÉTODOS DE PAGO --}}
<div x-show="currentTab === 'pagos'" x-transition:enter="transition duration-200" class="space-y-6">
    <div x-data="{ openForm: false }" class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        
        {{-- CABECERA DE LA SECCIÓN --}}
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-sm font-black theme-text-heading uppercase tracking-widest">Métodos de Pago Habilitados</h3>
                <p class="text-[11px] text-slate-400 font-medium mt-0.5">Configura las opciones que tus clientes tendrán disponibles para liquidar o abonar a sus notas médicas.</p>
            </div>
            <button @click="openForm = !openForm" class="theme-button-dark px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide shadow-sm transition-all">
                <span x-text="openForm ? '✕ Cerrar' : '+ Agregar Método'"></span>
            </button>
        </div>

        {{-- FORMULARIO INLINE DESPLEGABLE --}}
        <div x-show="openForm" x-collapse class="border-b border-slate-100 bg-slate-50/30 p-6">
            <form action="{{ route('client.payment-methods.store') }}" method="POST" class="max-w-xl space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Nombre comercial</label>
                        <input type="text" name="name" required placeholder="Ej: Terminal Clip, Efectivo" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                        @error('name') <span class="text-[11px] text-red-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Descripción (Opcional)</label>
                        <input type="text" name="description" placeholder="Ej: Cobros en caja central" class="w-full text-xs font-semibold theme-text-heading bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none theme-input transition-colors">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="theme-button-primary px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
                        Guardar Método
                    </button>
                </div>
            </form>
        </div>

        {{-- TABLA DE REGISTROS --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/10">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Método / Comercial</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Slug (Sistema)</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descripción</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse(auth()->user()->tenant->paymentMethods as $method)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-teal-50 theme-text-primary flex items-center justify-center font-black text-xs">
                                        💵
                                    </div>
                                    <span class="text-xs font-bold theme-text-heading">{{ $method->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-400">{{ $method->slug }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate">{{ $method->description ?? 'Sin descripción.' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex text-[9px] font-black uppercase tracking-widest {{ $method->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-slate-400 bg-slate-100' }} px-2.5 py-1 rounded-full">
                                    {{ $method->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('client.payment-methods.toggle', $method) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-lg text-xs font-semibold theme-text-heading transition-colors shadow-sm">
                                        {{ $method->is_active ? '⏸️ Desactivar' : '▶️ Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm font-bold text-slate-400">
                                No has dado de alta ningún método de pago personalizado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>