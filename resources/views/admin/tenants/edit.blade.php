@extends('layouts.admin')

@section('title', 'Configurar Cliente')

@section('content')

<div class="max-w-5xl mx-auto space-y-8">
    <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
        <a href="{{ route('admin.tenants.index') }}" class="hover:text-[#38B2AC] transition-colors">Clientes</a>
        <span>/</span>
        <a href="{{ route('admin.tenants.show', $tenant) }}" class="hover:text-[#38B2AC] transition-colors">{{ $tenant->name }}</a>
        <span>/</span>
        <span class="text-[#0F172A]">Configuracion</span>
    </nav>

    @if(session('activation_code'))
        <div class="bg-white border border-[#38B2AC]/30 rounded-[24px] shadow-sm overflow-hidden">
            <div class="p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#38B2AC]">
                        Codigo de activacion generado
                    </p>
                    <h2 class="text-3xl font-black text-[#0F172A] tracking-widest mt-2">
                        {{ session('activation_code') }}
                    </h2>
                    <p class="text-sm text-slate-500 font-semibold mt-2">
                        Usuario: {{ session('activation_email') }} · Expira: {{ session('activation_expires_at') }}
                    </p>
                </div>
                <button type="button"
                        x-data
                        @click="navigator.clipboard.writeText('{{ session('activation_code') }}')"
                        class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-slate-200 text-[#0F172A] text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition">
                    Copiar codigo
                </button>
            </div>
        </div>
    @endif

    <div class="border-l-4 border-[#38B2AC] pl-6">
        <h1 class="text-4xl font-black text-[#0F172A] tracking-tighter">
            Configurar Cliente
        </h1>
        <p class="text-slate-500 font-medium mt-1">
            Actualiza el estado, plan y datos principales de {{ $tenant->name }}.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <p class="font-black uppercase tracking-widest text-[10px] mb-2">Revisa la informacion</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500">
                    Informacion General
                </h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Nombre Comercial</label>
                    <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Razon Social</label>
                    <input type="text" name="business_name" value="{{ old('business_name', $tenant->business_name) }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Correo Corporativo</label>
                    <input type="email" name="email" value="{{ old('email', $tenant->email) }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Telefono</label>
                    <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Plan SaaS</label>
                        <select name="plan_id" class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all cursor-pointer">
                            <option value="">Sin plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id', $tenant->plan_id) == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Estado</label>
                        <select name="status" class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all cursor-pointer">
                            <option value="inactive" @selected(old('status', $tenant->status) === 'inactive')>Inactivo</option>
                            <option value="active" @selected(old('status', $tenant->status) === 'active')>Activo</option>
                            <option value="suspended" @selected(old('status', $tenant->status) === 'suspended')>Suspendido</option>
                            <option value="cancelled" @selected(old('status', $tenant->status) === 'cancelled')>Cancelado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between bg-slate-900 p-2 rounded-[20px] shadow-xl">
            <a href="{{ route('admin.tenants.show', $tenant) }}"
               class="px-6 py-3 text-xs font-black text-white/50 hover:text-white uppercase tracking-widest transition-colors">
                Volver
            </a>

            <button type="submit"
                    class="bg-[#38B2AC] px-8 py-3.5 rounded-xl text-slate-900 font-black text-xs uppercase tracking-widest hover:bg-[#2d918c] active:scale-95 transition-all shadow-lg shadow-[#38B2AC]/20">
                Guardar Cambios
            </button>
        </div>
    </form>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="px-8 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500">
                Activacion de Usuarios
            </h2>
        </div>

        <table class="w-full text-left">
            <tbody class="divide-y divide-slate-100">
                @forelse($tenant->users as $user)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-4">
                            <p class="font-black text-[#0F172A]">{{ $user->name }}</p>
                            <p class="text-xs text-slate-400 font-medium">{{ $user->email }}</p>
                        </td>
                        <td class="px-8 py-4">
                            @if($user->is_active)
                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[9px] font-black uppercase tracking-tighter">
                                    Activo
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded {{ $user->invitation_expires_at?->isPast() ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }} text-[9px] font-black uppercase tracking-tighter">
                                    {{ $user->invitation_expires_at?->isPast() ? 'Codigo expirado' : 'Pendiente activar' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-8 py-4 text-right">
                            @if(!$user->is_active)
                                <form method="POST" action="{{ route('admin.tenants.users.resend-activation-code', [$tenant, $user]) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-2 rounded-xl bg-[#0F172A] text-white text-[9px] font-black uppercase tracking-widest hover:bg-slate-800 transition-colors">
                                        Reenviar codigo
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-8 py-8 text-center text-slate-400 text-xs font-bold uppercase tracking-widest">
                            Sin usuarios registrados
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
