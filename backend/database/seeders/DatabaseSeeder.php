<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DatabaseSeeder - Tạo dữ liệu mẫu cho website bán hàng Việt Nam
 *
 * Chạy lệnh: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Chạy các seeder
     */
    public function run(): void
    {
        $this->command->info('Bắt đầu tạo dữ liệu mẫu cho website bán hàng...');

        // Thứ tự quan trọng: tạo user -> category -> product
        $this->seedUsers();
        $categoryIds = $this->seedCategories();
        $this->seedProducts($categoryIds);

        $this->command->info('Hoàn thành tạo dữ liệu mẫu!');
    }

    // ============================================================
    // TẠO TÀI KHOẢN NGƯỜI DÙNG
    // ============================================================

    private function seedUsers(): void
    {
        $this->command->info('Đang tạo tài khoản người dùng...');

        // Xóa dữ liệu cũ
        User::truncate();

        // Tạo tài khoản Admin
        User::create([
            'name'      => 'Quản trị viên',
            'email'     => 'admin@websitebanhang.vn',
            'password'  => 'admin123456',
            'phone'     => '0901234567',
            'role'      => 'admin',
            'is_active' => true,
            'address'   => [
                'street'   => '123 Nguyễn Huệ',
                'ward'     => 'Phường Bến Nghé',
                'district' => 'Quận 1',
                'city'     => 'Hồ Chí Minh',
            ],
        ]);

        // Tạo tài khoản khách hàng mẫu
        $customers = [
            [
                'name'    => 'Nguyễn Văn An',
                'email'   => 'nguyenvanan@gmail.com',
                'phone'   => '0912345678',
                'address' => [
                    'street'   => '456 Lê Lợi',
                    'ward'     => 'Phường 4',
                    'district' => 'Quận 3',
                    'city'     => 'Hồ Chí Minh',
                ],
            ],
            [
                'name'    => 'Trần Thị Bình',
                'email'   => 'tranthibinh@gmail.com',
                'phone'   => '0923456789',
                'address' => [
                    'street'   => '789 Hoàn Kiếm',
                    'ward'     => 'Phường Hàng Gai',
                    'district' => 'Quận Hoàn Kiếm',
                    'city'     => 'Hà Nội',
                ],
            ],
            [
                'name'    => 'Lê Minh Cường',
                'email'   => 'leminhcuong@gmail.com',
                'phone'   => '0934567890',
                'address' => [
                    'street'   => '321 Trần Phú',
                    'ward'     => 'Phường Hải Châu 1',
                    'district' => 'Quận Hải Châu',
                    'city'     => 'Đà Nẵng',
                ],
            ],
        ];

        foreach ($customers as $customer) {
            User::create(array_merge($customer, [
                'password'  => 'password123',
                'role'      => 'customer',
                'is_active' => true,
            ]));
        }

        $this->command->info('Đã tạo 1 admin + ' . count($customers) . ' khách hàng mẫu.');
    }

    // ============================================================
    // TẠO DANH MỤC SẢN PHẨM
    // ============================================================

    /**
     * @return array<string, string> Mảng [tên_danh_mục => id]
     */
    private function seedCategories(): array
    {
        $this->command->info('Đang tạo danh mục sản phẩm...');

        // Xóa dữ liệu cũ
        Category::truncate();

        $categoryIds = [];

        // --- Danh mục cha ---
        $parentCategories = [
            [
                'name'        => 'Điện tử - Công nghệ',
                'slug'        => 'dien-tu-cong-nghe',
                'description' => 'Điện thoại, laptop, máy tính bảng, phụ kiện điện tử',
                'image'       => 'https://placehold.co/400x300?text=Dien+Tu',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Thời trang',
                'slug'        => 'thoi-trang',
                'description' => 'Quần áo, giày dép, túi xách thời trang nam nữ',
                'image'       => 'https://placehold.co/400x300?text=Thoi+Trang',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Nhà cửa - Đời sống',
                'slug'        => 'nha-cua-doi-song',
                'description' => 'Đồ gia dụng, nội thất, trang trí nhà cửa',
                'image'       => 'https://placehold.co/400x300?text=Nha+Cua',
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Sức khỏe - Làm đẹp',
                'slug'        => 'suc-khoe-lam-dep',
                'description' => 'Mỹ phẩm, chăm sóc da, sức khỏe, thực phẩm chức năng',
                'image'       => 'https://placehold.co/400x300?text=Suc+Khoe',
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Thể thao - Dã ngoại',
                'slug'        => 'the-thao-da-ngoai',
                'description' => 'Dụng cụ thể thao, đồ dã ngoại, fitness',
                'image'       => 'https://placehold.co/400x300?text=The+Thao',
                'sort_order'  => 5,
            ],
            [
                'name'        => 'Sách - Văn phòng phẩm',
                'slug'        => 'sach-van-phong-pham',
                'description' => 'Sách, văn phòng phẩm, thiết bị học tập',
                'image'       => 'https://placehold.co/400x300?text=Sach',
                'sort_order'  => 6,
            ],
        ];

        foreach ($parentCategories as $catData) {
            $category = Category::create(array_merge($catData, ['is_active' => true]));
            $categoryIds[$catData['slug']] = (string) $category->_id;
        }

        // --- Danh mục con (thuộc "Điện tử - Công nghệ") ---
        $techSubCategories = [
            ['name' => 'Điện thoại di động', 'slug' => 'dien-thoai-di-dong', 'sort_order' => 1],
            ['name' => 'Laptop - Máy tính', 'slug' => 'laptop-may-tinh', 'sort_order' => 2],
            ['name' => 'Máy tính bảng', 'slug' => 'may-tinh-bang', 'sort_order' => 3],
            ['name' => 'Phụ kiện điện tử', 'slug' => 'phu-kien-dien-tu', 'sort_order' => 4],
            ['name' => 'Tai nghe - Loa', 'slug' => 'tai-nghe-loa', 'sort_order' => 5],
        ];

        foreach ($techSubCategories as $subCat) {
            $sub = Category::create(array_merge($subCat, [
                'parent_id'  => $categoryIds['dien-tu-cong-nghe'],
                'is_active'  => true,
                'description' => "Danh mục {$subCat['name']} - chất lượng cao, giá tốt",
            ]));
            $categoryIds[$subCat['slug']] = (string) $sub->_id;
        }

        // --- Danh mục con (thuộc "Thời trang") ---
        $fashionSubCategories = [
            ['name' => 'Áo nam', 'slug' => 'ao-nam', 'sort_order' => 1],
            ['name' => 'Quần nam', 'slug' => 'quan-nam', 'sort_order' => 2],
            ['name' => 'Áo nữ', 'slug' => 'ao-nu', 'sort_order' => 3],
            ['name' => 'Giày dép', 'slug' => 'giay-dep', 'sort_order' => 4],
            ['name' => 'Túi xách', 'slug' => 'tui-xach', 'sort_order' => 5],
        ];

        foreach ($fashionSubCategories as $subCat) {
            $sub = Category::create(array_merge($subCat, [
                'parent_id'  => $categoryIds['thoi-trang'],
                'is_active'  => true,
                'description' => "Danh mục {$subCat['name']} - thời trang, phong cách",
            ]));
            $categoryIds[$subCat['slug']] = (string) $sub->_id;
        }

        $this->command->info('Đã tạo ' . count($categoryIds) . ' danh mục.');

        return $categoryIds;
    }

    // ============================================================
    // TẠO SẢN PHẨM MẪU
    // ============================================================

    private function seedProducts(array $categoryIds): void
    {
        $this->command->info('Đang tạo sản phẩm mẫu...');

        // Xóa dữ liệu cũ
        Product::truncate();

        $products = [
            // ============================================================
            // ĐIỆN THOẠI DI ĐỘNG
            // ============================================================
            [
                'name'        => 'iPhone 15 Pro Max 256GB',
                'description' => 'iPhone 15 Pro Max với chip A17 Pro mạnh mẽ, camera 48MP, màn hình Super Retina XDR 6.7 inch. Thiết kế titan sang trọng, pin 29 giờ nghe nhạc.',
                'price'       => 34990000,
                'sale_price'  => 32990000,
                'category_id' => $categoryIds['dien-thoai-di-dong'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 50,
                'brand'       => 'Apple',
                'sku'         => 'IP15PM-256',
                'images'      => [
                    'https://placehold.co/800x800?text=iPhone+15+Pro+Max',
                    'https://placehold.co/800x800?text=iPhone+15+PM+2',
                ],
                'attributes'  => [
                    'color'   => ['Titan tự nhiên', 'Titan xanh', 'Titan trắng', 'Titan đen'],
                    'storage' => ['256GB', '512GB', '1TB'],
                    'ram'     => '8GB',
                ],
                'rating'      => 4.9,
                'review_count'=> 256,
                'is_active'   => true,
            ],
            [
                'name'        => 'Samsung Galaxy S24 Ultra 512GB',
                'description' => 'Samsung Galaxy S24 Ultra với bút S Pen, AI Galaxy, camera 200MP, màn hình Dynamic AMOLED 2X 6.8 inch. Chip Snapdragon 8 Gen 3 đỉnh cao.',
                'price'       => 31990000,
                'sale_price'  => 28990000,
                'category_id' => $categoryIds['dien-thoai-di-dong'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 35,
                'brand'       => 'Samsung',
                'sku'         => 'SS-S24U-512',
                'images'      => [
                    'https://placehold.co/800x800?text=Samsung+S24+Ultra',
                ],
                'attributes'  => [
                    'color'   => ['Titanium Gray', 'Titanium Black', 'Titanium Violet'],
                    'storage' => ['256GB', '512GB', '1TB'],
                    'ram'     => '12GB',
                ],
                'rating'      => 4.8,
                'review_count'=> 189,
                'is_active'   => true,
            ],
            [
                'name'        => 'Xiaomi Redmi Note 13 Pro 256GB',
                'description' => 'Điện thoại tầm trung Xiaomi Redmi Note 13 Pro với camera 200MP, màn hình AMOLED 120Hz, pin 5100mAh sạc nhanh 67W. Giá tốt, hiệu năng cao.',
                'price'       => 8990000,
                'sale_price'  => 7490000,
                'category_id' => $categoryIds['dien-thoai-di-dong'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 80,
                'brand'       => 'Xiaomi',
                'sku'         => 'XM-RN13P-256',
                'images'      => [
                    'https://placehold.co/800x800?text=Redmi+Note+13+Pro',
                ],
                'attributes'  => [
                    'color'   => ['Trắng', 'Đen', 'Tím', 'Xanh'],
                    'storage' => ['128GB', '256GB'],
                    'ram'     => ['8GB', '12GB'],
                ],
                'rating'      => 4.6,
                'review_count'=> 432,
                'is_active'   => true,
            ],

            // ============================================================
            // LAPTOP - MÁY TÍNH
            // ============================================================
            [
                'name'        => 'MacBook Air M3 15 inch 16GB/512GB',
                'description' => 'MacBook Air 15 inch với chip Apple M3, 16GB RAM, SSD 512GB. Thiết kế siêu mỏng nhẹ, màn hình Liquid Retina 15.3 inch, thời lượng pin lên đến 18 giờ.',
                'price'       => 42990000,
                'sale_price'  => null,
                'category_id' => $categoryIds['laptop-may-tinh'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 20,
                'brand'       => 'Apple',
                'sku'         => 'MBA-M3-15-512',
                'images'      => [
                    'https://placehold.co/800x800?text=MacBook+Air+M3',
                ],
                'attributes'  => [
                    'color'     => ['Midnight', 'Starlight', 'Space Gray', 'Silver'],
                    'ram'       => ['16GB', '24GB'],
                    'storage'   => ['512GB', '1TB', '2TB'],
                    'processor' => 'Apple M3',
                ],
                'rating'      => 4.9,
                'review_count'=> 98,
                'is_active'   => true,
            ],
            [
                'name'        => 'Laptop ASUS VivoBook 15 Core i5 16GB/512GB',
                'description' => 'Laptop ASUS VivoBook 15 với Intel Core i5-1335U, RAM 16GB, SSD 512GB, màn hình 15.6 inch FHD. Hiệu năng ổn định cho học tập và làm việc văn phòng.',
                'price'       => 14990000,
                'sale_price'  => 12990000,
                'category_id' => $categoryIds['laptop-may-tinh'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 30,
                'brand'       => 'ASUS',
                'sku'         => 'ASUS-VB15-I5',
                'images'      => [
                    'https://placehold.co/800x800?text=ASUS+VivoBook+15',
                ],
                'attributes'  => [
                    'color'     => ['Bạc', 'Đen'],
                    'ram'       => ['8GB', '16GB'],
                    'storage'   => ['256GB', '512GB'],
                    'processor' => 'Intel Core i5-1335U',
                ],
                'rating'      => 4.5,
                'review_count'=> 156,
                'is_active'   => true,
            ],

            // ============================================================
            // TAI NGHE - LOA
            // ============================================================
            [
                'name'        => 'Tai nghe Sony WH-1000XM5 Chống ồn',
                'description' => 'Tai nghe Sony WH-1000XM5 với công nghệ chống ồn tốt nhất, âm thanh Hi-Res, pin 30 giờ. Kết nối đa điểm, thoải mái khi đeo cả ngày.',
                'price'       => 8490000,
                'sale_price'  => 6990000,
                'category_id' => $categoryIds['tai-nghe-loa'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 45,
                'brand'       => 'Sony',
                'sku'         => 'SONY-WH1000XM5',
                'images'      => [
                    'https://placehold.co/800x800?text=Sony+WH-1000XM5',
                ],
                'attributes'  => [
                    'color' => ['Đen', 'Bạc'],
                    'type'  => 'Over-ear',
                ],
                'rating'      => 4.8,
                'review_count'=> 312,
                'is_active'   => true,
            ],
            [
                'name'        => 'Loa Bluetooth JBL Charge 5',
                'description' => 'Loa JBL Charge 5 chống nước IP67, âm thanh mạnh mẽ, pin 20 giờ, có thể sạc thiết bị di động. Phù hợp dã ngoại, picnic.',
                'price'       => 3490000,
                'sale_price'  => 2890000,
                'category_id' => $categoryIds['tai-nghe-loa'] ?? $categoryIds['dien-tu-cong-nghe'],
                'stock'       => 60,
                'brand'       => 'JBL',
                'sku'         => 'JBL-CHARGE5',
                'images'      => [
                    'https://placehold.co/800x800?text=JBL+Charge+5',
                ],
                'attributes'  => [
                    'color' => ['Đen', 'Xanh', 'Đỏ', 'Cam', 'Xám'],
                ],
                'rating'      => 4.7,
                'review_count'=> 287,
                'is_active'   => true,
            ],

            // ============================================================
            // THỜI TRANG NAM
            // ============================================================
            [
                'name'        => 'Áo polo nam Local Brand LADOS Slim Fit',
                'description' => 'Áo polo nam chất liệu cotton cao cấp, form slim fit tôn dáng. Phù hợp đi làm, đi chơi, đi tiệc. Nhiều màu sắc trẻ trung, hiện đại.',
                'price'       => 350000,
                'sale_price'  => 279000,
                'category_id' => $categoryIds['ao-nam'] ?? $categoryIds['thoi-trang'],
                'stock'       => 200,
                'brand'       => 'LADOS',
                'sku'         => 'LADOS-POLO-001',
                'images'      => [
                    'https://placehold.co/800x800?text=Ao+Polo+Nam',
                ],
                'attributes'  => [
                    'color' => ['Trắng', 'Đen', 'Navy', 'Xám', 'Xanh rêu'],
                    'size'  => ['S', 'M', 'L', 'XL', 'XXL'],
                ],
                'rating'      => 4.6,
                'review_count'=> 523,
                'is_active'   => true,
            ],
            [
                'name'        => 'Quần jeans nam skinny fit wash xanh',
                'description' => 'Quần jeans nam chất liệu denim co giãn 4 chiều, form skinny fit hiện đại. Wash xanh đậm cá tính, phù hợp nhiều phong cách.',
                'price'       => 499000,
                'sale_price'  => 389000,
                'category_id' => $categoryIds['quan-nam'] ?? $categoryIds['thoi-trang'],
                'stock'       => 150,
                'brand'       => 'GenZ Style',
                'sku'         => 'GZ-JEANS-SK-001',
                'images'      => [
                    'https://placehold.co/800x800?text=Quan+Jeans+Nam',
                ],
                'attributes'  => [
                    'color' => ['Xanh đậm', 'Xanh nhạt', 'Đen'],
                    'size'  => ['28', '29', '30', '31', '32', '33', '34'],
                ],
                'rating'      => 4.4,
                'review_count'=> 341,
                'is_active'   => true,
            ],

            // ============================================================
            // THỜI TRANG NỮ
            // ============================================================
            [
                'name'        => 'Áo sơ mi nữ lụa trơn tay dài',
                'description' => 'Áo sơ mi nữ chất liệu lụa mềm mại, tay dài thanh lịch. Phù hợp đi làm văn phòng, dự tiệc. Thiết kế thanh lịch, sang trọng.',
                'price'       => 450000,
                'sale_price'  => 349000,
                'category_id' => $categoryIds['ao-nu'] ?? $categoryIds['thoi-trang'],
                'stock'       => 100,
                'brand'       => 'Elegant Lady',
                'sku'         => 'EL-SHIRT-NU-001',
                'images'      => [
                    'https://placehold.co/800x800?text=Ao+So+Mi+Nu',
                ],
                'attributes'  => [
                    'color' => ['Trắng', 'Kem', 'Hồng nhạt', 'Xanh nhạt'],
                    'size'  => ['XS', 'S', 'M', 'L', 'XL'],
                ],
                'rating'      => 4.7,
                'review_count'=> 218,
                'is_active'   => true,
            ],

            // ============================================================
            // GIÀY DÉP
            // ============================================================
            [
                'name'        => 'Giày thể thao nam Nike Air Max 270',
                'description' => 'Giày Nike Air Max 270 với đệm Air lớn nhất, ôm chân hoàn hảo. Thiết kế năng động, phù hợp tập gym, chạy bộ, đi dạo.',
                'price'       => 3200000,
                'sale_price'  => 2590000,
                'category_id' => $categoryIds['giay-dep'] ?? $categoryIds['thoi-trang'],
                'stock'       => 75,
                'brand'       => 'Nike',
                'sku'         => 'NIKE-AM270-001',
                'images'      => [
                    'https://placehold.co/800x800?text=Nike+Air+Max+270',
                ],
                'attributes'  => [
                    'color' => ['Đen/Trắng', 'Trắng/Đen', 'Xanh/Trắng'],
                    'size'  => ['39', '40', '41', '42', '43', '44'],
                    'gender'=> 'Nam',
                ],
                'rating'      => 4.7,
                'review_count'=> 445,
                'is_active'   => true,
            ],

            // ============================================================
            // NHÀ CỬA - ĐỜI SỐNG
            // ============================================================
            [
                'name'        => 'Nồi chiên không dầu Philips HD9252 4.1L',
                'description' => 'Nồi chiên không dầu Philips 4.1L với công nghệ Rapid Air, giảm 90% lượng dầu so với chiên truyền thống. Dễ sử dụng, dễ làm sạch, an toàn cho sức khỏe.',
                'price'       => 2490000,
                'sale_price'  => 1990000,
                'category_id' => $categoryIds['nha-cua-doi-song'],
                'stock'       => 40,
                'brand'       => 'Philips',
                'sku'         => 'PH-HD9252',
                'images'      => [
                    'https://placehold.co/800x800?text=Noi+Chien+Philips',
                ],
                'attributes'  => [
                    'capacity' => '4.1L',
                    'power'    => '1400W',
                    'color'    => 'Đen',
                ],
                'rating'      => 4.8,
                'review_count'=> 567,
                'is_active'   => true,
            ],
            [
                'name'        => 'Máy hút bụi Xiaomi G20 không dây',
                'description' => 'Máy hút bụi Xiaomi G20 không dây cầm tay, lực hút 130AW, lọc HEPA 5 lớp. Pin lithium 25.9V, hoạt động 60 phút. Nhẹ nhàng, tiện lợi.',
                'price'       => 3990000,
                'sale_price'  => 3290000,
                'category_id' => $categoryIds['nha-cua-doi-song'],
                'stock'       => 25,
                'brand'       => 'Xiaomi',
                'sku'         => 'XM-G20-VACUUM',
                'images'      => [
                    'https://placehold.co/800x800?text=May+Hut+Bui+Xiaomi',
                ],
                'attributes'  => [
                    'power'     => '25.9V',
                    'suction'   => '130AW',
                    'battery'   => '60 phút',
                ],
                'rating'      => 4.6,
                'review_count'=> 234,
                'is_active'   => true,
            ],

            // ============================================================
            // SỨC KHỎE - LÀM ĐẸP
            // ============================================================
            [
                'name'        => 'Kem chống nắng Anessa Perfect UV Sunscreen SPF50+',
                'description' => 'Kem chống nắng Anessa nhật bản SPF50+ PA++++, chống tia UVA/UVB. Kết cấu nhẹ, thấm nhanh, không nhờn, phù hợp dùng hàng ngày và đi biển.',
                'price'       => 520000,
                'sale_price'  => 450000,
                'category_id' => $categoryIds['suc-khoe-lam-dep'],
                'stock'       => 90,
                'brand'       => 'Anessa',
                'sku'         => 'ANESSA-SPF50-60ML',
                'images'      => [
                    'https://placehold.co/800x800?text=Kem+Chong+Nang+Anessa',
                ],
                'attributes'  => [
                    'spf'     => 'SPF50+ PA++++',
                    'volume'  => ['60ml', '90ml'],
                    'origin'  => 'Nhật Bản',
                ],
                'rating'      => 4.9,
                'review_count'=> 892,
                'is_active'   => true,
            ],

            // ============================================================
            // THỂ THAO - DÃ NGOẠI
            // ============================================================
            [
                'name'        => 'Dụng cụ tập yoga MINISO thảm + bộ đồ nghề',
                'description' => 'Thảm yoga chống trượt 6mm kèm bộ đồ nghề: dây kháng lực, con lăn bọt biển, túi đựng. Phù hợp tập yoga, pilates tại nhà.',
                'price'       => 850000,
                'sale_price'  => 650000,
                'category_id' => $categoryIds['the-thao-da-ngoai'],
                'stock'       => 60,
                'brand'       => 'MINISO',
                'sku'         => 'MINISO-YOGA-SET',
                'images'      => [
                    'https://placehold.co/800x800?text=Yoga+Set+MINISO',
                ],
                'attributes'  => [
                    'color'    => ['Tím', 'Xanh', 'Hồng', 'Xám'],
                    'material' => 'NBR cao su thiên nhiên',
                    'size'     => '183cm x 61cm x 6mm',
                ],
                'rating'      => 4.5,
                'review_count'=> 178,
                'is_active'   => true,
            ],

            // ============================================================
            // SÁCH - VĂN PHÒNG PHẨM
            // ============================================================
            [
                'name'        => 'Sách "Đắc Nhân Tâm" - Dale Carnegie (Bìa Cứng)',
                'description' => 'Cuốn sách bán chạy nhất mọi thời đại về nghệ thuật giao tiếp và ứng xử. Bản dịch chuẩn, bìa cứng cao cấp. Quà tặng ý nghĩa cho người thân, bạn bè.',
                'price'       => 120000,
                'sale_price'  => 89000,
                'category_id' => $categoryIds['sach-van-phong-pham'],
                'stock'       => 500,
                'brand'       => 'NXB Tổng hợp TP.HCM',
                'sku'         => 'SACH-DNT-BIACU',
                'images'      => [
                    'https://placehold.co/800x800?text=Dac+Nhan+Tam',
                ],
                'attributes'  => [
                    'author'    => 'Dale Carnegie',
                    'pages'     => '320 trang',
                    'language'  => 'Tiếng Việt',
                    'cover'     => 'Bìa cứng',
                ],
                'rating'      => 4.9,
                'review_count'=> 1234,
                'is_active'   => true,
            ],
        ];

        // Tạo sản phẩm và gán slug tự động
        foreach ($products as $productData) {
            // Gán slug từ tên sản phẩm nếu chưa có
            if (empty($productData['slug'])) {
                $productData['slug'] = Str::slug($productData['name']);
            }

            // Xử lý category_id mặc định nếu danh mục con không tồn tại
            if (!isset($productData['category_id'])) {
                $productData['category_id'] = array_values($categoryIds)[0];
            }

            Product::create($productData);
        }

        $this->command->info('Đã tạo ' . count($products) . ' sản phẩm mẫu.');
    }
}
