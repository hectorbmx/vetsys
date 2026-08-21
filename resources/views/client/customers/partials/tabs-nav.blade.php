<button @click="tab = 'mascotas'" :class="tab === 'mascotas' ? 'theme-button-primary shadow-sm' : 'theme-button-dark shadow-sm'" class="relative inline-flex items-center justify-center rounded-xl px-4 py-2.5 pr-8 text-[11px] font-black uppercase tracking-widest transition-all">
    Caballos
    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1.5 text-[10px] font-black leading-none text-slate-500 shadow-sm ring-1 ring-slate-200">{{ $customer->animals->count() }}</span>
</button>
<button @click="tab = 'notas'" :class="tab === 'notas' ? 'theme-button-primary shadow-sm' : 'theme-button-dark shadow-sm'" class="relative inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest transition-all">
    {{ $usesMonthlyCutoffBilling ? 'Cuentas' : 'Notas' }}
    {{-- <span class="absolute -    right-1 top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full {{ $usesMonthlyCutoffBilling && $billingBalance > 0 ? 'bg-rose-50 text-rose-600 ring-rose-100' : 'bg-slate-100 text-slate-500 ring-slate-200' }} px-1.5 text-[10px] font-black leading-none shadow-sm ring-1">{{ $usesMonthlyCutoffBilling ? '$'.number_format($billingBalance, 0) : $customer->saleNotes->count() }}</span> --}}
</button>
<button @click="tab = 'pagos'" :class="tab === 'pagos' ? 'theme-button-primary shadow-sm' : 'theme-button-dark shadow-sm'" class="relative inline-flex items-center justify-center rounded-xl px-4 py-2.5 pr-8 text-[11px] font-black uppercase tracking-widest transition-all">
    Historial de Pagos
    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1.5 text-[10px] font-black leading-none text-slate-500 shadow-sm ring-1 ring-slate-200">{{ $customer->payments->count() }}</span>
</button>
<button @click="tab = 'datos'" :class="tab === 'datos' ? 'theme-button-primary shadow-sm' : 'theme-button-dark shadow-sm'" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest transition-all">Datos</button>
@unless($usesMonthlyCutoffBilling)
    <button @click="tab = 'configuracion'" :class="tab === 'configuracion' ? 'theme-button-primary shadow-sm' : 'theme-button-dark shadow-sm'" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest transition-all">Configuracion</button>
@endunless
