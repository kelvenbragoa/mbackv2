<?php

namespace App\Models;

use App\Support\AccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellDetails extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'form_answers' => 'array',
        'verified_at' => 'datetime',
    ];

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
