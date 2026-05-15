<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * Middleware kiểm tra quyền Admin
 *
 * Middleware này đảm bảo chỉ những người dùng có role = 'admin'
 * mới được truy cập vào các route admin.
 *
 * Cách sử dụng: Route::middleware(['auth:api', 'admin'])->group(...)
 */
class AdminMiddleware
{
    /**
     * Xử lý request đến
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Lấy thông tin người dùng từ JWT token
            $user = JWTAuth::parseToken()->authenticate();

            // Kiểm tra token có hợp lệ không
            if (!$user) {
                return $this->unauthorizedResponse('Token không hợp lệ. Vui lòng đăng nhập lại.');
            }

            // Kiểm tra tài khoản có bị vô hiệu hoá không
            if (!$user->is_active) {
                return $this->forbiddenResponse('Tài khoản của bạn đã bị vô hiệu hoá.');
            }

            // Kiểm tra quyền admin
            if (!$user->isAdmin()) {
                return $this->forbiddenResponse(
                    'Bạn không có quyền truy cập vào trang này. Chỉ dành cho quản trị viên.'
                );
            }

        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token đã hết hạn. Vui lòng đăng nhập lại.');

        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token không hợp lệ.');

        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Token không được cung cấp hoặc không hợp lệ.');
        }

        return $next($request);
    }

    /**
     * Trả về response lỗi 401 Unauthorized
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code'    => 'UNAUTHORIZED',
        ], 401);
    }

    /**
     * Trả về response lỗi 403 Forbidden
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code'    => 'FORBIDDEN',
        ], 403);
    }
}
