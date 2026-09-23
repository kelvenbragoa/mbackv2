<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgoraLiveGuest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LEFT = 'left';
    public const STATUS_REMOVED = 'removed';

    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED];

    protected $fillable = [
        'agora_live_id',
        'user_id',
        'uid',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'uid' => 'integer',
        'accepted_at' => 'datetime',
    ];

    public function live()
    {
        return $this->belongsTo(AgoraLive::class, 'agora_live_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function toArrayForHost(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'status' => $this->status,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'image' => $this->user?->image,
            ],
            'created_at' => $this->created_at,
            'accepted_at' => $this->accepted_at,
        ];
    }

    public function toArrayForGuest(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'accepted_at' => $this->accepted_at,
        ];
    }
}
