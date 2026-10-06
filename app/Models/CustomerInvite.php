<?php

namespace App\Models;

use App\Support\AccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerInvite extends Model
{
    use HasFactory;
    protected $guarded = [];

    public const STATUS_USED = 0;
    public const STATUS_VALID = 1;
    public const STATUS_RETURNED = 2;

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

    public static function nextBatchId(): int
    {
        return (int) (static::query()->max('batch_id') ?? 0) + 1;
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function createBatch(int $inviteId, int $eventId, string $name, int $qty, ?int $batchId = null)
    {
        $batchId = $batchId ?? self::nextBatchId();
        $name = trim($name);
        $created = collect();

        for ($i = 0; $i < $qty; $i++) {
            $created->push(static::create([
                'name' => $name,
                'event_id' => $eventId,
                'invite_id' => $inviteId,
                'batch_id' => $batchId,
                'status' => self::STATUS_VALID,
            ]));
        }

        return $created;
    }

    public function isValid(): bool
    {
        return (int) $this->status === self::STATUS_VALID && $this->verified_at === null;
    }

    public function isReturned(): bool
    {
        return (int) $this->status === self::STATUS_RETURNED;
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
