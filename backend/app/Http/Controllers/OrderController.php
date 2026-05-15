<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Đơn hàng - Quản lý đặt hàng và theo dõi đơn hàng
 */
class OrderController extends Controller
{
    // ============================================================
    // TẠO ĐƠN HÀNG
    // ============================================================

    /**
     * Tạo đơn hàng mới từ giỏ hàng
     *
     * POST /api/orders
     *
     * Body:
     * {
     *   shipping_address: { street, ward, district, city },
     *   payment_method: 'cod' | 'bank_transfer' | 'momo' | 'vnpay',
     *   note: string (tuỳ chọn),
     *   items: [{ product_id, quantity }] (tuỳ chọn - nếu không truyền sẽ dùng giỏ hàng)
     * }
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_address'          => 'required|array',
            'shipping_address.street'   => 'required|string|max:500',
            'shipping_address.ward'     => 'nullable|string|max:255',
            'shipping_address.district' => 'required|string|max:255',
            'shipping_address.city'     => 'required|string|max:255',
            'shipping_address.phone'    => 'required|string|max:20',
            'shipping_address.name'     => 'required|string|max:255',
            'payment_method'            => 'required|string|in:cod,bank_transfer,momo,vnpay',
            'note'                      => 'nullable|string|max:1000',
            'items'                     => 'nullable|array',
            'items.*.product_id'        => 'required_with:items|string',
            'items.*.quantity'          => 'required_with:items|integer|min:1',
        ], [
            'shipping_address.required'          => 'Vui lòng nhập địa chỉ giao hàng.',
            'shipping_address.street.required'   => 'Vui lòng nhập số nhà và tên đường.',
            'shipping_address.district.required' => 'Vui lòng nhập quận/huyện.',
            'shipping_address.city.required'     => 'Vui lòng nhập tỉnh/thành phố.',
            'shipping_address.phone.required'    => 'Vui lòng nhập số điện thoại nhận hàng.',
            'shipping_address.name.required'     => 'Vui lòng nhập tên người nhận.',
            'payment_method.required'            => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in'                  => 'Phương thức thanh toán không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        // Lấy danh sách sản phẩm cần đặt
        $cartItems = $request->has('items') ? $request->items : $user->getCart();

        if (empty($cartItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Giỏ hàng trống. Vui lòng thêm sản phẩm trước khi đặt hàng.',
            ], 422);
        }

        // Xác thực và chuẩn bị danh sách sản phẩm đặt hàng
        $orderItems  = [];
        $totalPrice  = 0;
        $stockErrors = [];

        foreach ($cartItems as $cartItem) {
            $productId = $cartItem['product_id'] ?? null;
            $quantity  = (int) ($cartItem['quantity'] ?? 1);

            if (!$productId) {
                continue;
            }

            $product = Product::find($productId);

            if (!$product || !$product->is_active) {
                $stockErrors[] = "Sản phẩm '{$cartItem['name']}' không còn bán.";
                continue;
            }

            if ($product->stock < $quantity) {
                $stockErrors[] = "Sản phẩm '{$product->name}' chỉ còn {$product->stock} cái, không đủ số lượng yêu cầu ({$quantity}).";
                continue;
            }

            $effectivePrice = $product->sale_price ?? $product->price;
            $itemTotal      = $effectivePrice * $quantity;
            $totalPrice    += $itemTotal;

            $orderItems[] = [
                'product_id'  => (string) $product->_id,
                'name'        => $product->name,
                'slug'        => $product->slug,
                'price'       => $product->price,
                'sale_price'  => $product->sale_price,
                'final_price' => $effectivePrice,
                'quantity'    => $quantity,
                'subtotal'    => $itemTotal,
                'image'       => $product->images[0] ?? null,
                'sku'         => $product->sku,
            ];
        }

        // Nếu có lỗi tồn kho, trả về lỗi
        if (!empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Một số sản phẩm không đủ hàng.',
                'errors'  => ['stock' => $stockErrors],
            ], 422);
        }

        if (empty($orderItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có sản phẩm hợp lệ trong giỏ hàng.',
            ], 422);
        }

        try {
            // Tính phí vận chuyển (logic đơn giản - có thể mở rộng)
            $shippingFee = $this->calculateShippingFee($totalPrice, $request->shipping_address);

            // Tạo đơn hàng
            $order = Order::create([
                'user_id'          => (string) $user->_id,
                'items'            => $orderItems,
                'total_price'      => $totalPrice,
                'shipping_fee'     => $shippingFee,
                'discount_amount'  => 0,
                'status'           => Order::STATUS_PENDING,
                'shipping_address' => $request->shipping_address,
                'payment_method'   => $request->payment_method,
                'payment_status'   => 'unpaid',
                'note'             => $request->note,
            ]);

            // Giảm số lượng tồn kho của từng sản phẩm
            foreach ($orderItems as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decreaseStock($item['quantity']);
                }
            }

            // Xóa giỏ hàng sau khi đặt hàng thành công
            $user->updateCart([]);

            return response()->json([
                'success' => true,
                'message' => 'Đặt hàng thành công! Cảm ơn bạn đã mua hàng.',
                'data'    => $this->formatOrder($order),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đặt hàng thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // DANH SÁCH ĐƠN HÀNG CỦA NGƯỜI DÙNG
    // ============================================================

    /**
     * Lấy danh sách đơn hàng của người dùng hiện tại
     *
     * GET /api/orders
     */
    public function getUserOrders(Request $request): JsonResponse
    {
        $user    = Auth::user();
        $status  = $request->get('status');
        $perPage = (int) $request->get('per_page', 10);

        $query = Order::where('user_id', (string) $user->_id);

        // Lọc theo trạng thái
        if ($status && in_array($status, Order::getValidStatuses())) {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'orders'     => $orders->map(fn($o) => $this->formatOrder($o)),
                'pagination' => [
                    'total'        => $orders->total(),
                    'per_page'     => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                ],
            ],
        ]);
    }

    // ============================================================
    // CHI TIẾT ĐƠN HÀNG
    // ============================================================

    /**
     * Lấy chi tiết đơn hàng
     *
     * GET /api/orders/{id}
     * GET /api/admin/orders/{id}
     */
    public function getOrderDetail(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], 404);
        }

        // Người dùng thường chỉ được xem đơn hàng của mình
        $user = Auth::user();
        if (!$user->isAdmin() && (string) $order->user_id !== (string) $user->_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem đơn hàng này.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order, true),
        ]);
    }

    // ============================================================
    // HUỶ ĐƠN HÀNG (Người dùng)
    // ============================================================

    /**
     * Huỷ đơn hàng (chỉ khi đang ở trạng thái pending)
     *
     * PUT /api/orders/{id}/cancel
     */
    public function cancelOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], 404);
        }

        $user = Auth::user();

        // Kiểm tra quyền sở hữu
        if ((string) $order->user_id !== (string) $user->_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền huỷ đơn hàng này.',
            ], 403);
        }

        // Chỉ cho phép huỷ khi đang ở trạng thái pending
        if (!$order->can_cancel) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể huỷ đơn hàng này. Chỉ có thể huỷ đơn hàng đang chờ xác nhận.',
            ], 422);
        }

        try {
            $reason = $request->get('reason', 'Khách hàng huỷ đơn');

            // Hoàn lại tồn kho
            foreach ($order->items as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->increaseStock($item['quantity']);
                }
            }

            // Cập nhật trạng thái đơn hàng
            $order->cancelled_reason = $reason;
            $order->save();
            $order->updateStatus(Order::STATUS_CANCELLED, "Khách hàng huỷ đơn: {$reason}");

            return response()->json([
                'success' => true,
                'message' => 'Huỷ đơn hàng thành công!',
                'data'    => $this->formatOrder($order->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Huỷ đơn hàng thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // ADMIN - Lấy tất cả đơn hàng
    // ============================================================

    /**
     * Lấy tất cả đơn hàng trong hệ thống (Admin)
     *
     * GET /api/admin/orders
     */
    public function getAllOrders(Request $request): JsonResponse
    {
        $perPage        = (int) $request->get('per_page', 15);
        $status         = $request->get('status');
        $paymentMethod  = $request->get('payment_method');
        $search         = $request->get('search');
        $dateFrom       = $request->get('date_from');
        $dateTo         = $request->get('date_to');

        $query = Order::query();

        // Lọc theo trạng thái
        if ($status && in_array($status, Order::getValidStatuses())) {
            $query->where('status', $status);
        }

        // Lọc theo phương thức thanh toán
        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        // Tìm kiếm theo mã đơn hàng
        if ($search) {
            $query->where('order_code', 'like', "%{$search}%");
        }

        // Lọc theo khoảng thời gian
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'orders'     => $orders->map(fn($o) => $this->formatOrder($o)),
                'pagination' => [
                    'total'        => $orders->total(),
                    'per_page'     => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                ],
            ],
        ]);
    }

    // ============================================================
    // ADMIN - Cập nhật trạng thái đơn hàng
    // ============================================================

    /**
     * Cập nhật trạng thái đơn hàng (Admin)
     *
     * PUT /api/admin/orders/{id}/status
     *
     * Body: { status: string, note: string (tuỳ chọn) }
     */
    public function updateOrderStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', Order::getValidStatuses()),
            'note'   => 'nullable|string|max:500',
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in'       => 'Trạng thái không hợp lệ. Các trạng thái hợp lệ: ' . implode(', ', Order::getValidStatuses()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], 404);
        }

        $newStatus = $request->status;

        // Nếu admin huỷ đơn, hoàn lại tồn kho
        if ($newStatus === Order::STATUS_CANCELLED && $order->status !== Order::STATUS_CANCELLED) {
            foreach ($order->items as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->increaseStock($item['quantity']);
                }
            }
        }

        try {
            $order->updateStatus($newStatus, $request->note);

            // Nếu đơn hàng đã giao, cập nhật trạng thái thanh toán (COD)
            if ($newStatus === Order::STATUS_DELIVERED && $order->payment_method === Order::PAYMENT_COD) {
                $order->payment_status = 'paid';
                $order->save();
            }

            return response()->json([
                'success' => true,
                'message' => "Cập nhật trạng thái đơn hàng thành '{$order->status_label}' thành công!",
                'data'    => $this->formatOrder($order->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật trạng thái thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Tính phí vận chuyển (logic đơn giản)
     * Có thể tích hợp API GHN, GHTK, ViettelPost sau này
     */
    private function calculateShippingFee(float $totalPrice, array $address): float
    {
        // Miễn phí vận chuyển cho đơn hàng từ 500.000đ
        if ($totalPrice >= 500000) {
            return 0;
        }

        // Phí mặc định 30.000đ
        return 30000;
    }

    /**
     * Định dạng thông tin đơn hàng để trả về API
     */
    private function formatOrder(Order $order, bool $includeDetails = false): array
    {
        $data = [
            'id'               => (string) $order->_id,
            'order_code'       => $order->order_code,
            'user_id'          => $order->user_id,
            'total_price'      => $order->total_price,
            'shipping_fee'     => $order->shipping_fee,
            'discount_amount'  => $order->discount_amount,
            'final_amount'     => $order->final_amount,
            'status'           => $order->status,
            'status_label'     => $order->status_label,
            'payment_method'   => $order->payment_method,
            'payment_method_label' => $order->payment_method_label,
            'payment_status'   => $order->payment_status,
            'shipping_address' => $order->shipping_address,
            'note'             => $order->note,
            'can_cancel'       => $order->can_cancel,
            'created_at'       => $order->created_at?->toISOString(),
            'updated_at'       => $order->updated_at?->toISOString(),
        ];

        if ($includeDetails) {
            $data['items']          = $order->items ?? [];
            $data['status_history'] = $order->status_history ?? [];
            $data['cancelled_reason'] = $order->cancelled_reason;
        } else {
            // Chỉ trả về tóm tắt sản phẩm trong danh sách
            $data['item_count'] = count($order->items ?? []);
            $data['items']      = $order->items ?? [];
        }

        return $data;
    }
}
