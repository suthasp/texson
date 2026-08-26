<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;

// ── ผู้ขาย ──────────────────────────────────────────────

it('สร้างและแก้ไขผู้ขายได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->post(route('suppliers.store'), [
        'code' => 'SUP-TEST-1',
        'name' => 'บริษัท ผู้ขายทดสอบ จำกัด',
        'tax_id' => '0105551000011',
        'contact_name' => 'คุณผู้ติดต่อ',
        'phone' => '02-111-2222',
        'email' => 'supplier@example.co.th',
        'lead_time_days' => '30',
        'is_active' => '1',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::where('code', 'SUP-TEST-1')->firstOrFail();
    expect($supplier->lead_time_days)->toBe(30);

    $this->put(route('suppliers.update', $supplier), [
        'code' => 'SUP-TEST-1',
        'name' => 'ชื่อใหม่หลังแก้ไข',
        'lead_time_days' => '45',
        'is_active' => '1',
    ])->assertRedirect();

    expect($supplier->refresh()->name)->toBe('ชื่อใหม่หลังแก้ไข')
        ->and($supplier->lead_time_days)->toBe(45);
});

// ── หมวดหมู่ ────────────────────────────────────────────

it('สร้างหมวดย่อยใต้หมวดแม่ได้และ fullName แสดงทั้งสองระดับ', function (): void {
    actingAsRole(RoleName::Warehouse);
    $parent = Category::factory()->create(['name_th' => 'Power & Backup']);

    $this->post(route('categories.store'), [
        'name_th' => 'UPS',
        'name_en' => 'UPS',
        'parent_id' => $parent->id,
        'sort_order' => '10',
    ])->assertRedirect(route('categories.index'));

    $child = Category::where('name_th', 'UPS')->firstOrFail();

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->fullName())->toBe('Power & Backup / UPS');
});

it('หมวดหมู่ตั้งตัวเองเป็นหมวดแม่ไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $category = Category::factory()->create();

    $this->put(route('categories.update', $category), [
        'name_th' => $category->name_th,
        'parent_id' => $category->id,
        'sort_order' => '0',
    ])->assertSessionHasErrors('parent_id');

    expect($category->refresh()->parent_id)->toBeNull();
});

it('ลบหมวดที่ยังมีสินค้าอยู่ไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->delete(route('categories.destroy', $category))->assertSessionHas('error');

    expect(Category::find($category->id))->not->toBeNull();
});

// ── ยี่ห้อ ──────────────────────────────────────────────

it('ลบยี่ห้อที่ยังมีสินค้าใช้อยู่ไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $brand = Brand::factory()->create();
    Product::factory()->create(['brand_id' => $brand->id]);

    $this->delete(route('brands.destroy', $brand))->assertSessionHas('error');

    expect(Brand::find($brand->id))->not->toBeNull();
});

it('ไม่ยอมให้ชื่อยี่ห้อซ้ำ', function (): void {
    actingAsRole(RoleName::Warehouse);
    Brand::factory()->create(['name' => 'APC']);

    $this->post(route('brands.store'), ['name' => 'APC', 'is_active' => '1'])
        ->assertSessionHasErrors('name');
});

// ── คลังสินค้า ──────────────────────────────────────────

it('ตั้งคลังใหม่เป็นคลังเริ่มต้นแล้วคลังเดิมถูกปลดอัตโนมัติ', function (): void {
    actingAsRole(RoleName::Warehouse);
    $old = Warehouse::factory()->default()->create(['code' => 'HQ']);

    $this->post(route('warehouses.store'), [
        'code' => 'VAN',
        'name' => 'สต็อกรถบริการ',
        'is_default' => '1',
        'is_active' => '1',
    ])->assertRedirect(route('warehouses.index'));

    expect($old->refresh()->is_default)->toBeFalse()
        ->and(Warehouse::where('is_default', true)->count())->toBe(1)
        ->and(Warehouse::where('is_default', true)->first()->code)->toBe('VAN');
});

it('ลบคลังเริ่มต้นไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $warehouse = Warehouse::factory()->default()->create();

    $this->delete(route('warehouses.destroy', $warehouse))->assertSessionHas('error');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});
