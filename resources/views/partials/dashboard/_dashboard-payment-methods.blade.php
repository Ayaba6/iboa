{{--
    Dashboard — Encaissements répartis par mode de paiement (donut).
--}}
{{-- ══════════════════════════════════════════════════════════════
     MODES DE PAIEMENT
══════════════════════════════════════════════════════════════ --}}
@if($paymentsByMethod->isNotEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-5 fade-up" style="animation-delay:.4s">
    @php
        $totalP = $paymentsByMethod->sum('total');
        $mpC    = ['#4f46e5','#10b981','#f59e0b','#6366f1','#ef4444','#3b82f6','#ec4899'];
    @endphp
    <div class="flex items-center justify-between mb-5">
        <div>
            <h3 class="text-base font-bold text-gray-900">Encaissements par mode de paiement</h3>
            <p class="text-xs text-gray-400 mt-0.5">Ce mois · <span class="font-semibold text-gray-600">{{ number_format($totalP, 0, ',', ' ') }} FCFA</span> total</p>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-{{ min($paymentsByMethod->count(), 5) }} gap-3">
        @foreach($paymentsByMethod as $i => $pm)
        @php $pct = $totalP > 0 ? round(($pm->total/$totalP)*100) : 0; $c = $mpC[$i % count($mpC)]; @endphp
        <div class="rounded-[4px] border border-gray-100 p-4 hover:border-gray-200 transition-colors">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $c }}"></div>
                <p class="text-xs text-gray-600 font-semibold truncate">{{ optional($pm->paymentMethod)->name ?? 'Autre' }}</p>
            </div>
            <p class="text-lg font-black text-gray-900 tabular-nums">{{ number_format($pm->total, 0, ',', ' ') }} F</p>
            <div class="mt-2.5 flex items-center justify-between gap-2">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full progress-fill rounded-full" style="width:{{ $pct }}%;background:{{ $c }};animation-delay:.2s"></div>
                </div>
                <span class="text-xs font-bold text-gray-400 flex-shrink-0">{{ $pct }}%</span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

</div><!-- /space-y-5 -->
