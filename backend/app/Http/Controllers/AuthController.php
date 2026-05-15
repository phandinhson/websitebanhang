<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * Controller Xác thực - Quản lý đăng ký, đăng nhập, đăng xuất và hồ sơ người dùng
 */
class AuthController extends Controller
{
    // ============================================================
    // ĐĂNG KÝ TÀI KHOẢN
    // ============================================================

    /**
     * Đăng ký tài khoản mới
     *
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone'    => 'nullable|string|max:20',
        ], [
            'name.required'      => 'Vui lòng nhập họ tên.',
            'email.required'     => 'Vui lòng nhập địa chỉ email.',
            'email.email'        => 'Địa chỉ email không hợp lệ.',
            'email.unique'       => 'Email này đã được sử dụng.',
            'password.required'  => 'Vui lòng nhập mật khẩu.',
            'password.min'       => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Tạo người dùng mới
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password, // Tự động hash trong model mutator
                'phone'    => $request->phone,
                'role'     => 'customer',
            ]);

            // Tạo JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký tài khoản thành công!',
                'data'    => [
                    'user'         => $this->formatUser($user),
                    'token'        => $token,
                    'token_type'   => 'bearer',
                    'expires_in'   => config('jwt.ttl') * 60, // giây
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // ĐĂNG NHẬP
    // ============================================================

    /**
     * Đăng nhập và nhận JWT token
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Vui lòng nhập email.',
            'email.email'       => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            // Thử đăng nhập và lấy token
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không đúng.',
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo token. Vui lòng thử lại.',
            ], 500);
        }

        $user = Auth::user();

        // Kiểm tra tài khoản có bị vô hiệu hoá không
        if (!$user->is_active) {
            JWTAuth::invalidate($token);
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị vô hiệu hoá. Vui lòng liên hệ hỗ trợ.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'data'    => [
                'user'       => $this->formatUser($user),
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60, // giây
            ],
        ]);
    }

    // ============================================================
    // ĐĂNG XUẤT
    // ============================================================

    /**
     * Đăng xuất (vô hiệu hoá token hiện tại)
     *
     * POST /api/auth/logout
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Đăng xuất thành công!',
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng xuất thất bại. Vui lòng thử lại.',
            ], 500);
        }
    }

    // ============================================================
    // LÀM MỚI TOKEN
    // ============================================================

    /**
     * Làm mới JWT token
     *
     * POST /api/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Token đã được làm mới.',
                'data'    => [
                    'token'      => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token không hợp lệ hoặc đã hết hạn.',
            ], 401);
        }
    }

    // ============================================================
    // HỒ SƠ NGƯỜI DÙNG
    // ============================================================

    /**
     * Lấy thông tin hồ sơ người dùng đang đăng nhập
     *
     * GET /api/auth/profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            return response()->json([
                'success' => true,
                'data'    => $this->formatUser($user),
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token không hợp lệ.',
            ], 401);
        }
    }

    /**
     * Cập nhật thông tin hồ sơ người dùng
     *
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name'             => 'sometimes|string|max:255',
            'phone'            => 'sometimes|nullable|string|max:20',
            'avatar'           => 'sometimes|nullable|string|url',
            'address'          => 'sometimes|nullable|array',
            'address.street'   => 'nullable|string|max:500',
            'address.ward'     => 'nullable|string|max:255',
            'address.district' => 'nullable|string|max:255',
            'address.city'     => 'nullable|string|max:255',
            'password'         => 'sometimes|string|min:6|confirmed',
            'current_password' => 'required_with:password|string',
        ], [
            'name.max'                => 'Họ tên không được quá 255 ký tự.',
            'phone.max'               => 'Số điện thoại không được quá 20 ký tự.',
            'password.min'            => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.confirmed'      => 'Xác nhận mật khẩu mới không khớp.',
            'current_password.required_with' => 'Vui lòng nhập mật khẩu hiện tại để đổi mật khẩu.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Kiểm tra mật khẩu hiện tại nếu muốn đổi mật khẩu
        if ($request->filled('password')) {
            if (!password_verify($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không đúng.',
                    'errors'  => ['current_password' => ['Mật khẩu hiện tại không đúng.']],
                ], 422);
            }
        }

        try {
            // Cập nhật các trường được cung cấp
            $updateData = $request->only(['name', 'phone', 'avatar', 'address']);
            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật hồ sơ thành công!',
                'data'    => $this->formatUser($user->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật hồ sơ thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // ADMIN - Quản lý người dùng
    // ============================================================

    /**
     * Lấy danh sách tất cả người dùng (Admin)
     *
     * GET /api/admin/users
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $search  = $request->get('search');
        $role    = $request->get('role');

        $query = User::query();

        // Tìm kiếm theo tên hoặc email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Lọc theo vai trò
        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')
                       ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'users'      => $users->map(fn($u) => $this->formatUser($u)),
                'pagination' => [
                    'total'        => $users->total(),
                    'per_page'     => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Lấy chi tiết thông tin một người dùng (Admin)
     *
     * GET /api/admin/users/{id}
     */
    public function getUserById(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Định dạng thông tin người dùng để trả về API
     */
    private function formatUser(User $user): array
    {
        return [
            'id'         => (string) $user->_id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'avatar'     => $user->avatar,
            'address'    => $user->address,
            'role'       => $user->role,
            'is_active'  => $user->is_active,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
