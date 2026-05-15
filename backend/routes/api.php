<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes - Website Bán Hàng
|--------------------------------------------------------------------------
|
| Định nghĩa tất cả các routes API cho website bán hàng.
| Các route được nhóm theo chức năng: Auth, Sản phẩm, Danh mục, Giỏ hàng, Đơn hàng.
|
*/

// ============================================================
// ROUTES XÁC THỰC (Authentication)
// ============================================================
Route::prefix('auth')->group(function () {
    // Đăng ký tài khoản mới
    Route::post('/register', [AuthController::class, 'register']);

    // Đăng nhập và nhận JWT token
    Route::post('/login', [AuthController::class, 'login']);

    // Các route yêu cầu đăng nhập
    Route::middleware('auth:api')->group(function () {
        // Đăng xuất
        Route::post('/logout', [AuthController::class, 'logout']);

        // Lấy thông tin người dùng hiện tại
        Route::get('/profile', [AuthController::class, 'getProfile']);

        // Cập nhật thông tin cá nhân
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        // Làm mới JWT token
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// ============================================================
// ROUTES DANH MỤC (Categories) - Public
// ============================================================
Route::prefix('categories')->group(function () {
    // Lấy danh sách tất cả danh mục
    Route::get('/', [CategoryController::class, 'index']);

    // Lấy chi tiết danh mục theo ID hoặc slug
    Route::get('/{id}', [CategoryController::class, 'show']);
});

// ============================================================
// ROUTES SẢN PHẨM (Products) - Public
// ============================================================
Route::prefix('products')->group(function () {
    // Lấy danh sách sản phẩm (có hỗ trợ tìm kiếm, lọc, phân trang)
    Route::get('/', [ProductController::class, 'index']);

    // Lấy chi tiết sản phẩm theo ID hoặc slug
    Route::get('/{id}', [ProductController::class, 'show']);
});

// ============================================================
// ROUTES GIỎ HÀNG (Cart) - Yêu cầu đăng nhập
// ============================================================
Route::prefix('cart')->middleware('auth:api')->group(function () {
    // Xem giỏ hàng hiện tại
    Route::get('/', [CartController::class, 'getCart']);

    // Thêm sản phẩm vào giỏ hàng
    Route::post('/add', [CartController::class, 'addItem']);

    // Cập nhật số lượng sản phẩm trong giỏ hàng
    Route::put('/update/{productId}', [CartController::class, 'updateItem']);

    // Xóa sản phẩm khỏi giỏ hàng
    Route::delete('/remove/{productId}', [CartController::class, 'removeItem']);

    // Xóa toàn bộ giỏ hàng
    Route::delete('/clear', [CartController::class, 'clearCart']);
});

// ============================================================
// ROUTES ĐƠN HÀNG (Orders) - Yêu cầu đăng nhập
// ============================================================
Route::prefix('orders')->middleware('auth:api')->group(function () {
    // Tạo đơn hàng mới từ giỏ hàng
    Route::post('/', [OrderController::class, 'createOrder']);

    // Lấy danh sách đơn hàng của người dùng
    Route::get('/', [OrderController::class, 'getUserOrders']);

    // Lấy chi tiết đơn hàng
    Route::get('/{id}', [OrderController::class, 'getOrderDetail']);

    // Huỷ đơn hàng (chỉ khi trạng thái là pending)
    Route::put('/{id}/cancel', [OrderController::class, 'cancelOrder']);
});

// ============================================================
// ROUTES ADMIN - Yêu cầu đăng nhập và quyền admin
// ============================================================
Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {

    // Thống kê tổng quan (dashboard)
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // --- Quản lý Danh mục ---
    Route::prefix('categories')->group(function () {
        // Lấy danh sách danh mục (admin có thể thấy cả danh mục ẩn)
        Route::get('/', [CategoryController::class, 'index']);

        // Tạo danh mục mới
        Route::post('/', [CategoryController::class, 'store']);

        // Lấy chi tiết danh mục
        Route::get('/{id}', [CategoryController::class, 'show']);

        // Cập nhật danh mục
        Route::put('/{id}', [CategoryController::class, 'update']);

        // Xóa danh mục
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });

    // --- Quản lý Sản phẩm ---
    Route::prefix('products')->group(function () {
        // Lấy danh sách sản phẩm (bao gồm cả sản phẩm ẩn)
        Route::get('/', [ProductController::class, 'index']);

        // Thêm sản phẩm mới
        Route::post('/', [ProductController::class, 'store']);

        // Lấy chi tiết sản phẩm
        Route::get('/{id}', [ProductController::class, 'show']);

        // Cập nhật sản phẩm
        Route::put('/{id}', [ProductController::class, 'update']);

        // Xóa sản phẩm
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

    // --- Quản lý Đơn hàng ---
    Route::prefix('orders')->group(function () {
        // Lấy tất cả đơn hàng của hệ thống
        Route::get('/', [OrderController::class, 'getAllOrders']);

        // Lấy chi tiết đơn hàng
        Route::get('/{id}', [OrderController::class, 'getOrderDetail']);

        // Cập nhật trạng thái đơn hàng
        Route::put('/{id}/status', [OrderController::class, 'updateOrderStatus']);
    });

    // --- Quản lý Người dùng ---
    Route::prefix('users')->group(function () {
        // Lấy danh sách người dùng
        Route::get('/', [AuthController::class, 'getAllUsers']);

        // Lấy chi tiết người dùng
        Route::get('/{id}', [AuthController::class, 'getUserById']);
    });
});
