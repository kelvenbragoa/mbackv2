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
    private $detail;
    private $event;
    private $msg;
    private $sell;

    /**
     * Create a new notification instance.
     */
    public function __construct($detail,$event,$sell, $msg,$number)
    {
        //
        $this->detail = $detail;
        $this->event = $event;
        $this->msg = $msg;
        $this->sell = $sell;
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
        $sell = Sell::find($this->sell);
       
        $detail = SellDetails::where('sell_id',$sell->id)->get();

        $event = Event::find($sell->event_id);

        $data = [
            'detail'=>$detail,
            'event'=>$event,
        ];
        $pdf = Pdf::loadView('pdf.ticket', $data)->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);

        return WhatsAppTemplate::create()
            ->name('mticket_purchased_') // Name of your configured template
            // ->header(Component::document('https://inogest-atas.s3.amazonaws.com/meeting-attachment/qPBGAXU72RPO9m5M2VuS4jFDjO1yHhIiuvYUtQfP.pdf?response-content-disposition=attachment&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAV2FXJ6Y2RJHFRKWL%2F20240808%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240808T172422Z&X-Amz-SignedHeaders=host&X-Amz-Expires=600&X-Amz-Signature=6650d4ea160a147573dbc2350d7dfc522834bf5f0b2b5711f8774dd8a31ea813'))
            // ->header(Component::image('https://mticket.co.mz/demo/images/logo2.png'))
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
