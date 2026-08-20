<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AppRelease extends Model
{
    protected $guarded = [];

    protected $casts = [
        'version_code' => 'integer',
        'file_size' => 'integer',
        'force_update' => 'boolean',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(MobileApp::class, 'app_id');
    }

    public function downloadUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function toStoreArray(): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'version_name' => $this->version_name,
            'version_code' => (int) $this->version_code,
            'file_size' => $this->file_size,
            'changelog' => $this->changelog,
            'force_update' => (bool) $this->force_update,
            'download_url' => $this->downloadUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
