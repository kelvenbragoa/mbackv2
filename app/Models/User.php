<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasSlug;

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
        'slug',
        'banner',
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

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function (User $model) {
                return $model->company_name ?: $model->name;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->skipGenerateWhen(fn () => ! $this->is_promotor);
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

    public function events()
    {
        return $this->hasMany(Event::class, 'user_id', 'id');
    }

    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'phone' => $this->mobile,
            'address' => $this->address,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toPublicPageArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'company_location' => $this->company_location,
            'description' => $this->description,
            'slug' => $this->slug,
            'image' => $this->image,
            'banner' => $this->banner,
        ];
    }
}
