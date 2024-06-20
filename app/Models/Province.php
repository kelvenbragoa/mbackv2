<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function events(){
        return $this->hasMany('App\Models\Event', 'province_id', 'id');
    }

}
