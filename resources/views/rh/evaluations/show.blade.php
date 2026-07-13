@extends('layouts.erp')
@section('title', 'Évaluation — '.$appraisal->employee?->full_name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.evaluations.index') }}" class="hover:text-gray-700">Évaluations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $appraisal->employee?->full_name }}</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $final = $appraisal->status === 'finalisee';
    $rc = ['insuffisant'=>'text-red-600','a_ameliorer'=>'text-amber-600','satisfaisant'=>'text-blue-700','bon'=>'text-emerald-700','excellent'=>'text-emerald-800'];
@endphp
<div class="space-y-4">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $appraisal->employee?->full_name }} — {{ $appraisal->campaign }} {{ $appraisal->period_year }}</h1>
            <p class="text-sm text-gray-500">Évaluateur : {{ $appraisal->evaluator_name ?? '—' }} · Statut : <span class="font-semibold">{{ $appraisal->statusLabel() }}</span></p>
        </div>
        @if($appraisal->overall_score !== null)
        <div class="text-right">
            <p class="text-[28px] font-bold {{ $rc[$appraisal->rating] ?? 'text-gray-900' }} leading-none tabular-nums">{{ number_format((float) $appraisal->overall_score, 2, ',', ' ') }}<span class="text-[14px] text-gray-400">/5</span></p>
            <p class="text-xs {{ $rc[$appraisal->rating] ?? 'text-gray-500' }} font-semibold">{{ $appraisal->ratingLabel() }}</p>
        </div>
        @endif
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($appraisal->objectives)<div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-[11px] font-semibold text-gray-400 uppercase mb-1">Objectifs</p><p class="text-sm text-gray-700 whitespace-pre-line">{{ $appraisal->objectives }}</p></div>@endif

    <form method="POST" action="{{ route('rh.evaluations.update', $appraisal) }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold">Notation des critères (0 à 5)</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#3b4248] text-white">
                        <tr>
                            <th class="{{ $th }} text-left">Critère</th>
                            <th class="{{ $th }} text-right">Poids</th>
                            <th class="{{ $th }} text-center">Auto-éval.</th>
                            <th class="{{ $th }} text-center">Manager</th>
                            <th class="{{ $th }} text-left">Commentaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appraisal->criteria as $i => $c)
                        <tr class="border-b border-gray-100">
                            <td class="px-3 py-1.5">{{ $c->label }}<input type="hidden" name="criteria[{{ $i }}][id]" value="{{ $c->id }}"></td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $c->weight }} %</td>
                            <td class="px-2 py-1 text-center"><input type="number" step="0.5" min="0" max="5" name="criteria[{{ $i }}][self_rating]" value="{{ $c->self_rating }}" class="w-16 h-7 px-1 border border-gray-300 rounded-[3px] text-[12px] text-center" @disabled($final)></td>
                            <td class="px-2 py-1 text-center"><input type="number" step="0.5" min="0" max="5" name="criteria[{{ $i }}][manager_rating]" value="{{ $c->manager_rating }}" class="w-16 h-7 px-1 border border-gray-300 rounded-[3px] text-[12px] text-center" @disabled($final)></td>
                            <td class="px-2 py-1"><input name="criteria[{{ $i }}][comment]" value="{{ $c->comment }}" class="w-full h-7 px-1.5 border border-gray-300 rounded-[3px] text-[12px]" @disabled($final)></td>
                        </tr>
                        @endforeach
                        @if($appraisal->criteria->isEmpty())<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Aucun critère.</td></tr>@endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Plan d'action / développement</label>
                <textarea name="action_plan" rows="3" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]" @disabled($final)>{{ $appraisal->action_plan }}</textarea>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Prime liée (F CFA)</label>
                <input type="number" step="0.01" min="0" name="bonus_amount" value="{{ $appraisal->bonus_amount }}" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px] text-right" @disabled($final)>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1 mt-3">Évaluateur</label>
                <input name="evaluator_name" value="{{ $appraisal->evaluator_name }}" class="w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]" @disabled($final)>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Commentaires généraux</label>
                <textarea name="comments" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]" @disabled($final)>{{ $appraisal->comments }}</textarea>
            </div>
        </div>

        @can('rh.employees.manage')
        @unless($final)
        <div class="flex items-center gap-2">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Enregistrer les notes</button>
        </div>
        @endunless
        @endcan
    </form>

    @can('rh.employees.manage')
    @unless($final)
    <form method="POST" action="{{ route('rh.evaluations.finalize', $appraisal) }}" onsubmit="return confirm('Finaliser l\'évaluation ? Les notes seront figées.');">@csrf
        <button class="bg-[#3b4248] hover:bg-black text-white text-sm font-semibold px-5 py-2 rounded-[4px]">✔ Finaliser l'évaluation</button>
    </form>
    @else
    <p class="text-emerald-700 text-sm font-semibold">✓ Évaluation finalisée le {{ optional($appraisal->finalized_at)->format('d/m/Y') }}.</p>
    @endunless
    @endcan

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Critères : <span class="text-white font-semibold">{{ $appraisal->criteria->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
