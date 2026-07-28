@if($usesMonthlyCutoffBilling)
    {{-- MODAL GENERAR CORTE --}}
    <div x-show="statementModal"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex min-h-screen items-center justify-center px-4 text-center sm:p-0">
            <div class="fixed inset-0 theme-overlay backdrop-blur-sm" @click="closeStatementModal()"></div>
            <div class="relative inline-block w-full max-w-3xl overflow-hidden rounded-[20px] bg-white text-left align-middle shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-700 px-6 py-4 text-white">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Generar corte</h3>
                        <p class="mt-1 text-xs font-semibold text-white/70" x-text="statementCustomer.name"></p>
                    </div>
                    <button type="button" @click="closeStatementModal()" class="text-2xl font-light text-white/70 hover:text-white">x</button>
                </div>

                <div class="p-6">
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <h4 class="text-sm font-black uppercase tracking-widest theme-text-heading">Seleccionar fechas para el corte</h4>
                        <p class="mt-1 text-[11px] font-semibold text-slate-400">Busca servicios dentro del rango que no pertenezcan a otro corte.</p>
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha inicio</label>
                                <input type="date" x-model="statementForm.date_from" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold theme-text-heading theme-input">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha fin</label>
                                <input type="date" x-model="statementForm.date_to" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold theme-text-heading theme-input">
                            </div>
                            <button type="button"
                                    @click="fetchStatementPreview()"
                                    :disabled="statementLoading"
                                    class="rounded-xl bg-emerald-600 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50">
                                <span x-text="statementLoading ? 'Buscando...' : 'Buscar'"></span>
                            </button>
                        </div>

                        <template x-if="statementError">
                            <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-xs font-bold text-rose-600" x-text="statementError"></p>
                        </template>

                        <template x-if="statementPreview">
                            <div class="mt-5">
                                <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Caballos atendidos</p>
                                        <p class="mt-2 text-2xl font-black theme-text-heading" x-text="statementPreview.animals_count"></p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Servicios realizados</p>
                                        <p class="mt-2 text-2xl font-black theme-text-heading" x-text="statementPreview.services_count"></p>
                                    </div>
                                    <div class="rounded-2xl bg-emerald-50 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Total</p>
                                        <p class="mt-2 text-2xl font-black text-emerald-700">$<span x-text="fmt(statementPreview.total)"></span></p>
                                    </div>
                                </div>

                                <template x-if="statementPreview.blocked_services_count > 0">
                                    <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800">
                                        <p>
                                            <span x-text="statementPreview.blocked_services_count"></span>
                                            <span x-text="statementPreview.blocked_services_count === 1 ? ' servicio del rango ya esta incluido en otro corte' : ' servicios del rango ya estan incluidos en otro u otros cortes'"></span>
                                            <span> y no puede agregarse de nuevo.</span>
                                        </p>
                                        <p class="mt-1 text-[11px] font-semibold text-amber-700">
                                            Total ya cubierto: $<span x-text="fmt(statementPreview.blocked_total)"></span>
                                        </p>
                                        <div class="mt-3 space-y-2" x-show="statementPreview.blocked_statements && statementPreview.blocked_statements.length">
                                            <template x-for="statement in statementPreview.blocked_statements" :key="statement.id">
                                                <div class="flex flex-col gap-2 rounded-xl bg-white/70 px-3 py-2 text-[11px] text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="font-black" x-text="statement.period"></p>
                                                        <p class="font-semibold text-amber-700">
                                                            <span x-text="statement.services_count"></span>
                                                            <span x-text="statement.services_count === 1 ? ' servicio contenido' : ' servicios contenidos'"></span>
                                                            <span> · $</span><span x-text="fmt(statement.total)"></span>
                                                        </p>
                                                    </div>
                                                    <a :href="statement.pdf_url"
                                                       target="_blank"
                                                       rel="noopener"
                                                       class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-amber-800 transition hover:bg-amber-100">
                                                        PDF
                                                    </a>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                            <th class="py-3">Caballo</th>
                                            <th class="py-3">Cantidad de servicios</th>
                                            <th class="py-3">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-center">
                                        <template x-for="row in statementPreview.rows" :key="row.animal">
                                            <tr>
                                                <td class="py-3 text-xs font-bold theme-text-heading" x-text="row.animal"></td>
                                                <td class="py-3 text-xs font-bold text-slate-600" x-text="row.services_count"></td>
                                                <td class="py-3 text-xs font-black theme-text-heading">$<span x-text="fmt(row.total)"></span></td>
                                            </tr>
                                        </template>
                                        <tr class="bg-slate-50">
                                            <td class="py-3 text-xs font-black theme-text-heading">Total</td>
                                            <td class="py-3 text-xs font-black theme-text-heading" x-text="statementPreview.services_count"></td>
                                            <td class="py-3 text-xs font-black theme-text-heading">$<span x-text="fmt(statementPreview.total)"></span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" @click="closeStatementModal()" class="rounded-xl bg-slate-500 px-5 py-3 text-xs font-bold text-white hover:bg-slate-600">
                        Cerrar
                    </button>
                    <form :action="statementCustomer.storeUrl" method="POST">
                        @csrf
                        <input type="hidden" name="date_from" :value="statementForm.date_from">
                        <input type="hidden" name="date_to" :value="statementForm.date_to">
                        <button type="submit"
                                :disabled="!statementPreview || statementPreview.services_count <= 0"
                                class="rounded-xl bg-slate-700 px-5 py-3 text-xs font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                            Generar corte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
