@extends('layouts.erp')
@section('title', $plan->exists ? 'Modifier plan' : 'Nouveau plan de maintenance')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.maintenance.index') }}" class="hover:text-gray-700">Maintenance</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.maintenance-plans.index') }}" class="hover:text-gray-700">Plans préventifs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $plan->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-4xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $plan->exists ? route('production.maintenance-plans.update', $plan) : route('production.maintenance-plans.store') }}">
        @csrf
        @if($plan->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">Plan de maintenance : {{ $plan->exists ? 'Modification' : 'Création complète' }}</h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.maintenance-plans.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <div class="p-4 space-y-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Général</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-5">
                            <label class="{{ $lbl }}">Machine <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="machine_id" required class="{{ $lk }}">
                                <option value="">— Choisir —</option>
                                @foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id',$plan->machine_id)==$m->id)>{{ $m->name }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-7">
                            <label class="{{ $lbl }}">Nom du plan <span class="text-red-600">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $plan->name) }}" required maxlength="150" placeholder="Ex. : graissage hebdomadaire" class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Fréquence (jours) <span class="text-red-600">*</span></label>
                            <input type="number" name="frequency_days" value="{{ old('frequency_days', $plan->frequency_days) }}" required min="1" class="{{ $inp }} text-right font-mono">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Prochaine échéance</label>
                            <input type="date" name="next_due_at" value="{{ old('next_due_at', optional($plan->next_due_at)->format('Y-m-d')) }}" class="{{ $inp }}">
                        </div>
                        <div class="sm:col-span-6 flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded border-[#c3d3c9] text-emerald-600 focus:ring-emerald-400">
                                Plan actif
                            </label>
                        </div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Instructions</div>
                    <div class="p-4">
                        <textarea name="instructions" rows="3" maxlength="2000"
                                  class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">{{ old('instructions', $plan->instructions) }}</textarea>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
