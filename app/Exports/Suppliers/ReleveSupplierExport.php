<?php

namespace App\Exports\Suppliers;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReleveSupplierExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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
    private array $typeRows = [];

    public function __construct(
        private int     $supplierId,
        private ?string $dateFrom,
        private ?string $dateTo,
    ) {
        $this->build();
    }

    public function title(): string { return 'Relevé fournisseur'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 20, 'C' => 16, 'D' => 16, 'E' => 18, 'F' => 18, 'G' => 18];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())];
    }

    private function build(): void
    {
        $company  = currentCompany();
        $supplier = Supplier::find($this->supplierId);

        $this->push([$this->companyNameCell($company), null, null, 'RELEVÉ DE COMPTE FOURNISSEUR', null, null,
            'Au ' . now()->format('d/m/Y')], self::T_DOC_HEADER);

        $periodText = 'Fournisseur : ' . ($supplier?->name ?? '—') . ($supplier?->code ? ' (' . $supplier->code . ')' : '');
        $this->push([
            $this->companyLegalLine($company, $periodText),
            null, null, null,
            'Période : ' . $this->fmtDate($this->dateFrom) . ' → ' . $this->fmtDate($this->dateTo),
            null, null,
        ], self::T_PERIOD);

        $this->push(array_fill(0, 7, null), self::T_BLANK);

        $this->push(['Date', 'Référence', 'Type', 'Échéance', 'Débit (FCFA)', 'Crédit (FCFA)', 'Solde (FCFA)'], self::T_COL_HEADER);

        [$lines, $soldeOuv] = $this->computeLines($supplier);

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
                match($l['type']) { 'facture' => 'Facture', 'retour' => 'Retour', default => 'Paiement' },
                $l['echeance'] ? (string)$l['echeance'] : '',
                $l['debit']  > 0 ? $l['debit']  : null,
                $l['credit'] > 0 ? $l['credit']  : null,
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

    private function computeLines(?Supplier $supplier): array
    {
        if (!$supplier || !$this->dateFrom || !$this->dateTo) return [collect(), 0];

        $factAvant   = SupplierInvoice::where('supplier_id', $supplier->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereDate('received_at', '<', $this->dateFrom)->sum('total_ttc');
        $retourAvant = SupplierReturn::where('supplier_id', $supplier->id)->where('status', 'valide')->whereDate('returned_at', '<', $this->dateFrom)->sum('total_ttc');
        $reglAvant   = SupplierPayment::where('supplier_id', $supplier->id)->whereNotIn('status', ['annule', 'rejete'])->whereDate('payment_date', '<', $this->dateFrom)->sum('amount');
        $soldeOuv    = $factAvant - $retourAvant - $reglAvant;

        $lines = collect();

        foreach (SupplierInvoice::where('supplier_id', $supplier->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereBetween('received_at', [$this->dateFrom, $this->dateTo])->orderBy('received_at')->get() as $inv) {
            $lines->push(['date' => $inv->received_at, 'type' => 'facture', 'reference' => $inv->supplier_invoice_number ?: $inv->number, 'echeance' => $inv->due_at, 'debit' => $inv->total_ttc, 'credit' => 0]);
        }
        foreach (SupplierReturn::where('supplier_id', $supplier->id)->where('status', 'valide')->whereBetween('returned_at', [$this->dateFrom, $this->dateTo])->orderBy('returned_at')->get() as $ret) {
            $lines->push(['date' => $ret->returned_at, 'type' => 'retour', 'reference' => $ret->number, 'echeance' => null, 'debit' => 0, 'credit' => $ret->total_ttc]);
        }
        foreach (SupplierPayment::where('supplier_id', $supplier->id)->whereNotIn('status', ['annule', 'rejete'])->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])->orderBy('payment_date')->get() as $p) {
            $lines->push(['date' => $p->payment_date, 'type' => 'paiement', 'reference' => $p->number, 'echeance' => null, 'debit' => 0, 'credit' => $p->amount]);
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
                'facture'  => ['FEE2E2', 'B91C1C'],
                'retour'   => ['FFEDD5', 'C2410C'],
                'paiement' => ['DCFCE7', '15803D'],
                default    => ['F3F4F6', '374151'],
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
        $ws->getHeaderFooter()->setOddHeader('&L&BRelevé Fournisseur&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');
        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:F{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:D{$r}"); $ws->mergeCells("E{$r}:G{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sOuv($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '92400E']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
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
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['E', 'F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
