<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sell extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user(){
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function ticket(){
        return $this->hasOne('App\Models\Ticket', 'id', 'ticket_id');
    }

    public function event(){
        return $this->hasOne('App\Models\Event', 'id', 'event_id');
    }

    

    public function selldetails(){
        return $this->hasMany('App\Models\SellDetails', 'sell_id', 'id');
    }


    //erro de logica, mas funciona ter em mente para alterar
    public function transaction(){
        return $this->hasOne('App\Models\Transaction', 'sell_id', 'id');
    }

    /**
     * Where the sale happened. Derived from existing columns (no extra table).
     * online | lote | box_office
     */
    public function saleChannel(): string
    {
        if (! empty($this->protocol_id) && (int) $this->protocol_id > 0) {
            return 'box_office';
        }

        $method = $this->relationLoaded('transaction')
            ? $this->transaction?->method
            : $this->transaction()->value('method');

        if ($method === 'lote') {
            return 'lote';
        }

        return 'online';
    }

}
