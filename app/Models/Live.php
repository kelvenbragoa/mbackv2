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
    ];

    protected $hidden = [
        'stream_key',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
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
