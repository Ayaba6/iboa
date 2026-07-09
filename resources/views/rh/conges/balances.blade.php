@extends('layouts.erp')
@section('title', 'Soldes de congés')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.conges.index') }}" class="hover:text-gray-700">Congés</a>
    <span class="mx-1">/</span><span>Soldes</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Soldes de congés — {{ $year }}</h1>
    <form method="GET" class="flex gap-2">
        <select name="year" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            @foreach(range(now()->year, now()->year-3) as $y)
            <option value="{{ $y }}" @selected($year==$y)>{{ $y }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3 py-1.5 bg-gray-700 text-white rounded-[4px] text-sm">Afficher</button>
    </form>
</div>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Employé</th>
                @foreach($types as $t)
                <th class="px-3 py-3 text-center" colspan="3">
                    <span class="inline-block w-2 h-2 rounded-full bg-{{ $t->color }}-500 mr-1"></span>{{ $t->name }}
                </th>
                @endforeach
            </tr>
            <tr>
                <th class="px-3 py-1.5"></th>
                @foreach($types as $t)
                <th class="px-2 py-1 text-center text-gray-400">Droit</th>
                <th class="px-2 py-1 text-center text-gray-400">Pris</th>
                <th class="px-2 py-1 text-center text-green-600">Reste</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($employees as $emp)
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-1.5 font-medium">
                {{ $emp->full_name }}<br>
                <span class="text-xs text-gray-400">{{ $emp->matricule }}</span>
            </td>
            @foreach($types as $t)
            @php
                $bal = $emp->leaveBalances->firstWhere('leave_type_id', $t->id);
                $entitled = $bal?->entitled_days ?? $t->days_per_year;
                $taken    = $bal?->taken_days ?? 0;
                $remaining= max(0, $entitled - $taken);
            @endphp
            <td class="px-2 py-3 text-center text-gray-600">{{ $entitled }}</td>
            <td class="px-2 py-3 text-center text-red-600">{{ $taken }}</td>
            <td class="px-2 py-3 text-center font-semibold {{ $remaining > 0 ? 'text-green-700' : 'text-gray-400' }}">{{ $remaining }}</td>
            @endforeach
        </tr>
        @empty
        <tr><td colspan="{{ 1 + count($types)*3 }}" class="px-4 py-10 text-center text-gray-400">Aucun employé actif.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
