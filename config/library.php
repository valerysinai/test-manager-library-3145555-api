<?php
return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'admin_username' => env('ADMIN_USERNAME', ''),
    'admin_password' => env('ADMIN_PASSWORD', ''),
    'clock_mode' => env('CLOCK_MODE', 'system'),
    'clock_fixed_at' => env('CLOCK_FIXED_AT'),
    'covers_dir' => env('COVERS_DIR', storage_path('app/private/covers')),
    'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
];
