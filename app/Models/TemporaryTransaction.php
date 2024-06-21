<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryTransaction extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function sell(){
        return $this->hasOne('App\Models\TemporarySell', 'id', 'sell_id');
    }

}
