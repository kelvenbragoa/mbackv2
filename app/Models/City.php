<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user(){
        return $this->hasMany('App\Models\User', 'id', 'city_id');
    }

    public function event(){
        return $this->hasMany('App\Models\Event', 'id', 'city_id');
    }

}
