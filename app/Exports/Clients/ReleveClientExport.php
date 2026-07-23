<?php

namespace App\Exports\Clients;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReleveClientExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_OUV        = 'ouv';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows     = [];
    private array $meta     = [];
    private int   $rowIdx   = 0;
    private array $dataRows = [];
    private array $typeRows = []; // rowIdx → type (facture|avoir|reglement)

    public function __construct(
        private int     $clientId,
        private ?string $dateFrom,
        private ?string $dateTo,
    ) {
        $this->build();
    }

    public function title(): string { return 'Relevé client'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 18, 'C' => 16, 'D' => 16, 'E' => 18, 'F' => 18, 'G' => 18];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())];
    }

    private function build(): void
    {
        $company = currentCompany();
        $client  = Client::find($this->clientId);

        // ── Header ──────────────────────────────────────────────────────────
        $this->push([$this->companyNameCell($company), null, null, 'RELEVÉ DE COMPTE CLIENT', null, null,
            'Au ' . now()->format('d/m/Y')], self::T_DOC_HEADER);

        $periodText = 'Client : ' . ($client?->name ?? '—') . ($client?->code ? ' (' . $client->code . ')' : '');
        $this->push([
            $this->companyLegalLine($company, $periodText),
            null, null, null,
            'Période : ' . $this->fmtDate($this->dateFrom) . ' → ' . $this->fmtDate($this->dateTo),
            null, null,
        ], self::T_PERIOD);

        $this->push(array_fill(0, 7, null), self::T_BLANK);

        // ── Columns ──────────────────────────────────────────────────────────
        $this->push(['Date', 'Référence', 'Type', 'Échéance', 'Débit (FCFA)', 'Crédit (FCFA)', 'Solde (FCFA)'], self::T_COL_HEADER);

        // ── Compute data ──────────────────────────────────────────────────
        [$lines, $soldeOuv] = $this->computeLines($client);

        // Ligne solde ouverture
        $this->push([
            $this->dateFrom, 'Solde ouverture', '', '',
            $soldeOuv > 0 ? $soldeOuv : null,
            $soldeOuv < 0 ? abs($soldeOuv) : null,
            $soldeOuv,
        ], self::T_OUV);

        $totDebit = $totCredit = 0;
        foreach ($lines as $l) {
            $this->push([
                $l['date'] instanceof \Carbon\Carbon ? $l['date']->format('d/m/Y') : (string)$l['date'],
                $l['reference'],
                $l['type'] === 'facture' ? 'Facture' : ($l['type'] === 'avoir' ? 'Avoir' : 'Règlement'),
                $l['echeance'] ? (string)$l['echeance'] : '',
                $l['debit']  > 0 ? $l['debit']  : null,
                $l['credit'] > 0 ? $l['credit'] : null,
                $l['solde'],
            ], self::T_DATA);
            $this->dataRows[]             = $this->rowIdx;
            $this->typeRows[$this->rowIdx] = $l['type'];
            $totDebit  += $l['debit'];
            $totCredit += $l['credit'];
        }

        $soldeFin = $soldeOuv + $totDebit - $totCredit;
        $this->push(['TOTAL PÉRIODE', null, null, null,
            $totDebit  ?: null, $totCredit ?: null, $soldeFin ?: null], self::T_TOTAL);
    }

    private function computeLines(?\App\Models\Client $client): array
    {
        if (!$client || !$this->dateFrom || !$this->dateTo) return [collect(), 0];

        $factAvant  = Invoice::where('client_id', $client->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereDate('issued_at', '<', $this->dateFrom)->sum('total_ttc');
        $avoirAvant = CreditNote::where('client_id', $client->id)->whereIn('status', ['valide', 'applique'])->whereDate('issued_at', '<', $this->dateFrom)->sum('total_ttc');
        $reglAvant  = ClientPayment::where('client_id', $client->id)->whereNotIn('status', ['annule', 'rejete'])->whereDate('payment_date', '<', $this->dateFrom)->sum('amount');
        $soldeOuv   = $factAvant - $avoirAvant - $reglAvant;

        $lines = collect();
        foreach (Invoice::where('client_id', $client->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereBetween('issued_at', [$this->dateFrom, $this->dateTo])->orderBy('issued_at')->get() as $inv) {
            $lines->push(['date' => $inv->issued_at, 'type' => 'facture', 'reference' => $inv->number, 'echeance' => $inv->due_at, 'debit' => $inv->total_ttc, 'credit' => 0]);
        }
        foreach (CreditNote::where('client_id', $client->id)->whereIn('status', ['valide', 'applique'])->whereBetween('issued_at', [$this->dateFrom, $this->dateTo])->orderBy('issued_at')->get() as $av) {
            $lines->push(['date' => $av->issued_at, 'type' => 'avoir', 'reference' => $av->number, 'echeance' => null, 'debit' => 0, 'credit' => $av->total_ttc]);
        }
        foreach (ClientPayment::where('client_id', $client->id)->whereNotIn('status', ['annule', 'rejete'])->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])->orderBy('payment_date')->get() as $r) {
            $lines->push(['date' => $r->payment_date, 'type' => 'reglement', 'reference' => $r->number, 'echeance' => null, 'debit' => 0, 'credit' => $r->amount]);
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

    private function fmtDate(?string $d): string
    {
        return $d ? date('d/m/Y', strtotime($d)) : '—';
    }

    /* ── Styles ─────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0';
        $numCols = ['E', 'F', 'G'];

        foreach ($this->dataRows as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_PERIOD     => $this->sPeriod($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_OUV        => $this->sOuv($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_TOTAL      => $this->sTotal($ws, $r),
                default            => null,
            };
        }

        // Type badge coloring (col C)
        foreach ($this->typeRows as $r => $type) {
            $color = match($type) {
                'facture'   => ['DBEAFE', '1D4ED8'],
                'avoir'     => ['FFEDD5', 'C2410C'],
                'reglement' => ['DCFCE7', '15803D'],
                default     => ['F3F4F6', '374151'],
            };
            $ws->getStyle('C' . $r)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $color[1]]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color[0]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            }
        }
        $ouvRow = array_key_first(array_filter($this->meta, fn($t) => $t === self::T_OUV));
        if ($ouvRow) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $ouvRow)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        $ws->freezePane('A5');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $ws->getHeaderFooter()->setOddHeader('&L&BRelevé Client&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');
        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:F{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:D{$r}"); $ws->mergeCells("E{$r}:G{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sOuv($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E40AF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        foreach (['E', 'F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        foreach (['E', 'F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['E', 'F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
