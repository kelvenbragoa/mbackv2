<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function event()
    {
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function sells()
    {
        return $this->hasMany('App\Models\SellDetails', 'ticket_id', 'id');
    }

    public function formFields()
    {
        return $this->hasMany(TicketFormField::class, 'ticket_id', 'id')->orderBy('sort_order')->orderBy('id');
    }

    public function sell()
    {
        return $this->hasMany('App\Models\Sell', 'ticket_id', 'id');
    }

    public function availableQuantity(): int
    {
        $sold = $this->relationLoaded('sells')
            ? $this->sells->count()
            : $this->sells()->count();

        return max(0, (int) $this->max_qtd - $sold);
    }

    /**
     * Order: event date first, then ticket sale window, then stock.
     * available | event_closed | not_started | expired | sold_out
     */
    public function saleStatus(?int $availableQuantity = null, ?Event $event = null): string
    {
        $event ??= $this->relationLoaded('event') ? $this->event : $this->event()->first();

        if ($event && $event->isSalesClosed()) {
            return 'event_closed';
        }

        $start = $this->saleStartsAt();
        $end = $this->saleEndsAt();
        $now = now();

        if ($start && $now->lt($start)) {
            return 'not_started';
        }

        if ($end && $now->gt($end)) {
            return 'expired';
        }

        $availableQuantity ??= $this->availableQuantity();

        if ($availableQuantity <= 0) {
            return 'sold_out';
        }

        return 'available';
    }

    public function isOnSale(?int $availableQuantity = null, ?Event $event = null): bool
    {
        return $this->saleStatus($availableQuantity, $event) === 'available';
    }

    /**
     * Remaining stock should only be shown when the ticket is purchasable
     * or truly sold out — not when blocked by event/ticket dates.
     */
    public function shouldExposeRemainingQuantity(?int $availableQuantity = null, ?Event $event = null): bool
    {
        $status = $this->saleStatus($availableQuantity, $event);

        return in_array($status, ['available', 'sold_out'], true);
    }

    public function saleStartsAt(): ?Carbon
    {
        return $this->parseSaleDateTime($this->start_date, $this->start_time);
    }

    public function saleEndsAt(): ?Carbon
    {
        return $this->parseSaleDateTime($this->end_date, $this->end_time);
    }

    private function parseSaleDateTime($date, $time): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        $time = $time ?: '00:00:00';

        try {
            return Carbon::parse(trim($date.' '.$time));
        } catch (\Throwable) {
            return null;
        }
    }
}
