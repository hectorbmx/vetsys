@extends('layouts.client')

@section('title', 'Notificaciones')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-black theme-text-heading tracking-tighter">Notificaciones</h1>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Avisos internos de telemedicina y eventos del sistema.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notification)
                <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $notification->read_at ? '' : 'theme-bg-primary-soft' }}">
                    <a href="{{ route('client.notifications.open', $notification) }}" class="flex-1 group">
                        <div class="flex items-start gap-3">
                            @if(!$notification->read_at)
                                <span class="mt-1.5 h-2.5 w-2.5 rounded-full theme-bg-primary flex-shrink-0"></span>
                            @else
                                <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-slate-200 flex-shrink-0"></span>
                            @endif
                            <div>
                                <p class="text-sm font-black theme-text-heading theme-group-hover-text-primary transition-colors">{{ $notification->title }}</p>
                                <p class="text-xs font-semibold text-slate-500 mt-1">{{ $notification->body }}</p>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </a>

                    <div class="flex items-center gap-2">
                        @if(!$notification->read_at)
                            <form action="{{ route('client.notifications.mark-read', $notification) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest">
                                    Marcar leida
                                </button>
                            </form>
                        @else
                            <span class="px-4 py-2 rounded-xl bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest">Leida</span>
                        @endif

                        <form action="{{ route('client.notifications.destroy', $notification) }}" method="POST" onsubmit="return confirm('¿Eliminar notificación?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2 rounded-xl bg-slate-100 hover:bg-red-50 text-slate-400 hover:text-red-600 transition-colors" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-black theme-text-heading">Sin notificaciones</p>
                    <p class="text-xs font-semibold text-slate-400 mt-2">Cuando otro tenant comparta un expediente contigo, aparecera aqui.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{ $notifications->links() }}
</div>
@endsection
