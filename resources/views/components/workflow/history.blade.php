{{--
    Composant : historique de validation d'un document commercial.

    Props :
      $document : le modèle (a le trait HasCommercialWorkflow)
--}}
@props(['document'])

@php
    $history = $document->workflowHistory()->with('user')->get();
@endphp

@if($history->isNotEmpty())
<div class="mt-6">
    <h3 class="text-[13px] font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Historique de validation
    </h3>
    <ol class="relative border-l border-gray-200 ml-3 space-y-4">
        @foreach($history as $entry)
            @php
                $colorMap = [
                    'creation'       => ['dot'=>'bg-gray-400',   'icon'=>'⚪'],
                    'soumission'     => ['dot'=>'bg-yellow-400',  'icon'=>'📤'],
                    'validation'     => ['dot'=>'bg-green-500',   'icon'=>'✅'],
                    'refus'          => ['dot'=>'bg-orange-500',  'icon'=>'🔄'],
                    'annulation'     => ['dot'=>'bg-red-500',     'icon'=>'❌'],
                    'transformation' => ['dot'=>'bg-blue-500',    'icon'=>'🔁'],
                ];
                $style = $colorMap[$entry->action] ?? ['dot'=>'bg-gray-300', 'icon'=>'•'];
            @endphp
            <li class="ml-4 relative">
                {{-- Dot --}}
                <div class="absolute -left-[1.4rem] top-1 size-3 rounded-full ring-2 ring-white {{ $style['dot'] }}"></div>

                <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[13px] font-medium text-gray-900">
                                {{ $style['icon'] }} {{ $entry->action_label }}
                            </span>
                            @if($entry->ancien_statut)
                                <span class="ml-2 text-[12px] text-gray-400">
                                    <x-workflow.status-badge :status="$entry->ancien_statut" size="sm" />
                                    <svg class="inline size-3 mx-0.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                    <x-workflow.status-badge :status="$entry->nouveau_statut" size="sm" />
                                </span>
                            @endif
                        </div>
                        <time class="text-[12px] text-gray-400 whitespace-nowrap">
                            {{ $entry->created_at->format('d/m/Y H:i') }}
                        </time>
                    </div>

                    <div class="mt-1 text-[13px] text-gray-600">
                        Par <strong>{{ $entry->user?->name ?? 'Inconnu' }}</strong>
                        @if($entry->user_role)
                            <span class="text-gray-400">({{ $entry->user_role }})</span>
                        @endif
                    </div>

                    @if($entry->motif)
                        <div class="mt-2 rounded bg-gray-50 border border-gray-100 px-3 py-2 text-[13px] text-gray-700 italic">
                            "{{ $entry->motif }}"
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
@endif
