<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


use Illuminate\Support\Str;

/**
 * Model Sản phẩm - Lưu trữ thông tin sản phẩm bán hàng
 *
 * @property string $_id          MongoDB ObjectID
 * @property string $name         Tên sản phẩm
 * @property string $description  Mô tả chi tiết sản phẩm
 * @property float  $price        Giá gốc (VNĐ)
 * @property float  $sale_price   Giá khuyến mãi (VNĐ) - null nếu không có KM
 * @property string $category_id  ID danh mục sản phẩm
 * @property array  $images       Danh sách URL ảnh sản phẩm
 * @property int    $stock        Số lượng tồn kho
 * @property string $slug         Slug URL thân thiện (duy nhất)
 * @property bool   $is_active    Trạng thái hiển thị sản phẩm
 * @property array  $attributes   Thuộc tính sản phẩm (màu sắc, kích thước, ...)
 * @property float  $rating       Điểm đánh giá trung bình (0-5)
 * @property int    $review_count Số lượng đánh giá
 */
class Product extends Model
{
    // Tên collection trong MongoDB
    protected $collection = 'products';

    // Kết nối database
    protected $connection = 'pgsql';

    /**
     * Các trường được phép gán hàng loạt
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'sale_price',
        'category_id',
        'images',
        'stock',
        'slug',
        'is_active',
        'attributes',
        'rating',
        'review_count',
        'sku',
        'weight',
        'brand',
    ];

    /**
     * Ép kiểu dữ liệu cho các trường
     */
    protected $casts = [
        'price'        => 'float',
        'sale_price'   => 'float',
        'stock'        => 'integer',
        'is_active'    => 'boolean',
        'images'       => 'array',
        'attributes'   => 'array',
        'rating'       => 'float',
        'review_count' => 'integer',
        'weight'       => 'float',
    ];

    /**
     * Giá trị mặc định
     */
    protected $attributes = [
        'is_active'    => true,
        'stock'        => 0,
        'images'       => [],
        'rating'       => 0,
        'review_count' => 0,
    ];

    // ============================================================
    // Relationships (Quan hệ)
    // ============================================================

    /**
     * Lấy thông tin danh mục của sản phẩm
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // ============================================================
    // Scopes (Phạm vi truy vấn)
    // ============================================================

    /**
     * Chỉ lấy sản phẩm đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Lọc sản phẩm theo danh mục
     */
    public function scopeByCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Tìm kiếm sản phẩm theo tên
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('description', 'like', "%{$keyword}%")
              ->orWhere('brand', 'like', "%{$keyword}%");
        });
    }

    /**
     * Lọc sản phẩm theo khoảng giá
     */
    public function scopePriceRange($query, ?float $minPrice, ?float $maxPrice)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        return $query;
    }

    /**
     * Chỉ lấy sản phẩm còn hàng
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // ============================================================
    // Accessors & Mutators
    // ============================================================

    /**
     * Tự động tạo slug từ tên sản phẩm nếu không có
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }

    /**
     * Lấy giá hiển thị (ưu tiên giá khuyến mãi nếu có)
     */
    public function getDisplayPriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Tính phần trăm giảm giá
     */
    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->sale_price && $this->sale_price < $this->price) {
            return (int) round((1 - $this->sale_price / $this->price) * 100);
        }
        return null;
    }

    /**
     * Kiểm tra sản phẩm còn hàng không
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Tạo slug duy nhất từ tên sản phẩm
     */
    private function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'like', "{$slug}%")->count();
        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Giảm số lượng tồn kho sau khi đặt hàng
     */
    public function decreaseStock(int $quantity): bool
    {
        if ($this->stock < $quantity) {
            return false;
        }
        $this->stock -= $quantity;
        return $this->save();
    }

    /**
     * Tăng số lượng tồn kho (khi huỷ đơn)
     */
    public function increaseStock(int $quantity): bool
    {
        $this->stock += $quantity;
        return $this->save();
    }
}
