<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\SellShop;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WhatsApp\Component;
use NotificationChannels\WhatsApp\WhatsAppChannel;
use NotificationChannels\WhatsApp\WhatsAppTemplate;

class ShopOrderPaid extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $url,
        private readonly int $sellId,
        private readonly string $number,
    ) {
    }

    public function via($notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsapp()
    {
        $sell = SellShop::find($this->sellId);
        $event = $sell ? Event::find($sell->event_id) : null;

        $amount = number_format((float) ($sell?->total ?? 0), 2, '.', '').' MT';
        $from = $this->whatsappAddress($event?->name ?: 'MTicket');
        $document = 'recibo';

        return WhatsAppTemplate::create()
            ->name('purchase_receipt_1')
            ->header(Component::document($this->url, 'recibo.pdf'))
            ->body(Component::text($amount))
            ->body(Component::text($from))
            ->body(Component::text($document))
            ->to($this->number);
    }

    private function whatsappAddress(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = $value !== '' ? $value : 'MTicket';

        return Str::limit($value, 60, '');
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
