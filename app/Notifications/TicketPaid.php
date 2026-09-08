<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WhatsApp\Component;
use NotificationChannels\WhatsApp\WhatsAppChannel;
use NotificationChannels\WhatsApp\WhatsAppTemplate;
use Illuminate\Support\Str;


class TicketPaid extends Notification
{
    use Queueable;
    protected $number;
    private $url;
    private $sell_id;
    private $msg;
    private $sell;

    /**
     * Create a new notification instance.
     */
    public function __construct($url,$sell_id,$number)
    {
        //
        $this->url = $url;
        $this->sell_id = $sell_id;
        $this->number = $number;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toWhatsapp()
    {
        $url = $this->url;
        $sell = Sell::find($this->sell_id);
        $event = $sell ? Event::find($sell->event_id) : null;

        $amount = number_format((float) ($sell?->total ?? $sell?->price ?? 0), 2, '.', '').' MT';
        $from = $this->whatsappAddress($event?->name ?: 'MTicket');
        $document = 'ticket';

        return WhatsAppTemplate::create()
            ->name('purchase_receipt_1')
            ->header(Component::document($url))
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

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return 'hello';
        return [
            //
        ];
    }
}
