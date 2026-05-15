<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Model Đơn hàng - Lưu trữ thông tin đơn hàng của khách hàng
 *
 * @property string $_id              MongoDB ObjectID
 * @property string $user_id          ID người đặt hàng
 * @property array  $items            Danh sách sản phẩm trong đơn hàng
 * @property float  $total_price      Tổng tiền đơn hàng (VNĐ)
 * @property float  $shipping_fee     Phí vận chuyển (VNĐ)
 * @property float  $discount_amount  Số tiền giảm giá (VNĐ)
 * @property string $status           Trạng thái: pending|confirmed|shipping|delivered|cancelled
 * @property array  $shipping_address Địa chỉ giao hàng
 * @property string $payment_method   Phương thức thanh toán: cod|bank_transfer|momo|vnpay
 * @property string $payment_status   Trạng thái thanh toán: unpaid|paid|refunded
 * @property string $note             Ghi chú của khách hàng
 * @property string $order_code       Mã đơn hàng (duy nhất, dễ đọc)
 * @property array  $status_history   Lịch sử thay đổi trạng thái
 */
class Order extends Eloquent
{
    // Tên collection trong MongoDB
    protected $collection = 'orders';

    // Kết nối database
    protected $connection = 'mongodb';

    /**
     * Các trạng thái đơn hàng hợp lệ
     */
    const STATUS_PENDING   = 'pending';    // Chờ xác nhận
    const STATUS_CONFIRMED = 'confirmed';  // Đã xác nhận
    const STATUS_SHIPPING  = 'shipping';   // Đang giao hàng
    const STATUS_DELIVERED = 'delivered';  // Đã giao hàng
    const STATUS_CANCELLED = 'cancelled';  // Đã huỷ

    /**
     * Các phương thức thanh toán hợp lệ
     */
    const PAYMENT_COD           = 'cod';            // Thanh toán khi nhận hàng
    const PAYMENT_BANK_TRANSFER = 'bank_transfer';  // Chuyển khoản ngân hàng
    const PAYMENT_MOMO          = 'momo';            // Ví MoMo
    const PAYMENT_VNPAY         = 'vnpay';           // VNPay

    /**
     * Các trường được phép gán hàng loạt
     */
    protected $fillable = [
        'user_id',
        'items',
        'total_price',
        'shipping_fee',
        'discount_amount',
        'status',
        'shipping_address',
        'payment_method',
        'payment_status',
        'note',
        'order_code',
        'status_history',
        'cancelled_reason',
    ];

    /**
     * Ép kiểu dữ liệu cho các trường
     */
    protected $casts = [
        'items'            => 'array',
        'shipping_address' => 'array',
        'status_history'   => 'array',
        'total_price'      => 'float',
        'shipping_fee'     => 'float',
        'discount_amount'  => 'float',
    ];

    /**
     * Giá trị mặc định
     */
    protected $attributes = [
        'status'          => self::STATUS_PENDING,
        'payment_status'  => 'unpaid',
        'shipping_fee'    => 0,
        'discount_amount' => 0,
        'items'           => [],
        'status_history'  => [],
    ];

    // ============================================================
    // Boot - Tự động tạo mã đơn hàng
    // ============================================================

    protected static function boot(): void
    {
        parent::boot();

        // Tự động tạo mã đơn hàng khi tạo mới
        static::creating(function ($order) {
            if (empty($order->order_code)) {
                $order->order_code = static::generateOrderCode();
            }
            // Ghi lại lịch sử trạng thái ban đầu
            $order->status_history = [[
                'status'     => $order->status ?? self::STATUS_PENDING,
                'note'       => 'Đơn hàng được tạo',
                'created_at' => now()->toISOString(),
            ]];
        });
    }

    // ============================================================
    // Relationships (Quan hệ)
    // ============================================================

    /**
     * Lấy thông tin người đặt hàng
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ============================================================
    // Scopes (Phạm vi truy vấn)
    // ============================================================

    /**
     * Lọc đơn hàng theo trạng thái
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Lọc đơn hàng theo người dùng
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Lọc đơn hàng theo phương thức thanh toán
     */
    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    // ============================================================
    // Accessors
    // ============================================================

    /**
     * Lấy tổng tiền cuối cùng (bao gồm phí ship, trừ giảm giá)
     */
    public function getFinalAmountAttribute(): float
    {
        return $this->total_price + ($this->shipping_fee ?? 0) - ($this->discount_amount ?? 0);
    }

    /**
     * Lấy nhãn trạng thái tiếng Việt
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Chờ xác nhận',
            self::STATUS_CONFIRMED => 'Đã xác nhận',
            self::STATUS_SHIPPING  => 'Đang giao hàng',
            self::STATUS_DELIVERED => 'Đã giao hàng',
            self::STATUS_CANCELLED => 'Đã huỷ',
            default                => 'Không xác định',
        };
    }

    /**
     * Lấy nhãn phương thức thanh toán tiếng Việt
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_COD           => 'Thanh toán khi nhận hàng (COD)',
            self::PAYMENT_BANK_TRANSFER => 'Chuyển khoản ngân hàng',
            self::PAYMENT_MOMO          => 'Ví MoMo',
            self::PAYMENT_VNPAY         => 'VNPay',
            default                     => $this->payment_method,
        };
    }

    /**
     * Kiểm tra đơn hàng có thể huỷ không (chỉ khi đang ở trạng thái pending)
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Tạo mã đơn hàng duy nhất theo định dạng: DH + YYMMDDHHmmss + random
     */
    public static function generateOrderCode(): string
    {
        $prefix = 'DH';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $timestamp . $random;
    }

    /**
     * Cập nhật trạng thái đơn hàng và ghi lịch sử
     */
    public function updateStatus(string $newStatus, ?string $note = null): bool
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status'     => $newStatus,
            'note'       => $note ?? $this->getDefaultStatusNote($newStatus),
            'created_at' => now()->toISOString(),
        ];

        $this->status = $newStatus;
        $this->status_history = $history;

        return $this->save();
    }

    /**
     * Lấy ghi chú mặc định cho mỗi trạng thái
     */
    private function getDefaultStatusNote(string $status): string
    {
        return match ($status) {
            self::STATUS_CONFIRMED => 'Đơn hàng đã được xác nhận',
            self::STATUS_SHIPPING  => 'Đơn hàng đang được giao',
            self::STATUS_DELIVERED => 'Đơn hàng đã giao thành công',
            self::STATUS_CANCELLED => 'Đơn hàng đã bị huỷ',
            default                => "Trạng thái chuyển sang: {$status}",
        };
    }

    /**
     * Lấy danh sách trạng thái hợp lệ
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_SHIPPING,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Lấy danh sách phương thức thanh toán hợp lệ
     */
    public static function getValidPaymentMethods(): array
    {
        return [
            self::PAYMENT_COD,
            self::PAYMENT_BANK_TRANSFER,
            self::PAYMENT_MOMO,
            self::PAYMENT_VNPAY,
        ];
    }
}
