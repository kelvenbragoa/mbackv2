<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;
    protected $guarded = [];


    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function sells(){
        return $this->hasMany('App\Models\SellDetails', 'ticket_id', 'id');
    }

    public function sell(){
        return $this->hasMany('App\Models\Sell', 'ticket_id', 'id');
    }

}
