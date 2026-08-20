{{--
  [CONCURRENCE-MULTI-USER] Bandeau de verrou d'édition.

  Usage dans un formulaire edit :
    <x-edit-lock-banner :model="$invoice" model-type="Invoice" />

  Props :
    $model     — instance Eloquent (pour l'ID)
    $modelType — nom court du modèle ('Invoice', 'PurchaseOrder', etc.)
    $editLock  — EditLock|null (injecté par le contrôleur via lockDataFor())
--}}
@props([
    'model',
    'modelType',
    'editLock' => null,
])

@php
    $myLock = $editLock && $editLock->isOwnedByCurrentSession();
    $otherLock = $editLock && !$editLock->isOwnedByCurrentSession();
    $refreshUrl = route('edit-lock.refresh');
    $releaseUrl = route('edit-lock.release');
    $ttlMs = (\App\Services\EditLockService::TTL_MINUTES - 2) * 60 * 1000; // ping 2 min avant expiration
@endphp

@if($otherLock)
{{-- ─── Verrou détenu par un autre utilisateur ─────────────────────────────── --}}
<div class="rounded-lg border border-orange-200 bg-orange-50 p-4 mb-4 flex items-start gap-3"
     role="alert">
    <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
    </svg>
    <div class="flex-1 text-[13px]">
        <p class="font-semibold text-orange-800">
            Document en cours de modification par <strong>{{ $editLock->user->name }}</strong>
        </p>
        <p class="text-orange-600 mt-0.5">
            Ouvert {{ $editLock->locked_at->diffForHumans() }} · Expire {{ $editLock->remainingForHumans() }}
        </p>
        <p class="text-orange-600 mt-1 text-[12px]">
            Vos modifications risquent d'être écrasées. Attendez que {{ $editLock->user->name }} sauvegarde.
        </p>
    </div>
</div>

@elseif($myLock)
{{-- ─── Verrou détenu par moi-même ─────────────────────────────────────────── --}}
<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 mb-4 flex items-center gap-2 text-[13px] text-green-700"
     id="edit-lock-banner">
    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
    <span>Vous éditez ce document — les autres utilisateurs voient qu'il est verrouillé.</span>
    <span class="ml-auto text-[12px] text-green-500" id="lock-expires-in">Verrou valide {{ $editLock->remainingForHumans() }}</span>
</div>

{{-- Ping JS toutes les (TTL-2) minutes pour renouveler le verrou --}}
<script>
(function() {
    const refreshUrl  = @json($refreshUrl);
    const releaseUrl  = @json($releaseUrl);
    const modelType   = @json($modelType);
    const modelId     = @json($model->getKey());
    const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const ttlMs       = @json($ttlMs);
    const expiresEl   = document.getElementById('lock-expires-in');

    // Ping de renouvellement
    const pingInterval = setInterval(async () => {
        try {
            const res = await fetch(refreshUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ model_type: modelType, model_id: modelId }),
            });
            const data = await res.json();
            if (!data.ok) {
                clearInterval(pingInterval);
                if (expiresEl) expiresEl.textContent = '⚠ Verrou expiré — rechargez la page';
            } else {
                if (expiresEl) expiresEl.textContent = 'Verrou renouvelé';
            }
        } catch (e) { /* réseau coupé : silencieux */ }
    }, ttlMs);

    // Libération automatique quand on quitte la page
    window.addEventListener('beforeunload', () => {
        navigator.sendBeacon(releaseUrl, JSON.stringify({
            model_type: modelType,
            model_id:   modelId,
            _token:     csrfToken,
        }));
    });
})();
</script>
@endif
