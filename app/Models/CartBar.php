<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartBar extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function product(){
        return $this->hasOne('App\Models\Products', 'id', 'product_id');
    }

}
