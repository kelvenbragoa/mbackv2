<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MuxLiveViewer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'mux_live_session_id',
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
        return $this->belongsTo(MuxLiveSession::class, 'mux_live_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
};
