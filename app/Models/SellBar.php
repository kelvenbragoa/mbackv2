<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellBar extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function selldetails(){
        return $this->hasMany('App\Models\SellDetailBar', 'sell_id', 'id');
    }

    public function user(){
        return $this->hasOne('App\Models\Barman', 'id', 'user_id');
    }

    public function verified_by_user(){
        return $this->hasOne('App\Models\Barman', 'id', 'verified_by');
    }

}
