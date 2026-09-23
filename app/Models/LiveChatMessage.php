<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveChatMessage extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'body',
        'is_host',
        'pinned_at',
        'hidden_at',
    ];

    protected $casts = [
        'is_host' => 'boolean',
        'pinned_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('hidden_at');
    }

    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'body' => $this->body,
            'is_host' => $this->is_host,
            'pinned' => $this->pinned_at !== null,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'Participante',
                'image' => $this->user?->image,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
