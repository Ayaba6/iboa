<?php

namespace App\Exports\HR;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * [RH-PRO] État IUTS — export Excel.
 * Liste des retenues IUTS par employé, prêt pour l'administration fiscale.
 */
class IutsExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use RegistersEventListeners;

    private array $rows = [];

    public function __construct(private PayrollRun $run)
    {
        $this->build();
    }

    public function array(): array { return $this->rows; }

    public function title(): string
    {
        return "IUTS {$this->run->period_month}-{$this->run->period_year}";
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 30, 'C' => 8, 'D' => 18, 'E' => 18, 'F' => 14, 'G' => 14];
    }

    private function build(): void
    {
        $run     = $this->run->load(['items' => fn($q) => $q->orderBy('employee_name'), 'company']);
        $company = $run->company;

        $months  = ['Janvier','Février','Mars','Avril','Mai','Juin',
                    'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $period  = ($months[$run->period_month - 1] ?? $run->period_month) . ' ' . $run->period_year;

        $this->rows[] = [$company->name ?? 'Entreprise'];
        $this->rows[] = ["ÉTAT IUTS — {$period}"];
        $this->rows[] = [];
        $this->rows[] = [
            'Matricule', 'Nom & Prénom', 'Charges',
            'Salaire Imposable', 'CNSS Salarié', 'IUTS Mensuel', 'Cumul IUTS YTD',
        ];

        foreach ($run->items as $item) {
            $this->rows[] = [
                $item->employee_matricule,
                $item->employee_name,
                $item->family_charges !== null ? $item->family_charges : number_format($item->nb_parts, 1),
                $item->salaire_imposable,
                $item->cnss_employee,
                $item->iuts_amount,
                $item->cumul_iuts_ytd ?? $item->iuts_amount,
            ];
        }

        $this->rows[] = [
            '', 'TOTAL', '',
            $run->items->sum('salaire_imposable'),
            $run->total_cnss_employee,
            $run->total_iuts,
            $run->items->sum(fn($i) => $i->cumul_iuts_ytd ?? $i->iuts_amount),
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet    = $event->sheet->getDelegate();
        $last     = count($event->getConcernable()->rows);
        $dataStart = 5;

        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A4:G4')->applyFromArray([
            'font'  => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'  => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (['D','E','F','G'] as $col) {
            if ($dataStart <= $last) {
                $sheet->getStyle("{$col}{$dataStart}:{$col}{$last}")
                      ->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $sheet->getStyle("A{$last}:G{$last}")->getFont()->setBold(true);
        $sheet->getStyle("A{$last}:G{$last}")
              ->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFF5F3FF');
        $sheet->getStyle("A{$last}:G{$last}")
              ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);

        if ($dataStart <= $last) {
            $sheet->getStyle("A4:G{$last}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
            ]);
        }
        $sheet->freezePane("A{$dataStart}");
    }
}
