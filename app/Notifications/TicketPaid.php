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
use Barryvdh\DomPDF\Facade\Pdf;


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
        $event = Event::find($sell->event_id);


        return WhatsAppTemplate::create()
            ->name('purchase_receipt_1') // Name of your configured template
            ->header(Component::document($url))
            // ->header(Component::image('https://mticket.co.mz/demo/images/logo2.png'))
            ->body(Component::text('ticket for '.$event->name))
            ->body(Component::text('Mticket. For support contact: suporte@email.com or mobile: +258 84 264 8618 / 84 228 0974'))
            ->body(Component::text('Ticket'))
            // ->body(Component::dateTime(new \DateTimeImmutable))
            // ->body(Component::text('Star Wars'))
            // ->body(Component::text('5'))
            // ->buttons(Component::quickReplyButton(['Thanks for your reply!']))
            // ->buttons(Component::urlButton(['reply/01234'])) // List of url suffixes
            ->to($this->number);
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
