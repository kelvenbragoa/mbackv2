<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'address',
        'province_id',
        'role_id',
        'city_id',
        'bi',
        'gender_id',
        'is_promotor',
        'image',
        'description',
        'company_name',
        'company_location',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(){
        return $this->hasOne('App\Models\Role', 'id', 'role_id');
    }
    public function city(){
        return $this->hasOne('App\Models\City', 'id', 'city_id');
    }
    public function province(){
        return $this->hasOne('App\Models\Province', 'id', 'province_id');
    }
    public function gender(){
        return $this->hasOne('App\Models\Gender', 'id', 'gender_id');
    }
}
