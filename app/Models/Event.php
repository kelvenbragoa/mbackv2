<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Event extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function hasMapCoordinates(): bool
    {
        return $this->latitude !== null
            && $this->longitude !== null
            && (float) $this->latitude !== 0.0
            && (float) $this->longitude !== 0.0;
    }

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

    public function sold_details()
    {
        return $this->hasMany(SellDetails::class, 'event_id', 'id')
            ->where('status', '!=', SellDetails::STATUS_RETURNED);
    }

    public function live()
    {
        return $this->hasOne(Live::class, 'event_id', 'id');
    }

    public function agoraLive()
    {
        return $this->hasOne(AgoraLive::class, 'event_id', 'id');
    }

    public function hasValidTicketFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->sell_details()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('sell', function ($sell) use ($user) {
                        $sell->where('user_id', $user->id);
                    });
            })
            ->exists();
    }

    public function hasLiveTicketFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->activeLiveSellDetailsQuery($user)->exists();
    }

    /**
     * Latest access_ends_at among active live passes.
     * Null means active without expiry (or no active pass — check hasLiveTicketFor first).
     */
    public function liveAccessEndsAtFor(?User $user): ?Carbon
    {
        if (! $user) {
            return null;
        }

        $ends = $this->activeLiveSellDetailsQuery($user)
            ->whereNotNull('access_ends_at')
            ->max('access_ends_at');

        if ($ends) {
            return Carbon::parse($ends);
        }

        return null;
    }

    public function hasExpiredLiveTicketFor(?User $user): bool
    {
        if (! $user || $this->hasLiveTicketFor($user)) {
            return false;
        }

        return $this->sell_details()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('sell', function ($sell) use ($user) {
                        $sell->where('user_id', $user->id);
                    });
            })
            ->where('status', '!=', SellDetails::STATUS_RETURNED)
            ->whereHas('ticket', function ($ticket) {
                $ticket->where('is_live', 1);
            })
            ->whereNotNull('access_ends_at')
            ->where('access_ends_at', '<=', now())
            ->exists();
    }

    public function canWatchLive(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $user->role_id === 1 || (int) $this->user_id === (int) $user->id) {
            return true;
        }

        return $this->hasLiveTicketFor($user);
    }

    private function activeLiveSellDetailsQuery(User $user)
    {
        return $this->sell_details()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('sell', function ($sell) use ($user) {
                        $sell->where('user_id', $user->id);
                    });
            })
            ->where('status', '!=', SellDetails::STATUS_RETURNED)
            ->whereHas('ticket', function ($ticket) {
                $ticket->where('is_live', 1);
            })
            ->where(function ($query) {
                $query->whereNull('access_ends_at')
                    ->orWhere('access_ends_at', '>', now());
            });
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

    public function shopProducts()
    {
        return $this->hasMany(ShopProduct::class, 'event_id', 'id')->orderBy('name');
    }

    public function sellShops()
    {
        return $this->hasMany(SellShop::class, 'event_id', 'id');
    }

    public function getPriceAttribute()
    {
        $firstTicket = $this->tickets()->orderBy('price', 'asc')->first();
        return $firstTicket ? (int) $firstTicket->price : 0;
    }

    /**
     * Same rule as the web checkout: sales close after the event end_date.
     */
    public function isSalesClosed(): bool
    {
        if (empty($this->end_date)) {
            return false;
        }

        try {
            return now()->gt(\Carbon\Carbon::parse($this->end_date)->endOfDay());
        } catch (\Throwable) {
            return false;
        }
    }
}
