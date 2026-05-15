<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


use Illuminate\Support\Str;

/**
 * Model Danh mục - Phân loại sản phẩm theo nhóm
 *
 * @property string      $_id         MongoDB ObjectID
 * @property string      $name        Tên danh mục
 * @property string      $slug        Slug URL thân thiện (duy nhất)
 * @property string      $description Mô tả danh mục
 * @property string      $image       URL ảnh đại diện danh mục
 * @property string|null $parent_id   ID danh mục cha (null = danh mục gốc)
 * @property bool        $is_active   Trạng thái hiển thị
 * @property int         $sort_order  Thứ tự hiển thị
 */
class Category extends Model
{
    // Tên collection trong MongoDB
    protected $collection = 'categories';

    // Kết nối database
    protected $connection = 'pgsql';

    /**
     * Các trường được phép gán hàng loạt
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'parent_id',
        'is_active',
        'sort_order',
    ];

    /**
     * Ép kiểu dữ liệu cho các trường
     */
    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Giá trị mặc định
     */
    protected $attributes = [
        'is_active'  => true,
        'sort_order' => 0,
    ];

    // ============================================================
    // Relationships (Quan hệ)
    // ============================================================

    /**
     * Lấy danh mục cha
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Lấy danh sách danh mục con
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Lấy danh sách sản phẩm trong danh mục
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // ============================================================
    // Scopes (Phạm vi truy vấn)
    // ============================================================

    /**
     * Chỉ lấy danh mục đang hiển thị
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Chỉ lấy danh mục gốc (không có danh mục cha)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Sắp xếp theo thứ tự hiển thị
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    // ============================================================
    // Accessors & Mutators
    // ============================================================

    /**
     * Tự động tạo slug từ tên danh mục nếu chưa có
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }

    /**
     * Kiểm tra danh mục có phải danh mục gốc không
     */
    public function getIsRootAttribute(): bool
    {
        return is_null($this->parent_id);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Tạo slug duy nhất từ tên danh mục
     */
    private function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'like', "{$slug}%")->count();
        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Đếm số sản phẩm trong danh mục
     */
    public function getProductCountAttribute(): int
    {
        return Product::where('category_id', (string) $this->_id)
                      ->where('is_active', true)
                      ->count();
    }

    /**
     * Lấy toàn bộ cây danh mục (bao gồm danh mục con)
     */
    public static function getTree(): \Illuminate\Database\Eloquent\Collection
    {
        $categories = static::active()
                            ->ordered()
                            ->get();

        return $categories->filter(fn($cat) => is_null($cat->parent_id))
                          ->map(function ($cat) use ($categories) {
                              $cat->children_list = $categories->filter(
                                  fn($child) => (string) $child->parent_id === (string) $cat->_id
                              )->values();
                              return $cat;
                          })->values();
    }
}
