@extends('layouts.admin')

@section('title', 'Nuevo Cliente')

@section('content')

<div class="max-w-4xl mx-auto space-y-8">

    {{-- Breadcrumb sutil --}}
    <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
        <a href="{{ route('admin.tenants.index') }}" class="hover:text-[#38B2AC] transition-colors">Clientes</a>
        <span>/</span>
        <span class="text-[#0F172A]">Registro</span>
    </nav>

    {{-- Header --}}
    <div class="border-l-4 border-[#38B2AC] pl-6">
        <h1 class="text-4xl font-black text-[#0F172A] tracking-tighter">
            Nuevo Cliente
        </h1>
        <p class="text-slate-500 font-medium mt-1">
            Configuración de nueva unidad de negocio en el ecosistema VetSys.
        </p>
    </div>

    <form action="{{ route('admin.tenants.store') }}"
          method="POST"
          class="space-y-8">

        @csrf

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

        <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
            
            {{-- Header del Card con contraste --}}
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <h2 class="font-black text-[11px] uppercase tracking-widest text-slate-500">
                    Información General
                </h2>
                <span class="text-[#38B2AC] text-xs font-bold">Paso 1 de 1</span>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                {{-- Input Group --}}
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                        Nombre Comercial
                    </label>
                    <input type="text"
                           name="name"
                           value="{{ old('name') }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all placeholder:text-slate-400"
                           placeholder="Ej. Clínica Veterinaria San José">
                    @error('name')
                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mt-1 ml-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug con estilo "readonly-look" pero editable --}}
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                        Slug (URL del sistema)
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">vetsys.io/</span>
                        <input type="text"
                               name="slug"
                               value="{{ old('slug') }}"
                               class="w-full bg-slate-50 border-slate-200 rounded-xl pl-[72px] pr-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                               placeholder="clinica-sanjose">
                    </div>
                    @error('slug')
                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mt-1 ml-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                        Razón Social
                    </label>
                    <input type="text"
                           name="business_name"
                           value="{{ old('business_name') }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="Nombre legal completo">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                        Correo Corporativo
                    </label>
                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="admin@empresa.com">
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                        Teléfono de Contacto
                    </label>
                    <input type="text"
                           name="phone"
                           value="{{ old('phone') }}"
                           class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all"
                           placeholder="+52 33 0000 0000">
                </div>

                {{-- Selects mejorados --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                            Plan SaaS
                        </label>
                        <select name="plan_id"
                                class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all cursor-pointer">
                            <option value="">Seleccionar</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        @error('plan_id')
                            <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mt-1 ml-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-[#0F172A] uppercase tracking-wider ml-1">
                            Estado Inicial
                        </label>
                        <select name="status"
                                class="w-full bg-slate-50 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#38B2AC] focus:ring-4 focus:ring-[#38B2AC]/10 transition-all cursor-pointer">
                            <option value="inactive" @selected(old('status', 'inactive') === 'inactive')>Inactivo</option>
                            <option value="active" @selected(old('status', 'inactive') === 'active')>Activo</option>
                            <option value="suspended" @selected(old('status', 'inactive') === 'suspended')>Suspendido</option>
                        </select>
                        @error('status')
                            <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mt-1 ml-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>
        </div>

        {{-- Footer de acciones con contraste invertido --}}
        <div class="flex items-center justify-between bg-slate-900 p-2 rounded-[20px] shadow-xl">
            <a href="{{ route('admin.tenants.index') }}"
               class="px-6 py-3 text-xs font-black text-white/50 hover:text-white uppercase tracking-widest transition-colors">
                Descartar
            </a>

            <button type="submit"
                    class="bg-[#38B2AC] px-8 py-3.5 rounded-xl text-slate-900 font-black text-xs uppercase tracking-widest hover:bg-[#2d918c] active:scale-95 transition-all shadow-lg shadow-[#38B2AC]/20">
                Finalizar Registro
            </button>
        </div>

    </form>

</div>

@endsection
