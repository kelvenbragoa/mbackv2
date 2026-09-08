<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Sell;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Support\TicketPdf;
use Illuminate\Mail\Mailables\Attachment;

class SendTickets extends Mailable
{
    use Queueable, SerializesModels;

    private $detail;
    private $event;
    private $msg;
    private $sell;
    private $pdfContents = null;


    /**
     * Create a new message instance.
     */
    public function __construct($detail, $event, $sell, $msg, ?string $pdfContents = null)
    {
        $this->detail = $detail;
        $this->event = $event;
        $this->msg = $msg;
        $this->sell = $sell;
        $this->pdfContents = $pdfContents;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = Event::find($this->event);
        $name = $event?->name ?: 'MTicket';

        return new Envelope(
            subject: 'O teu bilhete MTicket — '.$name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $sell_model = Sell::with(['event.province', 'event.city', 'ticket', 'transaction', 'selldetails'])
            ->find($this->sell);
        $event = $sell_model?->event ?? Event::with(['province', 'city'])->find($this->event);

        return new Content(
            html: 'mail.tickets',
            with: [
                'msg_content' => $this->msg,
                'sell_model' => $sell_model,
                'event' => $event,
                'has_pdf' => $this->pdfContents !== '',
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
        if ($this->pdfContents === '') {
            return [];
        }

        if (is_string($this->pdfContents)) {
            $binary = $this->pdfContents;

            return [
                Attachment::fromData(fn () => $binary, 'ticket.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        $sell = Sell::find($this->sell);
        if (! $sell) {
            return [];
        }

        $pdf = TicketPdf::forSellId((int) $sell->id);
        if (! $pdf) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $pdf->output(), 'ticket.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
