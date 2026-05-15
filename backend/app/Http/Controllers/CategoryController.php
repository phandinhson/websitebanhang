<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Danh mục - Quản lý danh mục sản phẩm
 */
class CategoryController extends Controller
{
    // ============================================================
    // DANH SÁCH DANH MỤC (Public + Admin)
    // ============================================================

    /**
     * Lấy danh sách danh mục
     *
     * GET /api/categories
     * GET /api/admin/categories
     *
     * Query params:
     *   - tree: Trả về dạng cây phân cấp (true/false)
     *   - parent_id: Lấy danh mục con của một danh mục cụ thể
     *   - include_products: Bao gồm số lượng sản phẩm (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        $isAdminRoute      = str_contains($request->path(), 'admin');
        $treeView          = $request->get('tree') === 'true';
        $parentId          = $request->get('parent_id');
        $includeProducts   = $request->get('include_products') === 'true';

        // Nếu yêu cầu dạng cây và không phải tìm theo parent
        if ($treeView && !$parentId) {
            $tree = Category::getTree();
            return response()->json([
                'success' => true,
                'data'    => $tree->map(fn($c) => $this->formatCategory($c, $includeProducts)),
            ]);
        }

        $query = Category::query();

        // Chỉ lấy danh mục active cho người dùng thường
        if (!$isAdminRoute) {
            $query->where('is_active', true);
        }

        // Lọc theo danh mục cha
        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            // Mặc định lấy danh mục gốc nếu không chỉ định
            // (Bỏ comment dòng dưới nếu muốn lấy TẤT CẢ danh mục)
            // $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('sort_order', 'asc')
                            ->orderBy('name', 'asc')
                            ->get();

        return response()->json([
            'success' => true,
            'data'    => $categories->map(fn($c) => $this->formatCategory($c, $includeProducts)),
        ]);
    }

    // ============================================================
    // CHI TIẾT DANH MỤC (Public)
    // ============================================================

    /**
     * Lấy chi tiết danh mục theo ID hoặc slug
     *
     * GET /api/categories/{id}
     */
    public function show(string $id): JsonResponse
    {
        // Tìm theo ID MongoDB hoặc slug
        $category = Category::where('_id', $id)->orWhere('slug', $id)->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục.',
            ], 404);
        }

        $data = $this->formatCategory($category, true);

        // Lấy danh mục con
        $data['children'] = Category::where('parent_id', (string) $category->_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => $this->formatCategory($c, true));

        // Lấy danh mục cha nếu có
        $data['parent'] = null;
        if ($category->parent_id) {
            $parent = Category::find($category->parent_id);
            if ($parent) {
                $data['parent'] = [
                    'id'   => (string) $parent->_id,
                    'name' => $parent->name,
                    'slug' => $parent->slug,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ============================================================
    // TẠO DANH MỤC (Admin)
    // ============================================================

    /**
     * Tạo danh mục mới
     *
     * POST /api/admin/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|unique:categories,slug',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
            'parent_id'   => 'nullable|string',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'slug.unique'   => 'Slug này đã được sử dụng.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Kiểm tra danh mục cha tồn tại nếu có
        if ($request->filled('parent_id')) {
            $parentCategory = Category::find($request->parent_id);
            if (!$parentCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Danh mục cha không tồn tại.',
                    'errors'  => ['parent_id' => ['Danh mục cha không tồn tại.']],
                ], 422);
            }
        }

        try {
            $categoryData = $request->only([
                'name', 'description', 'image',
                'parent_id', 'is_active', 'sort_order',
            ]);

            // Xử lý slug
            if ($request->filled('slug')) {
                $categoryData['slug'] = Str::slug($request->slug);
            }

            $category = Category::create($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Tạo danh mục thành công!',
                'data'    => $this->formatCategory($category),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo danh mục thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // CẬP NHẬT DANH MỤC (Admin)
    // ============================================================

    /**
     * Cập nhật danh mục
     *
     * PUT /api/admin/categories/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'slug'        => "sometimes|string|unique:categories,slug,{$id},_id",
            'description' => 'sometimes|nullable|string',
            'image'       => 'sometimes|nullable|string',
            'parent_id'   => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ], [
            'slug.unique' => 'Slug này đã được sử dụng.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Ngăn danh mục tự làm cha của chính nó
        if ($request->filled('parent_id') && $request->parent_id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không thể là danh mục cha của chính nó.',
                'errors'  => ['parent_id' => ['Danh mục không thể là danh mục cha của chính nó.']],
            ], 422);
        }

        try {
            $updateData = $request->only([
                'name', 'description', 'image',
                'parent_id', 'is_active', 'sort_order',
            ]);

            if ($request->filled('slug')) {
                $updateData['slug'] = Str::slug($request->slug);
            }

            $category->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật danh mục thành công!',
                'data'    => $this->formatCategory($category->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật danh mục thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // XÓA DANH MỤC (Admin)
    // ============================================================

    /**
     * Xóa danh mục
     *
     * DELETE /api/admin/categories/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục.',
            ], 404);
        }

        // Kiểm tra danh mục có sản phẩm không
        $productCount = Product::where('category_id', $id)->count();
        if ($productCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa danh mục này vì còn {$productCount} sản phẩm. Vui lòng chuyển sản phẩm sang danh mục khác trước.",
            ], 409);
        }

        // Kiểm tra danh mục có danh mục con không
        $childCount = Category::where('parent_id', $id)->count();
        if ($childCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa danh mục này vì còn {$childCount} danh mục con. Vui lòng xóa danh mục con trước.",
            ], 409);
        }

        try {
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa danh mục thành công!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa danh mục thất bại. Vui lòng thử lại.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Định dạng thông tin danh mục để trả về API
     */
    private function formatCategory(Category $category, bool $includeProductCount = false): array
    {
        $data = [
            'id'          => (string) $category->_id,
            'name'        => $category->name,
            'slug'        => $category->slug,
            'description' => $category->description,
            'image'       => $category->image,
            'parent_id'   => $category->parent_id,
            'is_active'   => $category->is_active,
            'sort_order'  => $category->sort_order,
            'is_root'     => $category->is_root,
            'created_at'  => $category->created_at?->toISOString(),
            'updated_at'  => $category->updated_at?->toISOString(),
        ];

        if ($includeProductCount) {
            $data['product_count'] = $category->product_count;
        }

        // Thêm danh mục con nếu đã được load (từ getTree())
        if (isset($category->children_list)) {
            $data['children'] = $category->children_list->map(
                fn($c) => $this->formatCategory($c, $includeProductCount)
            );
        }

        return $data;
    }
}
