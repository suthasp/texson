<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seed สินค้าอย่างน้อย 30 SKU ตามที่ spec กำหนด', function (): void {
    expect(Product::count())->toBeGreaterThanOrEqual(30);
});

it('สินค้าที่ seed มาผูกหมวดหมู่ ยี่ห้อ และผู้ขายหลักครบทุกตัว', function (): void {
    expect(Product::whereNull('category_id')->count())->toBe(0)
        ->and(Product::whereNull('brand_id')->count())->toBe(0);

    $withoutPreferredSupplier = Product::query()
        ->whereDoesntHave('suppliers', fn ($q) => $q->where('is_preferred', true))
        ->count();

    expect($withoutPreferredSupplier)->toBe(0);
});

it('ราคาขายทุกระดับสูงกว่าราคาทุนเสมอ', function (): void {
    $badRows = Product::query()
        ->where(function ($q): void {
            $q->whereColumn('list_price', '<=', 'cost_price')
                ->orWhereColumn('dealer_price', '<=', 'cost_price')
                ->orWhereColumn('project_price', '<=', 'cost_price');
        })
        ->pluck('sku');

    expect($badRows)->toBeEmpty();
});

it('ราคาโครงการถูกกว่าราคาตัวแทน และราคาตัวแทนถูกกว่าราคามาตรฐาน', function (): void {
    $badRows = Product::query()
        ->where(function ($q): void {
            $q->whereColumn('project_price', '>', 'dealer_price')
                ->orWhereColumn('dealer_price', '>', 'list_price');
        })
        ->pluck('sku');

    expect($badRows)->toBeEmpty();
});

it('seed หมวดหมู่ครบ 5 กลุ่มหลักตาม spec', function (): void {
    $roots = Category::query()->whereNull('parent_id')->pluck('name_th')->all();

    expect($roots)->toHaveCount(5)
        ->toContain('Power & Backup', 'Cooling', 'Rack & Infra', 'Monitoring & Safety', 'Consumable & Spare')
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBeGreaterThan(15);
});

it('seed คลังสินค้า HQ, VAN และ CONSIGN โดยมีคลังเริ่มต้นเพียงคลังเดียว', function (): void {
    expect(Warehouse::pluck('code')->all())->toContain('HQ', 'VAN', 'CONSIGN')
        ->and(Warehouse::where('is_default', true)->count())->toBe(1)
        ->and(Warehouse::where('is_default', true)->first()->code)->toBe('HQ');
});

it('seed ยี่ห้อและผู้ขายพร้อมใช้งาน', function (): void {
    expect(Brand::count())->toBeGreaterThanOrEqual(15)
        ->and(Supplier::count())->toBeGreaterThanOrEqual(10);
});

it('seed ลูกค้าตัวอย่างพร้อมผู้ติดต่อหลักและหน้างาน', function (): void {
    expect(Customer::count())->toBeGreaterThanOrEqual(5);

    Customer::with(['contacts', 'sites'])->get()->each(function (Customer $customer): void {
        expect($customer->contacts)->not->toBeEmpty()
            ->and($customer->contacts->where('is_primary', true))->toHaveCount(1)
            ->and($customer->sites)->not->toBeEmpty();
    });
});

it('seed ผู้ใช้ครบทุก role และทุกคนมี role อย่างน้อยหนึ่งอัน', function (): void {
    foreach (RoleName::cases() as $role) {
        expect(User::role($role->value)->count())->toBeGreaterThanOrEqual(1);
    }

    expect(User::doesntHave('roles')->count())->toBe(0);
});

it('รัน seeder ซ้ำได้โดยไม่สร้างข้อมูลซ้ำ', function (): void {
    $before = [
        'products' => Product::count(),
        'customers' => Customer::count(),
        'categories' => Category::count(),
        'users' => User::count(),
        'suppliers' => Supplier::count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe($before['products'])
        ->and(Customer::count())->toBe($before['customers'])
        ->and(Category::count())->toBe($before['categories'])
        ->and(User::count())->toBe($before['users'])
        ->and(Supplier::count())->toBe($before['suppliers']);
});
