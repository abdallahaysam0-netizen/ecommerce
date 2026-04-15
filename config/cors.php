<?php

return [
    // المسارات اللي بنطبق عليها الـ CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    // السماح لكل أنواع الطلبات (GET, POST, etc)
    'allowed_methods' => ['*'],

    // السماح لجميع الروابط عشان متتعبش مع تغيير لينكات Vercel و Ngrok
'allowed_origins' => [
    'http://localhost:3000', // إذا كنت تشغل React على هذا المنفذ
    'http://127.0.0.1:8000', // الرابط الافتراضي لـ php artisan serve
    'http://localhost',      // رابط XAMPP الافتراضي
    'https://myshop-frontend-git-master-abdallahaysam0-netizens-projects.vercel.app',
    'https://myshop-frontend-abdallahaysam0-netizens-projects.vercel.app'
],
    'allowed_origins_patterns' => [],

    // السماح بجميع الهيدرز (مهم جداً عشان هيدر ngrok اللي ضفناه)
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];