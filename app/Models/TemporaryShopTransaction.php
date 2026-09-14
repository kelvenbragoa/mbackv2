<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryShopTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
    ];

    public function sell()
    {
        return $this->belongsTo(TemporarySellShop::class, 'sell_id');
    }
}
