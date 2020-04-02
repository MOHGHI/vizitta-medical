<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class Manager extends Authenticatable  implements JWTSubject
{
    use Notifiable ,HasRoles;
    protected $table = 'managers';
    protected $guard_name = ['web', 'manager-api'];
    public $timestamps = true;

    protected $fillable = ['name_en', 'name_ar', 'mobile', 'email', 'password', 'api_token',
        'paid_balance', 'unpaid_balance','balance', 'app_price'
    ];

    //ff//
    protected $hidden = [
        'password', 'remember_token', 'api_token',
    ];

    public function messages()
    {
        return $this->hasMany('App\Models\Message', 'manager_id', 'id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function setPasswordAttribute($password)
    {
        if ( !empty($password) ) {
            $this->attributes['password'] = bcrypt($password);
        }
    }

    public static function laratablesCustomAction($admin)
    {
        return view('admins.actions', compact('admin'))->render();
    }

    public function laratablesCreatedAt( )
    {
        return Carbon::parse($this->created_at)->format('Y-m-d');
    }

}
