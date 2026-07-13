@extends('layouts.erp')
@section('title', 'Formation — '.$session->title)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.formations.index') }}" class="hover:text-gray-700">Formation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $session->title }}</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $cpp = $session->cost_per_participant;
@endphp
<div class="space-y-4">

    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $session->title }}</h1>
        <p class="text-sm text-gray-500">
            {{ $session->competence ?? 'Compétence non précisée' }} · {{ $session->provider ?? 'Organisme —' }}
            · {{ optional($session->start_date)->format('d/m/Y') ?? '—' }}{{ $session->end_date ? ' → '.$session->end_date->format('d/m/Y') : '' }}
            · <span class="font-semibold">{{ $session->statusLabel() }}</span>
            @if($session->cost !== null) · Coût {{ number_format((float) $session->cost, 0, ',', ' ') }} F{{ $cpp ? ' ('.number_format($cpp, 0, ',', ' ').' F/pers.)' : '' }}@endif
        </p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Inscription --}}
    @can('rh.employees.manage')
    <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-sm font-semibold">Inscrire un participant</div>
    <form method="POST" action="{{ route('rh.formations.participants.store', $session) }}" class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] p-4 flex items-end gap-3 flex-wrap">
        @csrf
        <div class="min-w-[240px]">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié</label>
            <select name="employee_id" class="w-full h-8 py-0 px-2 border border-gray-400 rounded-[3px] text-[13px]" required>
                <option value="">—</option>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
            </select>
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-4 h-8 rounded-[3px]">+ Inscrire</button>
    </form>
    @endcan

    {{-- Participants --}}
    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248] text-white">
                <tr>
                    <th class="{{ $th }} text-left">Salarié</th>
                    <th class="{{ $th }} text-center">Présence</th>
                    <th class="{{ $th }} text-center">Note /20</th>
                    <th class="{{ $th }} text-center">Acquis</th>
                    <th class="{{ $th }} text-left">Certificat</th>
                    <th class="{{ $th }} text-left">Échéance</th>
                    <th class="{{ $th }}"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($session->participants as $p)
                <tr class="{{ $p->certificateExpiringSoon() ? 'bg-amber-50/40' : '' }}">
                    <td class="px-3 py-1.5 font-medium">{{ $p->employee?->full_name ?? '—' }}</td>
                    <td class="px-2 py-1 text-center">
                        <select form="pf-{{ $p->id }}" name="status" class="h-7 py-0 px-1 border border-gray-300 rounded-[3px] text-[12px]">
                            @foreach(\App\Models\TrainingParticipant::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected($p->status===$k)>{{ $lbl }}</option>@endforeach
                        </select>
                    </td>
                    <td class="px-2 py-1 text-center"><input form="pf-{{ $p->id }}" type="number" step="0.5" min="0" max="20" name="score" value="{{ $p->score }}" class="w-16 h-7 px-1 border border-gray-300 rounded-[3px] text-[12px] text-center"></td>
                    <td class="px-2 py-1 text-center"><input form="pf-{{ $p->id }}" type="checkbox" name="passed" value="1" @checked($p->passed) class="rounded border-gray-400"></td>
                    <td class="px-2 py-1"><input form="pf-{{ $p->id }}" name="certificate_number" value="{{ $p->certificate_number }}" class="w-28 h-7 px-1 border border-gray-300 rounded-[3px] text-[12px]" placeholder="N°"></td>
                    <td class="px-2 py-1"><input form="pf-{{ $p->id }}" type="date" name="certificate_expiry" value="{{ optional($p->certificate_expiry)->format('Y-m-d') }}" class="h-7 px-1 border border-gray-300 rounded-[3px] text-[12px]">{{ $p->certificateExpiringSoon() ? ' ⚠' : '' }}</td>
                    <td class="px-2 py-1 text-right whitespace-nowrap">
                        @can('rh.employees.manage')
                        <button type="submit" form="pf-{{ $p->id }}" class="text-emerald-700 hover:underline text-[12px] font-semibold mr-2">Enregistrer</button>
                        <button type="submit" form="pd-{{ $p->id }}" onclick="return confirm('Retirer ce participant ?');" class="text-red-500 hover:text-red-700 text-[12px]">✕</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucun participant inscrit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Formulaires hors table (référencés par attribut form=) --}}
    @can('rh.employees.manage')
    @foreach($session->participants as $p)
        <form id="pf-{{ $p->id }}" method="POST" action="{{ route('rh.formations.participants.update', [$session, $p]) }}" class="hidden">@csrf @method('PUT')</form>
        <form id="pd-{{ $p->id }}" method="POST" action="{{ route('rh.formations.participants.destroy', [$session, $p]) }}" class="hidden">@csrf @method('DELETE')</form>
    @endforeach
    @endcan

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Participants : <span class="text-white font-semibold">{{ $session->participants->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
