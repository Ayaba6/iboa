@extends('layouts.erp')
@section('title', 'Numérotation des documents')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Numérotation des documents</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $sw   = "relative w-8 h-[18px] flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[14px] after:h-[14px] after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-[14px]";

    // Données aperçu Alpine : segments par type pour reconstruire un numéro
    $previewJs = collect($sequences)->map(function ($seq, $type) use ($labels) {
        return [
            'label'   => $labels[$type] ?? $type,
            'prefix'  => $seq->prefix ?? '',
            'suffix'  => $seq->suffix ?? '',
            'sep'     => $seq->year_separator ?: '-',
            'year'    => (bool) $seq->include_year,
            'yearFmt' => ($seq->year_format ?? '4') === '2' ? 'YY' : 'YYYY',
            'month'   => (bool) ($seq->include_month ?? false),
            'next'    => str_pad((string) ($seq->last_number + 1), max(1, (int) $seq->padding), '0', STR_PAD_LEFT),
            'mask'    => str_repeat('#', max(1, (int) $seq->padding)),
        ];
    });
@endphp
<div class="space-y-4"
     x-data="{
        seqs: @js($previewJs->all()),
        pType: @js($previewJs->keys()->first()),
        pYear: '{{ now()->format('Y') }}',
        pMonth: '{{ now()->format('m') }}',
        resetType: '',
        build(type, mask) {
            const s = this.seqs[type]; if (!s) return '—';
            let year = s.year ? (s.yearFmt === 'YY' ? String(this.pYear).slice(-2) : this.pYear) + s.sep : '';
            let month = s.month ? this.pMonth + s.sep : '';
            return s.prefix + year + month + (mask ? s.mask : s.next) + s.suffix;
        }
     }">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Numérotation des documents</h1>
            <p class="text-[11.5px] text-gray-500">Configuration — exercice : {{ $fiscalYear->label ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-numset" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <button type="button" @click="document.getElementById('sec-apercu')?.scrollIntoView({ behavior: 'smooth' })"
                    class="text-[13px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-full transition-colors">Aperçu des numéros</button>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-[4px] px-3 py-2.5 text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-[4px] px-3 py-2.5 text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- ═══════════ Paramètres généraux [Maquette] ═══════════ --}}
    <form id="form-numset" method="POST" action="{{ route('settings.sequences.settings') }}"
          class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf @method('PUT')
        <div class="{{ $secH }}">Paramètres généraux</div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-5 gap-y-3">
            <div>
                <label class="{{ $lbl }}">Exercice fiscal par défaut <span class="text-red-500">*</span></label>
                <select name="default_fiscal_year_id" class="{{ $inp }}">
                    @foreach($fiscalYears as $fyOpt)
                    <option value="{{ $fyOpt->id }}" {{ (string) old('default_fiscal_year_id', $settings->default_fiscal_year_id ?? '') === (string) $fyOpt->id ? 'selected' : '' }}>
                        {{ $fyOpt->label }} ({{ $fyOpt->starts_at->format('d/m/Y') }} - {{ $fyOpt->ends_at->format('d/m/Y') }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Séparateur <span class="text-red-500">*</span></label>
                <input type="text" name="separator" required maxlength="5" value="{{ old('separator', $settings->separator) }}" class="{{ $inp }} font-mono">
            </div>
            <div>
                <label class="{{ $lbl }}">Nombre de chiffres <span class="text-red-500">*</span></label>
                <select name="digits" class="{{ $inp }}">
                    @for($d = 3; $d <= 8; $d++)
                    <option value="{{ $d }}" {{ (int) old('digits', $settings->digits) === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endfor
                </select>
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Remise à zéro à la clôture de l'exercice</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="reset_on_close" value="0">
                    <input type="checkbox" name="reset_on_close" value="1" {{ old('reset_on_close', $settings->reset_on_close) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500 leading-snug">Redémarrer la numérotation au nouvel exercice.</span>
                </label>
            </div>

            <div>
                <label class="{{ $lbl }}">Préfixe société</label>
                <input type="text" name="company_prefix" maxlength="10" value="{{ old('company_prefix', $settings->company_prefix) }}" placeholder="OA" class="{{ $inp }} font-mono uppercase">
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Inclure l'année dans le numéro</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="include_year" value="0">
                    <input type="checkbox" name="include_year" value="1" {{ old('include_year', $settings->include_year) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500">Ex : 2026{{ $settings->separator }}0001</span>
                </label>
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Inclure le mois dans le numéro</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="include_month" value="0">
                    <input type="checkbox" name="include_month" value="1" {{ old('include_month', $settings->include_month) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500">Ex : 2026{{ $settings->separator }}07{{ $settings->separator }}0001</span>
                </label>
            </div>
            <div>
                <label class="{{ $lbl }}">Affichage de l'aperçu</label>
                <select name="preview_format" class="{{ $inp }}">
                    @foreach(['annee_mois_numero' => 'Année/Mois/Numéro (ex: 2026/07/0001)', 'annee_numero' => 'Année/Numéro (ex: 2026/0001)', 'numero' => 'Numéro seul (ex: 0001)'] as $val => $label)
                    <option value="{{ $val }}" {{ old('preview_format', $settings->preview_format) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="{{ $lbl }}">Gestion des trous de numérotation</label>
                <select name="gap_policy" class="{{ $inp }}">
                    <option value="interdite" {{ old('gap_policy', $settings->gap_policy) === 'interdite' ? 'selected' : '' }}>Interdite (aucun trou autorisé)</option>
                    <option value="toleree" {{ old('gap_policy', $settings->gap_policy) === 'toleree' ? 'selected' : '' }}>Tolérée (trous acceptés)</option>
                </select>
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Utiliser par établissement</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="per_site" value="0">
                    <input type="checkbox" name="per_site" value="1" {{ old('per_site', $settings->per_site) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500 leading-snug">Numérotation indépendante par site/dépôt.</span>
                </label>
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Utiliser par journal</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="per_journal" value="0">
                    <input type="checkbox" name="per_journal" value="1" {{ old('per_journal', $settings->per_journal) ? 'checked' : '' }} class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500 leading-snug">Numérotation distincte par journal.</span>
                </label>
            </div>
            <div>
                <label class="{{ $lbl }}">Format de date</label>
                <select name="date_format" class="{{ $inp }}">
                    @foreach(['JJ/MM/AAAA', 'AAAA-MM-JJ', 'MM/JJ/AAAA'] as $df)
                    <option value="{{ $df }}" {{ old('date_format', $settings->date_format) === $df ? 'selected' : '' }}>{{ $df }}</option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-3">
                <label class="{{ $lbl }}">Commentaires</label>
                <textarea name="comments" rows="2" maxlength="500" placeholder="Paramètres globaux appliqués à tous les types de documents."
                          class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('comments', $settings->comments) }}</textarea>
            </div>
            <div class="pt-0.5">
                <label class="{{ $lbl }}">Appliquer aux types existants</label>
                <label class="flex items-start gap-2.5 cursor-pointer mt-1.5">
                    <input type="hidden" name="apply_to_all" value="0">
                    <input type="checkbox" name="apply_to_all" value="1" class="sr-only peer">
                    <span class="{{ $sw }} mt-0.5"></span>
                    <span class="text-[11.5px] text-gray-500 leading-snug">Applique séparateur, chiffres, année et mois aux séquences non verrouillées (audité).</span>
                </label>
            </div>
        </div>
    </form>

    {{-- ═══════════ Types de documents [Maquette] ═══════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Types de documents</div>
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-center w-9">#</th>
                <th class="{{ $th }} text-left">Type de document</th>
                <th class="{{ $th }} text-left">Code</th>
                <th class="{{ $th }} text-left">Préfixe</th>
                <th class="{{ $th }} text-left">Format du numéro</th>
                <th class="{{ $th }} text-left">Dernier numéro utilisé</th>
                <th class="{{ $th }} text-left">Prochain numéro</th>
                <th class="{{ $th }} text-center w-24">Statut</th>
                <th class="{{ $th }} text-right w-40">Actions</th>
            </tr></thead>
            <tbody>
                @php $i = 0; @endphp
                @foreach($grouped as $category => $types)
                <tr class="bg-gray-50 border-b border-gray-200">
                    <td colspan="9" class="px-3 py-1 text-[11px] font-bold text-gray-600 uppercase tracking-wider">{{ $category }}</td>
                </tr>
                @foreach($types as $type)
                    @php
                        $seq = $sequences[$type] ?? null;
                        if (!$seq) continue;
                        $i++;
                        $mu = $maxUsed[$type] ?? 0;
                    @endphp
                    <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-center text-gray-400 tabular-nums">{{ $i }}</td>
                        <td class="px-3 py-1.5 font-semibold text-gray-700 whitespace-nowrap">{{ $labels[$type] ?? $type }}</td>
                        <td class="px-3 py-1.5 font-mono text-[11px] text-gray-500">{{ $type }}</td>
                        <td class="px-3 py-1.5 font-mono text-emerald-800">{{ $seq->prefix }}</td>
                        <td class="px-3 py-1.5 font-mono text-[11.5px] text-gray-500">{{ $previewData[$type]['format'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono text-[11.5px] tabular-nums {{ $mu > $seq->last_number ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                            {{ $mu > 0 ? app(\App\Services\DocumentSequenceService::class)->format($seq, $mu) : '—' }}@if($mu > $seq->last_number) ⚠@endif
                        </td>
                        <td class="px-3 py-1.5 font-mono text-[11.5px] tabular-nums font-semibold text-emerald-800">{{ $previewData[$type]['next'] ?? '' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $seq->is_locked ? 'bg-gray-100 text-gray-500' : 'bg-emerald-100 text-emerald-800' }}">{{ $seq->is_locked ? 'Verrouillé' : 'Actif' }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            <a href="{{ route('settings.sequences.edit', $seq) }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Modifier</a>
                            <span class="text-gray-300 mx-1">|</span>
                            <a href="{{ route('settings.sequences.audit', $seq) }}" class="text-[12px] font-medium text-gray-500 hover:text-emerald-700 hover:underline">Historique</a>
                        </td>
                    </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ═══════════ Aperçu d'un numéro | Réinitialisation / Clôture [Maquette] ═══════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">

        <div id="sec-apercu" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden scroll-mt-24">
            <div class="{{ $secH }}">Aperçu d'un numéro</div>
            <div class="p-4 flex flex-col sm:flex-row gap-4">
                <div class="space-y-3 sm:w-56 flex-shrink-0">
                    <div>
                        <label class="{{ $lbl }}">Type de document <span class="text-red-500">*</span></label>
                        <select x-model="pType" class="{{ $inp }}">
                            @foreach($previewData as $type => $pd)
                            <option value="{{ $type }}">{{ $pd['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Exercice <span class="text-red-500">*</span></label>
                        <select x-model="pYear" class="{{ $inp }}">
                            @foreach($fiscalYears as $fyOpt)
                            <option value="{{ $fyOpt->starts_at->format('Y') }}">{{ $fyOpt->starts_at->format('Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Mois <span class="text-red-500">*</span></label>
                        <select x-model="pMonth" class="{{ $inp }}">
                            @foreach(range(1, 12) as $m)
                            @php $mm = str_pad((string) $m, 2, '0', STR_PAD_LEFT); @endphp
                            <option value="{{ $mm }}">{{ ucfirst(\Carbon\Carbon::create(null, $m, 1)->translatedFormat('F')) }} ({{ $mm }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex-1 bg-[#f7faf8] border border-gray-200 rounded-[4px] p-4 flex flex-col items-center justify-center text-center">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2">Aperçu</p>
                    <p class="text-[22px] font-bold font-mono text-emerald-800 tabular-nums" x-text="build(pType, false)"></p>
                    <p class="text-[11.5px] text-gray-500 mt-2 font-mono">Format : <span x-text="build(pType, true)"></span></p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Prochain numéro qui sera utilisé.</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Réinitialisation / Clôture</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="border border-gray-200 rounded-[4px] p-3">
                    <p class="text-[13px] font-bold text-emerald-800 mb-1">Réinitialiser un compteur</p>
                    <p class="text-[11.5px] text-gray-500 mb-2">Remet le compteur à zéro pour un type de document (refusé si des numéros sont déjà émis, sauf audit).</p>
                    <select x-model="resetType" class="{{ $inp }} mb-2">
                        <option value="">— Type de document —</option>
                        @foreach($sequences as $type => $seq)
                        <option value="{{ $seq->id }}">{{ $labels[$type] ?? $type }}</option>
                        @endforeach
                    </select>
                    <form method="POST" :action="'{{ url('parametres/numerotation') }}/' + resetType + '/reset'"
                          onsubmit="return confirm('Remettre ce compteur à zéro ? Opération auditée.')">
                        @csrf
                        <button type="submit" :disabled="!resetType"
                                class="w-full text-[12.5px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 disabled:opacity-40 disabled:cursor-not-allowed px-3 py-1.5 rounded-full transition-colors">⟳ Réinitialiser un compteur</button>
                    </form>
                </div>
                <div class="border border-gray-200 rounded-[4px] p-3">
                    <p class="text-[13px] font-bold text-emerald-800 mb-1">Clôturer un exercice</p>
                    <p class="text-[11.5px] text-gray-500 mb-2">Clôturer l'exercice sélectionné pour empêcher toute modification. Géré depuis la page des exercices fiscaux.</p>
                    <a href="{{ route('settings.fiscal-years.index') }}"
                       class="inline-block w-full text-center text-[12.5px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-3 py-1.5 rounded-full transition-colors">🔒 Clôturer l'exercice</a>
                </div>
            </div>
        </div>
    </div>

    <p class="text-[11.5px] text-gray-500 flex items-center gap-1.5 bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-2">
        <svg class="w-3.5 h-3.5 flex-shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        La modification de la numérotation peut impacter les documents existants — les références déjà émises ne sont jamais modifiées et toute opération est tracée dans l'historique d'audit.
    </p>

</div>
@endsection
