@extends('layouts.erp')
@section('title', 'Saisie présences du ' . \Carbon\Carbon::parse($date)->format('d/m/Y'))

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.presences.index') }}" class="hover:text-gray-700">Présences</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Saisie du {{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</span>
@endsection

@section('content')
<div class="space-y-3" x-data="presenceSaisie()">

    <x-validation-errors />

    {{-- ── Header ────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Saisie des présences</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('rh.presences.store') }}" id="saisie-form">
        @csrf

        {{-- ── Sélection date + département ────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-4 items-end mb-5">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
                       onchange="window.location.href='{{ route('rh.presences.create') }}?date='+this.value+'&department_id={{ $deptId }}'"/>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Département</label>
                <select name="dept_filter" onchange="window.location.href='{{ route('rh.presences.create') }}?date={{ $date }}&department_id='+this.value"
                        class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Tous les départements</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected($dept->id == $deptId)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Action rapide : marquer tous comme présents --}}
            <button type="button" @click="setAll('present')"
                    class="px-3 py-1.5 bg-green-50 border border-green-200 text-green-700 rounded-[4px] text-sm font-medium hover:bg-green-100 transition-colors">
                ✅ Tous présents
            </button>
            <button type="button" @click="setAll('weekend')"
                    class="px-3 py-1.5 bg-gray-50 border border-gray-200 text-gray-600 rounded-[4px] text-sm font-medium hover:bg-gray-100 transition-colors">
                😴 Marquer week-end
            </button>
        </div>

        {{-- ── Tableau de saisie ────────────────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 text-sm">{{ $employees->count() }} employé(s)</h2>
                <span class="text-xs text-gray-400">Les champs Heure sont optionnels</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[220px]">Employé</th>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[180px]">Statut</th>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[100px]">Arrivée</th>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[100px]">Départ</th>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[80px]">H. supp.</th>
                            <th class="px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[200px]">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($employees as $i => $emp)
                        @php
                            $ex = $existing->get($emp->id);
                        @endphp
                        <input type="hidden" name="entries[{{ $i }}][employee_id]" value="{{ $emp->id }}"/>
                        <tr class="hover:bg-gray-50 transition-colors">
                            {{-- Employé --}}
                            <td class="px-3 py-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-xs font-bold text-emerald-700 flex-shrink-0">
                                        {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $emp->last_name }} {{ $emp->first_name }}</p>
                                        <p class="text-xs text-gray-400">{{ $emp->matricule }} · {{ $emp->department?->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Statut --}}
                            <td class="px-3 py-1.5">
                                <select name="entries[{{ $i }}][status]"
                                        x-model="rows[{{ $i }}]"
                                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        :class="{
                                            'bg-green-50 border-green-300': rows[{{ $i }}] === 'present',
                                            'bg-red-50 border-red-300':    rows[{{ $i }}] === 'absent',
                                            'bg-blue-50 border-blue-300':  rows[{{ $i }}] === 'conge' || rows[{{ $i }}] === 'maladie',
                                            'bg-gray-50 border-gray-200':  rows[{{ $i }}] === 'weekend' || rows[{{ $i }}] === 'ferie',
                                            'bg-purple-50 border-purple-300': rows[{{ $i }}] === 'mission',
                                            'bg-yellow-50 border-yellow-300': rows[{{ $i }}] === 'demi_j',
                                        }">
                                    @foreach($statuses as $key => $s)
                                        <option value="{{ $key }}" @selected(($ex?->status ?? 'present') === $key)>
                                            {{ $s['icon'] }} {{ $s['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Arrivée --}}
                            <td class="px-3 py-1.5">
                                <input type="time" name="entries[{{ $i }}][arrival_time]"
                                       value="{{ $ex?->arrival_time ?? '' }}"
                                       class="w-full border border-gray-300 rounded-[4px] px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"/>
                            </td>

                            {{-- Départ --}}
                            <td class="px-3 py-1.5">
                                <input type="time" name="entries[{{ $i }}][departure_time]"
                                       value="{{ $ex?->departure_time ?? '' }}"
                                       class="w-full border border-gray-300 rounded-[4px] px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"/>
                            </td>

                            {{-- H. supp. --}}
                            <td class="px-3 py-1.5">
                                <input type="number" name="entries[{{ $i }}][overtime_hours]"
                                       value="{{ $ex?->overtime_hours ?? 0 }}"
                                       min="0" max="12" step="0.5"
                                       class="w-full border border-gray-300 rounded-[4px] px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"/>
                            </td>

                            {{-- Note --}}
                            <td class="px-3 py-1.5">
                                <input type="text" name="entries[{{ $i }}][note]"
                                       value="{{ $ex?->note ?? '' }}"
                                       placeholder="Note optionnelle…"
                                       maxlength="200"
                                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"/>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Boutons --}}
            <div class="px-5 py-4 border-t border-gray-100 flex justify-between items-center">
                <a href="{{ route('rh.presences.index', ['year' => \Carbon\Carbon::parse($date)->year, 'month' => \Carbon\Carbon::parse($date)->month]) }}"
                   class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors shadow-sm">
                    💾 Enregistrer les présences
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function presenceSaisie() {
    return {
        rows: {
            @foreach($employees as $i => $emp)
            {{ $i }}: '{{ $existing->get($emp->id)?->status ?? 'present' }}',
            @endforeach
        },
        setAll(status) {
            for (let key in this.rows) {
                this.rows[key] = status;
            }
        }
    };
}
</script>
@endpush
@endsection
