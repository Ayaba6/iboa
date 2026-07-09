@extends('layouts.erp')
@section('title', 'Notifications')

@section('breadcrumb')
    <span class="text-gray-500">Accueil</span>
    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 font-semibold">Notifications</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Notifications</h1>
            @if($unreadCount > 0)
            <p class="text-sm text-gray-500 mt-0.5">{{ $unreadCount }} non lue(s)</p>
            @endif
        </div>
        @if($unreadCount > 0)
        <button id="markAllBtn"
                class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50 rounded-[4px] transition-colors border border-emerald-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Tout marquer comme lu
        </button>
        @endif
    </div>

    {{-- List --}}
    <div id="notifList" class="space-y-2">
        @forelse($notifications as $notif)
        @php
            $data  = $notif->data;
            $read  = !is_null($notif->read_at);
            $color = $data['color'] ?? 'indigo';
            $colors = [
                'red'    => 'bg-red-100 text-red-600',
                'orange' => 'bg-orange-100 text-orange-600',
                'green'  => 'bg-green-100 text-green-600',
                'blue'   => 'bg-blue-100 text-blue-600',
                'indigo' => 'bg-emerald-100 text-emerald-700',
            ];
            $dot = $colors[$color] ?? $colors['indigo'];
        @endphp
        <div class="group flex items-start gap-4 p-4 rounded-[4px] border {{ $read ? 'bg-white border-gray-100' : 'bg-[#eef5f0]/50 border-emerald-100' }} hover:shadow-sm transition-shadow"
             data-id="{{ $notif->id }}">

            {{-- Dot --}}
            <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center {{ $dot }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900">{{ $data['title'] ?? '' }}</p>
                    @if(!$read)
                    <span class="w-2 h-2 rounded-full bg-emerald-600 flex-shrink-0 mt-1.5"></span>
                    @endif
                </div>
                <p class="text-sm text-gray-600 mt-0.5 leading-relaxed">{{ $data['message'] ?? '' }}</p>
                <div class="flex items-center gap-4 mt-2">
                    <span class="text-xs text-gray-400">{{ $notif->created_at->diffForHumans() }}</span>
                    @if(!empty($data['url']))
                    <a href="{{ route('notifications.read', $notif->id) }}"
                       class="text-xs font-medium text-emerald-700 hover:text-emerald-900 transition-colors">
                        Voir →
                    </a>
                    @endif
                </div>
            </div>

            {{-- Delete --}}
            <button class="delete-btn opacity-0 group-hover:opacity-100 w-7 h-7 flex items-center justify-center rounded-[4px] text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all flex-shrink-0"
                    data-id="{{ $notif->id }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @empty
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-sm font-medium">Aucune notification</p>
        </div>
        @endforelse
    </div>

    {{ $notifications->links() }}
</div>

@push('scripts')
<script>
// Exécution directe — Turbo re-évalue ce script à chaque navigation,
// les éléments DOM sont frais donc pas de stacking de listeners.
(function () {
    const csrf = () => document.querySelector('meta[name=csrf-token]').content;

    // Delete single
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const id  = btn.dataset.id;
            const row = btn.closest('[data-id]');
            try {
                await fetch(`{{ url('/notifications') }}/${id}`, { method:'DELETE', headers:{ 'X-CSRF-TOKEN': csrf() } });
                row?.remove();
            } catch (_) {}
        });
    });

    // Mark all read — utilise Turbo.visit pour éviter un rechargement complet
    document.getElementById('markAllBtn')?.addEventListener('click', async () => {
        try {
            await fetch('{{ url('/notifications/mark-all-read') }}', { method:'POST', headers:{ 'X-CSRF-TOKEN': csrf() } });
            window.Turbo?.visit(location.href, { action: 'replace' }) ?? location.reload();
        } catch (_) {}
    });
}());
</script>
@endpush
@endsection
