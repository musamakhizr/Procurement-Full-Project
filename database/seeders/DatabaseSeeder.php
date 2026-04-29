<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customer = User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
            'organization_name' => 'Bright Future School',
            'organization_type' => 'school',
            'role' => 'customer',
        ]);

        User::query()->updateOrCreate([
            'email' => 'admin@procurepro.test',
        ], [
            'name' => 'ProcurePro Admin',
            'password' => 'password',
            'organization_name' => 'ProcurePro',
            'organization_type' => 'business',
            'role' => 'admin',
        ]);

        $catalog = [
            [
                'name' => 'Office & School Admin',
                'slug' => 'office',
                'children' => [
                    ['name' => 'Writing', 'slug' => 'writing'],
                    ['name' => 'Paper', 'slug' => 'paper'],
                    ['name' => 'Organization', 'slug' => 'organization'],
                ],
            ],
            [
                'name' => 'Classroom Supplies',
                'slug' => 'classroom',
                'children' => [
                    ['name' => 'Teaching Aids', 'slug' => 'teaching-aids'],
                    ['name' => 'Student Supplies', 'slug' => 'student-supplies'],
                ],
            ],
            [
                'name' => 'Art & Craft',
                'slug' => 'art',
                'children' => [
                    ['name' => 'Paints', 'slug' => 'paints'],
                    ['name' => 'Drawing', 'slug' => 'drawing'],
                ],
            ],
            [
                'name' => 'Sports & Outdoor',
                'slug' => 'sports',
                'children' => [
                    ['name' => 'Balls', 'slug' => 'balls'],
                    ['name' => 'Fitness', 'slug' => 'fitness'],
                ],
            ],
            [
                'name' => 'School Events & Custom Products',
                'slug' => 'events',
                'children' => [
                    ['name' => 'Decorations', 'slug' => 'decorations'],
                    ['name' => 'Tableware', 'slug' => 'tableware'],
                ],
            ],
            [
                'name' => 'Technology & Electronics',
                'slug' => 'technology',
                'children' => [
                    ['name' => 'Computers', 'slug' => 'computers'],
                    ['name' => 'Accessories', 'slug' => 'accessories'],
                ],
            ],
            [
                'name' => 'Early Years',
                'slug' => 'early-years',
                'children' => [],
            ],
            [
                'name' => 'Science & Lab',
                'slug' => 'science-lab',
                'children' => [],
            ],
            [
                'name' => 'Music & Performing Arts',
                'slug' => 'music-performing-arts',
                'children' => [],
            ],
            [
                'name' => 'Furniture & Storage',
                'slug' => 'furniture-storage',
                'children' => [],
            ],
            [
                'name' => 'Books & Learning Resources',
                'slug' => 'books-learning-resources',
                'children' => [],
            ],
            [
                'name' => 'SEN & Student Support',
                'slug' => 'sen-student-support',
                'children' => [],
            ],
            [
                'name' => 'Facilities & Campus Supplies',
                'slug' => 'facilities-campus-supplies',
                'children' => [],
            ],
            [
                'name' => 'Cleaning, Health & Safety',
                'slug' => 'cleaning-health-safety',
                'children' => [],
            ],
            [
                'name' => 'Pantry & Hospitality',
                'slug' => 'pantry-hospitality',
                'children' => [],
            ],
        ];

        $categories = [];

        Category::query()
            ->whereIn('slug', [
                'classroom-supplies',
                'art-craft',
                'office-school-admin',
                'technology-electronics',
                'sports-outdoor',
                'school-events-custom-products',
            ])
            ->delete();

        foreach ($catalog as $index => $categoryData) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                ['name' => $categoryData['name'], 'sort_order' => $index + 1],
            );

            $categories[$categoryData['slug']] = $parent;

            foreach ($categoryData['children'] as $childIndex => $childData) {
                $categories[$childData['slug']] = Category::query()->updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'sort_order' => $childIndex + 1,
                    ],
                );
            }
        }

        $products = [
            [
                'sku' => 'PPO-WBM-001',
                'category_slug' => 'writing',
                'name' => 'Premium Whiteboard Markers - 12 Pack',
                'description' => 'High-quality dry-erase whiteboard markers with vivid ink and precision tips for classrooms and offices.',
                'image_url' => 'https://images.unsplash.com/photo-1586958060273-f1ddaafbd396?w=800',
                'moq' => 10,
                'lead_time_min_days' => 3,
                'lead_time_max_days' => 5,
                'stock_quantity' => 5000,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 15.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 15.99],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 13.80],
                    ['min_quantity' => 50, 'max_quantity' => null, 'price' => 12.50],
                ],
            ],
            [
                'sku' => 'PPO-SCS-002',
                'category_slug' => 'student-supplies',
                'name' => 'Student Scissors - Safety Edge (Pack of 24)',
                'description' => 'Rounded-edge classroom scissors designed for safe daily student use.',
                'image_url' => 'https://images.unsplash.com/photo-1765484253358-70f69979d307?w=800',
                'moq' => 5,
                'lead_time_min_days' => 2,
                'lead_time_max_days' => 4,
                'stock_quantity' => 3200,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 28.50,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 28.50],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 24.60],
                ],
            ],
            [
                'sku' => 'PPO-APS-003',
                'category_slug' => 'paints',
                'name' => 'Acrylic Paint Set - 24 Colors',
                'description' => 'Vibrant acrylic paint set for arts programs, classroom creativity, and workshop kits.',
                'image_url' => 'https://images.unsplash.com/photo-1513519245088-0e12902e35ca?w=800',
                'moq' => 8,
                'lead_time_min_days' => 4,
                'lead_time_max_days' => 6,
                'stock_quantity' => 850,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 32.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 32.00],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 27.50],
                ],
            ],
            [
                'sku' => 'PPO-WMS-004',
                'category_slug' => 'accessories',
                'name' => 'Wireless Mouse - Ergonomic Design',
                'description' => 'Comfortable wireless mouse with ergonomic shape for office and classroom computer labs.',
                'image_url' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800',
                'moq' => 15,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 7,
                'stock_quantity' => 1200,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 18.75,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 19, 'price' => 18.75],
                    ['min_quantity' => 20, 'max_quantity' => null, 'price' => 15.90],
                ],
            ],
            [
                'sku' => 'PPO-STB-005',
                'category_slug' => 'organization',
                'name' => 'Storage Bins with Lids (Set of 6)',
                'description' => 'Stackable storage bins ideal for classroom organization and office supply management.',
                'image_url' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?w=800',
                'moq' => 3,
                'lead_time_min_days' => 3,
                'lead_time_max_days' => 5,
                'stock_quantity' => 420,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 45.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 4, 'price' => 45.00],
                    ['min_quantity' => 5, 'max_quantity' => null, 'price' => 39.80],
                ],
            ],
            [
                'sku' => 'PPO-SOC-006',
                'category_slug' => 'balls',
                'name' => 'Soccer Balls - Official Size (Pack of 12)',
                'description' => 'Competition-size soccer balls for schools, academies, and sports programs.',
                'image_url' => 'https://images.unsplash.com/photo-1614632537239-d3537b5d6e2e?w=800',
                'moq' => 2,
                'lead_time_min_days' => 7,
                'lead_time_max_days' => 10,
                'stock_quantity' => 680,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 89.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 2, 'price' => 89.99],
                    ['min_quantity' => 3, 'max_quantity' => null, 'price' => 78.50],
                ],
            ],
            // Office Supplies
            [
                'sku' => 'PPO-A4P-007',
                'category_slug' => 'paper',
                'name' => 'A4 Copy Paper - 80gsm (Case of 5 Reams)',
                'description' => 'Bright white 80gsm A4 copy paper suitable for all laser and inkjet printers. 500 sheets per ream.',
                'image_url' => 'https://images.unsplash.com/photo-1568667256549-094345857637?w=800',
                'moq' => 10,
                'lead_time_min_days' => 2,
                'lead_time_max_days' => 3,
                'stock_quantity' => 8000,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 22.50,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 22.50],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 19.90],
                    ['min_quantity' => 50, 'max_quantity' => null, 'price' => 17.50],
                ],
            ],
            [
                'sku' => 'PPO-ORG-008',
                'category_slug' => 'organization',
                'name' => 'Desktop Organizer Set - 5 Piece',
                'description' => 'Professional bamboo desktop organizer set including file holder, pen cup, business card holder, and two trays.',
                'image_url' => 'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800',
                'moq' => 5,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 8,
                'stock_quantity' => 320,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 38.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 38.00],
                    ['min_quantity' => 10, 'max_quantity' => null, 'price' => 32.50],
                ],
            ],
            // Classroom Materials
            [
                'sku' => 'PPO-TAI-009',
                'category_slug' => 'teaching-aids',
                'name' => 'Magnetic Whiteboard - 120x90cm',
                'description' => 'Heavy-duty magnetic dry-erase whiteboard with aluminum frame. Perfect for classrooms, conference rooms, and home offices.',
                'image_url' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=800',
                'moq' => 1,
                'lead_time_min_days' => 7,
                'lead_time_max_days' => 12,
                'stock_quantity' => 150,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 89.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 4, 'price' => 89.00],
                    ['min_quantity' => 5, 'max_quantity' => null, 'price' => 74.00],
                ],
            ],
            [
                'sku' => 'PPO-SSK-010',
                'category_slug' => 'student-supplies',
                'name' => 'Student Backpack - Heavy Duty (Pack of 10)',
                'description' => 'Durable polyester school backpacks with laptop compartment, multiple pockets, and ergonomic straps. Available in assorted colors.',
                'image_url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800',
                'moq' => 10,
                'lead_time_min_days' => 10,
                'lead_time_max_days' => 14,
                'stock_quantity' => 500,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 24.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 24.99],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 21.50],
                    ['min_quantity' => 50, 'max_quantity' => null, 'price' => 18.90],
                ],
            ],
            // Art & Crafts
            [
                'sku' => 'PPO-WCP-011',
                'category_slug' => 'drawing',
                'name' => 'Watercolor Pencils - 48 Color Set',
                'description' => 'Professional-grade watercolor pencils with vivid, lightfast pigments. Ideal for fine art, illustration, and adult coloring.',
                'image_url' => 'https://images.unsplash.com/photo-1452860606245-08befc0ff44b?w=800',
                'moq' => 5,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 8,
                'stock_quantity' => 600,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 42.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 42.00],
                    ['min_quantity' => 10, 'max_quantity' => null, 'price' => 35.50],
                ],
            ],
            [
                'sku' => 'PPO-CRP-012',
                'category_slug' => 'paints',
                'name' => 'Craft Paint Bundle - 60 Colors (2oz Bottles)',
                'description' => 'Non-toxic, water-based craft paints in 60 vibrant colors. Perfect for school projects, ceramics, and mixed media art.',
                'image_url' => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?w=800',
                'moq' => 3,
                'lead_time_min_days' => 4,
                'lead_time_max_days' => 7,
                'stock_quantity' => 720,
                'is_verified' => false,
                'is_customizable' => false,
                'base_price' => 55.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 4, 'price' => 55.00],
                    ['min_quantity' => 5, 'max_quantity' => null, 'price' => 47.00],
                ],
            ],
            // Sports Equipment
            [
                'sku' => 'PPO-BSK-013',
                'category_slug' => 'balls',
                'name' => 'Basketballs - Indoor/Outdoor (Pack of 6)',
                'description' => 'Official size 7 composite leather basketballs suitable for indoor courts and outdoor play. Includes pump.',
                'image_url' => 'https://images.unsplash.com/photo-1546519638405-a9f8e8f87302?w=800',
                'moq' => 2,
                'lead_time_min_days' => 7,
                'lead_time_max_days' => 10,
                'stock_quantity' => 450,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 119.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 1, 'price' => 119.99],
                    ['min_quantity' => 2, 'max_quantity' => null, 'price' => 99.00],
                ],
            ],
            [
                'sku' => 'PPO-FIT-014',
                'category_slug' => 'fitness',
                'name' => 'Resistance Band Set - 5 Levels',
                'description' => 'Professional resistance bands in 5 resistance levels. Includes carry bag and exercise guide. Ideal for school PE programs.',
                'image_url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800',
                'moq' => 10,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 8,
                'stock_quantity' => 1800,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 12.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 19, 'price' => 12.99],
                    ['min_quantity' => 20, 'max_quantity' => 99, 'price' => 10.50],
                    ['min_quantity' => 100, 'max_quantity' => null, 'price' => 8.99],
                ],
            ],
            // Event Supplies
            [
                'sku' => 'PPO-EVD-015',
                'category_slug' => 'decorations',
                'name' => 'Event Balloon Bundle - 200 Pack Assorted',
                'description' => 'High-quality latex balloons in 15 vibrant colors. Includes ribbon and tie tool. Perfect for graduation, school fairs, and corporate events.',
                'image_url' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=800',
                'moq' => 5,
                'lead_time_min_days' => 3,
                'lead_time_max_days' => 5,
                'stock_quantity' => 2500,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 18.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 18.99],
                    ['min_quantity' => 10, 'max_quantity' => null, 'price' => 15.50],
                ],
            ],
            [
                'sku' => 'PPO-TWR-016',
                'category_slug' => 'tableware',
                'name' => 'Eco Disposable Tableware Set - 100 Person',
                'description' => 'Compostable plates, cups, and cutlery set for 100 people. Made from sugarcane bagasse and CPLA. Perfect for school events.',
                'image_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800',
                'moq' => 2,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 7,
                'stock_quantity' => 800,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 68.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 4, 'price' => 68.00],
                    ['min_quantity' => 5, 'max_quantity' => null, 'price' => 57.00],
                ],
            ],
            // Technology
            [
                'sku' => 'PPO-TAB-017',
                'category_slug' => 'computers',
                'name' => 'Education Tablet 10" - WiFi (Android)',
                'description' => '10-inch Android tablet designed for education with parental controls, pre-installed learning apps, and durable casing.',
                'image_url' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=800',
                'moq' => 5,
                'lead_time_min_days' => 10,
                'lead_time_max_days' => 15,
                'stock_quantity' => 200,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 129.99,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 129.99],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 109.00],
                    ['min_quantity' => 50, 'max_quantity' => null, 'price' => 94.99],
                ],
            ],
            [
                'sku' => 'PPO-KBD-018',
                'category_slug' => 'accessories',
                'name' => 'USB Keyboard & Mouse Combo (Pack of 10)',
                'description' => 'Wired USB keyboard and optical mouse combo. Quiet keys, plug-and-play. Compatible with Windows, Mac, and Linux.',
                'image_url' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800',
                'moq' => 10,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 8,
                'stock_quantity' => 950,
                'is_verified' => true,
                'is_customizable' => false,
                'base_price' => 22.50,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 19, 'price' => 22.50],
                    ['min_quantity' => 20, 'max_quantity' => 99, 'price' => 18.90],
                    ['min_quantity' => 100, 'max_quantity' => null, 'price' => 15.50],
                ],
            ],
            [
                'sku' => 'PPO-PRJ-019',
                'category_slug' => 'computers',
                'name' => 'Mini LED Projector - 3500 Lumens',
                'description' => 'Portable mini projector with HDMI, USB, and WiFi connectivity. Supports 1080p content, ideal for classroom presentations.',
                'image_url' => 'https://images.unsplash.com/photo-1626379953822-baec19c3accd?w=800',
                'moq' => 1,
                'lead_time_min_days' => 7,
                'lead_time_max_days' => 14,
                'stock_quantity' => 80,
                'is_verified' => false,
                'is_customizable' => false,
                'base_price' => 189.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 2, 'price' => 189.00],
                    ['min_quantity' => 3, 'max_quantity' => null, 'price' => 165.00],
                ],
            ],
            [
                'sku' => 'PPO-NTB-020',
                'category_slug' => 'writing',
                'name' => 'Hardcover Spiral Notebooks - A5 (Pack of 25)',
                'description' => 'Durable A5 hardcover spiral notebooks with 200 ruled pages, pen holder loop, and pocket divider.',
                'image_url' => 'https://images.unsplash.com/photo-1531346878377-a5be20888e57?w=800',
                'moq' => 10,
                'lead_time_min_days' => 5,
                'lead_time_max_days' => 8,
                'stock_quantity' => 3500,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 36.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 36.00],
                    ['min_quantity' => 10, 'max_quantity' => 49, 'price' => 30.00],
                    ['min_quantity' => 50, 'max_quantity' => null, 'price' => 25.50],
                ],
            ],
            [
                'sku' => 'PPO-FLG-021',
                'category_slug' => 'decorations',
                'name' => 'Custom School Banner - Full Color Print',
                'description' => 'Heavy-duty vinyl banner with full-color printing. Includes grommets for easy hanging. Custom sizes available on request.',
                'image_url' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800',
                'moq' => 5,
                'lead_time_min_days' => 7,
                'lead_time_max_days' => 14,
                'stock_quantity' => 250,
                'is_verified' => true,
                'is_customizable' => true,
                'base_price' => 28.00,
                'tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 28.00],
                    ['min_quantity' => 10, 'max_quantity' => null, 'price' => 22.50],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $productData['sku']],
                [
                    'category_id' => $categories[$productData['category_slug']]->id,
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'image_url' => $productData['image_url'],
                    'moq' => $productData['moq'],
                    'lead_time_min_days' => $productData['lead_time_min_days'],
                    'lead_time_max_days' => $productData['lead_time_max_days'],
                    'stock_quantity' => $productData['stock_quantity'],
                    'is_verified' => $productData['is_verified'],
                    'is_customizable' => $productData['is_customizable'],
                    'base_price' => $productData['base_price'],
                ],
            );

            $product->priceTiers()->delete();
            $product->priceTiers()->createMany($productData['tiers']);
        }

        $markers = Product::query()->where('sku', 'PPO-WBM-001')->first();

        if ($markers) {
            $customer->procurementListItems()->updateOrCreate(
                ['product_id' => $markers->id],
                ['quantity' => 50, 'unit_price' => $markers->priceForQuantity(50)],
            );
        }

        $customer->sourcingRequests()->updateOrCreate(
            ['reference' => 'REQ-'.now()->format('Y').'-001'],
            [
                'type' => 'custom',
                'status' => 'quoted',
                'title' => 'Custom branded notebooks for new student kits',
                'details' => 'Need soft-touch covers, 200 pages, and school branding on the front.',
                'quantity' => 500,
                'budget_text' => '$2,000 - $3,000',
                'delivery_date' => now()->addWeeks(3)->toDateString(),
                'notes' => 'Please prioritize eco-friendly paper stock.',
            ],
        );
    }
}
