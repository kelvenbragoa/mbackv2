<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockNote extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockNoteItem::class);
    }

    public function barstore()
    {
        return $this->belongsTo(BarStore::class, 'bar_store_id');
    }

    public function toBarstore()
    {
        return $this->belongsTo(BarStore::class, 'to_bar_store_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function movements()
    {
        return $this->hasMany(ProductStockMovement::class);
    }
}
