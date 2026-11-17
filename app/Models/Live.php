<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Live extends Model
{
    use HasFactory;

    public const STATUS_IDLE = 'idle';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    public const RTMP_URL = 'rtmps://global-live.mux.com:443/app';

    protected $fillable = [
        'event_id',
        'mux_live_stream_id',
        'stream_key',
        'playback_id',
        'policy',
        'status',
        'current_session_id',
    ];

    protected $hidden = [
        'stream_key',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function sessions()
    {
        return $this->hasMany(MuxLiveSession::class, 'live_id', 'id');
    }

    public function currentSession()
    {
        return $this->belongsTo(MuxLiveSession::class, 'current_session_id');
    }

    /**
     * Session that viewers should be counted against, only while the stream is on air.
     */
    public function liveSession(): ?MuxLiveSession
    {
        if (! $this->isActive() || ! $this->current_session_id) {
            return null;
        }

        $session = $this->currentSession;

        return $session && ! $session->ended_at ? $session : null;
    }

    /**
     * A new session starts every time the broadcast goes on air after being off.
     */
    public function openSession(): MuxLiveSession
    {
        if ($session = $this->liveSession()) {
            return $session;
        }

        $this->currentSession?->close();

        $session = $this->sessions()->create([
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

    /**
     * Open a session when the stream is active and close it when it is not.
     */
    public function syncAudienceSession(): void
    {
        if ($this->isActive()) {
            $this->openSession();

            return;
        }

        $this->closeSession();
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function toPromotorArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'policy' => $this->policy,
            'rtmp_url' => self::RTMP_URL,
            'stream_key' => $this->stream_key,
            'playback_id' => $this->playback_id,
            'viewers' => $this->viewersSummary(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'status' => $this->status,
            'active' => $this->isActive(),
        ];
    }
}
