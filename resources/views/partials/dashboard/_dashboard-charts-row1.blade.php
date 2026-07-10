{{--
    Dashboard — Charts row 1 :
    Évolution du CA (avec sélecteur 7j/30j/6mois) + Trésorerie par compte (donut).
--}}
{{-- ══════════════════════════════════════════════════════════════
     CHARTS ROW 1 — CA avec sélecteur de période + Trésorerie
══════════════════════════════════════════════════════════════ --}}
@php
$caChartConfig = [
    'ca7Days'   => $ca7Days,
    'ca7Labels' => $ca7Labels,
    'ca30Days'  => $ca30Days,
    'ca30Labels'=> $ca30Labels,
    'caParMois' => $caParMois,
];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 fade-up" style="animation-delay:.22s">

    {{-- CA — avec toggle 7j / 30j / 6m --}}
    <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5"
         x-data="caChartFromConfig({{ \Illuminate\Support\Js::from($caChartConfig) }})">

        <div class="flex items-start justify-between mb-5 gap-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Chiffre d'affaires</h2>
                <p class="text-xs text-gray-400 mt-0.5">Évolution des ventes · TTC</p>
            </div>
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 flex-shrink-0">
                <button @click="setPeriod('7j')"
                        :class="period==='7j' ? 'active' : ''"
                        class="period-btn px-3 py-1.5 rounded-lg text-xs font-bold text-gray-500">7 jours</button>
                <button @click="setPeriod('30j')"
                        :class="period==='30j' ? 'active' : ''"
                        class="period-btn px-3 py-1.5 rounded-lg text-xs font-bold text-gray-500">30 jours</button>
                <button @click="setPeriod('6m')"
                        :class="period==='6m' ? 'active' : ''"
                        class="period-btn px-3 py-1.5 rounded-lg text-xs font-bold text-gray-500">6 mois</button>
            </div>
        </div>

        {{-- Summary mini-stats --}}
        <div class="grid grid-cols-3 sm:grid-cols-3 gap-2 sm:gap-3 mb-5">
            <div class="rounded-xl bg-[#eef5f0] px-3 py-2.5">
                <p class="text-xs font-semibold text-emerald-500 uppercase tracking-wide">Total période</p>
                <p class="text-sm font-black text-emerald-800 tabular-nums mt-1" x-text="sumFormatted()">—</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pic</p>
                <p class="text-sm font-black text-gray-700 tabular-nums mt-0.5" x-text="maxFormatted()">—</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Moyenne</p>
                <p class="text-sm font-black text-gray-700 tabular-nums mt-0.5" x-text="avgFormatted()">—</p>
            </div>
        </div>

        <div id="chart-ca"></div>
    </div>

    {{-- Répartition trésorerie --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="mb-4">
            <h2 class="text-base font-bold text-gray-900">Trésorerie</h2>
            <p class="text-xs text-gray-400 mt-0.5">Soldes par compte</p>
        </div>

        @if($cashAccounts->isEmpty())
        <div class="flex flex-col items-center justify-center h-48 gap-3 text-gray-300">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <p class="text-sm text-gray-400">Aucun compte configuré</p>
        </div>
        @else
        <div id="chart-caisse" class="mb-3"></div>
        {{-- Liste comptes --}}
        <div class="space-y-2 pt-2 border-t border-gray-50">
            @foreach($cashAccounts as $ca)
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500 truncate max-w-[60%]">{{ $ca->name }}</span>
                <span class="text-xs font-bold {{ $ca->current_balance >= 0 ? 'text-gray-800' : 'text-rose-600' }} tabular-nums">
                    {{ number_format($ca->current_balance, 0, ',', ' ') }} F
                </span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

