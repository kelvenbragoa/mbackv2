<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventCard extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function user(){
        return $this->hasOne('App\Models\Barman', 'id', 'user_id');
    }

}
