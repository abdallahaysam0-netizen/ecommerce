<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\SendOrdersStatusEmail;
use Illuminate\Support\ServiceProvider;

class EventServiceListener extends ServiceProvider
{
protected $listen=[
    OrderStatusChanged::class=>[
    SendOrdersStatusEmail::class,
    
    ],
];
}