<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

/**
 * HTTP Kernel - Quản lý middleware cho ứng dụng
 *
 * Đăng ký các middleware toàn cục, middleware group và middleware alias.
 * Đặc biệt đăng ký JWT middleware và AdminMiddleware cho dự án.
 */
class Kernel extends HttpKernel
{
    /**
     * Middleware toàn cục - chạy cho MỌI request HTTP
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Kiểm tra ứng dụng đang trong chế độ maintenance
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        // Giới hạn kích thước request
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        // Trim whitespace khỏi input
        \App\Http\Middleware\TrimStrings::class,
        // Chuyển chuỗi rỗng thành null
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        // CORS middleware
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

    /**
     * Middleware groups - nhóm middleware theo mục đích
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        // Middleware cho web routes (có session, CSRF, v.v.)
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        // Middleware cho API routes (stateless - không dùng session)
        'api' => [
            // Giới hạn số request (rate limiting)
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            // Resolve route model binding
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Middleware aliases - đặt tên ngắn gọn cho middleware
     *
     * Các alias này được dùng trong routes:
     * Route::middleware('auth:api')->...
     * Route::middleware('admin')->...
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        // Authentication middleware
        'auth'             => \App\Http\Middleware\Authenticate::class,
        'auth.basic'       => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'     => \Illuminate\Session\Middleware\AuthenticateSession::class,

        // Cache middleware
        'cache.headers'    => \Illuminate\Http\Middleware\SetCacheHeaders::class,

        // Authorization middleware
        'can'              => \Illuminate\Auth\Middleware\Authorize::class,

        // Guest middleware (chỉ cho user chưa đăng nhập)
        'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,

        // Password confirmation middleware
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,

        // Rate limiting
        'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,

        // Route model binding (verified)
        'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Signed URLs
        'signed'           => \App\Http\Middleware\ValidateSignature::class,

        // ============================================================
        // JWT Authentication Middleware
        // ============================================================

        // JWT Authenticate - bắt buộc đăng nhập bằng JWT token
        // Sử dụng: Route::middleware('jwt.auth')->...
        'jwt.auth'         => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,

        // JWT Refresh - tự động làm mới token
        // Sử dụng: Route::middleware('jwt.refresh')->...
        'jwt.refresh'      => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,

        // JWT Verify - chỉ xác minh token, không bắt buộc
        // Sử dụng: Route::middleware('jwt.verify')->...
        'jwt.verify'       => \Tymon\JWTAuth\Http\Middleware\Check::class,

        // ============================================================
        // Custom Middleware cho Website Bán Hàng
        // ============================================================

        // Admin middleware - chỉ cho phép người dùng có role = 'admin'
        // Sử dụng: Route::middleware(['auth:api', 'admin'])->...
        'admin'            => \App\Http\Middleware\AdminMiddleware::class,
    ];

    /**
     * Middleware priority - thứ tự ưu tiên xử lý middleware
     * (Không cần thay đổi trong hầu hết các trường hợp)
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ];
}
