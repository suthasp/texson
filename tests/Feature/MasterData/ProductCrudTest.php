<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\Uom;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;

function productPayload(array $overrides = []): array
{
    return [
        'sku' => 'UPS-TEST-0001',
        'name_th' => 'เครื่องสำรองไฟทดสอบ 10kVA',
        'name_en' => 'Test UPS 10kVA',
        'category_id' => Category::factory()->create()->id,
        'brand_id' => Brand::factory()->create()->id,
        'model' => 'TST-10K',
        'part_number' => 'PN-TST-10K',
        'uom' => Uom::Set->value,
        'cost_price' => '100000.00',
        'list_price' => '135000.00',
        'dealer_price' => '122000.00',
        'project_price' => '115000.00',
        'is_serialized' => '1',
        'track_lot' => '0',
        'min_stock' => '2',
        'reorder_qty' => '4',
        'lead_time_days' => '30',
        'warranty_months' => '24',
        'is_active' => '1',
        ...$overrides,
    ];
}

it('แสดงรายการสินค้าให้ role ที่มีสิทธิ์ดูได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $product = Product::factory()->create(['name_th' => 'แบตเตอรี่ทดสอบ']);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee($product->sku)
        ->assertSee('แบตเตอรี่ทดสอบ');
});

it('สร้างสินค้าใหม่พร้อมสเปกและผู้ขายได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $supplier = Supplier::factory()->create();

    $payload = productPayload([
        'spec' => [
            ['key' => 'kva', 'value' => '10'],
            ['key' => 'phase', 'value' => '3P'],
            ['key' => '', 'value' => 'ทิ้งไว้ว่าง'],
        ],
        'suppliers' => [
            ['supplier_id' => $supplier->id, 'supplier_sku' => 'SUP-SKU-1', 'cost_price' => '98000', 'lead_time_days' => '21', 'is_preferred' => '1'],
        ],
    ]);

    $this->post(route('products.store'), $payload)->assertRedirect();

    $product = Product::where('sku', 'UPS-TEST-0001')->firstOrFail();

    expect($product->name_th)->toBe('เครื่องสำรองไฟทดสอบ 10kVA')
        ->and($product->is_serialized)->toBeTrue()
        ->and($product->uom)->toBe(Uom::Set)
        // แถวที่ไม่มี key ต้องถูกตัดทิ้ง ไม่เก็บลง DB
        ->and($product->spec)->toBe(['kva' => '10', 'phase' => '3P'])
        ->and($product->suppliers)->toHaveCount(1)
        ->and($product->suppliers->first()->pivot->supplier_sku)->toBe('SUP-SKU-1');
});

it('เก็บราคาเป็น decimal 2 ตำแหน่งเสมอ ไม่ปัดเป็น float', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->post(route('products.store'), productPayload([
        'cost_price' => '1234567.89',
        'list_price' => '1999999.99',
    ]))->assertRedirect();

    $product = Product::where('sku', 'UPS-TEST-0001')->firstOrFail();

    expect($product->cost_price)->toBe('1234567.89')
        ->and($product->list_price)->toBe('1999999.99');
});

it('ไม่ยอมให้ SKU ซ้ำ', function (): void {
    actingAsRole(RoleName::Warehouse);
    Product::factory()->create(['sku' => 'UPS-TEST-0001']);

    $this->post(route('products.store'), productPayload())
        ->assertSessionHasErrors('sku');

    expect(Product::where('sku', 'UPS-TEST-0001')->count())->toBe(1);
});

it('แก้ไขสินค้าแล้วเปลี่ยนผู้ขายหลักได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $product = Product::factory()->create();
    $oldSupplier = Supplier::factory()->create();
    $newSupplier = Supplier::factory()->create();
    $product->suppliers()->attach($oldSupplier, ['is_preferred' => true, 'cost_price' => 100]);

    $this->put(route('products.update', $product), productPayload([
        'sku' => $product->sku,
        'name_th' => 'ชื่อใหม่หลังแก้ไข',
        'category_id' => $product->category_id,
        'brand_id' => $product->brand_id,
        'suppliers' => [
            ['supplier_id' => $newSupplier->id, 'cost_price' => '90000', 'lead_time_days' => '10', 'is_preferred' => '1'],
        ],
    ]))->assertRedirect();

    $product->refresh()->load('suppliers');

    expect($product->name_th)->toBe('ชื่อใหม่หลังแก้ไข')
        ->and($product->suppliers)->toHaveCount(1)
        ->and($product->suppliers->first()->id)->toBe($newSupplier->id);
});

it('เก็บผู้ขายหลักได้รายเดียวแม้ติ๊กมาหลายราย', function (): void {
    actingAsRole(RoleName::Warehouse);
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $this->post(route('products.store'), productPayload([
        'suppliers' => [
            ['supplier_id' => $first->id, 'cost_price' => '1', 'lead_time_days' => '1', 'is_preferred' => '1'],
            ['supplier_id' => $second->id, 'cost_price' => '2', 'lead_time_days' => '2', 'is_preferred' => '1'],
        ],
    ]))->assertRedirect();

    $product = Product::where('sku', 'UPS-TEST-0001')->firstOrFail()->load('suppliers');

    expect($product->suppliers->where('pivot.is_preferred', true))->toHaveCount(1);
});

it('ลบสินค้าแบบ soft delete ไม่ได้ลบจริงออกจากฐานข้อมูล', function (): void {
    actingAsRole(RoleName::Warehouse);
    $product = Product::factory()->create();

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('บันทึก activity log ทุกครั้งที่แก้ไขสินค้า พร้อมค่าก่อนและหลัง', function (): void {
    actingAsRole(RoleName::Warehouse);
    $product = Product::factory()->create(['list_price' => '1000.00']);

    $product->update(['list_price' => '1500.00']);

    $activity = $product->activities()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['old']['list_price'])->toBe('1000.00')
        ->and($activity->properties['attributes']['list_price'])->toBe('1500.00');
});
