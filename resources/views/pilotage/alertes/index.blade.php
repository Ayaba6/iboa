@extends('layouts.erp')
@section('title', 'Alertes par seuil')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Alertes par seuil</span>
@endsection

@section('content')
@php
    $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0';
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
@endphp
<div class="space-y-4">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Alertes par seuil</h1>
            <p class="text-sm text-gray-500">Règles configurables évaluées automatiquement (chaque heure) — notification des rôles cibles.</p>
        </div>
        <form method="POST" action="{{ route('pilotage.alertes.run') }}">@csrf
            <button class="bg-[#3b4248] hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-[4px]">⟳ Évaluer maintenant</button>
        </form>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Table des règles --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Indicateur</th>
                        <th class="{{ $th }} text-center">Condition</th>
                        <th class="{{ $th }} text-right">Valeur actuelle</th>
                        <th class="{{ $th }} text-left">Destinataires</th>
                        <th class="{{ $th }} text-center">État</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rules as $r)
                    <tr class="{{ $r->eval['triggered'] ? 'bg-red-50/50' : '' }}">
                        <td class="px-3 py-1.5 font-medium">{{ $r->name }}{!! $r->is_active ? '' : ' <span class="text-[10px] text-gray-400">(inactive)</span>' !!}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $metrics[$r->metric]['label'] ?? $r->metric }}</td>
                        <td class="px-3 py-1.5 text-center tabular-nums">{{ $r->operatorSymbol() }} {{ $num($r->threshold) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $r->eval['triggered'] ? 'text-red-600' : 'text-gray-700' }}">{{ $num($r->eval['value']) }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-[11px]">{{ implode(', ', $r->target_roles ?? []) ?: '— super_admin' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if($r->eval['triggered'])<span class="text-red-600 text-xs font-semibold">⚠ Déclenchée</span>
                            @else<span class="text-emerald-700 text-xs">✓ OK</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <form method="POST" action="{{ route('pilotage.alertes.destroy', $r) }}" class="inline" onsubmit="return confirm('Supprimer cette alerte ?');">@csrf @method('DELETE')
                                <button class="text-red-500 hover:text-red-700 text-xs">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune règle d'alerte. Créez-en une ci-dessous.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Création --}}
    <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Nouvelle règle d'alerte</div>
    <form method="POST" action="{{ route('pilotage.alertes.store') }}" class="bg-white border border-t-0 border-gray-300 rounded-b-[4px] p-4 space-y-3">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Nom *</label>
                <input name="name" value="{{ old('name') }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Indicateur *</label>
                <select name="metric" class="{{ $sel }}" required>
                    @foreach($metrics as $k => $m)<option value="{{ $k }}" @selected(old('metric')===$k)>{{ $m['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <div class="w-20">
                    <label class="block text-[12px] font-semibold text-gray-800 mb-1">Op. *</label>
                    <select name="operator" class="{{ $sel }}" required>
                        @foreach(\App\Models\AlertRule::OPERATORS as $k => $s)<option value="{{ $k }}" @selected(old('operator','gt')===$k)>{{ $s }}</option>@endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-[12px] font-semibold text-gray-800 mb-1">Seuil *</label>
                    <input type="number" step="0.01" name="threshold" value="{{ old('threshold', 0) }}" class="{{ $inp }} text-right" required>
                </div>
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Rôles à notifier</label>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                @foreach($roles as $role)
                <label class="inline-flex items-center gap-1.5 text-[12px] text-gray-700"><input type="checkbox" name="target_roles[]" value="{{ $role }}" class="rounded border-gray-400"> {{ $role }}</label>
                @endforeach
            </div>
        </div>
        <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-400"> Active</label>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Créer l'alerte</button>
        </div>
    </form>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Règles : <span class="text-white font-semibold">{{ $rules->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
