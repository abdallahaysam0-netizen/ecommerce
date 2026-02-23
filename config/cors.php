<?php

return [
    // المسارات اللي بنطبق عليها الـ CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // السماح لكل أنواع الطلبات (GET, POST, etc)
    'allowed_methods' => ['*'],

    // السماح لجميع الروابط عشان متتعبش مع تغيير لينكات Vercel و Ngrok
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // السماح بجميع الهيدرز (مهم جداً عشان هيدر ngrok اللي ضفناه)
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];