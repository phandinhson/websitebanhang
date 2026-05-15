<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Kết nối database mặc định - sử dụng MongoDB cho website bán hàng
    |
    */

    'default' => env('DB_CONNECTION', 'mongodb'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Cấu hình các kết nối database.
    | Dự án này sử dụng MongoDB làm database chính.
    |
    */

    'connections' => [

        // ============================================================
        // Kết nối SQLite (mặc định Laravel, không dùng trong dự án này)
        // ============================================================
        'sqlite' => [
            'driver'                  => 'sqlite',
            'url'                     => env('DATABASE_URL'),
            'database'                => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        // ============================================================
        // Kết nối MySQL (tuỳ chọn)
        // ============================================================
        'mysql' => [
            'driver'         => 'mysql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '3306'),
            'database'       => env('DB_DATABASE', 'forge'),
            'username'       => env('DB_USERNAME', 'forge'),
            'password'       => env('DB_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ============================================================
        // Kết nối MongoDB - Database chính của dự án
        // ============================================================
        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGO_URI', 'mongodb://localhost:27017/websitebanhang'),
            'database' => env('MONGO_DB', 'websitebanhang'),

            /*
            | Tuỳ chọn kết nối MongoDB nâng cao
            |
            | Nếu không dùng MONGO_URI, có thể cấu hình thủ công:
            |
            | 'host'     => env('DB_HOST', '127.0.0.1'),
            | 'port'     => env('DB_PORT', 27017),
            | 'database' => env('MONGO_DB', 'websitebanhang'),
            | 'username' => env('DB_USERNAME'),
            | 'password' => env('DB_PASSWORD'),
            |
            | Tuỳ chọn SSL/TLS:
            | 'options' => [
            |     'ssl' => true,
            |     'replicaSet' => env('MONGO_REPLICA_SET'),
            |     'authSource' => env('MONGO_AUTH_SOURCE', 'admin'),
            | ],
            */
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
        ],

        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
