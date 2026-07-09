<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    // ─── Index : grille mensuelle ─────────────────────────────────────────────

    public function index(Request $request)
    {
        $companyId  = Auth::user()->company_id;
        $year       = (int) $request->get('year',  now()->year);
        $month      = (int) $request->get('month', now()->month);
        $deptId     = $request->get('department_id');
        $search     = $request->get('search');

        $period = Carbon::createFromDate($year, $month, 1);

        // Employés actifs
        $employeesQuery = Employee::where('company_id', $companyId)
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($deptId) {
            $employeesQuery->where('department_id', $deptId);
        }
        if ($search) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%$search%")
                  ->orWhere('first_name', 'like', "%$search%")
                  ->orWhere('matricule', 'like', "%$search%");
            });
        }

        // Pagination : la grille mensuelle (1 ligne/employé × 28-31 colonnes)
        // devient illisible et lourde au-delà de ~30 employés par page.
        $employees = $employeesQuery->paginate(30)->withQueryString();

        // Présences du mois — uniquement pour les employés de la page courante
        $attendances = Attendance::where('company_id', $companyId)
            ->forMonth($year, $month)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn($rows) => $rows->keyBy(fn($a) => $a->date->format('Y-m-d')));

        // Jours du mois
        $daysInMonth = $period->daysInMonth;
        $days = collect(range(1, $daysInMonth))->map(fn($d) => Carbon::createFromDate($year, $month, $d));

        // Statistiques globales du mois
        $stats = $this->monthStats($companyId, $year, $month);

        $departments = Department::where('company_id', $companyId)->orderBy('name')->get();

        return view('rh.presences.index', compact(
            'employees', 'attendances', 'days', 'period',
            'year', 'month', 'deptId', 'search',
            'stats', 'departments'
        ));
    }

    // ─── Saisie journalière (GET) ─────────────────────────────────────────────

    public function create(Request $request)
    {
        $companyId  = Auth::user()->company_id;
        $date       = $request->get('date', today()->toDateString());
        $deptId     = $request->get('department_id');

        $employeesQuery = Employee::where('company_id', $companyId)
            ->where('status', 'actif')
            ->with('department')
            ->orderBy('last_name');

        if ($deptId) {
            $employeesQuery->where('department_id', $deptId);
        }

        $employees = $employeesQuery->get();

        // Présences existantes pour cette date
        $existing = Attendance::where('company_id', $companyId)
            ->where('date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $departments = Department::where('company_id', $companyId)->orderBy('name')->get();
        $statuses    = Attendance::STATUSES;

        return view('rh.presences.create', compact(
            'employees', 'existing', 'date', 'deptId', 'departments', 'statuses'
        ));
    }

    // ─── Saisie journalière (POST) ────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'date'                    => 'required|date',
            'entries'                 => 'required|array',
            'entries.*.employee_id'   => 'required|integer',
            'entries.*.status'        => 'required|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'entries.*.arrival_time'  => 'nullable|date_format:H:i',
            'entries.*.departure_time'=> 'nullable|date_format:H:i',
            'entries.*.overtime_hours'=> 'nullable|numeric|min:0|max:12',
            'entries.*.note'          => 'nullable|string|max:500',
        ]);

        $companyId = Auth::user()->company_id;
        $date      = $request->date;
        $userId    = Auth::id();
        $now       = now();

        DB::transaction(function () use ($request, $companyId, $date, $userId, $now) {
            foreach ($request->entries as $entry) {
                $employeeId = $entry['employee_id'];
                $arrivalStr   = $entry['arrival_time']   ?? null;
                $departureStr = $entry['departure_time'] ?? null;

                // Calcul des heures travaillées
                $workedHours = null;
                if ($arrivalStr && $departureStr) {
                    $arrival   = Carbon::createFromFormat('H:i', $arrivalStr);
                    $departure = Carbon::createFromFormat('H:i', $departureStr);
                    if ($departure->greaterThan($arrival)) {
                        $workedHours = round($departure->diffInMinutes($arrival) / 60, 2);
                    }
                }

                Attendance::updateOrCreate(
                    ['company_id' => $companyId, 'employee_id' => $employeeId, 'date' => $date],
                    [
                        'status'         => $entry['status'],
                        'arrival_time'   => $arrivalStr,
                        'departure_time' => $departureStr,
                        'worked_hours'   => $workedHours,
                        'overtime_hours' => $entry['overtime_hours'] ?? 0,
                        'note'           => $entry['note'] ?? null,
                        'created_by'     => $userId,
                        'updated_at'     => $now,
                    ]
                );
            }
        });

        return redirect()->route('rh.presences.index', [
            'year'  => Carbon::parse($date)->year,
            'month' => Carbon::parse($date)->month,
        ])->with('success', 'Présences du ' . Carbon::parse($date)->format('d/m/Y') . ' enregistrées.');
    }

    // ─── Fiche employé ────────────────────────────────────────────────────────

    public function employee(Request $request, Employee $employee)
    {
        $companyId = Auth::user()->company_id;
        abort_if($employee->company_id !== $companyId, 403);

        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $period      = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $period->daysInMonth;
        $days        = collect(range(1, $daysInMonth))->map(fn($d) => Carbon::createFromDate($year, $month, $d));

        $attendances = Attendance::where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->forMonth($year, $month)
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        // Statistiques
        $stats = [
            'present'       => $attendances->where('status', 'present')->count()
                             + $attendances->where('status', 'demi_j')->count() * 0.5
                             + $attendances->where('status', 'mission')->count(),
            'absent'        => $attendances->where('status', 'absent')->count(),
            'conge'         => $attendances->where('status', 'conge')->count(),
            'maladie'       => $attendances->where('status', 'maladie')->count(),
            'worked_hours'  => $attendances->sum('worked_hours'),
            'overtime_hours'=> $attendances->sum('overtime_hours'),
        ];

        return view('rh.presences.employee', compact(
            'employee', 'attendances', 'days', 'period',
            'year', 'month', 'stats'
        ));
    }

    // ─── Export Excel ─────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $year      = (int) $request->get('year',  now()->year);
        $month     = (int) $request->get('month', now()->month);
        $deptId    = $request->get('department_id');

        $period    = Carbon::createFromDate($year, $month, 1);
        $monthName = $period->locale('fr')->isoFormat('MMMM YYYY');

        $employeesQuery = Employee::where('company_id', $companyId)
            ->where('status', 'actif')
            ->with('department')
            ->orderBy('last_name');

        if ($deptId) {
            $employeesQuery->where('department_id', $deptId);
        }

        $employees = $employeesQuery->get();
        $daysInMonth = $period->daysInMonth;
        $days = range(1, $daysInMonth);

        $attendances = Attendance::where('company_id', $companyId)
            ->forMonth($year, $month)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn($rows) => $rows->keyBy(fn($a) => (int) $a->date->format('j')));

        // Génération CSV en mémoire
        $filename = "presences_{$year}_{$month}.csv";
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($employees, $attendances, $days, $year, $month, $monthName) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // BOM UTF-8

            // En-tête
            $header = ['Matricule', 'Nom', 'Prénom', 'Département'];
            foreach ($days as $d) {
                $header[] = $d;
            }
            $header = array_merge($header, ['Présents', 'Absents', 'Congés', 'Maladies', 'Heures trav.', 'H. supp.']);
            fputcsv($f, $header, ';');

            foreach ($employees as $emp) {
                $empAtt = $attendances->get($emp->id, collect());
                $row = [$emp->matricule, $emp->last_name, $emp->first_name, $emp->department?->name ?? ''];

                $presents = $absents = $conges = $maladies = 0;
                $workedH  = $overtimeH = 0;

                foreach ($days as $d) {
                    $att = $empAtt->get($d);
                    if ($att) {
                        $row[]      = Attendance::STATUSES[$att->status]['label'] ?? $att->status;
                        $workedH   += $att->worked_hours ?? 0;
                        $overtimeH += $att->overtime_hours ?? 0;
                        match ($att->status) {
                            'present', 'mission' => $presents++,
                            'demi_j'             => $presents += 0.5,
                            'absent'             => $absents++,
                            'conge'              => $conges++,
                            'maladie'            => $maladies++,
                            default              => null,
                        };
                    } else {
                        $row[] = '';
                    }
                }

                $row[] = $presents;
                $row[] = $absents;
                $row[] = $conges;
                $row[] = $maladies;
                $row[] = number_format($workedH, 1, '.', '');
                $row[] = number_format($overtimeH, 1, '.', '');
                fputcsv($f, $row, ';');
            }

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function monthStats(int $companyId, int $year, int $month): array
    {
        $counts = Attendance::where('company_id', $companyId)
            ->forMonth($year, $month)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'present'  => ($counts['present']  ?? 0) + ($counts['mission'] ?? 0),
            'absent'   => $counts['absent']   ?? 0,
            'conge'    => $counts['conge']    ?? 0,
            'maladie'  => $counts['maladie']  ?? 0,
            'demi_j'   => $counts['demi_j']   ?? 0,
            'total'    => $counts->sum(),
        ];
    }
}
