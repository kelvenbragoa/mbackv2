<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellDetailBar extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function product(){
        return $this->hasOne('App\Models\Products', 'id', 'product_id');
    }

    public function sell(){
        return $this->hasOne('App\Models\SellBar', 'id', 'sell_id');
    }

    public function user(){
        return $this->hasOne('App\Models\Barman', 'id', 'user_id');
    }

    public function transaction(){
        return $this->hasOne('App\Models\CardTransaction', 'sell_id', 'sell_id');
    }

}
