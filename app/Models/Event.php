<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Event extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
    public function city(){
        return $this->hasOne('App\Models\City', 'id', 'city_id');
    }

    public function province(){
        return $this->hasOne('App\Models\Province', 'id', 'province_id');
    }

    public function category(){
        return $this->hasOne('App\Models\Category', 'id', 'main_category_id');
    }
    public function secondcategory(){
        return $this->hasOne('App\Models\Category', 'id', 'second_category_id');
    }

    public function status(){
        return $this->hasOne('App\Models\Status', 'id', 'status_id');
    }
    public function type(){
        return $this->hasOne('App\Models\TypeEvent', 'id', 'type_event_id');
    }

    public function user(){
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function tickets(){
        return $this->hasMany('App\Models\Ticket', 'event_id', 'id')->where('is_package',0);
    }

    public function packages(){
        return $this->hasMany('App\Models\Ticket', 'event_id', 'id')->where('is_package',1);
    }

    public function protocols(){
        return $this->hasMany('App\Models\Protocol', 'event_id', 'id');
    }

    public function barman(){
        return $this->hasMany('App\Models\Barman', 'event_id', 'id');
    }

    public function lineups(){
        return $this->hasMany('App\Models\LineUp', 'event_id', 'id');
    }

    public function sells(){
        return $this->hasMany('App\Models\Sell', 'event_id', 'id');
    }

    public function products(){
        return $this->hasMany('App\Models\Products', 'event_id', 'id')->orderBy('name','asc');
    }

    public function barstores(){
        return $this->hasMany('App\Models\BarStore', 'event_id', 'id')->orderBy('name','asc');
    }

    public function sell_details(){
        return $this->hasMany('App\Models\SellDetails', 'event_id', 'id');
    }

    public function review(){
        return $this->hasMany('App\Models\Review', 'event_id', 'id');
    }

    public function like(){
        return $this->hasMany('App\Models\LikeEvent', 'event_id', 'id');
    }

    public function sell_bar(){
        return $this->hasMany('App\Models\SellBar', 'event_id', 'id');
    }

    public function sell_bar_detail(){
        return $this->hasMany('App\Models\SellDetailBar', 'event_id', 'id');
    }

    public function invites(){
        return $this->hasMany('App\Models\Invite', 'event_id', 'id');
    }

    public function getPriceAttribute()
    {
        $firstTicket = $this->tickets()->orderBy('price', 'asc')->first();
        return $firstTicket ? (int) $firstTicket->price : 0;
    }
}
