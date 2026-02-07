<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
// إذا كنت تستخدم Sanctum
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('admin.orders',function(User $user){
return $user->hasRole('admin');
});