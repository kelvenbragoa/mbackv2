<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarStore extends Model
{
    use HasFactory;
    protected $guarded = [];
    
    public function sells(){
        return $this->hasMany('App\Models\SellDetailBar', 'bar_store_id', 'id');
    }
 
    public function barman(){
        return $this->hasMany('App\Models\Barman', 'bar_store_id', 'id');
    }

    public function sell_bar_detail(){
        return $this->hasMany('App\Models\SellDetailBar', 'bar_store_id', 'id')->orderBy('created_at','asc');
    }

    public function products(){
        return $this->hasMany('App\Models\Products', 'bar_store_id', 'id')->orderBy('name','asc');
    }

}
