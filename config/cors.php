<?php
return [
    'paths'=>['api/*'],'allowed_methods'=>['GET','POST','PUT','DELETE','OPTIONS'],
    'allowed_origins'=>[env('CORS_ORIGIN','http://localhost:5173')],'allowed_origins_patterns'=>[],
    'allowed_headers'=>['Authorization','Content-Type'],'exposed_headers'=>[],
    'max_age'=>600,'supports_credentials'=>false,
];
