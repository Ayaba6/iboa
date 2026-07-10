@extends('layouts.erp')
@section('title', 'Relances & Impayés')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Relances</span>
@endsection

@section('content')
<div class="space-y-3" x-data="relanceManager()">

    {{-- Header + Stats --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Relances & Impayés</h1>
            <p class="text-sm text-gray-500 mt-0.5">Factures en attente de règlement</p>
        </div>
    </div>

    {{-- KPIs : tous les états d'impayés --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 sm:col-span-2">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total impayé</p>
            <p class="text-[16px] font-bold text-red-600 whitespace-nowrap">{{ number_format($stats['total_montant'], 0, ',', ' ') }}<span class="text-sm font-normal text-gray-400 ml-1">FCFA</span></p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['total_factures'] }} facture(s) · {{ $stats['total_clients'] }} client(s)</p>
        </div>
        <a href="{{ route('relances.index', array_merge(request()->query(), ['urgency' => 'critique'])) }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'critique' ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-200' }} p-4 hover:border-red-300 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Critique</p>
            <p class="text-[17px] font-bold text-red-600">{{ $stats['critique'] }}</p>
            <p class="text-xs text-gray-400">≥ 60 j</p>
        </a>
        <a href="{{ route('relances.index', array_merge(request()->query(), ['urgency' => 'urgent'])) }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'urgent' ? 'border-orange-400 ring-2 ring-orange-200' : 'border-gray-200' }} p-4 hover:border-orange-300 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Urgent</p>
            <p class="text-[17px] font-bold text-orange-600">{{ $stats['urgent'] }}</p>
            <p class="text-xs text-gray-400">30–59 j</p>
        </a>
        <a href="{{ route('relances.index', array_merge(request()->query(), ['urgency' => 'normal'])) }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'normal' ? 'border-yellow-400 ring-2 ring-yellow-200' : 'border-gray-200' }} p-4 hover:border-yellow-300 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Normal</p>
            <p class="text-[17px] font-bold text-yellow-600">{{ $stats['normal'] }}</p>
            <p class="text-xs text-gray-400">1–29 j</p>
        </a>
        <a href="{{ route('relances.index', array_merge(request()->query(), ['urgency' => 'a_venir'])) }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'a_venir' ? 'border-blue-400 ring-2 ring-blue-200' : 'border-gray-200' }} p-4 hover:border-blue-300 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">À venir</p>
            <p class="text-[17px] font-bold text-blue-600">{{ $stats['a_venir'] }}</p>
            <p class="text-xs text-gray-400">non échue</p>
        </a>
        <a href="{{ route('relances.index', array_merge(request()->query(), ['urgency' => 'sans_ech'])) }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'sans_ech' ? 'border-gray-500 ring-2 ring-gray-300' : 'border-gray-200' }} p-4 hover:border-gray-400 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sans échéance</p>
            <p class="text-[17px] font-bold text-gray-700">{{ $stats['sans_ech'] }}</p>
            <p class="text-xs text-gray-400">⚠ à régulariser</p>
        </a>
        <a href="{{ route('relances.index') }}"
           class="bg-white rounded-[4px] border {{ $urgency === 'all' ? 'border-gray-400 ring-2 ring-gray-100' : 'border-gray-200' }} p-4 hover:border-gray-300 transition-colors">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Tous</p>
            <p class="text-[17px] font-bold text-gray-700">{{ $stats['total_factures'] }}</p>
            <p class="text-xs text-gray-400">afficher tout</p>
        </a>
    </div>

    {{-- Filtre client --}}
    <form method="GET" action="{{ route('relances.index') }}" class="flex gap-2">
        <input type="hidden" name="urgency" value="{{ $urgency }}">
        <select name="client_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-400 focus:border-red-400 min-w-48">
            <option value="">Tous les clients</option>
            @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->trade_name ?? $c->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">Filtrer</button>
        @if($clientId)
        <a href="{{ route('relances.index', ['urgency' => $urgency]) }}" class="border border-gray-300 text-gray-500 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">✕</a>
        @endif
    </form>

    {{-- Liste par client --}}
    @forelse($byClient as $clientId => $data)
    @php
        $maxDays = $data['max_days_overdue'];
        // Couleur selon l'urgence max
        if ($maxDays === null) {
            $urgencyColor = 'gray';            // pas d'échéance définie sur aucune facture du client
        } elseif ($maxDays >= 60) {
            $urgencyColor = 'red';
        } elseif ($maxDays >= 30) {
            $urgencyColor = 'orange';
        } elseif ($maxDays > 0) {
            $urgencyColor = 'yellow';
        } else {
            $urgencyColor = 'blue';            // due_at >= today (à venir)
        }
        $urgencyColors = [
            'red'    => ['border' => 'border-red-200',    'bg' => 'bg-red-50',     'badge' => 'bg-red-100 text-red-700',       'btn' => 'bg-red-600 hover:bg-red-700'],
            'orange' => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50',  'badge' => 'bg-orange-100 text-orange-700', 'btn' => 'bg-emerald-700 hover:bg-emerald-800'],
            'yellow' => ['border' => 'border-yellow-200', 'bg' => 'bg-yellow-50',  'badge' => 'bg-yellow-100 text-yellow-700', 'btn' => 'bg-yellow-600 hover:bg-yellow-700'],
            'blue'   => ['border' => 'border-blue-200',   'bg' => 'bg-blue-50',   'badge' => 'bg-blue-100 text-blue-700',     'btn' => 'bg-emerald-700 hover:bg-emerald-800'],
            'gray'   => ['border' => 'border-gray-200',   'bg' => 'bg-gray-50',   'badge' => 'bg-gray-200 text-gray-700',     'btn' => 'bg-gray-600 hover:bg-gray-700'],
        ];
        $c = $urgencyColors[$urgencyColor];
        $invoiceIds = $data['invoices']->pluck('id')->toArray();

        // Libellé du badge synthétique
        if ($maxDays === null) {
            $statusLabel = 'sans échéance';
        } elseif ($maxDays > 0) {
            $statusLabel = $maxDays.' j de retard';
        } elseif ($maxDays === 0) {
            $statusLabel = 'échéance aujourd\'hui';
        } else {
            $statusLabel = 'à venir (J'.($maxDays).')';
        }
    @endphp
    <div class="bg-white rounded-[4px] border {{ $c['border'] }} overflow-hidden">
        {{-- Client header --}}
        <div class="{{ $c['bg'] }} px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b {{ $c['border'] }}">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-white border {{ $c['border'] }} flex items-center justify-center text-sm font-bold text-gray-600">
                    {{ strtoupper(substr($data['client']->displayName(), 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('clients.show', $data['client']) }}" class="font-semibold text-gray-900 hover:text-blue-600">
                            {{ $data['client']->displayName() }}
                        </a>
                        <span class="text-xs font-mono text-gray-400">{{ $data['client']->code }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold {{ $c['badge'] }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500">
                        @if($data['client']->email)
                        <span>{{ $data['client']->email }}</span>
                        @endif
                        @if($data['client']->phone)
                        <span>{{ $data['client']->phone }}</span>
                        @endif
                        @if($data['last_relance'])
                        <span class="text-purple-600">
                            Dernière relance : {{ $data['last_relance']->occurred_at->format('d/m/Y') }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="text-right">
                    <p class="text-xs text-gray-500">Total dû</p>
                    <p class="font-bold text-gray-900 tabular-nums">{{ number_format($data['total_du'], 0, ',', ' ') }} FCFA</p>
                </div>
                <button type="button"
                        @click="openModal({{ $data['client']->id }}, '{{ addslashes($data['client']->displayName()) }}', {{ json_encode($invoiceIds) }}, '{{ $data['client']->email }}')"
                        class="{{ $c['btn'] }} text-white text-xs font-medium px-2.5 py-1.5 rounded-[4px] transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Envoyer relance
                </button>
            </div>
        </div>

        {{-- Factures du client --}}
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-2.5 text-left">
                        <input type="checkbox" @change="toggleAll($event, {{ json_encode($invoiceIds) }})"
                               class="w-3.5 h-3.5 text-red-600 border-gray-300 rounded">
                    </th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Facture</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Émission</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                    <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Reste dû</th>
                    <th class="px-3 py-2.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Retard</th>
                    <th class="px-3 py-2.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Dernière relance</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($data['invoices'] as $invoice)
                @php
                    $days = $invoice->days_overdue;
                    if ($days === null) {
                        $daysBadge   = 'bg-gray-200 text-gray-700';
                        $daysDisplay = 'sans éch.';
                    } elseif ($days >= 60) {
                        $daysBadge   = 'bg-red-100 text-red-700';
                        $daysDisplay = $days.' j';
                    } elseif ($days >= 30) {
                        $daysBadge   = 'bg-orange-100 text-orange-700';
                        $daysDisplay = $days.' j';
                    } elseif ($days > 0) {
                        $daysBadge   = 'bg-yellow-100 text-yellow-700';
                        $daysDisplay = $days.' j';
                    } elseif ($days === 0) {
                        $daysBadge   = 'bg-blue-100 text-blue-700';
                        $daysDisplay = 'aujourd\'hui';
                    } else {
                        $daysBadge   = 'bg-blue-100 text-blue-700';
                        $daysDisplay = 'J'.$days;   // J-3, J-7 (à venir)
                    }
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-2.5">
                        <input type="checkbox" :value="{{ $invoice->id }}" x-model="selected"
                               class="w-3.5 h-3.5 text-red-600 border-gray-300 rounded">
                    </td>
                    <td class="px-3 py-2.5">
                        <a href="{{ route('ventes.factures.show', $invoice) }}"
                           class="font-mono font-semibold text-emerald-700 hover:text-emerald-900 text-xs">{{ $invoice->number }}</a>
                    </td>
                    <td class="px-3 py-2.5 text-gray-500 text-xs hidden md:table-cell">{{ $invoice->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 text-xs {{ $days > 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                        {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-gray-900 text-sm">
                        {{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold {{ $daysBadge }}">{{ $daysDisplay }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-center hidden lg:table-cell">
                        @if($invoice->last_relance)
                        <span class="text-xs text-purple-600">{{ $invoice->last_relance->occurred_at->format('d/m/Y') }}</span>
                        @else
                        <span class="text-xs text-gray-400">Jamais</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <button type="button"
                                @click="openModal({{ $data['client']->id }}, '{{ addslashes($data['client']->displayName()) }}', [{{ $invoice->id }}], '{{ $data['client']->email }}')"
                                class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors">
                            Relancer
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @empty
    <div class="bg-white rounded-[4px] border border-gray-300 py-20 text-center">
        <svg class="w-12 h-12 mx-auto mb-3 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-medium text-gray-600">Aucune facture impayée</p>
        <p class="text-xs text-gray-400 mt-1">Tous vos clients sont à jour !</p>
    </div>
    @endforelse

    @if($byClient->hasPages())
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
        {{ $byClient->links() }}
    </div>
    @endif

    {{-- Modal envoi relance --}}
    <div x-show="modal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="modal = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md" @click.stop>
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Envoyer une relance</h3>
                <p class="text-sm text-gray-500 mt-0.5" x-text="'Client : ' + clientName"></p>
            </div>
            <form method="POST" action="{{ route('relances.send') }}" class="px-6 py-5 space-y-4">
                @csrf
                <input type="hidden" name="client_id" :value="clientId">
                <template x-for="id in invoiceIds" :key="id">
                    <input type="hidden" name="invoice_ids[]" :value="id">
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de relance <span class="text-red-500">*</span></label>
                    <select name="type" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-400 focus:border-red-400">
                        <option value="amiable">1ère relance (amiable)</option>
                        <option value="formelle">2ème relance (formelle)</option>
                        <option value="mise_en_demeure">Mise en demeure</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email destinataire</label>
                    <input type="text" :value="clientEmail" readonly
                           class="w-full border border-gray-200 rounded-[4px] px-3 py-2 text-sm bg-gray-50 text-gray-500">
                    <p class="text-xs text-gray-400 mt-1">+ contacts avec "reçoit les factures"</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message personnalisé (optionnel)</label>
                    <textarea name="message" rows="3" placeholder="Message additionnel à inclure dans l'email..."
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-400 resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-1.5 rounded-[4px] transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Envoyer la relance
                    </button>
                    <button type="button"
                            @click="
                                const f = $el.closest('form');
                                const p = new URLSearchParams();
                                p.set('client_id', clientId);
                                invoiceIds.forEach(id => p.append('invoice_ids[]', id));
                                p.set('type', f.querySelector('[name=type]').value);
                                const m = f.querySelector('[name=message]').value; if (m) p.set('message', m);
                                window.open('{{ route('relances.letter') }}?' + p.toString(), '_blank');
                            "
                            class="border border-violet-300 text-violet-700 hover:bg-violet-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Courrier PDF
                    </button>
                    <button type="button" @click="modal = false"
                            class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function relanceManager() {
    return {
        modal: false,
        clientId: null,
        clientName: '',
        clientEmail: '',
        invoiceIds: [],
        selected: [],

        openModal(clientId, clientName, invoiceIds, email) {
            this.clientId   = clientId;
            this.clientName = clientName;
            this.invoiceIds = invoiceIds;
            this.clientEmail = email || '(pas d\'email renseigné)';
            this.modal = true;
        },

        toggleAll(event, ids) {
            if (event.target.checked) {
                ids.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
            } else {
                this.selected = this.selected.filter(id => !ids.includes(id));
            }
        },
    };
}
</script>
@endpush
