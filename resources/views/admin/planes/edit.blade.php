@extends('layouts.admin')

@section('title', 'Editar Plan')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="border-b-2 border-slate-100 pb-6">
        <a href="{{ route('admin.planes.index') }}" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Planes</a>
        <h1 class="text-4xl font-black text-[#0F172A] tracking-tighter mt-2">Editar {{ $plan->name }}</h1>
        <p class="text-slate-500 font-medium mt-1">Configura capacidades sin afectar suscripciones ni referencias de Stripe.</p>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.planes.update', $plan) }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="bg-white border border-slate-200 rounded-[24px] p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="text-[11px] font-black uppercase">Nombre</label><input name="name" value="{{ old('name', $plan->name) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Slug</label><input name="slug" value="{{ old('slug', $plan->slug) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div class="md:col-span-2"><label class="text-[11px] font-black uppercase">Descripcion</label><textarea name="description" rows="3" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3">{{ old('description', $plan->description) }}</textarea></div>
            <div><label class="text-[11px] font-black uppercase">Precio</label><input type="number" step="0.01" name="price" value="{{ old('price', $plan->price) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Divisa</label><input name="currency" value="{{ old('currency', $plan->currency) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Ciclo</label><select name="billing_period" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3">@foreach(['monthly','yearly','one_time','free'] as $period)<option value="{{ $period }}" @selected(old('billing_period', $plan->billing_period) === $period)>{{ $period }}</option>@endforeach</select></div>
            <div><label class="text-[11px] font-black uppercase">Trial dias</label><input type="number" name="trial_days" value="{{ old('trial_days', $plan->trial_days) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Usuarios</label><input type="number" name="max_users" value="{{ old('max_users', $plan->max_users) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Clientes</label><input type="number" name="max_clients" value="{{ old('max_clients', $plan->max_clients) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
        </div>

        <div class="bg-white border border-slate-200 rounded-[24px] p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <label class="flex items-center gap-3"><input type="checkbox" name="web_access" value="1" @checked(old('web_access', $plan->web_access))><span class="font-bold">Permitir acceso web</span></label>
            <label class="flex items-center gap-3"><input type="checkbox" name="mobile_access" value="1" @checked(old('mobile_access', $plan->mobile_access))><span class="font-bold">Permitir app movil</span></label>
            <div><label class="text-[11px] font-black uppercase">Navegadores por usuario</label><input type="number" min="0" name="max_web_sessions_per_user" value="{{ old('max_web_sessions_per_user', $plan->max_web_sessions_per_user) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Moviles por usuario</label><input type="number" min="0" name="max_mobile_sessions_per_user" value="{{ old('max_mobile_sessions_per_user', $plan->max_mobile_sessions_per_user) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <label class="md:col-span-2 flex items-center gap-3"><input type="checkbox" name="allow_cross_platform_sessions" value="1" @checked(old('allow_cross_platform_sessions', $plan->allow_cross_platform_sessions))><span class="font-bold">Permitir web y movil simultaneamente</span></label>
        </div>

        <div class="bg-white border border-slate-200 rounded-[24px] p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="text-[11px] font-black uppercase">Stripe Product ID</label><input name="stripe_product_id" value="{{ old('stripe_product_id', $plan->stripe_product_id) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <div><label class="text-[11px] font-black uppercase">Stripe Price ID</label><input name="stripe_price_id" value="{{ old('stripe_price_id', $plan->stripe_price_id) }}" class="mt-2 w-full rounded-xl border-slate-200 px-4 py-3"></div>
            <label class="md:col-span-2 flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))><span class="font-bold">Plan activo</span></label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.planes.index') }}" class="px-6 py-3 text-xs font-black uppercase">Cancelar</a>
            <button class="bg-[#38B2AC] px-8 py-3 rounded-xl text-xs font-black uppercase">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
