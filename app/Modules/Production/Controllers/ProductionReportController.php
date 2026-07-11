<?php

namespace App\Modules\Production\Controllers;

use App\Exports\Reports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ProductionReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.report.view');
    }

    private const TYPES = [
        'production'   => 'Production journalière',
        'consommation' => 'Consommation matière',
        'rendement'    => 'Rendement par OF',
        'pertes'       => 'Pertes & chutes',
        'couts'        => 'Coût de revient',
        'cout_produit' => 'Coût standard / réel par produit',
        'client'       => 'Production par client',
        'machine'      => 'Production par machine',
        'operateur'    => 'Pertes par opérateur',
        'charge'       => 'Plan de charge (centres)',
        'maintenance'  => 'Maintenance machines',
        'qualite'      => 'Contrôles qualité',
        'non_conformites' => 'Non-conformités',
        'of_retard'    => 'OF en retard',
        'of_statut'    => 'OF par statut',
        'perf_ligne'   => 'Performance par ligne',
        'stock_mp_pf'  => 'Stock matière / produit fini',
    ];

    public function index(Request $request): mixed
    {
        $type = $request->input('type', 'production');
        if (! array_key_exists($type, self::TYPES)) {
            $type = 'production';
        }
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));
        $f    = Carbon::parse($from)->startOfDay();
        $t    = Carbon::parse($to)->endOfDay();

        $report = $this->build($type, $f, $t);

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new GenericTableExport($report['title'], $report['headers'], $report['rows'], $from, $to, $report['numeric'], [], $report['totals']),
                'production-' . $type . '-' . now()->format('Ymd') . '.xlsx'
            );
        }

        if ($request->input('export') === 'pdf') {
            $company = currentCompany();
            $pdf = Pdf::loadView('production.reports.pdf', compact('report', 'company', 'from', 'to'))->setPaper('a4', 'landscape');

            return $pdf->download('production-' . $type . '-' . now()->format('Ymd') . '.pdf');
        }

        $types = self::TYPES;

        return view('production.reports.index', compact('report', 'type', 'types', 'from', 'to'));
    }

    /** @return array{title:string,headers:array,rows:array,numeric:array,totals:?array} */
    private function build(string $type, Carbon $f, Carbon $t): array
    {
        return match ($type) {
            'consommation' => $this->consommation($f, $t),
            'rendement'    => $this->rendement($f, $t),
            'pertes'       => $this->pertes($f, $t),
            'couts'        => $this->couts($f, $t),
            'cout_produit' => $this->coutProduit($f, $t),
            'client'       => $this->client($f, $t),
            'machine'      => $this->machine($f, $t),
            'operateur'    => $this->operateur($f, $t),
            'charge'       => $this->charge(),
            'maintenance'  => $this->maintenance($f, $t),
            'qualite'      => $this->qualite($f, $t),
            'non_conformites' => $this->nonConformites(),
            'of_retard'    => $this->ofEnRetard(),
            'of_statut'    => $this->ofParStatut(),
            'perf_ligne'   => $this->performanceLigne($f, $t),
            'stock_mp_pf'  => $this->stockMpPf(),
            default        => $this->production($f, $t),
        };
    }

    /** [X3 §26] OF en retard : date fin prévue dépassée, statut actif. */
    private function ofEnRetard(): array
    {
        $rows = \App\Modules\Production\Models\ProductionOrder::with(['client:id,name', 'product:id,name', 'productionLine:id,name'])
            ->enRetard()
            ->orderBy('date_fin_prevue')->get();

        $data = $rows->map(fn ($o) => [
            $o->number, $o->client?->name ?? '—', $o->product?->name ?? '—',
            $o->statusLabel(), $o->date_fin_prevue?->format('d/m/Y'),
            (int) $o->date_fin_prevue?->diffInDays(today()),
            (float) $o->quantity_requested, (float) $o->quantity_produced,
        ])->all();

        return [
            'title'   => 'OF en retard',
            'headers' => ['N° OF', 'Client', 'Article', 'Statut', 'Fin prévue', 'Retard (j)', 'Qté demandée', 'Qté produite'],
            'rows'    => $data,
            'numeric' => [5, 6, 7],
            'totals'  => null,
        ];
    }

    /** [X3 §26] Répartition des OF par statut. */
    private function ofParStatut(): array
    {
        $rows = \App\Modules\Production\Models\ProductionOrder::selectRaw('status, COUNT(*) nb, SUM(quantity_requested) qd, SUM(quantity_produced) qp')
            ->groupBy('status')->orderByDesc('nb')->get();

        $data = $rows->map(fn ($r) => [
            (new \App\Modules\Production\Models\ProductionOrder(['status' => $r->status]))->statusLabel(),
            (int) $r->nb, (float) $r->qd, (float) $r->qp,
        ])->all();

        return [
            'title'   => 'OF par statut',
            'headers' => ['Statut', 'Nombre', 'Qté demandée', 'Qté produite'],
            'rows'    => $data,
            'numeric' => [1, 2, 3],
            'totals'  => ['TOTAL', (int) $rows->sum('nb'), (float) $rows->sum('qd'), (float) $rows->sum('qp')],
        ];
    }

    /** [X3 §26] Performance par ligne de production (période). */
    private function performanceLigne(Carbon $f, Carbon $t): array
    {
        $rows = \App\Modules\Production\Models\ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders', 'production_orders.id', '=', 'production_outputs.production_order_id')
            ->leftJoin('production_lines', 'production_lines.id', '=', 'production_orders.production_line_id')
            ->selectRaw('COALESCE(production_lines.name, "—") ligne, COUNT(DISTINCT production_orders.id) nof, SUM(production_outputs.quantity) q, SUM(production_outputs.total_meters) m')
            ->groupByRaw('production_lines.name')->orderByDesc('m')->get();

        $data = $rows->map(fn ($r) => [$r->ligne, (int) $r->nof, (float) $r->q, round((float) $r->m, 1)])->all();

        return [
            'title'   => 'Performance par ligne de production',
            'headers' => ['Ligne', 'OF', 'Quantité', 'Mètres'],
            'rows'    => $data,
            'numeric' => [1, 2, 3],
            'totals'  => ['TOTAL', (int) $rows->sum('nof'), (float) $rows->sum('q'), round((float) $rows->sum('m'), 1)],
        ];
    }

    /** [X3 §26] Stock matière première & produit fini par article. */
    private function stockMpPf(): array
    {
        $rows = \App\Models\ProductStock::join('products', 'products.id', '=', 'product_stocks.product_id')
            ->join('product_families', 'product_families.id', '=', 'products.family_id')
            ->whereIn('product_families.code', ['MP', 'BPRE', 'BGAL', 'PF'])
            ->selectRaw('product_families.code fam, products.name produit, SUM(product_stocks.quantity) q, SUM(product_stocks.reserved_quantity) r, SUM(product_stocks.quantity - product_stocks.reserved_quantity) dispo, MAX(products.stock_min) smin')
            ->groupByRaw('product_families.code, products.id, products.name')
            ->orderByRaw('product_families.code, products.name')->get();

        $data = $rows->map(fn ($r) => [
            $r->fam === 'PF' ? 'Produit fini' : 'Matière',
            $r->produit, (float) $r->q, (float) $r->r, (float) $r->dispo,
            (float) $r->smin > 0 && (float) $r->dispo < (float) $r->smin ? 'SOUS SEUIL' : 'OK',
        ])->all();

        return [
            'title'   => 'Stock matière première / produit fini',
            'headers' => ['Type', 'Article', 'Stock', 'Réservé', 'Disponible', 'Alerte'],
            'rows'    => $data,
            'numeric' => [2, 3, 4],
            'totals'  => null,
        ];
    }

    private function charge(): array
    {
        $plan = app(\App\Modules\Production\Services\PlanningService::class)->loadByWorkCenter(7);
        $data = collect($plan['rows'])->map(fn ($r) => [
            $r['name'], $r['ops'], $r['planned_h'], $r['capacity_h'], $r['occupation'],
        ])->all();

        return [
            'title'   => 'Plan de charge (centres de travail · 7 j)',
            'headers' => ['Centre', 'Opérations', 'Charge (h)', 'Capacité (h)', 'Occupation %'],
            'rows'    => $data,
            'numeric' => [1, 2, 3, 4],
            'totals'  => ['TOTAL', '', $plan['total_planned_h'], $plan['total_capacity_h'], ''],
        ];
    }

    private function maintenance(Carbon $f, Carbon $t): array
    {
        $rows = \App\Modules\Production\Models\MachineMaintenance::with('machine')
            ->whereBetween('updated_at', [$f, $t])->orderByDesc('id')->get();

        $data = $rows->map(fn ($m) => [
            $m->machine?->name ?? '—', $m->typeLabel(), $m->statusLabel(),
            round((float) $m->downtime_minutes / 60, 1), (int) $m->cost,
        ])->all();

        return [
            'title'   => 'Maintenance machines',
            'headers' => ['Machine', 'Type', 'Statut', 'Arrêt (h)', 'Coût (F)'],
            'rows'    => $data,
            'numeric' => [3, 4],
            'totals'  => ['TOTAL', '', '', round((float) $rows->sum('downtime_minutes') / 60, 1), (int) $rows->sum('cost')],
        ];
    }

    private function qualite(Carbon $f, Carbon $t): array
    {
        $rows = \App\Modules\Quality\Models\QualityInspection::with('product')
            ->whereBetween('inspected_at', [$f, $t])->orderByDesc('id')->get();

        $labels = ['reception' => 'Réception', 'en_cours' => 'En cours', 'produit_fini' => 'Produit fini'];
        $data = $rows->map(fn ($i) => [
            $i->reference, $labels[$i->type] ?? $i->type, $i->statusLabel(),
            round((float) $i->quantity_checked, 0), round((float) $i->quantity_rejected, 0),
        ])->all();

        return [
            'title'   => 'Contrôles qualité',
            'headers' => ['Réf.', 'Type', 'Verdict', 'Contrôlé', 'Rejeté'],
            'rows'    => $data,
            'numeric' => [3, 4],
            'totals'  => ['TOTAL', '', '', round((float) $rows->sum('quantity_checked'), 0), round((float) $rows->sum('quantity_rejected'), 0)],
        ];
    }

    private function nonConformites(): array
    {
        $rows = \App\Modules\Quality\Models\NonConformity::with('responsible')->orderByDesc('id')->get();

        $sev = ['mineure' => 'Mineure', 'majeure' => 'Majeure', 'critique' => 'Critique'];
        $data = $rows->map(fn ($nc) => [
            $nc->reference, $nc->title, $sev[$nc->severity] ?? $nc->severity, $nc->statusLabel(),
            $nc->responsible?->full_name ?? '—', optional($nc->due_date)->format('d/m/Y') ?? '—',
        ])->all();

        return [
            'title'   => 'Non-conformités (CAPA)',
            'headers' => ['Réf.', 'Titre', 'Gravité', 'Statut', 'Responsable', 'Échéance'],
            'rows'    => $data,
            'numeric' => [],
            'totals'  => null,
        ];
    }

    private function production(Carbon $f, Carbon $t): array
    {
        $rows = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->selectRaw('DATE(produced_at) d, SUM(quantity) q, SUM(total_meters) m, COUNT(*) n')
            ->groupByRaw('DATE(produced_at)')->orderByRaw('DATE(produced_at)')->get();

        $data = $rows->map(fn ($r) => [
            Carbon::parse($r->d)->format('d/m/Y'), (int) $r->n, round((float) $r->q, 2), round((float) $r->m, 2),
        ])->all();

        return [
            'title'   => 'Production journalière',
            'headers' => ['Date', 'Sorties', 'Quantité', 'Mètres'],
            'rows'    => $data,
            'numeric' => [1, 2, 3],
            'totals'  => ['TOTAL', $rows->count() ? (int) $rows->sum('n') : 0, round((float) $rows->sum('q'), 2), round((float) $rows->sum('m'), 2)],
        ];
    }

    private function consommation(Carbon $f, Carbon $t): array
    {
        $rows = ProductionConsumption::with(['coil', 'productionOrder'])
            ->whereBetween('consumed_at', [$f, $t])->orderBy('consumed_at')->get();

        $data = $rows->map(fn ($r) => [
            optional($r->consumed_at)->format('d/m/Y') ?? '—',
            $r->productionOrder?->number ?? '—',
            $r->coil?->reference ?? '—',
            round((float) $r->weight_consumed, 2),
            round((float) $r->length_consumed, 2),
            (int) $r->cost,
        ])->all();

        return [
            'title'   => 'Consommation matière',
            'headers' => ['Date', 'OF', 'Bobine', 'Poids (kg)', 'Longueur (m)', 'Coût (F)'],
            'rows'    => $data,
            'numeric' => [3, 4, 5],
            'totals'  => ['TOTAL', '', '', round((float) $rows->sum('weight_consumed'), 2), round((float) $rows->sum('length_consumed'), 2), (int) $rows->sum('cost')],
        ];
    }

    private function rendement(Carbon $f, Carbon $t): array
    {
        $orders = ProductionOrder::with(['consumptions', 'outputs', 'wastes', 'client'])
            ->whereBetween('updated_at', [$f, $t])
            ->whereIn('status', ['en_cours', 'termine'])->orderBy('number')->get();

        $data = $orders->map(function ($o) {
            $cons  = (float) $o->consumptions->sum('weight_consumed');
            $waste = (float) $o->wastes->sum('weight');
            $yield = $cons > 0 ? round((($cons - $waste) / $cons) * 100, 1) : 0;

            return [
                $o->number,
                $o->client?->name ?? '—',
                round($cons, 2),
                round($waste, 2),
                round((float) $o->outputs->sum('total_meters'), 2),
                $yield,
            ];
        })->all();

        return [
            'title'   => 'Rendement par OF',
            'headers' => ['OF', 'Client', 'Consommé (kg)', 'Chutes (kg)', 'Produit (m)', 'Rendement %'],
            'rows'    => $data,
            'numeric' => [2, 3, 4, 5],
            'totals'  => null,
        ];
    }

    private function pertes(Carbon $f, Carbon $t): array
    {
        $rows = ProductionWaste::with(['machine', 'productionOrder'])
            ->whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$f, $t]))
            ->orderByDesc('id')->get();

        $labels = ['reutilisable' => 'Réutilisable', 'non_reutilisable' => 'Non réutilisable', 'rebut' => 'Rebut'];
        $data = $rows->map(fn ($r) => [
            $r->productionOrder?->number ?? '—',
            $labels[$r->type] ?? $r->type,
            round((float) $r->weight, 2),
            (int) $r->value,
            $r->machine?->name ?? '—',
            $r->reason ?? '—',
        ])->all();

        return [
            'title'   => 'Pertes & chutes',
            'headers' => ['OF', 'Type', 'Poids (kg)', 'Valeur (F)', 'Machine', 'Motif'],
            'rows'    => $data,
            'numeric' => [2, 3],
            'totals'  => ['TOTAL', '', round((float) $rows->sum('weight'), 2), (int) $rows->sum('value'), '', ''],
        ];
    }

    private function couts(Carbon $f, Carbon $t): array
    {
        $rows = ProductionCost::with('productionOrder')
            ->whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$f, $t]))
            ->orderByDesc('id')->get();

        $data = $rows->map(fn ($r) => [
            $r->productionOrder?->number ?? '—',
            (int) $r->material_cost, (int) $r->labor_cost, (int) $r->machine_cost,
            (int) $r->energy_cost, (int) $r->maintenance_cost, (int) $r->packaging_cost,
            (int) $r->overhead_cost, (int) $r->total_cost,
            (int) $r->standard_total, $r->variance !== null ? (int) $r->variance : '—',
            (int) $r->margin,
        ])->all();

        return [
            'title'   => 'Coût de revient (matière · MO · machine · énergie · maintenance · emballage)',
            'headers' => ['OF', 'Matière', 'MO', 'Machine', 'Énergie', 'Maint.', 'Emball.', 'Indirect', 'Total réel', 'Standard', 'Écart', 'Marge'],
            'rows'    => $data,
            'numeric' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'totals'  => [
                'TOTAL',
                (int) $rows->sum('material_cost'), (int) $rows->sum('labor_cost'), (int) $rows->sum('machine_cost'),
                (int) $rows->sum('energy_cost'), (int) $rows->sum('maintenance_cost'), (int) $rows->sum('packaging_cost'),
                (int) $rows->sum('overhead_cost'), (int) $rows->sum('total_cost'),
                (int) $rows->sum('standard_total'), (int) $rows->sum('variance'),
                (int) $rows->sum('margin'),
            ],
        ];
    }

    /**
     * [CDC §Coût de revient industriel] Comparaison coût standard / coût réel
     * PAR PRODUIT : coût réel unitaire moyen des OF de la période vs coût
     * standard produit (products.cout_standard) — écart en valeur et en %.
     */
    private function coutProduit(Carbon $f, Carbon $t): array
    {
        $rows = ProductionCost::query()
            ->join('production_orders as po', 'po.id', '=', 'production_costs.production_order_id')
            ->join('products as p', 'p.id', '=', 'po.product_id')
            ->whereBetween('po.updated_at', [$f, $t])
            ->selectRaw('
                p.code_article,
                p.name,
                p.cout_standard,
                COUNT(production_costs.id)                              as nb_of,
                SUM(GREATEST(po.quantity_produced, 0))                  as qty,
                SUM(production_costs.total_cost)                        as real_total,
                SUM(production_costs.standard_total)                    as std_total
            ')
            ->groupBy('p.id', 'p.code_article', 'p.name', 'p.cout_standard')
            ->orderBy('p.name')
            ->get();

        $data = $rows->map(function ($r) {
            $qty      = (float) $r->qty;
            $realUnit = $qty > 0 ? round((float) $r->real_total / $qty, 2) : 0;
            $stdUnit  = (float) $r->cout_standard > 0
                ? round((float) $r->cout_standard, 2)
                : ($qty > 0 ? round((float) $r->std_total / $qty, 2) : 0);
            $ecart    = $stdUnit > 0 ? round($realUnit - $stdUnit, 2) : 0;
            $ecartPct = $stdUnit > 0 ? round($ecart / $stdUnit * 100, 1) : 0;

            return [
                $r->code_article ?? '—',
                $r->name,
                (int) $r->nb_of,
                round($qty, 2),
                $stdUnit,
                $realUnit,
                $ecart,
                $ecartPct,
            ];
        })->all();

        return [
            'title'   => 'Coût standard / réel par produit',
            'headers' => ['Code article', 'Produit', 'OF', 'Qté produite', 'Coût std/u', 'Coût réel/u', 'Écart/u', 'Écart %'],
            'rows'    => $data,
            'numeric' => [2, 3, 4, 5, 6, 7],
            'totals'  => null,
        ];
    }

    private function client(Carbon $f, Carbon $t): array
    {
        $rows = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders', 'production_orders.id', '=', 'production_outputs.production_order_id')
            ->leftJoin('clients', 'clients.id', '=', 'production_orders.client_id')
            ->selectRaw('COALESCE(clients.name, "—") client, COUNT(DISTINCT production_orders.id) nof, SUM(production_outputs.quantity) q, SUM(production_outputs.total_meters) m')
            ->groupByRaw('clients.name')->orderByDesc('m')->get();

        $data = $rows->map(fn ($r) => [$r->client, (int) $r->nof, round((float) $r->q, 2), round((float) $r->m, 2)])->all();

        return [
            'title'   => 'Production par client',
            'headers' => ['Client', 'OF', 'Quantité', 'Mètres'],
            'rows'    => $data,
            'numeric' => [1, 2, 3],
            'totals'  => ['TOTAL', (int) $rows->sum('nof'), round((float) $rows->sum('q'), 2), round((float) $rows->sum('m'), 2)],
        ];
    }

    private function machine(Carbon $f, Carbon $t): array
    {
        $rows = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders', 'production_orders.id', '=', 'production_outputs.production_order_id')
            ->leftJoin('production_lines', 'production_lines.id', '=', 'production_orders.production_line_id')
            ->leftJoin('production_machines', 'production_machines.id', '=', 'production_lines.machine_id')
            ->selectRaw('COALESCE(production_machines.name, "—") machine, COUNT(DISTINCT production_orders.id) nof, SUM(production_outputs.total_meters) m')
            ->groupByRaw('production_machines.name')->orderByDesc('m')->get();

        $data = $rows->map(fn ($r) => [$r->machine, (int) $r->nof, round((float) $r->m, 2)])->all();

        return [
            'title'   => 'Production par machine',
            'headers' => ['Machine', 'OF', 'Mètres'],
            'rows'    => $data,
            'numeric' => [1, 2],
            'totals'  => ['TOTAL', (int) $rows->sum('nof'), round((float) $rows->sum('m'), 2)],
        ];
    }

    private function operateur(Carbon $f, Carbon $t): array
    {
        $rows = ProductionWaste::whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$f, $t]))
            ->leftJoin('employees', 'employees.id', '=', 'production_wastes.operator_id')
            ->selectRaw('employees.last_name ln, employees.first_name fn, SUM(production_wastes.weight) w, SUM(production_wastes.value) v, COUNT(*) n')
            ->groupByRaw('employees.last_name, employees.first_name')->orderByDesc('w')->get();

        $data = $rows->map(fn ($r) => [
            trim(($r->ln ?? '') . ' ' . ($r->fn ?? '')) ?: '—',
            (int) $r->n, round((float) $r->w, 2), (int) $r->v,
        ])->all();

        return [
            'title'   => 'Pertes par opérateur',
            'headers' => ['Opérateur', 'Nb chutes', 'Poids (kg)', 'Valeur (F)'],
            'rows'    => $data,
            'numeric' => [1, 2, 3],
            'totals'  => ['TOTAL', (int) $rows->sum('n'), round((float) $rows->sum('w'), 2), (int) $rows->sum('v')],
        ];
    }
}
