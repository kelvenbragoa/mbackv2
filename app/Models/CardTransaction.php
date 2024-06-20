<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTransaction extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function card(){
        return $this->hasOne('App\Models\EventCard', 'id', 'event_card_id');
    }

}
