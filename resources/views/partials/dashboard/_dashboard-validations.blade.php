{{-- [CDC §Workflow] Bandeau « validations en attente » : même source de vérité
     que la cloche et « Mes validations » (PendingValidationsService) —
     couvre tous les modules, y compris maintenance et modifications d'OF. --}}
@php
    $pendingValidations = app(\App\Services\PendingValidationsService::class)
        ->for(auth()->user())
        ->count();
@endphp

@if($pendingValidations > 0)
<a href="{{ route('validations.index') }}"
   class="flex items-center justify-between gap-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl px-5 py-3.5 hover:from-amber-100 hover:to-orange-100 transition-colors group">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-amber-800">
                {{ $pendingValidations }} document{{ $pendingValidations > 1 ? 's' : '' }} en attente de votre validation
            </p>
            <p class="text-xs text-amber-600">Devis, commandes, OF, rebuts, demandes d'achat… selon vos habilitations</p>
        </div>
    </div>
    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 group-hover:translate-x-0.5 transition-transform">
        Traiter
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
    </span>
</a>
@endif
