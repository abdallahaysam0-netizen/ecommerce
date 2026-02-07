<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'type'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

 public function isAdmin(): bool
{
    return $this->hasRole('admin') || $this->type === 'admin';
}

    public function isCustomer(): bool
    {
        return $this->type === "customer";
    }



    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    protected $guard_name = 'sanctum';
}
