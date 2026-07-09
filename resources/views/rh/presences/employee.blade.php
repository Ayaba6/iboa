@extends('layouts.erp')
@section('title', 'Présences — ' . $employee->full_name)

@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.presences.index', ['year' => $year, 'month' => $month]) }}" class="hover:text-gray-700">Présences</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $employee->full_name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ── Header ────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-[16px] font-bold text-emerald-700">
                {{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}
            </div>
            <div>
                <h1 class="text-[16px] font-bold text-gray-900">{{ $employee->full_name }}</h1>
                <p class="text-sm text-gray-500">{{ $employee->matricule }} · {{ $employee->job_title }} · {{ $period->locale('fr')->isoFormat('MMMM YYYY') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @php
                $prev = \Carbon\Carbon::createFromDate($year, $month, 1)->subMonth();
                $next = \Carbon\Carbon::createFromDate($year, $month, 1)->addMonth();
            @endphp
            <a href="{{ route('rh.presences.employee', [$employee, 'year' => $prev->year, 'month' => $prev->month]) }}"
               class="p-2 rounded-[4px] border border-gray-200 hover:bg-gray-50 text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <span class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-[4px] text-sm font-medium text-gray-700">
                {{ $period->locale('fr')->isoFormat('MMMM YYYY') }}
            </span>
            <a href="{{ route('rh.presences.employee', [$employee, 'year' => $next->year, 'month' => $next->month]) }}"
               class="p-2 rounded-[4px] border border-gray-200 hover:bg-gray-50 text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- ── Stats ──────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach([
            ['label' => 'Jours présents',  'value' => $stats['present'],       'icon' => '✅', 'bg' => 'bg-green-50',  'text' => 'text-green-700'],
            ['label' => 'Absences',         'value' => $stats['absent'],        'icon' => '❌', 'bg' => 'bg-red-50',    'text' => 'text-red-700'],
            ['label' => 'Congés',           'value' => $stats['conge'],         'icon' => '🏖️', 'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
            ['label' => 'Maladie',          'value' => $stats['maladie'],       'icon' => '🏥', 'bg' => 'bg-orange-50', 'text' => 'text-orange-700'],
            ['label' => 'Heures trav.',     'value' => number_format($stats['worked_hours'],1).'h', 'icon' => '⏱️', 'bg' => 'bg-[#eef5f0]', 'text' => 'text-emerald-800'],
            ['label' => 'H. supplémentaires','value' => number_format($stats['overtime_hours'],1).'h', 'icon' => '⚡', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-700'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">{{ $kpi['icon'] }}</span>
                <p class="text-xs text-gray-500 leading-tight">{{ $kpi['label'] }}</p>
            </div>
            <p class="text-[17px] font-bold {{ $kpi['text'] }}">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Calendrier ────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Calendrier du mois</h2>
        </div>
        <div class="p-5">
            {{-- En-têtes jours --}}
            <div class="grid grid-cols-7 gap-1 mb-2">
                @foreach(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d)
                <div class="text-center text-xs font-semibold text-gray-500 py-1">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Jours du mois --}}
            @php
                $firstDayOfWeek = (int) $period->copy()->startOfMonth()->dayOfWeekIso - 1; // 0=Lun
                $daysInMonth = $period->daysInMonth;
            @endphp
            <div class="grid grid-cols-7 gap-1">
                {{-- Cellules vides avant le 1er --}}
                @for($i = 0; $i < $firstDayOfWeek; $i++)
                <div></div>
                @endfor

                @foreach($days as $day)
                @php
                    $dateStr = $day->format('Y-m-d');
                    $att     = $attendances->get($dateStr);
                    $isWknd  = $day->isWeekend();
                    $isToday = $day->isToday();
                    $cellBg  = match($att?->status) {
                        'present'          => 'bg-green-100 border-green-200 text-green-800',
                        'absent'           => 'bg-red-100 border-red-200 text-red-800',
                        'conge'            => 'bg-blue-100 border-blue-200 text-blue-800',
                        'maladie'          => 'bg-orange-100 border-orange-200 text-orange-800',
                        'mission'          => 'bg-purple-100 border-purple-200 text-purple-800',
                        'ferie'            => 'bg-gray-100 border-gray-300 text-gray-500',
                        'weekend'          => 'bg-gray-50 border-gray-200 text-gray-400',
                        'demi_j'           => 'bg-yellow-100 border-yellow-200 text-yellow-800',
                        default            => $isWknd ? 'bg-gray-50 border-gray-100 text-gray-400' : 'bg-white border-gray-200 text-gray-400',
                    };
                @endphp
                <a href="{{ route('rh.presences.create', ['date' => $dateStr]) }}"
                   class="relative border rounded-[4px] p-2 text-center transition-all hover:shadow-sm hover:scale-105 {{ $cellBg }} {{ $isToday ? 'ring-2 ring-indigo-400' : '' }}">
                    <div class="text-sm font-bold">{{ $day->day }}</div>
                    @if($att)
                    <div class="text-[10px] leading-tight mt-0.5 font-medium">
                        {{ \App\Models\Attendance::STATUSES[$att->status]['icon'] ?? '' }}
                    </div>
                    @if($att->worked_hours)
                    <div class="text-[9px] text-gray-500 mt-0.5">{{ number_format($att->worked_hours,1) }}h</div>
                    @endif
                    @else
                    <div class="text-[10px] text-gray-300 mt-0.5">—</div>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Détail journalier ─────────────────────────────────────────────────── --}}
    @if($attendances->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Détail des pointages</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($days->filter(fn($d) => $attendances->has($d->format('Y-m-d'))) as $day)
            @php
                $att = $attendances->get($day->format('Y-m-d'));
                $colors = [
                    'present' => 'bg-green-100 text-green-700',
                    'absent'  => 'bg-red-100 text-red-700',
                    'conge'   => 'bg-blue-100 text-blue-700',
                    'maladie' => 'bg-orange-100 text-orange-700',
                    'mission' => 'bg-purple-100 text-purple-700',
                    'ferie'   => 'bg-gray-100 text-gray-500',
                    'weekend' => 'bg-gray-50 text-gray-400',
                    'demi_j'  => 'bg-yellow-100 text-yellow-700',
                ];
            @endphp
            <div class="px-5 py-3 flex items-center gap-4">
                <div class="w-24 flex-shrink-0 text-sm text-gray-600 font-medium">
                    {{ $day->locale('fr')->isoFormat('ddd D MMM') }}
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium {{ $colors[$att->status] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $att->status_icon }} {{ $att->status_label }}
                </span>
                @if($att->arrival_time || $att->departure_time)
                <span class="text-xs text-gray-500">
                    {{ $att->arrival_time ?? '—' }} → {{ $att->departure_time ?? '—' }}
                    @if($att->worked_hours) · <strong>{{ number_format($att->worked_hours,1) }}h</strong> @endif
                </span>
                @endif
                @if($att->overtime_hours > 0)
                <span class="text-xs text-yellow-600 font-medium">+{{ number_format($att->overtime_hours,1) }}h supp.</span>
                @endif
                @if($att->note)
                <span class="text-xs text-gray-400 italic truncate">{{ $att->note }}</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
