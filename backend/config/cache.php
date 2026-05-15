<?php
return [
    'default' => env('CACHE_DRIVER', 'file'),
    'stores' => [
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'array' => ['driver' => 'array', 'serialize' => false],
        'null' => ['driver' => 'null'],
    ],
    'prefix' => env('CACHE_PREFIX', 'websitebanhang_cache'),
];
