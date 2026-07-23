<?php

namespace App\Exports\Clients;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Excel "Relevés clients — tous" : un classeur multi-feuilles.
 *  - Feuille 1 : Récapitulatif (1 ligne par client)
 *  - Feuilles suivantes : un onglet par client ayant eu de l'activité
 *
 * Limité aux clients actifs.
 */
class ReleveAllClientsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
    ) {}

    public function sheets(): array
    {
        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'code', 'email', 'phone']);

        $sheets = [
            new RecapAllClientsSheet($clients, $this->dateFrom, $this->dateTo),
        ];

        foreach ($clients as $c) {
            $sheets[] = new ReleveClientExport($c->id, $this->dateFrom, $this->dateTo);
        }

        return $sheets;
    }
}

/**
 * Feuille "Récapitulatif" : un tableau condensé avec une ligne par client.
 */
class RecapAllClientsSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows     = [];
    private array $meta     = [];
    private int   $rowIdx   = 0;
    private array $dataRows = [];

    public function __construct(
        private \Illuminate\Support\Collection $clients,
        private string $dateFrom,
        private string $dateTo,
    ) {
        $this->build();
    }

    public function title(): string { return 'Récapitulatif'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 32, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 16];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())];
    }

    private function build(): void
    {
        $company = currentCompany();

        // ── Header ──────────────────────────────────────────────────────────
        $this->push([
            $this->companyNameCell($company), null, null,
            'RELEVÉS DE COMPTE — TOUS LES CLIENTS', null,
            'Au ' . now()->format('d/m/Y'),
        ], self::T_DOC_HEADER);

        $this->push([
            $this->companyLegalLine($company, 'Récapitulatif consolidé'),
            null, null, null,
            'Période : ' . date('d/m/Y', strtotime($this->dateFrom)) . ' → ' . date('d/m/Y', strtotime($this->dateTo)),
            null,
        ], self::T_PERIOD);

        $this->push(array_fill(0, 6, null), self::T_BLANK);

        // ── Columns ────────────────────────────────────────────────────────
        $this->push(['Code', 'Client', 'Solde ouv.', 'Débit', 'Crédit', 'Solde fin.'], self::T_COL_HEADER);

        // ── Data ──────────────────────────────────────────────────────────
        $totOuv = $totDebit = $totCredit = $totFin = 0;

        foreach ($this->clients as $c) {
            [$lines, $soldeOuv] = $this->compute($c);

            $debit  = (int) $lines->sum('debit');
            $credit = (int) $lines->sum('credit');
            $fin    = $lines->count() ? (int) $lines->last()['solde'] : $soldeOuv;

            $totOuv    += $soldeOuv;
            $totDebit  += $debit;
            $totCredit += $credit;
            $totFin    += $fin;

            $this->push([
                $c->code ?? '',
                $c->name,
                $soldeOuv,
                $debit ?: null,
                $credit ?: null,
                $fin,
            ], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
        }

        $this->push([
            'TOTAL GÉNÉRAL', null,
            $totOuv,
            $totDebit ?: null,
            $totCredit ?: null,
            $totFin,
        ], self::T_TOTAL);
    }

    private function compute(Client $client): array
    {
        $factAvant  = Invoice::where('client_id', $client->id)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereDate('issued_at', '<', $this->dateFrom)->sum('total_ttc');
        $avoirAvant = CreditNote::where('client_id', $client->id)
            ->whereIn('status', ['valide', 'applique'])
            ->whereDate('issued_at', '<', $this->dateFrom)->sum('total_ttc');
        $reglAvant  = ClientPayment::where('client_id', $client->id)
            ->whereNotIn('status', ['annule', 'rejete'])
            ->whereDate('payment_date', '<', $this->dateFrom)->sum('amount');
        $soldeOuv   = $factAvant - $avoirAvant - $reglAvant;

        $lines = collect();
        foreach (Invoice::where('client_id', $client->id)->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereBetween('issued_at', [$this->dateFrom, $this->dateTo])->orderBy('issued_at')->get() as $inv) {
            $lines->push(['date' => $inv->issued_at, 'debit' => $inv->total_ttc, 'credit' => 0]);
        }
        foreach (CreditNote::where('client_id', $client->id)->whereIn('status', ['valide', 'applique'])
            ->whereBetween('issued_at', [$this->dateFrom, $this->dateTo])->orderBy('issued_at')->get() as $av) {
            $lines->push(['date' => $av->issued_at, 'debit' => 0, 'credit' => $av->total_ttc]);
        }
        foreach (ClientPayment::where('client_id', $client->id)
            ->whereNotIn('status', ['annule', 'rejete'])
            ->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])->orderBy('payment_date')->get() as $r) {
            $lines->push(['date' => $r->payment_date, 'debit' => 0, 'credit' => $r->amount]);
        }

        $solde = $soldeOuv;
        $lines = $lines->sortBy('date')->values()->map(function ($l) use (&$solde) {
            $solde += $l['debit'] - $l['credit'];
            return array_merge($l, ['solde' => $solde]);
        });

        return [$lines, $soldeOuv];
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]             = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    /* ── Styles ─────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numCols = ['C', 'D', 'E', 'F'];
        foreach ($this->dataRows as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_PERIOD     => $this->sPeriod($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_TOTAL      => $this->sTotal($ws, $r),
                default            => null,
            };
        }

        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $ws->freezePane('A5');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->setPrintGridlines(false);

        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:B{$r}"); $ws->mergeCells("C{$r}:E{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:D{$r}"); $ws->mergeCells("E{$r}:F{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
            ],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['C', 'D', 'E', 'F'] as $c) {
            $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:B{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E', 'F'] as $c) {
            $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
