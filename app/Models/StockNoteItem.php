<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockNoteItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function note()
    {
        return $this->belongsTo(StockNote::class, 'stock_note_id');
    }

    public function product()
    {
        return $this->belongsTo(Products::class, 'product_id');
    }

    public function toProduct()
    {
        return $this->belongsTo(Products::class, 'to_product_id');
    }
}
