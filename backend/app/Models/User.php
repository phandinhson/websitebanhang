<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;

use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Model Người dùng - Lưu trữ thông tin tài khoản khách hàng và admin
 *
 * @property string $_id         MongoDB ObjectID
 * @property string $name        Họ và tên
 * @property string $email       Email đăng nhập
 * @property string $password    Mật khẩu đã được hash
 * @property string $phone       Số điện thoại
 * @property array  $address     Địa chỉ giao hàng mặc định
 * @property string $role        Vai trò: customer | admin
 * @property string $avatar      URL ảnh đại diện
 * @property bool   $is_active   Trạng thái tài khoản
 * @property array  $cart        Giỏ hàng của người dùng
 */
class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable;

    // Tên collection trong MongoDB
    protected $collection = 'users';

    // Kết nối database
    protected $connection = 'pgsql';

    /**
     * Các trường được phép gán hàng loạt
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'avatar',
        'is_active',
        'cart',
    ];

    /**
     * Các trường ẩn khi trả về JSON (không bao giờ trả về mật khẩu)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Ép kiểu dữ liệu cho các trường
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'address'           => 'array',
        'cart'              => 'array',
    ];

    /**
     * Giá trị mặc định cho các trường
     */
    protected $attributes = [
        'role'      => 'customer',
        'is_active' => true,
        'cart'      => [],
    ];

    // ============================================================
    // JWTSubject Interface Methods
    // ============================================================

    /**
     * Lấy key định danh để tạo JWT token
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Trả về các claims tùy chỉnh cho JWT payload
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role'  => $this->role,
            'email' => $this->email,
            'name'  => $this->name,
        ];
    }

    // ============================================================
    // Relationships (Quan hệ)
    // ============================================================

    /**
     * Lấy danh sách đơn hàng của người dùng
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    // ============================================================
    // Accessors & Mutators
    // ============================================================

    /**
     * Tự động hash mật khẩu khi lưu
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Kiểm tra người dùng có phải admin không
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Kiểm tra người dùng có phải khách hàng không
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Lấy giỏ hàng hiện tại của người dùng
     */
    public function getCart(): array
    {
        return $this->cart ?? [];
    }

    /**
     * Cập nhật giỏ hàng của người dùng
     */
    public function updateCart(array $cart): bool
    {
        $this->cart = $cart;
        return $this->save();
    }
}
