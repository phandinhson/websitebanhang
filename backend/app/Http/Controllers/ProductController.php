<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Sản phẩm - Quản lý sản phẩm bán hàng
 */
class ProductController extends Controller
{
    // ============================================================
    // DANH SÁCH SẢN PHẨM (Public + Admin)
    // ============================================================

    /**
     * Lấy danh sách sản phẩm với tìm kiếm, lọc và phân trang
     *
     * GET /api/products
     * GET /api/admin/products
     *
     * Query params:
     *   - search: Từ khóa tìm kiếm
     *   - category_id: ID danh mục
     *   - min_price: Giá tối thiểu
     *   - max_price: Giá tối đa
     *   - in_stock: Chỉ lấy sản phẩm còn hàng (true/false)
     *   - sort: Sắp xếp (newest, price_asc, price_desc, popular)
     *   - per_page: Số sản phẩm mỗi trang (mặc định 12)
     *   - page: Trang hiện tại
     */
    public function index(Request $request): JsonResponse
    {
        $perPage  = (int) $request->get('per_page', config('app.pagination_per_page', 12));
        $search   = $request->get('search');
        $category = $request->get('category_id');
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $inStock  = $request->get('in_stock');
        $sort     = $request->get('sort', 'newest');
        $brand    = $request->get('brand');

        $query = Product::query();

        // Chỉ hiển thị sản phẩm active với người dùng thông thường
        // Admin có thể xem tất cả qua route /admin/products
        $isAdminRoute = str_contains($request->path(), 'admin');
        if (!$isAdminRoute) {
            $query->where('is_active', true);
        }

        // Tìm kiếm theo từ khóa
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Lọc theo danh mục
        if ($category) {
            $query->where('category_id', $category);
        }

        // Lọc theo thương hiệu
        if ($brand) {
            $query->where('brand', 'like', "%{$brand}%");
        }

        // Lọc theo khoảng giá
        if ($minPrice !== null) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // Lọc sản phẩm còn hàng
        if ($inStock === 'true' || $inStock === '1') {
            $query->where('stock', '>', 0);
        }

        // Sắp xếp
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderBy('review_count', 'desc'),
            'rating'     => $query->orderBy('rating', 'desc'),
            default      => $query->orderBy('created_at', 'desc'), // newest
        };

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'products'   => $products->map(fn($p) => $this->formatProduct($p)),
                'pagination' => [
                    'total'        => $products->total(),
                    'per_page'     => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                    'from'         => $products->firstItem(),
                    'to'           => $products->lastItem(),
                ],
            ],
        ]);
    }

    // ============================================================
    // CHI TIẾT SẢN PHẨM (Public)
    // ============================================================

    /**
     * Lấy chi tiết sản phẩm theo ID hoặc slug
     *
     * GET /api/products/{id}
     */
    public function show(string $id): JsonResponse
    {
        // Tìm theo ID MongoDB hoặc slug
        $product = Product::where('_id', $id)->orWhere('slug', $id)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        // Lấy thêm thông tin danh mục
        $category = null;
        if ($product->category_id) {
            $category = Category::find($product->category_id);
        }

        // Lấy sản phẩm liên quan (cùng danh mục)
        $relatedProducts = [];
        if ($product->category_id) {
            $relatedProducts = Product::where('category_id', $product->category_id)
                ->where('_id', '!=', $product->_id)
                ->where('is_active', true)
                ->limit(8)
                ->get()
                ->map(fn($p) => $this->formatProduct($p));
        }

        $productData = $this->formatProduct($product);
        $productData['category']         = $category ? [
            'id'   => (string) $category->_id,
            'name' => $category->name,
            'slug' => $category->slug,
        ] : null;
        $productData['related_products'] = $relatedProducts;

        return response()->json([
            'success' => true,
            'data'    => $productData,
        ]);
    }

    // ============================================================
    // TẠO SẢN PHẨM (Admin)
    // ============================================================

    /**
     * Tạo sản phẩm mới
     *
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:500',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'sale_price'  => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|string',
            'images'      => 'nullable|array',
            'images.*'    => 'string',
            'stock'       => 'required|integer|min:0',
            'slug'        => 'nullable|string|unique:products,slug',
            'is_active'   => 'nullable|boolean',
            'sku'         => 'nullable|string|unique:products,sku',
            'brand'       => 'nullable|string|max:255',
            'weight'      => 'nullable|numeric|min:0',
            'attributes'  => 'nullable|array',
        ], [
            'name.required'       => 'Vui lòng nhập tên sản phẩm.',
            'price.required'      => 'Vui lòng nhập giá sản phẩm.',
            'price.numeric'       => 'Giá sản phẩm phải là số.',
            'price.min'           => 'Giá sản phẩm không được âm.',
            'sale_price.lt'       => 'Giá khuyến mãi phải nhỏ hơn giá gốc.',
            'category_id.required'=> 'Vui lòng chọn danh mục.',
            'stock.required'      => 'Vui lòng nhập số lượng tồn kho.',
            'stock.integer'       => 'Số lượng tồn kho phải là số nguyên.',
            'slug.unique'         => 'Slug này đã được sử dụng.',
            'sku.unique'          => 'Mã SKU này đã được sử dụng.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Kiểm tra danh mục tồn tại
        $category = Category::find($request->category_id);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại.',
                'errors'  => ['category_id' => ['Danh mục không tồn tại.']],
            ], 422);
        }

        try {
            $productData = $request->only([
                'name', 'description', 'price', 'sale_price',
                'category_id', 'images', 'stock', 'is_active',
                'sku', 'brand', 'weight', 'attributes',
            ]);

            // Tạo slug nếu không có
            if ($request->filled('slug')) {
                $productData['slug'] = Str::slug($request->slug);
            }

            $product = Product::create($productData);

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công!',
                'data'    => $this->formatProduct($product),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo sản phẩm thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // CẬP NHẬT SẢN PHẨM (Admin)
    // ============================================================

    /**
     * Cập nhật thông tin sản phẩm
     *
     * PUT /api/admin/products/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:500',
            'description' => 'sometimes|nullable|string',
            'price'       => 'sometimes|numeric|min:0',
            'sale_price'  => 'sometimes|nullable|numeric|min:0',
            'category_id' => 'sometimes|string',
            'images'      => 'sometimes|array',
            'images.*'    => 'string',
            'stock'       => 'sometimes|integer|min:0',
            'slug'        => "sometimes|string|unique:products,slug,{$id},_id",
            'is_active'   => 'sometimes|boolean',
            'sku'         => "sometimes|string|unique:products,sku,{$id},_id",
            'brand'       => 'sometimes|nullable|string|max:255',
            'weight'      => 'sometimes|nullable|numeric|min:0',
            'attributes'  => 'sometimes|nullable|array',
        ], [
            'price.min'    => 'Giá sản phẩm không được âm.',
            'stock.min'    => 'Số lượng tồn kho không được âm.',
            'slug.unique'  => 'Slug này đã được sử dụng.',
            'sku.unique'   => 'Mã SKU này đã được sử dụng.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $updateData = $request->only([
                'name', 'description', 'price', 'sale_price',
                'category_id', 'images', 'stock', 'is_active',
                'sku', 'brand', 'weight', 'attributes',
            ]);

            if ($request->filled('slug')) {
                $updateData['slug'] = Str::slug($request->slug);
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công!',
                'data'    => $this->formatProduct($product->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật sản phẩm thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // XÓA SẢN PHẨM (Admin)
    // ============================================================

    /**
     * Xóa sản phẩm (soft delete bằng cách đặt is_active = false)
     *
     * DELETE /api/admin/products/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        try {
            // Xoá thực sự khỏi database
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa sản phẩm thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Định dạng thông tin sản phẩm để trả về API
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id'               => (string) $product->_id,
            'name'             => $product->name,
            'description'      => $product->description,
            'price'            => $product->price,
            'sale_price'       => $product->sale_price,
            'display_price'    => $product->display_price,
            'discount_percent' => $product->discount_percent,
            'category_id'      => $product->category_id,
            'images'           => $product->images ?? [],
            'stock'            => $product->stock,
            'is_in_stock'      => $product->is_in_stock,
            'slug'             => $product->slug,
            'is_active'        => $product->is_active,
            'sku'              => $product->sku,
            'brand'            => $product->brand,
            'weight'           => $product->weight,
            'attributes'       => $product->attributes ?? [],
            'rating'           => $product->rating,
            'review_count'     => $product->review_count,
            'created_at'       => $product->created_at?->toISOString(),
            'updated_at'       => $product->updated_at?->toISOString(),
        ];
    }
}
