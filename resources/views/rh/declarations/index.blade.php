@extends('layouts.erp')
@section('title', 'Déclarations sociales & fiscales')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Déclarations</span>
@endsection

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ') . ' F'; @endphp

<div class="space-y-3">

    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Déclarations CNSS &amp; IUTS</h1>
        <p class="text-sm text-gray-500">Archive légale des déclarations figées par run — assiette, part salariale, part patronale, suivi de dépôt et paiement.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">{{ session('error') }}</div>@endif

    <form method="GET" class="bg-white border border-gray-200 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Année</label>
            <select name="year" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Toutes</option>
                @foreach($years as $y)<option value="{{ $y }}" @selected(request('year')==$y)>{{ $y }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type</label>
            <select name="type" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\PayrollDeclaration::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut</label>
            <select name="status" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]">
                <option value="">Tous</option>
                @foreach(\App\Models\PayrollDeclaration::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 h-8 rounded-[3px]">Filtrer</button>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248] text-white text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">Période</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-right">Effectif</th>
                    <th class="px-3 py-2 text-right">Assiette</th>
                    <th class="px-3 py-2 text-right">Part salariale</th>
                    <th class="px-3 py-2 text-right">Part patronale</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-center">Statut</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($declarations as $d)
                @php $sc = ['a_deposer'=>'text-amber-600','depose'=>'text-blue-700','paye'=>'text-emerald-700'][$d->status] ?? 'text-gray-500'; @endphp
                <tr class="hover:bg-emerald-50/40">
                    <td class="px-3 py-1.5 tabular-nums">{{ $d->periodLabel() }}</td>
                    <td class="px-3 py-1.5 font-semibold">{{ $d->typeLabel() }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $d->headcount }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($d->base_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($d->salarial_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($d->patronal_amount) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($d->total_amount) }}</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="{{ $sc }} text-xs font-semibold">{{ $d->statusLabel() }}</span>
                        @if($d->receipt_number)<span class="block text-[10px] text-gray-400">N° {{ $d->receipt_number }}</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap">
                        @can('rh.payroll.manage')
                        @if($d->status === 'a_deposer')
                        <form method="POST" action="{{ route('rh.paie.declarations.deposit', $d) }}" class="inline-flex items-center gap-1">@csrf
                            <input type="text" name="receipt_number" placeholder="N° accusé" class="h-7 py-0 px-1 w-24 border border-gray-300 rounded-[3px] text-xs">
                            <button class="text-blue-700 hover:underline text-xs font-semibold">Déposer</button>
                        </form>
                        @elseif($d->status === 'depose')
                        <form method="POST" action="{{ route('rh.paie.declarations.pay', $d) }}" class="inline">@csrf
                            <button class="text-emerald-700 hover:underline text-xs font-semibold">Marquer payée</button>
                        </form>
                        @else
                        <span class="text-emerald-700 text-xs">✓ Réglée{{ $d->paid_at ? ' '.$d->paid_at->format('d/m/Y') : '' }}</span>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Aucune déclaration figée. Générez-les depuis la fiche d'un run de paie validé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $declarations->links() }}

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Déclarations : <span class="text-white font-semibold">{{ $declarations->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
