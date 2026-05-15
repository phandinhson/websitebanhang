<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        $users = [
            ['name' => 'Admin',          'email' => 'admin@websitebanhang.vn', 'password' => Hash::make('Admin@123'),   'phone' => '0901234567', 'address' => null,                      'role' => 'admin',    'is_active' => true],
            ['name' => 'Nguyễn Văn An', 'email' => 'an@example.com',          'password' => Hash::make('password123'), 'phone' => '0912345678', 'address' => '123 Nguyễn Huệ, Q1, TP.HCM', 'role' => 'customer', 'is_active' => true],
            ['name' => 'Trần Thị Bích', 'email' => 'bich@example.com',        'password' => Hash::make('password123'), 'phone' => '0923456789', 'address' => '456 Lê Lợi, Q3, TP.HCM',     'role' => 'customer', 'is_active' => true],
        ];
        foreach ($users as $u) {
            DB::table('users')->insert(array_merge($u, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Categories cha
        $catIds = [];
        $parentCats = [
            ['name' => 'Điện thoại & Phụ kiện', 'slug' => 'dien-thoai-phu-kien'],
            ['name' => 'Máy tính & Laptop',     'slug' => 'may-tinh-laptop'],
            ['name' => 'Thời trang Nam',         'slug' => 'thoi-trang-nam'],
            ['name' => 'Thời trang Nữ',          'slug' => 'thoi-trang-nu'],
            ['name' => 'Nhà cửa & Đời sống',    'slug' => 'nha-cua-doi-song'],
            ['name' => 'Sức khỏe & Làm đẹp',    'slug' => 'suc-khoe-lam-dep'],
        ];
        foreach ($parentCats as $cat) {
            $catIds[] = DB::table('categories')->insertGetId([
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Categories con
        $childCats = [
            ['name' => 'Điện thoại',    'slug' => 'dien-thoai',    'parent_id' => $catIds[0]],
            ['name' => 'Ốp lưng',       'slug' => 'op-lung',       'parent_id' => $catIds[0]],
            ['name' => 'Laptop',         'slug' => 'laptop',         'parent_id' => $catIds[1]],
            ['name' => 'Máy tính bảng', 'slug' => 'may-tinh-bang', 'parent_id' => $catIds[1]],
            ['name' => 'Áo nam',         'slug' => 'ao-nam',         'parent_id' => $catIds[2]],
            ['name' => 'Quần nam',       'slug' => 'quan-nam',       'parent_id' => $catIds[2]],
            ['name' => 'Áo nữ',          'slug' => 'ao-nu',          'parent_id' => $catIds[3]],
            ['name' => 'Váy đầm',        'slug' => 'vay-dam',        'parent_id' => $catIds[3]],
        ];
        $childCatIds = [];
        foreach ($childCats as $cat) {
            $childCatIds[] = DB::table('categories')->insertGetId([
                'name'      => $cat['name'],
                'slug'      => $cat['slug'],
                'parent_id' => $cat['parent_id'],
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Products
        $products = [
            ['name' => 'iPhone 15 Pro Max 256GB',    'price' => 34990000, 'sale_price' => 32990000, 'category_id' => $childCatIds[0], 'stock' => 50,  'featured' => true],
            ['name' => 'Samsung Galaxy S24 Ultra',   'price' => 31990000, 'sale_price' => 29990000, 'category_id' => $childCatIds[0], 'stock' => 40,  'featured' => true],
            ['name' => 'OPPO Reno 11 Pro 5G',        'price' => 14990000, 'sale_price' => 13490000, 'category_id' => $childCatIds[0], 'stock' => 60,  'featured' => false],
            ['name' => 'Ốp lưng iPhone 15 chống sốc','price' => 299000,   'sale_price' => 199000,   'category_id' => $childCatIds[1], 'stock' => 200, 'featured' => false],
            ['name' => 'Laptop Dell XPS 13 Plus',    'price' => 45990000, 'sale_price' => null,      'category_id' => $childCatIds[2], 'stock' => 20,  'featured' => true],
            ['name' => 'MacBook Air M3 15 inch',     'price' => 38990000, 'sale_price' => 36990000, 'category_id' => $childCatIds[2], 'stock' => 25,  'featured' => true],
            ['name' => 'iPad Pro M4 11 inch WiFi',   'price' => 27990000, 'sale_price' => 25990000, 'category_id' => $childCatIds[3], 'stock' => 30,  'featured' => false],
            ['name' => 'Áo Polo Nam Slim Fit',        'price' => 450000,   'sale_price' => 350000,   'category_id' => $childCatIds[4], 'stock' => 150, 'featured' => false],
            ['name' => 'Áo thun nam basic unisex',   'price' => 199000,   'sale_price' => 159000,   'category_id' => $childCatIds[4], 'stock' => 300, 'featured' => false],
            ['name' => 'Quần jean nam ống đứng',     'price' => 590000,   'sale_price' => 490000,   'category_id' => $childCatIds[5], 'stock' => 100, 'featured' => false],
            ['name' => 'Áo blazer nữ công sở',       'price' => 890000,   'sale_price' => 690000,   'category_id' => $childCatIds[6], 'stock' => 80,  'featured' => true],
            ['name' => 'Váy maxi hoa nhí dáng dài',  'price' => 490000,   'sale_price' => 390000,   'category_id' => $childCatIds[7], 'stock' => 120, 'featured' => false],
            ['name' => 'Đầm wrap dự tiệc',           'price' => 750000,   'sale_price' => 599000,   'category_id' => $childCatIds[7], 'stock' => 60,  'featured' => true],
            ['name' => 'Serum vitamin C dưỡng sáng', 'price' => 380000,   'sale_price' => 299000,   'category_id' => $catIds[5],      'stock' => 200, 'featured' => false],
            ['name' => 'Kem chống nắng SPF50+ PA+++','price' => 250000,   'sale_price' => 199000,   'category_id' => $catIds[5],      'stock' => 180, 'featured' => false],
        ];

        foreach ($products as $p) {
            $slug = Str::slug($p['name']) . '-' . Str::random(4);
            DB::table('products')->insert([
                'name'           => $p['name'],
                'slug'           => $slug,
                'description'    => 'Mô tả chi tiết sản phẩm ' . $p['name'],
                'price'          => $p['price'],
                'sale_price'     => $p['sale_price'],
                'category_id'   => $p['category_id'],
                'stock'          => $p['stock'],
                'sku'            => 'SKU-' . strtoupper(Str::random(8)),
                'is_active'      => true,
                'is_featured'    => $p['featured'],
                'rating'         => round(rand(35, 50) / 10, 1),
                'review_count'   => rand(5, 200),
                'images'         => json_encode([]),
                'specifications' => json_encode([]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
