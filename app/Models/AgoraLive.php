<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgoraLive extends Model
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    public const HOST_TIMEOUT_SECONDS = 45;

    protected $fillable = [
        'event_id',
        'channel',
        'status',
        'max_guests',
        'host_user_id',
        'host_seen_at',
        'started_at',
    ];

    protected $casts = [
        'max_guests' => 'integer',
        'host_seen_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function guests()
    {
        return $this->hasMany(AgoraLiveGuest::class, 'agora_live_id', 'id');
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * Active only while the host keeps sending heartbeats, so a closed browser tab doesn't leave the live "on air".
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->host_seen_at
            && $this->host_seen_at->gt(now()->subSeconds(self::HOST_TIMEOUT_SECONDS));
    }

    public function publicStatus(): string
    {
        if ($this->isDisabled()) {
            return self::STATUS_DISABLED;
        }

        return $this->isActive() ? self::STATUS_ACTIVE : self::STATUS_IDLE;
    }

    public function acceptedGuestsCount(): int
    {
        return $this->guests()->where('status', AgoraLiveGuest::STATUS_ACCEPTED)->count();
    }

    public function closeGuests(): void
    {
        $this->guests()->where('status', AgoraLiveGuest::STATUS_PENDING)
            ->update(['status' => AgoraLiveGuest::STATUS_REJECTED]);
        $this->guests()->where('status', AgoraLiveGuest::STATUS_ACCEPTED)
            ->update(['status' => AgoraLiveGuest::STATUS_REMOVED]);
    }

    public function toPromotorArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'provider' => 'agora',
            'mode' => 'interactive',
            'channel' => $this->channel,
            'status' => $this->publicStatus(),
            'active' => $this->isActive(),
            'max_guests' => $this->max_guests,
            'started_at' => $this->started_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'provider' => 'agora',
            'mode' => 'interactive',
            'status' => $this->publicStatus(),
            'active' => $this->isActive(),
            'max_guests' => $this->max_guests,
        ];
    }
}
