<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\PriceTier;
use App\Enums\RoleName;
use App\Enums\Uom;
use App\Models\Category;
use App\Models\Product;

// ── Enum ────────────────────────────────────────────────

it('PriceTier ชี้ไปยังคอลัมน์ราคาที่ถูกต้อง', function (): void {
    expect(PriceTier::Standard->priceColumn())->toBe('list_price')
        ->and(PriceTier::Dealer->priceColumn())->toBe('dealer_price')
        ->and(PriceTier::Project->priceColumn())->toBe('project_price');
});

it('มี role ครบ 6 ตัวรวม sales_manager ตาม ADR-003', function (): void {
    expect(RoleName::cases())->toHaveCount(6)
        ->and(RoleName::tryFrom('sales_manager'))->not->toBeNull()
        ->and(RoleName::seeAllDocuments())->toBe(['admin', 'sales_manager']);
});

it('forResource คัดเฉพาะสิทธิ์ของทรัพยากรที่ระบุ', function (): void {
    $productPermissions = PermissionName::forResource('product');

    expect($productPermissions)->toContain('product.viewAny', 'product.viewCost')
        ->and($productPermissions)->not->toContain('customer.viewAny')
        ->and(PermissionName::readOnlyForResource('product'))
        ->toBe(['product.viewAny', 'product.view']);
});

it('Uom แปลงเป็นตัวเลือกสำหรับ dropdown ได้', function (): void {
    expect(Uom::options())->toHaveKeys(['pcs', 'set', 'box', 'roll', 'm'])
        ->and(Uom::from('set')->label())->toBe('ชุด');
});

// ── Model ───────────────────────────────────────────────

it('priceFor คืนราคาตามระดับราคาของลูกค้า', function (): void {
    $product = Product::factory()->make([
        'list_price' => '135000.00',
        'dealer_price' => '122000.00',
        'project_price' => '115000.00',
    ]);

    expect($product->priceFor(PriceTier::Standard))->toBe('135000.00')
        ->and($product->priceFor(PriceTier::Dealer))->toBe('122000.00')
        ->and($product->priceFor(PriceTier::Project))->toBe('115000.00');
});

it('fullName ของหมวดหลักไม่มีเครื่องหมายคั่น', function (): void {
    $root = Category::factory()->create(['name_th' => 'Cooling']);

    expect($root->fullName())->toBe('Cooling');
});

it('กรองตามหมวดแม่แล้วเห็นสินค้าในหมวดย่อยด้วย', function (): void {
    $parent = Category::factory()->create(['name_th' => 'Power & Backup']);
    $childUps = Category::factory()->childOf($parent)->create(['name_th' => 'UPS']);
    $other = Category::factory()->create(['name_th' => 'Cooling']);

    Product::factory()->create(['category_id' => $childUps->id, 'sku' => 'IN-CHILD']);
    Product::factory()->create(['category_id' => $parent->id, 'sku' => 'IN-PARENT']);
    Product::factory()->create(['category_id' => $other->id, 'sku' => 'OUT']);

    $skus = Product::query()->inCategory($parent->id)->pluck('sku')->all();

    expect($skus)->toHaveCount(2)
        ->and($skus)->toContain('IN-CHILD', 'IN-PARENT')
        ->and($skus)->not->toContain('OUT');
});

it('scopeSearch หาเจอทั้งจาก SKU part number ชื่อ และรุ่น', function (string $term): void {
    Product::factory()->create([
        'sku' => 'UPS-APC-SRT10K',
        'part_number' => 'SRT10KXLI',
        'name_th' => 'เครื่องสำรองไฟ APC 10kVA',
        'model' => 'SRT10K',
    ]);
    Product::factory()->create(['sku' => 'OTHER-1', 'part_number' => 'ZZZ', 'name_th' => 'อย่างอื่น', 'model' => 'ZZZ']);

    expect(Product::query()->search($term)->pluck('sku')->all())->toBe(['UPS-APC-SRT10K']);
})->with(['UPS-APC', 'SRT10KXLI', 'สำรองไฟ', 'SRT10K']);

it('scopeSearch ที่ไม่มีคำค้นคืนทุกรายการ', function (): void {
    Product::factory()->count(3)->create();

    expect(Product::query()->search(null)->count())->toBe(3)
        ->and(Product::query()->search('')->count())->toBe(3);
});

it('displayName รวมชื่อกับรุ่นและไม่มีช่องว่างส่วนเกินเมื่อไม่มีรุ่น', function (): void {
    expect(Product::factory()->make(['name_th' => 'แบตเตอรี่', 'model' => 'NP7-12'])->displayName())
        ->toBe('แบตเตอรี่ NP7-12')
        ->and(Product::factory()->make(['name_th' => 'แบตเตอรี่', 'model' => null])->displayName())
        ->toBe('แบตเตอรี่');
});
