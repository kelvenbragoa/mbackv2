<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellBar extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'total' => 'float',
    ];

    public function selldetails()
    {
        return $this->hasMany('App\Models\SellDetailBar', 'sell_id', 'id');
    }

    public function user()
    {
        return $this->hasOne('App\Models\Barman', 'id', 'user_id');
    }

    public function verified_by_user()
    {
        return $this->hasOne('App\Models\Barman', 'id', 'verified_by');
    }
}
