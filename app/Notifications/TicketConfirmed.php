<?php

namespace App\Notifications;

use App\Models\SellDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WhatsApp\Component;
use NotificationChannels\WhatsApp\WhatsAppChannel;
use NotificationChannels\WhatsApp\WhatsAppTemplate;

class TicketConfirmed extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $sellDetailsId,
        private readonly string $number,
    ) {
    }

    public function via($notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsapp()
    {
        $ticket = SellDetails::with(['event', 'sell', 'verified_by_protocol'])
            ->find($this->sellDetailsId);

        $registrar = $this->whatsappText(
            $ticket?->name ?: 'MTicket',
            60
        );
        $confirmedAt = $this->whatsappText($this->confirmedAt($ticket), 60);
        $eventName = $this->whatsappText($ticket?->event?->name ?: 'MTicket', 60);
        $reference = $this->whatsappText(
            $ticket?->ticket_number ?: ('MTK-'.$this->sellDetailsId),
            20
        );

        return WhatsAppTemplate::create()
            ->name('appointment_confirmed')
            // ->language('en_US')
            ->body(Component::text($registrar))
            ->body(Component::text($confirmedAt))
            ->body(Component::text($eventName))
            ->body(Component::text($reference))
            ->to($this->number);
    }

    private function confirmedAt(?SellDetails $ticket): string
    {
        $when = $ticket?->verified_at ?? now();

        return $when->timezone(config('app.timezone'))->format('d/m/Y').' às '.$when->format('H:i');
    }

    private function whatsappText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = $value !== '' ? $value : 'MTicket';

        return Str::limit($value, $limit, '');
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
