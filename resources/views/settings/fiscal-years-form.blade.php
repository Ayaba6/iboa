@extends('layouts.erp')
@section('title', $fiscalYear ? 'Exercice fiscal : Modification' : 'Exercice fiscal : Création')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.fiscal-years.index') }}" class="hover:text-gray-700">Exercices fiscaux</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $fiscalYear ? 'Modification' : 'Création' }}</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $txa  = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $sw   = "relative w-8 h-[18px] flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[14px] after:h-[14px] after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-[14px]";
    $fy   = $fiscalYear;
    $company = \App\Models\Company::first();
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Exercice fiscal : {{ $fy ? 'Modification' : 'Création complète' }}</h1>
            <p class="text-[11.5px] text-gray-500">{{ $fy ? ($fy->code ? $fy->code.' — ' : '').$fy->label : 'Nouvel exercice comptable de la société' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-fy" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('settings.fiscal-years.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- Informations générales — 4 colonnes [Maquette] --}}
    <form id="form-fy" method="POST" action="{{ $fy ? route('settings.fiscal-years.update', $fy) : route('settings.fiscal-years.store') }}"
          class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf
        @if($fy) @method('PUT') @endif
        <div class="{{ $secH }}">Informations générales</div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-5 gap-y-3">
            <div>
                <label class="{{ $lbl }}">Code exercice <span class="text-red-500">*</span></label>
                <input type="text" name="code" maxlength="20" value="{{ old('code', $fy->code ?? 'EX-'.date('Y')) }}" placeholder="EX-2026" class="{{ $inp }} font-mono uppercase">
            </div>
            <div>
                <label class="{{ $lbl }}">Date de clôture prévue <span class="text-red-500">*</span></label>
                <input type="date" name="ends_at" required value="{{ old('ends_at', $fy?->ends_at?->toDateString()) }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Périodicité <span class="text-red-500">*</span></label>
                <select name="periodicity" class="{{ $inp }}">
                    @foreach(['mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'annuelle' => 'Annuelle'] as $val => $label)
                    <option value="{{ $val }}" {{ old('periodicity', $fy->periodicity ?? 'mensuelle') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Responsable comptable <span class="text-red-500">*</span></label>
                <select name="responsible_id" class="{{ $inp }}">
                    <option value="">— Sélectionner —</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ (string) old('responsible_id', $fy->responsible_id ?? '') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
            </div>
            <div>
                <label class="{{ $lbl }}">Date de clôture effective</label>
                <input type="date" name="actual_close_date" value="{{ old('actual_close_date', $fy?->actual_close_date?->toDateString()) }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Type d'exercice <span class="text-red-500">*</span></label>
                <select name="exercise_type" class="{{ $inp }}">
                    @foreach(['normal' => 'Normal', 'premier' => 'Premier exercice', 'cloture' => 'Exercice de clôture'] as $val => $label)
                    <option value="{{ $val }}" {{ old('exercise_type', $fy->exercise_type ?? 'normal') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Régime fiscal <span class="text-red-500">*</span></label>
                <select name="fiscal_regime" class="{{ $inp }}">
                    <option value="">— Sélectionner —</option>
                    @foreach(['reel_normal' => 'Régime réel normal', 'reel_simplifie' => 'Régime réel simplifié', 'forfait' => 'Régime du forfait', 'exonere' => 'Exonéré'] as $val => $label)
                    <option value="{{ $val }}" {{ old('fiscal_regime', $fy->fiscal_regime ?? 'reel_normal') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="{{ $lbl }}">Intitulé <span class="text-red-500">*</span></label>
                <input type="text" name="label" required maxlength="50" value="{{ old('label', $fy->label ?? 'Exercice fiscal '.date('Y')) }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label>
                @php $st = $fy->status ?? 'ouvert'; @endphp
                <input type="text" disabled class="{{ $inp }} !bg-gray-50 font-semibold {{ $st === 'ouvert' ? 'text-emerald-700' : ($st === 'cloture' ? 'text-orange-600' : 'text-gray-500') }}"
                       value="{{ ['ouvert' => '● Ouvert', 'cloture' => '● Clôturé', 'archive' => '● Archivé'][$st] ?? $st }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Référence exercice précédent</label>
                <select name="previous_reference" class="{{ $inp }} font-mono">
                    <option value="">— Aucune —</option>
                    @foreach($previousYears as $py)
                    <option value="{{ $py->code ?: $py->label }}" {{ old('previous_reference', $fy->previous_reference ?? '') === ($py->code ?: $py->label) ? 'selected' : '' }}>{{ $py->code ?: $py->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="xl:row-span-2">
                <label class="{{ $lbl }}">Commentaire</label>
                <textarea name="comment" rows="4" maxlength="500" placeholder="Exercice fiscal {{ date('Y') }} de la société." class="{{ $txa }}">{{ old('comment', $fy->comment ?? '') }}</textarea>
            </div>

            <div>
                <label class="{{ $lbl }}">Date d'ouverture <span class="text-red-500">*</span></label>
                <input type="date" name="starts_at" required value="{{ old('starts_at', $fy?->starts_at?->toDateString()) }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Devise de base <span class="text-red-500">*</span></label>
                <select name="base_currency" class="{{ $inp }}">
                    @forelse($currencies as $c)
                    <option value="{{ $c->code }}" {{ old('base_currency', $fy->base_currency ?? 'XOF') === $c->code ? 'selected' : '' }}>{{ $c->code }} — {{ $c->name }}</option>
                    @empty
                    <option value="XOF">XOF — Franc CFA</option>
                    @endforelse
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Référence exercice suivant</label>
                <input type="text" name="next_reference" maxlength="30" value="{{ old('next_reference', $fy->next_reference ?? '') }}" placeholder="Saisir la référence" class="{{ $inp }} font-mono">
            </div>

            @if(!$fy)
            <div class="xl:col-span-4 pt-1 border-t border-gray-100">
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_current" value="0">
                    <input type="checkbox" name="is_current" value="1" {{ old('is_current') ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }}"></span>
                    <span class="text-[13px] font-medium text-gray-700">Définir comme exercice courant</span>
                </label>
            </div>
            @endif
        </div>
    </form>

    {{-- Rangée: Périodes | Paramètres de gestion | Synthèse [Maquette] --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">

        {{-- Périodes --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-7">
            <div class="{{ $secH }}">Périodes</div>
            @if($periods->isNotEmpty())
            <div class="max-h-[420px] overflow-y-auto">
                <table class="w-full text-[12px]">
                    <thead class="sticky top-0"><tr class="bg-[#eef5f0] border-b border-gray-300">
                        <th class="{{ $th }} text-center w-9">N°</th>
                        <th class="{{ $th }} text-left">Période</th>
                        <th class="{{ $th }} text-left">Date début</th>
                        <th class="{{ $th }} text-left">Date fin</th>
                        <th class="{{ $th }} text-left">Statut</th>
                        <th class="{{ $th }} text-left">TVA</th>
                        <th class="{{ $th }} text-left">État comptable</th>
                    </tr></thead>
                    <tbody>
                        @foreach($periods as $p)
                        <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                            <td class="px-3 py-1.5 text-center text-gray-400 tabular-nums">{{ $p->number }}</td>
                            <td class="px-3 py-1.5 font-semibold text-gray-700">{{ $p->label }}</td>
                            <td class="px-3 py-1.5 font-mono tabular-nums text-gray-600">{{ $p->starts_at->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5 font-mono tabular-nums text-gray-600">{{ $p->ends_at->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $p->is_locked ? 'bg-gray-100 text-gray-500' : 'bg-emerald-100 text-emerald-800' }}">{{ $p->is_locked ? 'Verrouillée' : 'Ouverte' }}</span></td>
                            <td class="px-3 py-1.5"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $p->vat_done ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-700' }}">{{ $p->vat_done ? 'Déclarée' : 'À déclarer' }}</span></td>
                            <td class="px-3 py-1.5 text-gray-500">{{ $p->is_locked ? 'Clôturée' : 'Non clôturée' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-gray-400 px-3 py-1.5 border-t border-gray-100">Verrouillage des périodes : Comptabilité → Verrouillage des périodes. Déclarations : Comptabilité → TVA.</p>
            @else
            <p class="text-[12px] text-gray-400 px-3 py-1.5">Les périodes mensuelles seront générées à partir des dates d'ouverture et de clôture après enregistrement.</p>
            @endif
        </div>

        {{-- Paramètres de gestion --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-3">
            <div class="{{ $secH }}">Paramètres de gestion</div>
            <div class="px-1 py-1">
                @foreach([
                    'allow_entries_after_provisional_close' => 'Autoriser écritures après clôture provisoire',
                    'monthly_close_required'    => 'Clôture mensuelle obligatoire',
                    'auto_centralization'       => 'Centralisation automatique',
                    'analytics_active'          => 'Gestion analytique active',
                    'vat_lock_after_validation' => 'Verrouillage TVA après validation',
                ] as $opt => $optLbl)
                <label class="flex items-center justify-between gap-3 cursor-pointer px-3 py-2 rounded-[3px] hover:bg-gray-50">
                    <span class="text-[12px] font-medium text-gray-700 leading-tight">{{ $optLbl }}</span>
                    <input type="hidden" name="{{ $opt }}" value="0" form="form-fy">
                    <input type="checkbox" name="{{ $opt }}" value="1" form="form-fy" {{ old($opt, $fy->{$opt} ?? in_array($opt, ['monthly_close_required','auto_centralization','analytics_active','vat_lock_after_validation'])) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }}"></span>
                </label>
                @endforeach
            </div>
            <div class="px-3 py-2.5 border-t border-gray-100 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <label class="text-[12px] font-medium text-gray-700">Nombre de jours tolérés</label>
                    <div class="flex items-center gap-1.5">
                        <input type="number" name="tolerated_days" form="form-fy" min="0" max="60" value="{{ old('tolerated_days', $fy->tolerated_days ?? 5) }}"
                               class="w-16 h-7 px-2 border border-[#c3d3c9] rounded-[3px] text-[12.5px] text-right font-mono bg-white focus:outline-none focus:border-emerald-600">
                        <span class="text-[11.5px] text-gray-500">jours</span>
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Dernière clôture mensuelle</label>
                    <input type="date" name="last_monthly_close" form="form-fy" value="{{ old('last_monthly_close', $fy?->last_monthly_close?->toDateString()) }}" class="{{ $inp }} !h-7 text-[12.5px]">
                </div>
            </div>
        </div>

        {{-- Synthèse --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-2">
            <div class="{{ $secH }}">Synthèse</div>
            @php
                $nb = $periods->count();
                $locked = $periods->where('is_locked', true)->count();
            @endphp
            <div class="divide-y divide-gray-100 text-[12.5px]">
                <div class="flex items-center justify-between px-3 py-1.5"><span class="text-gray-500">Nombre de périodes</span><span class="font-bold tabular-nums">{{ $nb ?: '—' }}</span></div>
                <div class="flex items-center justify-between px-3 py-1.5"><span class="text-gray-500">Périodes ouvertes</span><span class="font-bold tabular-nums text-emerald-700">{{ $nb ? $nb - $locked : '—' }}</span></div>
                <div class="flex items-center justify-between px-3 py-1.5"><span class="text-gray-500">Périodes clôturées</span><span class="font-bold tabular-nums {{ $locked ? 'text-red-600' : '' }}">{{ $nb ? $locked : '—' }}</span></div>
                <div class="flex items-center justify-between px-3 py-1.5"><span class="text-gray-500">Exercice en cours</span><span class="font-bold {{ ($fy?->is_current) ? 'text-emerald-700' : 'text-gray-500' }}">{{ $fy ? ($fy->is_current ? 'Oui' : 'Non') : '—' }}</span></div>
                <div class="px-3 py-1.5">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-0.5">Dernière modification</p>
                    <p class="font-mono tabular-nums text-gray-700">{{ $fy?->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Informations système [Maquette] --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Informations système</div>
        <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Créé par</p>
                <p class="text-[13px] text-gray-700">{{ $fy ? '—' : auth()->user()->name }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Date de création</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $fy?->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Dernière modification</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $fy?->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Commentaires internes</p>
                <textarea name="internal_notes" form="form-fy" rows="2" maxlength="500" placeholder="Ajouter un commentaire interne…" class="{{ $txa }} !text-[12.5px]">{{ old('internal_notes', $fy->internal_notes ?? '') }}</textarea>
            </div>
        </div>
    </div>

</div>
@endsection
