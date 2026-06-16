@if(session('activation_link') || session('activation_code'))
    <div
        x-data="{ copiedLink: false, copiedCode: false, link: @js(session('activation_link')), code: @js(session('activation_code')) }"
        class="bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 rounded-2xl text-xs font-bold space-y-3 shadow-sm"
    >
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
            <div>
                <p class="font-black uppercase tracking-widest text-amber-700">Invitacion para activar cuenta</p>
                <p class="text-[11px] text-amber-800 mt-1">
                    Comparte este enlace con el customer para que defina su contrasena y pueda entrar a la app.
                </p>
                @if(session('activation_email'))
                    <p class="text-[11px] text-amber-800 mt-1">Correo: {{ session('activation_email') }}</p>
                @endif
            </div>
            <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-white border border-amber-200 text-[10px] font-black uppercase tracking-widest text-amber-700">
                Expira en 7 dias
            </span>
        </div>

        @if(session('activation_link'))
            <div>
                <p class="text-[10px] uppercase tracking-widest text-amber-700">Link de activacion</p>
                <div class="flex flex-col sm:flex-row gap-2 mt-1">
                    <input readonly value="{{ session('activation_link') }}" class="flex-1 bg-white border border-amber-200 rounded-xl px-3 py-2 text-[11px] font-semibold text-slate-700">
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText(link); copiedLink = true; setTimeout(() => copiedLink = false, 1800)"
                        class="bg-amber-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest"
                    >
                        <span x-text="copiedLink ? 'Copiado' : 'Copiar link'"></span>
                    </button>
                </div>
            </div>
        @endif

        @if(session('activation_code'))
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <p>Codigo: <span class="font-black text-lg tracking-[0.3em]">{{ session('activation_code') }}</span></p>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText(code); copiedCode = true; setTimeout(() => copiedCode = false, 1800)"
                    class="self-start bg-white border border-amber-200 text-amber-700 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest"
                >
                    <span x-text="copiedCode ? 'Copiado' : 'Copiar codigo'"></span>
                </button>
            </div>
        @endif
    </div>
@endif
