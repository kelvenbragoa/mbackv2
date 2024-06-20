<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function customers(){
        return $this->hasMany('App\Models\CustomerInvite', 'invite_id', 'id');
    }

}
