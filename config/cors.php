<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
// config/cors.php
'allowed_headers' => ['*', 'ngrok-skip-browser-warning'], // لازم تضيف الهيدر ده هنا بالاسم
'exposed_headers' => [],
'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'https://myshop.vercel.app',
    'https://marisa-nonretired-willis.ngrok-free.dev', // الرابط الحالي الخاص بك بدقة
    'https://*.ngrok-free.app',                       // احتياطاً لنطاقات ngrok الجديدة
    'https://*.ngrok-free.dev',
],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,

];