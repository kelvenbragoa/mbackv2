<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function sells(){
        return $this->hasMany('App\Models\SellDetailBar', 'product_id', 'id');
    }

    public function barstore(){
        return $this->hasOne('App\Models\BarStore', 'id', 'bar_store_id');
    }

}
