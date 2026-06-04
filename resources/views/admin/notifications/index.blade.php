@extends('layouts.admin')

@section('title', 'Notificaciones')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-black text-[#0F172A] tracking-tighter">Notificaciones SaaS</h1>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Eventos de pagos, tenants y operacion del sistema.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notification)
                <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $notification->read_at ? '' : 'bg-[#38B2AC]/5' }}">
                    <a href="{{ route('admin.notifications.open', $notification) }}" class="flex-1 group">
                        <div class="flex items-start gap-3">
                            @if(!$notification->read_at)
                                <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-[#38B2AC] flex-shrink-0"></span>
                            @else
                                <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-slate-200 flex-shrink-0"></span>
                            @endif
                            <div>
                                <p class="text-sm font-black text-[#0F172A] group-hover:text-[#38B2AC] transition-colors">{{ $notification->title }}</p>
                                <p class="text-xs font-semibold text-slate-500 mt-1">{{ $notification->body }}</p>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </a>

                    @if(!$notification->read_at)
                        <form action="{{ route('admin.notifications.mark-read', $notification) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest">
                                Marcar leida
                            </button>
                        </form>
                    @else
                        <span class="px-4 py-2 rounded-xl bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest">Leida</span>
                    @endif
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-black text-[#0F172A]">Sin notificaciones</p>
                    <p class="text-xs font-semibold text-slate-400 mt-2">Cuando un tenant pague con Stripe, aparecera aqui.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{ $notifications->links() }}
</div>
@endsection
