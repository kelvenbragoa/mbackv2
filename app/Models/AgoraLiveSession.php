<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AgoraLiveSession extends Model
{
    /** A viewer stops counting as "watching now" after this many seconds without a ping. */
    public const VIEWER_TIMEOUT_SECONDS = 45;

    protected $fillable = [
        'agora_live_id',
        'host_user_id',
        'started_at',
        'ended_at',
        'peak_viewers',
        'unique_viewers',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'peak_viewers' => 'integer',
        'unique_viewers' => 'integer',
    ];

    public function live()
    {
        return $this->belongsTo(AgoraLive::class, 'agora_live_id');
    }

    public function viewers()
    {
        return $this->hasMany(AgoraLiveViewer::class, 'agora_live_session_id');
    }

    public function currentViewers(): int
    {
        if ($this->ended_at) {
            return 0;
        }

        return $this->viewers()
            ->where('last_seen_at', '>=', now()->subSeconds(self::VIEWER_TIMEOUT_SECONDS))
            ->count();
    }

    /**
     * Records a viewer ping and keeps the stored peak/unique counters up to date.
     */
    public function touchViewer(int $userId): int
    {
        $now = now();

        $inserted = DB::table('agora_live_viewers')->insertOrIgnore([
            'agora_live_session_id' => $this->id,
            'user_id' => $userId,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        if (! $inserted) {
            DB::table('agora_live_viewers')
                ->where('agora_live_session_id', $this->id)
                ->where('user_id', $userId)
                ->update(['last_seen_at' => $now]);
        }

        $current = $this->currentViewers();
        $updates = [];

        if ($inserted) {
            $updates['unique_viewers'] = DB::raw('unique_viewers + 1');
        }
        if ($current > $this->peak_viewers) {
            $updates['peak_viewers'] = DB::raw('GREATEST(peak_viewers, '.(int) $current.')');
        }
        if ($updates) {
            static::whereKey($this->id)->update($updates);
        }

        return $current;
    }

    public function close(): void
    {
        if ($this->ended_at) {
            return;
        }

        $this->update(['ended_at' => $this->live?->host_seen_at ?? now()]);
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_seconds' => $this->started_at
                ? (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), true)
                : 0,
            'peak_viewers' => $this->peak_viewers,
            'unique_viewers' => $this->unique_viewers,
        ];
    }
}
