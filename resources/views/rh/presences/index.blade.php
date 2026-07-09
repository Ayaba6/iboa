@extends('layouts.erp')
@section('title', 'Présences & Absences')

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Présences & Absences</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Présences & Absences</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $period->locale('fr')->isoFormat('MMMM YYYY') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('rh.presences.create', ['date' => today()->toDateString()]) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Saisir présences
            </a>
            <a href="{{ route('rh.presences.export', ['year' => $year, 'month' => $month, 'department_id' => $deptId]) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors"
               data-loading data-loading-text="Export en cours…">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Exporter CSV
            </a>
        </div>
    </div>

    {{-- ── KPIs ──────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Présences', 'value' => $stats['present'],  'icon' => '✅', 'bg' => 'bg-green-50',  'text' => 'text-green-700'],
            ['label' => 'Absences',  'value' => $stats['absent'],   'icon' => '❌', 'bg' => 'bg-red-50',    'text' => 'text-red-700'],
            ['label' => 'Congés',    'value' => $stats['conge'],    'icon' => '🏖️', 'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
            ['label' => 'Maladies',  'value' => $stats['maladie'],  'icon' => '🏥', 'bg' => 'bg-orange-50', 'text' => 'text-orange-700'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center text-xl flex-shrink-0">
                {{ $kpi['icon'] }}
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold {{ $kpi['text'] }}">{{ $kpi['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Filtres ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            @php
                $prev = \Carbon\Carbon::createFromDate($year, $month, 1)->subMonth();
                $next = \Carbon\Carbon::createFromDate($year, $month, 1)->addMonth();
            @endphp
            <div class="flex items-center gap-1">
                <a href="{{ route('rh.presences.index', ['year' => $prev->year, 'month' => $prev->month, 'department_id' => $deptId]) }}"
                   class="p-2 rounded-[4px] border border-gray-200 hover:bg-gray-50 text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <select name="month" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected($m == $month)>
                            {{ \Carbon\Carbon::createFromDate(2000,$m,1)->locale('fr')->isoFormat('MMMM') }}
                        </option>
                    @endforeach
                </select>
                <select name="year" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    @foreach(range(now()->year - 2, now()->year + 1) as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
                <a href="{{ route('rh.presences.index', ['year' => $next->year, 'month' => $next->month, 'department_id' => $deptId]) }}"
                   class="p-2 rounded-[4px] border border-gray-200 hover:bg-gray-50 text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <select name="department_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les départements</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected($dept->id == $deptId)>{{ $dept->name }}</option>
                @endforeach
            </select>

            <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher un employé…"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 w-44"/>

            <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700 transition-colors">
                Filtrer
            </button>
        </form>
    </div>

    {{-- ── Grille mensuelle ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="sticky left-0 bg-gray-50 z-10 px-3 py-1.5 text-left font-semibold text-gray-600 min-w-[200px] border-r border-gray-200">
                            Employé
                        </th>
                        @foreach($days as $day)
                        @php
                            $isWeekend = $day->isWeekend();
                            $isToday   = $day->isToday();
                        @endphp
                        <th class="px-0.5 py-2 text-center font-medium w-8 min-w-[2rem]
                            {{ $isWeekend ? 'bg-gray-100 text-gray-400' : 'text-gray-600' }}
                            {{ $isToday ? 'ring-2 ring-inset ring-indigo-400' : '' }}">
                            <div class="text-[10px] leading-none">{{ $day->locale('fr')->isoFormat('ddd') }}</div>
                            <div class="font-bold leading-tight">{{ $day->day }}</div>
                        </th>
                        @endforeach
                        <th class="px-2 py-3 text-center font-bold text-green-700 min-w-[36px] border-l border-gray-200 bg-green-50">P</th>
                        <th class="px-2 py-3 text-center font-bold text-red-600 min-w-[36px] bg-red-50">A</th>
                        <th class="px-2 py-3 text-center font-bold text-blue-600 min-w-[36px] bg-blue-50">C</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($employees as $emp)
                    @php
                        $empAtt   = $attendances->get($emp->id, collect());
                        $presents = 0; $absents = 0; $conges = 0;
                        foreach ($days as $d) {
                            $att = $empAtt->get($d->format('Y-m-d'));
                            if ($att) {
                                match ($att->status) {
                                    'present', 'mission' => $presents++,
                                    'demi_j'             => $presents += 0.5,
                                    'absent'             => $absents++,
                                    'conge', 'maladie'   => $conges++,
                                    default              => null,
                                };
                            }
                        }
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="sticky left-0 bg-white hover:bg-gray-50 z-10 px-3 py-1.5 border-r border-gray-200">
                            <a href="{{ route('rh.presences.employee', [$emp, 'year' => $year, 'month' => $month]) }}"
                               class="flex items-center gap-2 group">
                                <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center text-[10px] font-bold text-emerald-700 flex-shrink-0">
                                    {{ strtoupper(substr($emp->first_name, 0, 1) . substr($emp->last_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate group-hover:text-emerald-700 leading-tight text-xs">
                                        {{ $emp->last_name }} {{ $emp->first_name }}
                                    </p>
                                    <p class="text-gray-400 truncate" style="font-size:10px">{{ $emp->matricule }}</p>
                                </div>
                            </a>
                        </td>

                        @foreach($days as $day)
                        @php
                            $dateStr  = $day->format('Y-m-d');
                            $att      = $empAtt->get($dateStr);
                            $isWeek   = $day->isWeekend();
                            $colorMap = [
                                'present' => 'bg-green-100 text-green-700 hover:bg-green-200',
                                'absent'  => 'bg-red-100 text-red-700 hover:bg-red-200',
                                'conge'   => 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                                'maladie' => 'bg-orange-100 text-orange-700 hover:bg-orange-200',
                                'mission' => 'bg-purple-100 text-purple-700 hover:bg-purple-200',
                                'ferie'   => 'bg-gray-100 text-gray-500',
                                'weekend' => 'bg-gray-50 text-gray-300',
                                'demi_j'  => 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200',
                            ];
                        @endphp
                        <td class="px-0.5 py-1 text-center {{ $isWeek ? 'bg-gray-50' : '' }}">
                            @if($att)
                            <a href="{{ route('rh.presences.create', ['date' => $dateStr]) }}"
                               title="{{ $att->status_label }}{{ $att->note ? ' — '.$att->note : '' }}">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded text-[10px] font-bold {{ $colorMap[$att->status] ?? 'bg-gray-100 text-gray-500' }} transition-colors">
                                    {{ strtoupper(substr(\App\Models\Attendance::STATUSES[$att->status]['label'] ?? $att->status, 0, 1)) }}
                                </span>
                            </a>
                            @else
                            <a href="{{ route('rh.presences.create', ['date' => $dateStr]) }}" title="Saisir">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded text-gray-200 hover:bg-gray-100 hover:text-gray-400 transition-colors text-[10px]">—</span>
                            </a>
                            @endif
                        </td>
                        @endforeach

                        <td class="px-2 py-2 text-center font-bold text-green-700 border-l border-gray-200 bg-green-50/50">{{ $presents }}</td>
                        <td class="px-2 py-2 text-center font-bold text-red-600 bg-red-50/50">{{ $absents }}</td>
                        <td class="px-2 py-2 text-center font-bold text-blue-600 bg-blue-50/50">{{ $conges }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($days) + 4 }}" class="px-6 py-16 text-center text-gray-400 text-sm">
                            <p class="text-4xl mb-3">👥</p>
                            Aucun employé actif trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $employees->links() }}
        </div>
        @endif

        {{-- Légende --}}
        <div class="px-3 py-1.5 border-t border-gray-100 bg-gray-50 flex flex-wrap gap-x-4 gap-y-2">
            @foreach(\App\Models\Attendance::STATUSES as $key => $s)
            @php
                $lc = match($key) {
                    'present' => 'bg-green-100 text-green-700',
                    'absent'  => 'bg-red-100 text-red-700',
                    'conge'   => 'bg-blue-100 text-blue-700',
                    'maladie' => 'bg-orange-100 text-orange-700',
                    'mission' => 'bg-purple-100 text-purple-700',
                    'ferie'   => 'bg-gray-100 text-gray-500',
                    'weekend' => 'bg-gray-50 text-gray-400',
                    'demi_j'  => 'bg-yellow-100 text-yellow-700',
                    default   => 'bg-gray-100 text-gray-500',
                };
            @endphp
            <span class="flex items-center gap-1 text-xs text-gray-600">
                <span class="w-5 h-5 rounded flex items-center justify-center font-bold text-[10px] {{ $lc }}">
                    {{ strtoupper(substr($s['label'], 0, 1)) }}
                </span>
                {{ $s['label'] }}
            </span>
            @endforeach
        </div>
    </div>

</div>
@endsection
