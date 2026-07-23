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

class BalanceSupplierExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows = [];
    private array $meta = [];
    private int   $rowIdx = 0;
    private array $dataRows = [];

    public function __construct(private ?string $search = null) { $this->build(); }

    public function title(): string { return 'Balance fournisseurs'; }
    public function array(): array  { return $this->rows; }
    public function columnWidths(): array { return ['A' => 10, 'B' => 34, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18]; }
    public function registerEvents(): array { return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())]; }

    private function build(): void
    {
        $company = currentCompany();
        $this->push([$this->companyNameCell($company), null, null, 'BALANCE FOURNISSEURS', null, 'Au ' . now()->format('d/m/Y')], self::T_DOC_HEADER);
        $periodText = 'Solde comptable par fournisseur';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, 'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);
        $this->push(array_fill(0, 6, null), self::T_BLANK);
        $this->push(['Code', 'Fournisseur', 'Total facturé', 'Retours', 'Total payé', 'Solde dû'], self::T_COL_HEADER);

        $query = Supplier::query();
        if ($this->search) $query->where(fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"));

        $rows = $query->get()->map(function ($s) {
            return [
                'code'         => $s->code ?? '',
                'name'         => $s->name,
                'total_fact'   => SupplierInvoice::where('supplier_id', $s->id)->whereNotIn('status', ['brouillon', 'annulee'])->sum('total_ttc'),
                'total_retour' => SupplierReturn::where('supplier_id', $s->id)->where('status', 'valide')->sum('total_ttc'),
                'total_paye'   => SupplierPayment::where('supplier_id', $s->id)->whereNotIn('status', ['annule', 'rejete'])->sum('amount'),
            ];
        })->map(fn($r) => array_merge($r, ['solde' => $r['total_fact'] - $r['total_retour'] - $r['total_paye']]))
          ->filter(fn($r) => $r['total_fact'] > 0 || $r['solde'] != 0)
          ->sortByDesc('solde')->values();

        foreach ($rows as $row) {
            $this->push([$row['code'], $row['name'], $row['total_fact'] ?: null, $row['total_retour'] ?: null, $row['total_paye'] ?: null, $row['solde'] ?: null], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
        }

        $this->push(['', 'TOTAL', $rows->sum('total_fact') ?: null, $rows->sum('total_retour') ?: null, $rows->sum('total_paye') ?: null, $rows->sum('solde') ?: null], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[] = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0';
        $numCols = ['C', 'D', 'E', 'F'];
        foreach ($this->dataRows as $r) {
            foreach ($numCols as $c) { $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt); }
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
        if ($totalRow) { foreach ($numCols as $c) { $ws->getStyle($c . $totalRow)->getNumberFormat()->setFormatCode($numFmt); } }
        $ws->freezePane('A4');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getHeaderFooter()->setOddHeader('&L&BBalance Fournisseurs&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:E{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:E{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        foreach (['C', 'D', 'E', 'F'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E', 'F'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
