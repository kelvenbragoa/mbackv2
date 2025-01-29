<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Protocol extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function tickets(){
        return $this->hasMany('App\Models\SellDetails', 'protocol_id', 'id');
    }

}
