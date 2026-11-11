<?php

namespace App\Models;

use App\Support\AccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellDetails extends Model
{
    use HasFactory;
    protected $guarded = [];

    public const STATUS_USED = 0;
    public const STATUS_VALID = 1;
    public const STATUS_RETURNED = 2;

    protected $casts = [
        'form_answers' => 'array',
        'verified_at' => 'datetime',
        'access_ends_at' => 'datetime',
    ];

    public function hasActiveLiveAccess(?\Carbon\Carbon $at = null): bool
    {
        $this->loadMissing('ticket');

        if (! $this->ticket || (int) $this->ticket->is_live !== 1) {
            return false;
        }

        if ((int) $this->status === self::STATUS_RETURNED) {
            return false;
        }

        if ($this->access_ends_at === null) {
            return true;
        }

        return $this->access_ends_at->gt($at ?? now());
    }

    public function scopeConsumingStock($query)
    {
        return $query->where('status', '!=', self::STATUS_RETURNED);
    }

    public function isValid(): bool
    {
        return (int) $this->status === self::STATUS_VALID && $this->verified_at === null;
    }

    public function isReturned(): bool
    {
        return (int) $this->status === self::STATUS_RETURNED;
    }

    protected static function booted(): void
    {
        static::creating(function (SellDetails $ticket) {
            $ticket->ticket_number = AccessCode::uniqueTicketNumber();
            $ticket->qrcode = AccessCode::uniqueTicketQrcode();
        });
    }

    public static function findByAccessCode(string $code): ?self
    {
        $code = AccessCode::normalize($code);

        if ($code === '') {
            return null;
        }

        return static::query()
            ->where(function ($query) use ($code) {
                $query->where('qrcode', $code)
                    ->orWhere('ticket_number', $code);

                if (ctype_digit($code) && strlen($code) < 10) {
                    $query->orWhere('id', (int) $code);
                }
            })
            ->first();
    }

    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function ticket(){
        return $this->hasOne('App\Models\Ticket', 'id', 'ticket_id');
    }

    public function user(){
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function sell(){
        return $this->hasOne('App\Models\Sell', 'id', 'sell_id');
    }

    public function verified_by_protocol(){
        return $this->hasOne('App\Models\Protocol', 'id', 'verified_by');
    }

}
