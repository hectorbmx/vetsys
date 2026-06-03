@extends('layouts.admin')

@section('title', 'Nuevo Plan')

@section('content')

<div class="max-w-4xl mx-auto space-y-8">

    {{-- Header --}}
    <div class="flex items-end justify-between border-b-2 border-slate-100 pb-6">
        <div>
            <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">
                <a href="{{ route('admin.planes.index') }}" class="hover:text-[#38B2AC] transition-colors">Planes</a>
                <span>/</span>
                <span class="text-[#0F172A]">Crear</span>
            </nav>
            <h1 class="text-4xl font-black text-[#0F172A] tracking-tighter">
                Nuevo Plan
            </h1>
            <p class="text-slate-500 font-medium mt-1">Define las reglas y costos de tu modelo de negocio.</p>
        </div>
        <div class="hidden md:block">
            <span class="text-5xl opacity-10">💎</span>
        </div>
    </div>

    <form action="{{ route('admin.planes.store') }}" method="POST" class="space-y-8">
        @csrf

        {{-- Sección 1: Identidad --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden transition-all hover:shadow-md">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-black text-[11px] uppercase tracking-widest text-[#38B2AC]">
                    01. Identidad del Plan
                </h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Nombre Comercial</label>
                    <input type="text" name="name" value="{{ old('name') }}" 
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="Ej. Plan Professional">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Slug Identificador</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" 
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="plan-professional">
                </div>

                <div class="md:col-span-2 space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Descripción de Beneficios</label>
                    <textarea name="description" rows="3" 
                              class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                              placeholder="Describe qué incluye este plan...">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Sección 2: Configuración Financiera y Límites --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-black text-[11px] uppercase tracking-widest text-[#38B2AC]">
                    02. Suscripción y Capacidades
                </h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Precio Unitario</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">$</span>
                        <input type="number" step="0.01" name="price" value="{{ old('price', 0) }}" 
                               class="w-full bg-slate-50 border-slate-200 rounded-xl pl-8 pr-4 py-3 text-sm font-black text-[#0F172A] focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Divisa</label>
                    <select name="currency" class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                        <option value="MXN">MXN - Peso Mexicano</option>
                        <option value="USD">USD - Dólar Americano</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Ciclo de Cobro</label>
                    <select name="billing_period" class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                        <option value="monthly">Suscripción Mensual</option>
                        <option value="yearly">Suscripción Anual</option>
                        <option value="free">Gratuito</option>
                        <option value="one_time">Pago Único</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Límite Usuarios</label>
                    <input type="number" name="max_users" value="{{ old('max_users') }}" 
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="∞ Ilimitado">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Límite Clientes</label>
                    <input type="number" name="max_clients" value="{{ old('max_clients') }}" 
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="Ej. 1000">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">Trial (Días)</label>
                    <input type="number" name="trial_days" value="{{ old('trial_days', 0) }}" 
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all">
                </div>
            </div>
        </div>

        {{-- Sección 3: Stripe Integration --}}
        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <span class="text-blue-500 font-bold">Stripe</span>
                <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-400">
                    Sincronización de Pasarela
                </h2>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1 text-blue-600">Product ID</label>
                    <input type="text" name="stripe_product_id" value="{{ old('stripe_product_id') }}" 
                           class="w-full bg-blue-50/30 border-blue-100 rounded-xl px-4 py-3 text-sm font-mono text-blue-900 focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-400/10 transition-all"
                           placeholder="prod_...">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1 text-blue-600">Price ID</label>
                    <input type="text" name="stripe_price_id" value="{{ old('stripe_price_id') }}" 
                           class="w-full bg-blue-50/30 border-blue-100 rounded-xl px-4 py-3 text-sm font-mono text-blue-900 focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-400/10 transition-all"
                           placeholder="price_...">
                </div>
            </div>
        </div>

        {{-- Action Bar --}}
        <div class="flex items-center justify-between bg-[#0F172A] p-3 rounded-[24px] shadow-2xl">
            <div class="px-6">
                <label class="inline-flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" name="is_active" value="1" checked 
                           class="w-5 h-5 rounded border-white/20 bg-white/10 text-[#38B2AC] focus:ring-[#38B2AC] focus:ring-offset-[#0F172A]">
                    <span class="text-xs font-black text-white/70 group-hover:text-white uppercase tracking-widest transition-colors">
                        Publicar Inmediatamente
                    </span>
                </label>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.planes.index') }}" 
                   class="px-6 py-3 text-xs font-black text-white/50 hover:text-white uppercase tracking-widest transition-colors">
                    Cancelar
                </a>
                <button type="submit" 
                        class="bg-[#38B2AC] px-8 py-3.5 rounded-xl text-slate-900 font-black text-xs uppercase tracking-widest hover:bg-[#2d918c] active:scale-95 transition-all">
                    Crear Plan
                </button>
            </div>
        </div>
    </form>
</div>

@endsection