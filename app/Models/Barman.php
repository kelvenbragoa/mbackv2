<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barman extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }


    public function sells(){
        return $this->hasMany('App\Models\SellBar', 'user_id', 'id')->orderBy('created_at','asc');
    }

    public function barstore(){
        return $this->hasOne('App\Models\BarStore', 'id', 'bar_store_id');
    }

}
