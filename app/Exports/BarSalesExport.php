<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\SellBar;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BarSalesExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithColumnWidths, WithStyles, WithEvents, WithTitle
{
    public const METHODS = [
        'dinheiro' => 'Dinheiro',
        'cartao' => 'Cartão',
        'mpesa' => 'M-Pesa',
        'emola' => 'e-Mola',
        'cashless' => 'Cashless',
    ];

    public function __construct(private Event $event)
    {
    }

    public function query()
    {
        return SellBar::query()
            ->where('event_id', $this->event->id)
            ->with(['user:id,name', 'barstore:id,name', 'selldetails.product:id,name'])
            ->orderBy('id');
    }

    public function title(): string
    {
        return 'Vendas bar';
    }

    public function headings(): array
    {
        return [
            'Nº venda',
            'Data',
            'Bar',
            'Barman',
            'Pagamento',
            'Estado',
            'Produto',
            'Qtd',
            'Preço (MT)',
            'Subtotal (MT)',
            'Total venda (MT)',
        ];
    }

    public function map($sell): array
    {
        $common = [
            $sell->id,
            $sell->created_at ? Date::dateTimeToExcel($sell->created_at) : null,
            $sell->barstore->name ?? '—',
            $sell->user->name ?? '—',
            self::METHODS[$sell->method] ?? $sell->method,
            (int) $sell->status === 0 ? 'Entregue' : 'Por levantar',
        ];

        if ($sell->selldetails->isEmpty()) {
            return [array_merge($common, ['(sem produtos registados)', null, null, null, (float) $sell->total])];
        }

        return $sell->selldetails->values()->map(fn ($detail, $index) => array_merge($common, [
            $detail->product->name ?? '—',
            (int) $detail->qtd,
            (float) $detail->price,
            (float) $detail->total,
            $index === 0 ? (float) $sell->total : null,
        ]))->all();
    }

    public function columnFormats(): array
    {
        return [
            'B' => 'dd/mm/yyyy hh:mm',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 17, 'C' => 20, 'D' => 24, 'E' => 12, 'F' => 13,
            'G' => 30, 'H' => 7, 'I' => 12, 'J' => 14, 'K' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0A2540']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            },
        ];
    }
}
