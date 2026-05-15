<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Giỏ hàng - Quản lý giỏ hàng của người dùng
 *
 * Giỏ hàng được lưu trực tiếp trong document của User trong MongoDB.
 * Cấu trúc mỗi item trong cart:
 * {
 *   product_id: string,
 *   name: string,
 *   price: float,         // Giá tại thời điểm thêm vào giỏ
 *   sale_price: float|null,
 *   image: string|null,
 *   quantity: int,
 *   slug: string,
 * }
 */
class CartController extends Controller
{
    // ============================================================
    // XEM GIỎ HÀNG
    // ============================================================

    /**
     * Lấy giỏ hàng của người dùng hiện tại
     *
     * GET /api/cart
     */
    public function getCart(): JsonResponse
    {
        $user = Auth::user();
        $cart = $user->getCart();

        // Tính tổng tiền giỏ hàng
        $summary = $this->calculateCartSummary($cart);

        return response()->json([
            'success' => true,
            'data'    => [
                'items'   => $cart,
                'summary' => $summary,
            ],
        ]);
    }

    // ============================================================
    // THÊM SẢN PHẨM VÀO GIỎ HÀNG
    // ============================================================

    /**
     * Thêm sản phẩm vào giỏ hàng
     *
     * POST /api/cart/add
     *
     * Body: { product_id: string, quantity: int }
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'quantity'   => 'required|integer|min:1|max:100',
        ], [
            'product_id.required' => 'Vui lòng chọn sản phẩm.',
            'quantity.required'   => 'Vui lòng nhập số lượng.',
            'quantity.integer'    => 'Số lượng phải là số nguyên.',
            'quantity.min'        => 'Số lượng phải ít nhất là 1.',
            'quantity.max'        => 'Số lượng không được quá 100.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Kiểm tra sản phẩm tồn tại và còn hàng
        $product = Product::find($request->product_id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm này hiện không còn bán.',
            ], 422);
        }

        $user = Auth::user();
        $cart = $user->getCart();

        $productId = (string) $product->_id;
        $quantity  = (int) $request->quantity;

        // Tìm sản phẩm trong giỏ hàng
        $existingIndex = $this->findItemIndex($cart, $productId);

        if ($existingIndex !== null) {
            // Nếu đã có trong giỏ, cộng thêm số lượng
            $newQuantity = $cart[$existingIndex]['quantity'] + $quantity;

            // Kiểm tra tổng số lượng không vượt quá tồn kho
            if ($newQuantity > $product->stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Số lượng vượt quá tồn kho. Sản phẩm chỉ còn {$product->stock} cái.",
                ], 422);
            }

            $cart[$existingIndex]['quantity'] = $newQuantity;
        } else {
            // Kiểm tra số lượng không vượt quá tồn kho
            if ($quantity > $product->stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Số lượng vượt quá tồn kho. Sản phẩm chỉ còn {$product->stock} cái.",
                ], 422);
            }

            // Thêm sản phẩm mới vào giỏ hàng
            $cart[] = [
                'product_id' => $productId,
                'name'       => $product->name,
                'price'      => $product->price,
                'sale_price' => $product->sale_price,
                'image'      => $product->images[0] ?? null,
                'quantity'   => $quantity,
                'slug'       => $product->slug,
                'sku'        => $product->sku,
            ];
        }

        // Lưu giỏ hàng vào database
        $user->updateCart($cart);

        $summary = $this->calculateCartSummary($cart);

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm sản phẩm vào giỏ hàng!',
            'data'    => [
                'items'   => $cart,
                'summary' => $summary,
            ],
        ]);
    }

    // ============================================================
    // CẬP NHẬT SỐ LƯỢNG
    // ============================================================

    /**
     * Cập nhật số lượng sản phẩm trong giỏ hàng
     *
     * PUT /api/cart/update/{productId}
     *
     * Body: { quantity: int }
     */
    public function updateItem(Request $request, string $productId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0|max:100',
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer'  => 'Số lượng phải là số nguyên.',
            'quantity.min'      => 'Số lượng không được âm.',
            'quantity.max'      => 'Số lượng không được quá 100.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user  = Auth::user();
        $cart  = $user->getCart();
        $index = $this->findItemIndex($cart, $productId);

        if ($index === null) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không có trong giỏ hàng.',
            ], 404);
        }

        $quantity = (int) $request->quantity;

        // Nếu số lượng = 0, xóa khỏi giỏ hàng
        if ($quantity === 0) {
            array_splice($cart, $index, 1);
        } else {
            // Kiểm tra tồn kho
            $product = Product::find($productId);
            if ($product && $quantity > $product->stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Số lượng vượt quá tồn kho. Sản phẩm chỉ còn {$product->stock} cái.",
                ], 422);
            }

            $cart[$index]['quantity'] = $quantity;
        }

        $user->updateCart($cart);

        $summary = $this->calculateCartSummary($cart);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật giỏ hàng thành công!',
            'data'    => [
                'items'   => $cart,
                'summary' => $summary,
            ],
        ]);
    }

    // ============================================================
    // XÓA SẢN PHẨM KHỎI GIỎ HÀNG
    // ============================================================

    /**
     * Xóa một sản phẩm khỏi giỏ hàng
     *
     * DELETE /api/cart/remove/{productId}
     */
    public function removeItem(string $productId): JsonResponse
    {
        $user  = Auth::user();
        $cart  = $user->getCart();
        $index = $this->findItemIndex($cart, $productId);

        if ($index === null) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không có trong giỏ hàng.',
            ], 404);
        }

        // Xóa sản phẩm khỏi mảng
        array_splice($cart, $index, 1);
        $user->updateCart($cart);

        $summary = $this->calculateCartSummary($cart);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!',
            'data'    => [
                'items'   => $cart,
                'summary' => $summary,
            ],
        ]);
    }

    // ============================================================
    // XÓA TOÀN BỘ GIỎ HÀNG
    // ============================================================

    /**
     * Xóa toàn bộ giỏ hàng
     *
     * DELETE /api/cart/clear
     */
    public function clearCart(): JsonResponse
    {
        $user = Auth::user();
        $user->updateCart([]);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa toàn bộ giỏ hàng!',
            'data'    => [
                'items'   => [],
                'summary' => $this->calculateCartSummary([]),
            ],
        ]);
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Tìm vị trí của sản phẩm trong mảng giỏ hàng
     *
     * @return int|null Vị trí trong mảng, hoặc null nếu không tìm thấy
     */
    private function findItemIndex(array $cart, string $productId): ?int
    {
        foreach ($cart as $index => $item) {
            if ((string) $item['product_id'] === $productId) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Tính tóm tắt giỏ hàng (tổng sản phẩm, tổng tiền)
     */
    private function calculateCartSummary(array $cart): array
    {
        $totalItems    = 0;
        $totalQuantity = 0;
        $totalPrice    = 0;
        $totalDiscount = 0;

        foreach ($cart as $item) {
            $quantity  = (int) ($item['quantity'] ?? 0);
            $price     = (float) ($item['price'] ?? 0);
            $salePrice = isset($item['sale_price']) && $item['sale_price'] ? (float) $item['sale_price'] : null;

            $effectivePrice = $salePrice ?? $price;

            $totalItems++;
            $totalQuantity += $quantity;
            $totalPrice    += $effectivePrice * $quantity;
            $totalDiscount += ($salePrice ? ($price - $salePrice) * $quantity : 0);
        }

        return [
            'total_items'    => $totalItems,     // Số loại sản phẩm
            'total_quantity' => $totalQuantity,   // Tổng số lượng
            'total_price'    => $totalPrice,      // Tổng tiền sau giảm giá
            'total_discount' => $totalDiscount,   // Tổng số tiền đã giảm
            'total_original' => $totalPrice + $totalDiscount, // Tổng giá gốc
        ];
    }
}
