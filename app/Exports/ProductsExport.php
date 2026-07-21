<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProductsExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_HEADER = 'header';
    private const T_PERIOD = 'period';
    private const T_TOTALS = 'totals';
    private const T_BLANK  = 'blank';
    private const T_COLHDR = 'colhdr';
    private const T_DATA   = 'data';
    private const T_FOOTER = 'footer';

    private const COLS     = 14; // A–N
    private const LAST_COL = 'N';
    private const COLOR    = '7C3AED';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    private array $dataRowNums = [];

    public function __construct(private array $filters = [])
    {
        $this->build();
    }

    public function title(): string { return 'Articles'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 16, // Référence
            'B' => 18, // Code-barres
            'C' => 30, // Désignation
            'D' => 18, // Famille
            'E' => 16, // Marque
            'F' => 10, // Unité
            'G' => 10, // TVA (%)
            'H' => 18, // Prix achat
            'I' => 18, // Prix vente
            'J' => 12, // Stock min
            'K' => 12, // Stock max
            'L' => 22, // Méthode valorisation
            'M' => 14, // Type
            'N' => 10, // Actif
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    // -------------------------------------------------------------------------
    // Data builder
    // -------------------------------------------------------------------------

    private function build(): void
    {
        $company = currentCompany();

        $products = Product::with(['family', 'brand', 'unit', 'taxRate'])
            ->where('is_active', true)
            ->orderBy('name')
            ->when(!empty($this->filters['family_id']), fn($q) => $q->where(fn($qq) => $qq
                ->where('family_id', $this->filters['family_id'])
                ->orWhere('sub_family_id', $this->filters['family_id'])))
            ->when(!empty($this->filters['item_category_id']), fn($q) => $q->where('item_category_id', $this->filters['item_category_id']))
            ->when(!empty($this->filters['search']), function ($q) {
                $s = '%' . $this->filters['search'] . '%';
                $q->where(fn($q2) => $q2->where('name', 'like', $s)->orWhere('reference', 'like', $s));
            })
            ->get();

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, null, null, null, null, 'CATALOGUE ARTICLES', null, null, null, null, null, null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period + filters ──────────────────────────────────────────
        $familyLabel = !empty($this->filters['family_id']) ? ' | Famille filtrée' : '';
        $searchLabel = !empty($this->filters['search']) ? ' | Recherche : "'.$this->filters['search'].'"' : '';
        $period = 'Export complet des articles actifs'.$familyLabel.$searchLabel;

        $this->push(
            [$this->companyLegalLine($company, $period), null, null, null, null, null, null, null, null, null, null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $products->count().' article(s)', null, null, null, null, null,
            null, null, null, null, null, null, null, null,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'Référence', 'Code-barres', 'Désignation', 'Famille', 'Marque', 'Unité',
            'TVA (%)', 'Prix achat (FCFA)', 'Prix vente (FCFA)', 'Stock min', 'Stock max',
            'Méthode valorisation', 'Type', 'Actif',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($products as $product) {
            $this->push([
                $product->reference,
                $product->barcode ?? '—',
                $product->name,
                $product->family?->name ?? '—',
                $product->brand?->name ?? '—',
                $product->unit?->abbreviation ?? '—',
                $product->taxRate?->rate ?? 0,
                (int) $product->purchase_price,
                (int) $product->sale_price,
                (float) $product->stock_min,
                (float) $product->stock_max,
                strtoupper($product->valuation_method ?? 'CMP'),
                ucfirst($product->type ?? 'standard'),
                $product->is_active ? 'Oui' : 'Non',
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$products->count().' article(s)',
            null, null, null, null, null, null, null, null, null, null, null, null, null,
        ], self::T_FOOTER);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]                = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    // -------------------------------------------------------------------------
    // Styles (AfterSheet)
    // -------------------------------------------------------------------------

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $lastCol = self::LAST_COL;
        $numFmt  = '#,##0';
        $numCols = ['H', 'I'];

        foreach ($this->dataRowNums as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_HEADER => $this->styleHeader($ws, $r, $lastCol),
                self::T_PERIOD => $this->stylePeriod($ws, $r, $lastCol),
                self::T_TOTALS => $this->styleTotals($ws, $r, $lastCol, $numFmt),
                self::T_COLHDR => $this->styleColHeader($ws, $r, $lastCol),
                self::T_DATA   => $this->styleData($ws, $r),
                self::T_FOOTER => $this->styleFooter($ws, $r, $lastCol, $numFmt),
                default        => null,
            };
        }

        $ws->freezePane('A6');

        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
        $ws->getHeaderFooter()->setOddHeader('&L&BCatalogue Articles&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->mergeCells("E{$r}:K{$r}");
        $ws->mergeCells("L{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6D28D9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B21B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(17);
    }

    private function styleColHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']],
            ],
        ]);
        foreach (['A', 'B', 'C', 'D', 'E', 'L', 'M'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        foreach (['H', 'I'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function styleData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle("A{$r}:".self::LAST_COL."{$r}")->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['H', 'I', 'J', 'K'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('G'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('N'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B21B6']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
