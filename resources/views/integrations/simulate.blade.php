@extends('layouts.erp')
@section('title', 'Simulateur — ' . $integration->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.show', $integration) }}" class="hover:text-gray-700">{{ $integration->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Simulateur</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-6"
     x-data="{
        outcome: 'success',
        loading: false,
        result: null,
        amount: '',
        phone: '',
        orderId: '',
        async submit(event) {
            if (this.loading) return;
            this.loading = true;
            this.result = null;
            const form = event.target;
            const data = new FormData(form);
            try {
                const resp = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: data
                });
                const json = await resp.json();
                this.result = { ok: resp.ok, data: json };
            } catch(e) {
                this.result = { ok: false, data: { message: 'Erreur réseau : ' + e.message } };
            } finally {
                this.loading = false;
            }
        }
     }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">{{ $integration->typeIcon() }}</span>
                <h1 class="text-2xl font-bold text-gray-900">Simulateur de paiement</h1>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-100 text-amber-700">SANDBOX</span>
            </div>
            <p class="text-sm text-gray-500">{{ $integration->name }} — {{ $integration->provider }}</p>
        </div>
        <a href="{{ route('integrations.show', $integration) }}"
           class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-2 rounded-lg font-medium flex items-center gap-1.5 self-start">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Retour
        </a>
    </div>

    {{-- Info banner --}}
    <div class="bg-violet-50 border border-violet-200 rounded-[4px] p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-violet-800">
            <p class="font-semibold mb-1">Mode sandbox actif</p>
            <p>Les transactions simulées n'impliquent aucun mouvement financier réel. Elles permettent de tester le circuit complet : webhook → confirmation → mise à jour facture.</p>
        </div>
    </div>

    {{-- Flash messages (non-AJAX) --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- AJAX Result --}}
    <div x-show="result !== null" x-cloak>
        <div x-show="result && result.ok" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-4 text-sm">
            <div class="flex items-center gap-2 font-semibold mb-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Transaction simulée avec succès
            </div>
            <p x-text="result && result.data && result.data.message"></p>
            <a x-show="result && result.data && result.data.transaction_id"
               :href="'{{ route('integrations.index') }}'"
               class="mt-2 inline-block text-emerald-700 underline">Voir les transactions →</a>
        </div>
        <div x-show="result && !result.ok" class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-4 text-sm">
            <div class="font-semibold mb-1">Erreur de simulation</div>
            <p x-text="result && result.data && (result.data.message || JSON.stringify(result.data))"></p>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('integrations.simulate.send', $integration) }}"
          method="POST"
          @submit.prevent="submit($event)"
          class="bg-white rounded-[4px] border border-gray-300 divide-y divide-gray-100">

        @csrf

        {{-- Section: Montant --}}
        <div class="p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Paramètres du paiement</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Montant <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number" name="amount" x-model="amount"
                               min="100" max="5000000" step="1" required
                               placeholder="Ex : 25000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-14 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-400">XOF</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Min : 100 XOF — Max : 5 000 000 XOF</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Numéro de téléphone <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="phone" x-model="phone"
                           required placeholder="+22670000000"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1">Format international (+226…)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Référence commande</label>
                    <input type="text" name="order_id" x-model="orderId"
                           placeholder="Facultatif — Ex : FAC-2024-001"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                </div>
            </div>
        </div>

        {{-- Section: Résultat attendu --}}
        <div class="p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Résultat attendu</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                {{-- Success --}}
                <label :class="outcome === 'success' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-300' : 'border-gray-200 hover:border-gray-300'"
                       class="relative flex flex-col items-center gap-2 p-4 rounded-[4px] border-2 cursor-pointer transition-all">
                    <input type="radio" name="outcome" value="success" x-model="outcome" class="sr-only">
                    <span class="text-2xl">✅</span>
                    <span class="text-sm font-semibold text-gray-800">Succès</span>
                    <span class="text-xs text-gray-500 text-center">Paiement confirmé, webhook déclenché</span>
                    <span x-show="outcome === 'success'"
                          class="absolute top-2 right-2 w-4 h-4 bg-emerald-500 rounded-full flex items-center justify-center">
                        <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </span>
                </label>

                {{-- Failure --}}
                <label :class="outcome === 'failure' ? 'border-red-400 bg-red-50 ring-2 ring-red-300' : 'border-gray-200 hover:border-gray-300'"
                       class="relative flex flex-col items-center gap-2 p-4 rounded-[4px] border-2 cursor-pointer transition-all">
                    <input type="radio" name="outcome" value="failure" x-model="outcome" class="sr-only">
                    <span class="text-2xl">❌</span>
                    <span class="text-sm font-semibold text-gray-800">Échec</span>
                    <span class="text-xs text-gray-500 text-center">Paiement refusé, solde insuffisant</span>
                    <span x-show="outcome === 'failure'"
                          class="absolute top-2 right-2 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center">
                        <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </span>
                </label>

                {{-- Pending --}}
                <label :class="outcome === 'pending' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-300' : 'border-gray-200 hover:border-gray-300'"
                       class="relative flex flex-col items-center gap-2 p-4 rounded-[4px] border-2 cursor-pointer transition-all">
                    <input type="radio" name="outcome" value="pending" x-model="outcome" class="sr-only">
                    <span class="text-2xl">⏳</span>
                    <span class="text-sm font-semibold text-gray-800">En attente</span>
                    <span class="text-xs text-gray-500 text-center">Transaction initiée, pas encore confirmée</span>
                    <span x-show="outcome === 'pending'"
                          class="absolute top-2 right-2 w-4 h-4 bg-amber-500 rounded-full flex items-center justify-center">
                        <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </span>
                </label>
            </div>

            {{-- Outcome descriptions --}}
            <div x-show="outcome === 'success'" class="mt-3 text-xs text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2 border border-emerald-200">
                <strong>Flux déclenché :</strong> Transaction créée → webhook simulé → job <code>ProcessExternalPayment</code> dispatché → facture mise à jour si référence trouvée.
            </div>
            <div x-show="outcome === 'failure'" class="mt-3 text-xs text-red-700 bg-red-50 rounded-lg px-3 py-2 border border-red-200">
                <strong>Flux déclenché :</strong> Transaction créée avec statut <code>failed</code> → aucune mise à jour de facture → log API enregistré.
            </div>
            <div x-show="outcome === 'pending'" class="mt-3 text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 border border-amber-200">
                <strong>Flux déclenché :</strong> Transaction créée avec statut <code>pending</code> → en attente d'un second webhook ou d'une confirmation manuelle.
            </div>
        </div>

        {{-- Submit --}}
        <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-between gap-3">
            <p class="text-xs text-gray-400">Aucune transaction financière réelle ne sera effectuée.</p>
            <button type="submit"
                    :disabled="loading || !amount || !phone"
                    class="bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="loading ? 'Simulation en cours…' : 'Lancer la simulation'"></span>
            </button>
        </div>
    </form>

    {{-- Recent simulated transactions --}}
    @if(isset($recentTransactions) && $recentTransactions->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Transactions simulées récentes</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($recentTransactions as $tx)
            <div class="px-6 py-3 flex items-center justify-between text-sm">
                <div class="flex items-center gap-3">
                    <span class="font-mono text-xs text-gray-500">{{ $tx->internal_reference }}</span>
                    <span class="text-gray-700">{{ number_format($tx->amount, 0, ',', ' ') }} XOF</span>
                    <span class="text-xs text-gray-400">{{ $tx->phone_number ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    @php
                        $statusColors = ['confirmed' => 'emerald', 'failed' => 'red', 'pending' => 'amber', 'refunded' => 'violet'];
                        $sc = $statusColors[$tx->status] ?? 'gray';
                    @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                        {{ ucfirst($tx->status) }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $tx->created_at->diffForHumans() }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-6 py-3 border-t border-gray-100 text-right">
            <a href="{{ route('integrations.transactions', ['provider' => $integration->provider]) }}"
               class="text-xs text-violet-600 hover:text-violet-800 font-medium">
                Voir toutes les transactions →
            </a>
        </div>
    </div>
    @endif

</div>
@endsection
