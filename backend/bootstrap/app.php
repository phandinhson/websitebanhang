<?php

/*
|--------------------------------------------------------------------------
| Create The Application - Website Bán Hàng Backend
|--------------------------------------------------------------------------
|
| Đây là điểm khởi đầu của ứng dụng Laravel. Chúng ta tạo instance
| ứng dụng, đăng ký các service provider và middleware, sau đó
| trả về instance để bootstrap/index.php sử dụng.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Đăng ký các interface cốt lõi của ứng dụng để khi cần
| framework biết cách resolve chúng.
|
*/

// Đăng ký HTTP Kernel
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

// Đăng ký Console Kernel (Artisan)
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

// Đăng ký Exception Handler
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| Trả về instance ứng dụng để framework có thể sử dụng.
|
*/

return $app;
