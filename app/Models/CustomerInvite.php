<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerInvite extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function invite(){
        return $this->hasOne('App\Models\Invite', 'id', 'invite_id');
    }
    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }


}
