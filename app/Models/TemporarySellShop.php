<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporarySellShop extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total' => 'float',
        'status' => 'integer',
    ];

    public function details()
    {
        return $this->hasMany(TemporarySellDetailShop::class, 'sell_id');
    }

    public function transaction()
    {
        return $this->hasOne(TemporaryShopTransaction::class, 'sell_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
