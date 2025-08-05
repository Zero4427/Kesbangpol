<?php

return [
    'paths' => ['api/*'], // Izinkan semua rute di bawah /api dan /faq
    'allowed_methods' => ['*'], // Izinkan semua metode (GET, POST, PUT, DELETE, dll.)
    'allowed_origins' => ['http://localhost:3000'], // Izinkan origin frontend Anda
    'allowed_headers' => ['*'], // Izinkan semua header, termasuk Authorization
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Jika Anda menggunakan cookie atau Authorization
];