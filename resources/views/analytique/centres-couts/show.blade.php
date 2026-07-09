@extends('layouts.erp')
@section('title', $costCenter->code . ' — ' . $costCenter->name)

@section('breadcrumb')
    <a href="{{ route('analytique.centres-couts.index') }}" class="hover:text-gray-700">Centres de coûts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $costCenter->code }}</span>
@endsection

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">{{ $costCenter->name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $costCenter->typeLabel() }} · Code : <span class="font-mono">{{ $costCenter->code }}</span></p>
    </div>
    @can('analytic.manage')
    <a href="{{ route('analytique.centres-couts.edit', $costCenter) }}" class="btn-secondary text-sm">Modifier</a>
    @endcan
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach(\App\Models\AnalyticLine::$categoryLabels as $key => $label)
    @php $total = $byCategory[$key]->total ?? 0; @endphp
    @if($total != 0)
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">{{ $label }}</p>
        <p class="text-xl font-black {{ $total >= 0 ? 'text-rose-600' : 'text-emerald-600' }} tabular-nums">
            {{ number_format(abs($total), 0, ',', ' ') }} F
        </p>
    </div>
    @endif
    @endforeach
</div>

{{-- Saisie ligne manuelle --}}
@can('analytic.manage')
<div class="bg-white rounded-[4px] border border-gray-300 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Saisir une ligne analytique</h3>
    <form method="POST" action="{{ route('analytique.lignes.store') }}" class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @csrf
        <input type="hidden" name="cost_center_id" value="{{ $costCenter->id }}">
        <input type="date" name="date" value="{{ today()->toDateString() }}" class="input-field" required>
        <input type="text" name="label" class="input-field md:col-span-2" placeholder="Libellé" required maxlength="200">
        <select name="category" class="input-field">
            @foreach(\App\Models\AnalyticLine::$categoryLabels as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <input type="number" name="amount" step="1" class="input-field flex-1" placeholder="Montant FCFA" required>
            <button type="submit" class="btn-primary px-3">+</button>
        </div>
    </form>
    <p class="text-xs text-gray-400 mt-1">Montant positif = charge / négatif = produit</p>
</div>
@endcan

{{-- Liste des lignes --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Catégorie</th>
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant FCFA</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($lines as $line)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-2.5 text-gray-500 tabular-nums">{{ $line->date->format('d/m/Y') }}</td>
                <td class="px-3 py-2.5 text-gray-900">{{ $line->label }}</td>
                <td class="px-3 py-2.5">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $line->categoryLabel() }}</span>
                </td>
                <td class="px-3 py-2.5 text-right font-mono font-semibold {{ $line->amount >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ $line->amount >= 0 ? '' : '−' }}{{ number_format(abs($line->amount), 0, ',', ' ') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucune ligne analytique.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-3 py-1.5 border-t border-gray-100">{{ $lines->links() }}</div>
</div>
@endsection
