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
        'current_session_id',
    ];

    protected $casts = [
        'max_guests' => 'integer',
        'host_seen_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function sessions()
    {
        return $this->hasMany(AgoraLiveSession::class, 'agora_live_id', 'id');
    }

    public function currentSession()
    {
        return $this->belongsTo(AgoraLiveSession::class, 'current_session_id');
    }

    /**
     * Session that viewers should be counted against, only while the host is on air.
     */
    public function liveSession(): ?AgoraLiveSession
    {
        if (! $this->isActive() || ! $this->current_session_id) {
            return null;
        }

        $session = $this->currentSession;

        return $session && ! $session->ended_at ? $session : null;
    }

    /**
     * A new session starts every time the host goes on air after being off (stopped or timed out).
     */
    public function openSession(?int $hostUserId): AgoraLiveSession
    {
        if ($session = $this->liveSession()) {
            return $session;
        }

        $this->currentSession?->close();

        $session = $this->sessions()->create([
            'host_user_id' => $hostUserId,
            'started_at' => now(),
        ]);
        $this->update(['current_session_id' => $session->id]);
        $this->setRelation('currentSession', $session);

        return $session;
    }

    public function closeSession(): void
    {
        $this->currentSession?->close();
    }

    public function viewersSummary(): array
    {
        $session = $this->liveSession();

        return [
            'current' => $session?->currentViewers() ?? 0,
            'peak' => $session?->peak_viewers ?? 0,
            'unique' => $session?->unique_viewers ?? 0,
        ];
    }

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
            'viewers' => $this->viewersSummary(),
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
            'viewers' => $this->liveSession()?->currentViewers() ?? 0,
        ];
    }
}
