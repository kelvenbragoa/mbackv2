<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\SellDetails;
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

class TicketSalesExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithColumnWidths, WithStyles, WithEvents, WithTitle
{
    private const CHANNELS = [
        'online' => 'Online',
        'lote' => 'Lote',
        'box_office' => 'Bilheteira',
    ];

    public function __construct(private Event $event)
    {
    }

    public function query()
    {
        return SellDetails::query()
            ->where('event_id', $this->event->id)
            ->consumingStock()
            ->with(['ticket:id,name,price,is_package', 'user:id,name', 'sell.transaction'])
            ->orderBy('id');
    }

    public function title(): string
    {
        return 'Vendas';
    }

    public function headings(): array
    {
        return [
            'Nº bilhete',
            'Nº venda',
            'Bilhete',
            'Tipo',
            'Preço (MT)',
            'Canal',
            'Estado',
            'Validado em',
            'Cliente',
            'Email',
            'Telefone',
            'Data da venda',
        ];
    }

    public function map($detail): array
    {
        $used = (int) $detail->status === SellDetails::STATUS_USED;

        return [
            $detail->ticket_number ?? $detail->id,
            $detail->sell_id,
            $detail->ticket->name ?? '—',
            $detail->ticket && $detail->ticket->is_package ? 'Pacote' : 'Bilhete',
            (float) ($detail->sell->price ?? $detail->ticket->price ?? 0),
            $detail->sell ? self::CHANNELS[$detail->sell->saleChannel()] : '—',
            $used ? 'Validado' : 'Por validar',
            $used && $detail->verified_at ? Date::dateTimeToExcel($detail->verified_at) : null,
            $detail->user->name ?? $detail->name,
            $detail->email,
            $detail->mobile,
            $detail->created_at ? Date::dateTimeToExcel($detail->created_at) : null,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00',
            'H' => 'dd/mm/yyyy hh:mm',
            'L' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 10, 'C' => 28, 'D' => 10, 'E' => 12, 'F' => 12,
            'G' => 12, 'H' => 17, 'I' => 28, 'J' => 30, 'K' => 15, 'L' => 17,
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
