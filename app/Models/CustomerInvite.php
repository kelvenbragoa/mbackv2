<?php

namespace App\Models;

use App\Support\AccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerInvite extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerInvite $invite) {
            $invite->invite_number = AccessCode::uniqueInviteNumber();
            $invite->qrcode = AccessCode::uniqueInviteQrcode();
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
                    ->orWhere('invite_number', $code);

                if (ctype_digit($code) && strlen($code) < 10) {
                    $query->orWhere('id', (int) $code);
                }
            })
            ->first();
    }

    public function invite(){
        return $this->hasOne('App\Models\Invite', 'id', 'invite_id');
    }
    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function verified_by_protocol(){
        return $this->hasOne('App\Models\Protocol', 'id', 'verified_by');
    }


}
