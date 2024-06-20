<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellDetails extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    public function ticket(){
        return $this->hasOne('App\Models\Ticket', 'id', 'ticket_id');
    }

    public function user(){
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function sell(){
        return $this->hasOne('App\Models\Sell', 'id', 'sell_id');
    }

}
