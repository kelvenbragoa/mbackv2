<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\SellShop;
use App\Support\ShopOrderPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendShopOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SellShop $sell,
        public Event $event,
    ) {
    }

    public function envelope(): Envelope
    {
        $name = $this->event->name ?: 'MTicket';

        return new Envelope(
            subject: 'O teu recibo da loja MTicket — '.$name,
        );
    }

    public function content(): Content
    {
        $this->sell->loadMissing(['details', 'transaction']);
        $this->event->loadMissing(['province', 'city']);

        return new Content(
            html: 'mail.shop-order',
            with: [
                'sell' => $this->sell,
                'event' => $this->event,
            ],
        );
    }

    public function attachments(): array
    {
        try {
            $pdf = ShopOrderPdf::forSell($this->sell);
            if (! $pdf) {
                return [];
            }

            $binary = $pdf->output();

            return [
                Attachment::fromData(fn () => $binary, ShopOrderPdf::filename($this->sell))
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $th) {
            Log::error('Shop receipt PDF attach failed: '.$th->getMessage());

            return [];
        }
    }
}
