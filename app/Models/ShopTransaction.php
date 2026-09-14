<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function sell()
    {
        return $this->belongsTo(SellShop::class, 'sell_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
