<?php
// use App\Http\Controllers\PaymobController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/paymob/process', [PaymobController::class, 'process']);
// Route::get('/paymob/callback', [PaymobController::class, 'callback']);
