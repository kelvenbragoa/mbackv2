<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgoraLiveViewer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agora_live_session_id',
        'user_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AgoraLiveSession::class, 'agora_live_session_id');
    }
}
