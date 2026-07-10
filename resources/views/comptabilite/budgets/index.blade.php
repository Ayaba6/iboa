@extends('layouts.erp')
@section('title', 'Budgets')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Budgets</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $fmt   = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $mois  = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
    $editable = $budget && $budget->status === 'en_cours';
@endphp
<div class="space-y-3" x-data="{ showNew: {{ $budgets->isEmpty() ? 'true' : 'false' }}, showLine: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Budgets</h1>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="budget-filter"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Rechercher</button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            @can('accounting.manage')
            <button type="button" @click="showNew = !showNew"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Nouveau</button>
            @if($editable)
            <form method="POST" action="{{ route('comptabilite.budgets.validate', $budget) }}"
                  onsubmit="return confirm('Valider le budget {{ $budget->code }} ? Les montants seront figés.')">
                @csrf
                <button type="submit"
                        class="text-[14px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-5 py-2 rounded-[4px] transition-colors">Valider</button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    <x-validation-errors />
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-2 rounded-[4px] text-[13px]">{{ session('success') }}</div>
    @endif

    {{-- Formulaire nouveau budget --}}
    @can('accounting.manage')
    <form x-show="showNew" x-cloak method="POST" action="{{ route('comptabilite.budgets.store') }}"
          class="bg-white rounded-[4px] border border-emerald-300 p-4">
        @csrf
        <h2 class="text-[13px] font-bold text-emerald-700 mb-3">Nouveau scénario budgétaire</h2>
        <div class="grid grid-cols-12 gap-x-3 gap-y-3 items-end">
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" required maxlength="30" value="BUD-{{ date('Y') }}" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Libellé <span class="text-red-500">*</span></label>
                <input type="text" name="label" required maxlength="120" value="Budget principal {{ date('Y') }}" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Exercice</label>
                <div class="relative">
                    <select name="fiscal_year_id" class="{{ $lk }}">
                        @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected($fy->is_current)>{{ $fy->label }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Version</label>
                <input type="text" name="version" maxlength="10" value="V1" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Du (mois)</label>
                <input type="number" name="period_from" min="1" max="12" value="1" class="{{ $inp }} text-center">
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Au (mois)</label>
                <input type="number" name="period_to" min="1" max="12" value="12" class="{{ $inp }} text-center">
            </div>
            <div class="col-span-12 sm:col-span-2">
                <button type="submit" class="w-full h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold rounded-[4px] transition-colors">Créer le budget</button>
            </div>
        </div>
    </form>
    @endcan

    {{-- Zone de filtres — fiche maquette --}}
    <form method="GET" id="budget-filter" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="grid grid-cols-12 gap-x-3 gap-y-3">
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Société principale</p>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Exercice <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $budget?->fiscalYear?->label ?? date('Y') }}" class="{{ $inpRo }} tabular-nums" readonly>
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Scénario budgétaire <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select name="budget_id" class="{{ $lk }} font-mono">
                        @forelse($budgets as $b)
                        <option value="{{ $b->id }}" @selected($budget?->id === $b->id)>{{ $b->code }} ({{ $b->version }}) — {{ $b->label }}</option>
                        @empty
                        <option value="">— Aucun budget —</option>
                        @endforelse
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-1">
                <label class="{{ $lbl }}">Version</label>
                <input type="text" value="{{ $budget?->version ?? '—' }}" class="{{ $inpRo }} font-mono" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Statut</label>
                <input type="text" value="{{ $budget?->statusLabel() ?? '—' }}" class="{{ $inpRo }}" readonly>
            </div>

            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période de début <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $budget ? sprintf('%02d — %s', $budget->period_from, $mois[$budget->period_from]) : '—' }}" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période de fin <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $budget ? sprintf('%02d — %s', $budget->period_to, $mois[$budget->period_to]) : '—' }}" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Centre de coût</label>
                <input type="text" value="Tous" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Axe analytique</label>
                <input type="text" value="Tous" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-12 sm:col-span-4 flex items-end pb-1">
                <span class="text-[12.5px] text-gray-500">Réalisé calculé sur les écritures validées de la période (classe 6 : D−C · classe 7 : C−D).</span>
            </div>
        </div>
    </form>

    @if($budget)
    {{-- ═══ Lignes budgétaires ═══ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gray-50">
            <span class="text-[12px] text-gray-500">{{ $lines->count() }} ligne(s) budgétaire(s) — {{ $budget->code }} ({{ $budget->version }})</span>
            @can('accounting.manage')
            @if($editable)
            <button type="button" @click="showLine = !showLine"
                    class="h-6 inline-flex items-center gap-1 px-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-[3px] text-[12px] font-medium">+ Ligne</button>
            @else
            <span class="text-[11px] font-bold text-emerald-700 uppercase">Validé — figé</span>
            @endif
            @endcan
        </div>

        {{-- Formulaire ajout de ligne --}}
        @can('accounting.manage')
        @if($editable)
        <form x-show="showLine" x-cloak method="POST" action="{{ route('comptabilite.budgets.lines.store', $budget) }}"
              class="px-3 py-2 border-b border-emerald-100 bg-emerald-50/40 flex flex-wrap items-end gap-2">
            @csrf
            <div class="relative">
                <select name="account_id" required class="appearance-none h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[13px] font-mono bg-white max-w-[240px]">
                    <option value="">— Compte 6x/7x —</option>
                    @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                    @endforeach
                </select>
                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>
            </div>
            <input type="text" name="cost_center" placeholder="Centre coût" maxlength="30" class="h-8 w-24 border border-gray-400 rounded-[3px] px-2 text-[13px] font-mono">
            <input type="text" name="axe" placeholder="Axe" maxlength="30" class="h-8 w-20 border border-gray-400 rounded-[3px] px-2 text-[13px] font-mono">
            <input type="number" name="initial_amount" placeholder="Budget initial" required min="0" class="h-8 w-32 border border-gray-400 rounded-[3px] px-2 text-[13px] text-right tabular-nums">
            <input type="number" name="revised_amount" placeholder="Révisé (=initial)" min="0" class="h-8 w-32 border border-gray-400 rounded-[3px] px-2 text-[13px] text-right tabular-nums">
            <input type="number" name="committed_amount" placeholder="Engagements" min="0" class="h-8 w-28 border border-gray-400 rounded-[3px] px-2 text-[13px] text-right tabular-nums">
            <button type="submit" class="h-8 px-3 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Ajouter</button>
        </form>
        @endif
        @endcan

        <div class="overflow-x-auto">
        <table class="min-w-full text-[13px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">Compte</th>
                    <th class="px-3 py-1.5 text-left">Libellé</th>
                    <th class="px-3 py-1.5 text-left">Centre de coût</th>
                    <th class="px-3 py-1.5 text-left">Axe</th>
                    <th class="px-3 py-1.5 text-right">Budget initial (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Révisé (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Réalisé (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Engagements (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Disponible (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Écart (XOF)</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                    @if($editable)<th class="px-3 py-1.5 w-8"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lines as $l)
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1 font-mono font-semibold text-blue-600 text-[13px]">{{ $l->account?->code }}</td>
                    <td class="px-3 py-1 text-gray-900">{{ $l->account?->name }}</td>
                    <td class="px-3 py-1 font-mono text-[12px] text-gray-600">{{ $l->cost_center ?: '—' }}</td>
                    <td class="px-3 py-1 font-mono text-[12px] text-gray-600">{{ $l->axe ?: '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums">{{ $fmt($l->initial_amount) }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium">{{ $fmt($l->revised_amount) }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium">{{ $fmt($l->realise) }}</td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-600">{{ $l->committed_amount ? $fmt($l->committed_amount) : '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-bold {{ $l->disponible < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($l->disponible) }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium {{ $l->ecart > 0 ? 'text-red-600' : 'text-gray-600' }}">
                        {{ $l->ecart < 0 ? '-' : '' }}{{ $fmt(abs($l->ecart)) }}
                    </td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium
                            {{ $l->disponible < 0 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $l->disponible < 0 ? 'Dépassé' : $budget->statusLabel() }}
                        </span>
                    </td>
                    @if($editable)
                    <td class="px-3 py-1 text-center">
                        <form method="POST" action="{{ route('comptabilite.budgets.lines.destroy', [$budget, $l]) }}"
                              onsubmit="return confirm('Supprimer cette ligne budgétaire ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-400 hover:text-red-600" title="Supprimer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M6 7V4a1 1 0 011-1h10a1 1 0 011 1v3"/></svg>
                            </button>
                        </form>
                    </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="12" class="px-4 py-10 text-center text-gray-400 text-[13px]">Aucune ligne budgétaire — cliquez sur « + Ligne » pour budgéter un compte.</td></tr>
                @endforelse
            </tbody>
            @if($lines->isNotEmpty())
            @php
                $tInit = $lines->sum('initial_amount'); $tRev = $lines->sum('revised_amount');
                $tReal = $lines->sum('realise'); $tEng = $lines->sum('committed_amount');
                $tDisp = $lines->sum('disponible'); $tEcart = $lines->sum('ecart');
            @endphp
            <tfoot>
                <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold text-gray-900">
                    <td colspan="4" class="px-3 py-1.5 text-right text-[11px] uppercase text-gray-500">Total</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($tInit) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($tRev) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($tReal) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($tEng) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums {{ $tDisp < 0 ? 'text-red-600' : '' }}">{{ $fmt($tDisp) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums {{ $tEcart > 0 ? 'text-red-600' : '' }}">{{ $tEcart < 0 ? '-' : '' }}{{ $fmt(abs($tEcart)) }}</td>
                    <td colspan="{{ $editable ? 2 : 1 }}"></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>

    {{-- ═══ Bandeau 4 zones (maquette) ═══ --}}
    @php
        $tInit = $lines->sum('initial_amount'); $tRev = $lines->sum('revised_amount');
        $tReal = $lines->sum('realise'); $tDisp = $lines->sum('disponible');
    @endphp
    <div class="bg-white border border-gray-200 rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-4 gap-3 items-center text-center">
        <div>
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Budget initial</p>
            <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight">{{ $fmt($tInit) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] text-gray-400">100%</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Budget révisé</p>
            <p class="text-[17px] font-bold tabular-nums text-blue-800 leading-tight">{{ $fmt($tRev) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] {{ $tInit > 0 && $tRev >= $tInit ? 'text-emerald-600' : 'text-gray-400' }}">
                {{ $tInit > 0 ? ($tRev >= $tInit ? '+' : '') . round(($tRev - $tInit) / $tInit * 100, 2) . '% vs initial' : '—' }}
            </p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Réalisé</p>
            <p class="text-[17px] font-bold tabular-nums text-emerald-700 leading-tight">{{ $fmt($tReal) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] text-gray-400">{{ $tRev > 0 ? round($tReal / $tRev * 100, 2) . '% vs révisé' : '—' }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Disponible</p>
            <p class="text-[17px] font-bold tabular-nums leading-tight {{ $tDisp < 0 ? 'text-red-600' : 'text-orange-500' }}">{{ $fmt($tDisp) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] text-gray-400">{{ $tRev > 0 ? round($tDisp / $tRev * 100, 2) . '% vs révisé' : '—' }}</p>
        </div>
    </div>

    {{-- Barre de contexte pied de page --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Exercice : <span class="text-white font-semibold">{{ $budget->fiscalYear?->label ?? '—' }}</span></span>
        <span class="border-l border-white/10 pl-6">Scénario : <span class="text-white font-semibold">{{ $budget->code }}</span></span>
        <span class="border-l border-white/10 pl-6">Version : <span class="text-white font-semibold">{{ $budget->version }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold">{{ sprintf('%02d à %02d', $budget->period_from, $budget->period_to) }}</span></span>
        <span class="border-l border-white/10 pl-6">Statut : <span class="text-white font-semibold">{{ $budget->statusLabel() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    @else
    <div class="bg-white rounded-[4px] border border-gray-300 py-16 text-center text-gray-400">
        <p class="text-sm font-medium">Aucun scénario budgétaire.</p>
        @can('accounting.manage')
        <button type="button" @click="showNew = true" class="mt-2 text-emerald-700 hover:text-emerald-900 text-sm underline">Créer le premier budget</button>
        @endcan
    </div>
    @endif

</div>
@endsection
