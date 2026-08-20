<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MobileApp extends Model
{
    protected $table = 'apps';

    protected $guarded = [];

    protected $casts = [
        'force_update' => 'boolean',
        'min_version_code' => 'integer',
    ];

    public function releases(): HasMany
    {
        return $this->hasMany(AppRelease::class, 'app_id')->orderByDesc('version_code');
    }

    public function latestRelease(): HasOne
    {
        return $this->hasOne(AppRelease::class, 'app_id')->latestOfMany('version_code');
    }

    public function isInternal(): bool
    {
        return $this->distribution === 'internal';
    }

    public function toStoreArray(?AppRelease $release = null): array
    {
        $release ??= $this->latestRelease;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'distribution' => $this->distribution,
            'package_name' => $this->package_name,
            'play_store_url' => $this->play_store_url,
            'app_store_url' => $this->app_store_url,
            'min_version_code' => (int) $this->min_version_code,
            'force_update' => (bool) $this->force_update,
            'latest_release' => $release ? $release->toStoreArray() : null,
        ];
    }
}
