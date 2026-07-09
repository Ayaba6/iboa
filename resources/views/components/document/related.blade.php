@props([
    'links' => [],   // tableau de ['label' => 'PO BC-2026-001', 'href' => '/...', 'badge' => 'Reçu', 'badgeColor' => 'emerald']
    'title' => 'Documents liés',
])
@php
    $links = collect($links)->filter(fn($l) => !empty($l['label']));
@endphp

@if($links->isNotEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">🔗 {{ $title }}</h2>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($links as $link)
        <a href="{{ $link['href'] ?? '#' }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-lg">{{ $link['icon'] ?? '📄' }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $link['label'] }}</p>
                    @if(!empty($link['subtitle']))
                    <p class="text-xs text-gray-500 truncate">{{ $link['subtitle'] }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if(!empty($link['badge']))
                    @php $bc = $link['badgeColor'] ?? 'gray'; @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $bc }}-100 text-{{ $bc }}-700">{{ $link['badge'] }}</span>
                @endif
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif
