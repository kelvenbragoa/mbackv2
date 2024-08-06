<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WhatsApp\Component;
use NotificationChannels\WhatsApp\WhatsAppChannel;
use NotificationChannels\WhatsApp\WhatsAppTemplate;

class TicketPaid extends Notification
{
    use Queueable;
    protected $number;

    /**
     * Create a new notification instance.
     */
    public function __construct($number)
    {
        //
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
        return WhatsAppTemplate::create()
            ->name('mticket_purchased_') // Name of your configured template
            // ->header(Component::image('https://lumiere-a.akamaihd.net/v1/images/image_c671e2ee.jpeg'))
            // ->body(Component::text('Star Wars'))
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
