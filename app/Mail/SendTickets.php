<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Attachment;

class SendTickets extends Mailable
{
    use Queueable, SerializesModels;

    private $detail;
    private $event;
    private $msg;
    private $sell;


    /**
     * Create a new message instance.
     */
    public function __construct($detail,$event,$sell, $msg)
    {
        //
        $this->detail = $detail;
        $this->event = $event;
        $this->msg = $msg;
        $this->sell = $sell;
        
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = Event::find($this->event);
        return new Envelope(
            subject: 'Ticket Issued -'.$event->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $msg_content = $this->msg;
        $sell_model = Sell::find($this->sell);
        $event = Event::find($sell_model->event_id);

        return new Content(
            markdown: 'mail.tickets',
            with:[
                'msg_content'=>$msg_content,
                'sell_model'=>$sell_model,
                'event'=>$event,
               
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments()
    {
        // App::setLocale(Auth::user()->lang);
        $sell = Sell::find($this->sell);

        // $pdf = Pdf::loadView('manager.meeting.meeting', $meeting)->setOptions([
        //     'defaultFont' => 'sans-serif',
        //     'isRemoteEnabled' => 'true'
        // ]);
       
        $detail = SellDetails::where('sell_id',$sell->id)->get();

        $event = Event::find($sell->event_id);

        $data = [
            'detail'=>$detail,
            'event'=>$event,
        ];

        // $pdf = Pdf::loadView('pdf.ticket', $data)->setOptions([
        //     'defaultFont' => 'sans-serif',
        //     'isRemoteEnabled' => 'true'
        // ]);

        $pdf = Pdf::loadView('pdf.ticket', $data)->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'ticket.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
