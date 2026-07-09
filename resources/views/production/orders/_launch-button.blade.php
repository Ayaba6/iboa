{{-- [CDC §3] Bouton « Lancer l'OF ». En cas de rupture matière, le lancement
     normal est bloqué ; seuls les valideurs (production.validate) voient une
     dérogation explicite « Lancer malgré rupture ». --}}
@php($shortages = $materialShortages ?? [])
@if(empty($shortages))
    <form method="POST" action="{{ route('production.orders.launch', $order) }}">@csrf
        <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Lancer l'OF</button>
    </form>
@else
    <div class="flex flex-col items-end gap-1">
        <button type="button" disabled title="Matière insuffisante — réapprovisionnez le stock"
                class="bg-gray-200 text-gray-400 text-sm font-medium px-3 py-1.5 rounded-[4px] cursor-not-allowed">
            Lancer l'OF
        </button>
        @can('production.validate')
        <form method="POST" action="{{ route('production.orders.launch', $order) }}"
              onsubmit="return confirm('Rupture matière confirmée. Lancer l\'OF en dérogation malgré le stock insuffisant ?');">
            @csrf
            <input type="hidden" name="bypass_material" value="1">
            <button class="text-[11px] font-semibold text-red-700 underline hover:text-red-800">
                Lancer malgré rupture (dérogation)
            </button>
        </form>
        @endcan
    </div>
@endif
