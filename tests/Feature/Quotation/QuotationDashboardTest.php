<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Quotation;
use Illuminate\Support\Carbon;

it('แดชบอร์ดของฝ่ายขายแสดงใบที่ใกล้หมดอายุใน 7 วัน', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->create([
        'quote_no' => 'QT-202608-0100',
        'valid_until' => Carbon::now()->addDays(3)->toDateString(),
    ]);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->create([
        'quote_no' => 'QT-202608-0200',
        'valid_until' => Carbon::now()->addDays(60)->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('QT-202608-0100')
        ->assertDontSee('QT-202608-0200');
});

it('ใบที่ถูกแทนที่ด้วยฉบับแก้ไขแล้วไม่ขึ้นในรายการใกล้หมดอายุ', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->create([
        'quote_no' => 'QT-202608-0300',
        'valid_until' => Carbon::now()->addDays(2)->toDateString(),
        'superseded_at' => Carbon::now(),
    ]);

    $this->get(route('dashboard'))->assertOk()->assertDontSee('QT-202608-0300');
});

it('ผู้จัดการฝ่ายขายเห็นคิวใบที่รออนุมัติ', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $manager = userWithRole(RoleName::SalesManager);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::PendingApproval)->create([
        'quote_no' => 'QT-202608-0400',
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('QT-202608-0400')
        ->assertSee('ใบเสนอราคารออนุมัติจากคุณ');
});

it('ฝ่ายขายไม่เห็นคิวอนุมัติ เพราะกดอนุมัติเองไม่ได้', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::PendingApproval)->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('ใบเสนอราคารออนุมัติจากคุณ');
});

it('sales ไม่เห็นใบของ sales คนอื่นบนแดชบอร์ด', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($theirs)->status(QuotationStatus::Sent)->create([
        'quote_no' => 'QT-202608-0500',
        'valid_until' => Carbon::now()->addDay()->toDateString(),
    ]);

    $this->actingAs($mine)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('QT-202608-0500');
});

it('คลังสินค้าเปิดแดชบอร์ดได้โดยไม่พังแม้ไม่มีสิทธิ์ดูใบเสนอราคา', function (): void {
    actingAsRole(RoleName::Warehouse);

    Quotation::factory()->status(QuotationStatus::PendingApproval)->create();

    $this->get(route('dashboard'))->assertOk();
});
