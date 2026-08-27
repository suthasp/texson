<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ConvertQuotationToSalesOrder;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use App\Services\SalesOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * ใบสั่งขายและใบส่งของตัวอย่างสำหรับเครื่องพัฒนา
 *
 * ทุกใบเดินผ่าน action และ service จริงเหมือนที่ผู้ใช้กด (เหตุผลเดียวกับ ADR-007)
 * ยอดจอง ยอดส่ง และ ledger จึงสอดคล้องกันทั้งหมด ไม่ใช่ตัวเลขที่ยัดลงตารางเอง
 */
class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $sales = User::role(RoleName::Sales->value)->first();
        $warehouseUser = User::role(RoleName::Warehouse->value)->first();
        $warehouse = Warehouse::query()->where('is_default', true)->first() ?? Warehouse::query()->first();

        // ใบที่ลูกค้าตอบรับแล้วและยังไม่เคยถูกแปลง
        $accepted = Quotation::query()
            ->where('status', QuotationStatus::Accepted)
            ->whereDoesntHave('salesOrder')
            ->with('items')
            ->get();

        if ($sales === null || $warehouse === null || $accepted->isEmpty()) {
            return;
        }

        Auth::login($sales);

        $convert = app(ConvertQuotationToSalesOrder::class);
        $orders = app(SalesOrderService::class);

        // ── 1. ใบที่ยังไม่ยืนยัน — ให้เห็นปุ่ม "ยืนยันและจองของ" บนหน้าจอ ──
        $pending = $convert->handle($accepted->shift(), [
            'warehouse_id' => $warehouse->id,
            'customer_po_no' => 'PO-'.now()->format('Y').'-0101',
            'required_date' => now()->addDays(14)->toDateString(),
        ]);

        // ── 2. ใบที่ยืนยันแล้วและส่งของไปบางส่วน ──
        if ($accepted->isNotEmpty()) {
            $partial = $convert->handle($accepted->shift(), [
                'warehouse_id' => $warehouse->id,
                'customer_po_no' => 'PO-'.now()->format('Y').'-0102',
                'required_date' => now()->addDays(7)->toDateString(),
            ]);

            $orders->confirm($partial, $sales);

            $this->deliverHalf($partial->refresh(), $warehouseUser ?? $sales, $sales);
        }

        // ── 3. ใบที่ยืนยันแล้วรอส่งทั้งใบ ──
        if ($accepted->isNotEmpty()) {
            $orders->confirm($convert->handle($accepted->shift(), [
                'warehouse_id' => $warehouse->id,
                'required_date' => now()->addDays(21)->toDateString(),
            ]), $sales);
        }

        Auth::logout();

        $this->command?->info(sprintf(
            'สร้างใบสั่งขายตัวอย่าง %d ใบ (ใบแรกยังไม่ยืนยัน: %s)',
            SalesOrder::count(),
            $pending->so_no,
        ));
    }

    /**
     * ส่งของครึ่งหนึ่งของบรรทัดแรกที่ยังค้าง เพื่อให้เห็นสถานะ partially_delivered
     */
    private function deliverHalf(SalesOrder $order, User $poster, User $sales): void
    {
        $line = $order->items->first(
            fn (SalesOrderItem $item): bool => $item->isStockable()
                && bccomp((string) $item->qty_reserved, '1', 3) >= 0,
        );

        if ($line === null) {
            return;
        }

        // ส่งครึ่งเดียว ปัดลงให้เหลือของค้างไว้อย่างน้อย 1 ชิ้นเสมอ
        $half = bcdiv((string) $line->qty_reserved, '2', 0);

        if (bccomp($half, '0', 3) <= 0) {
            return;
        }

        $deliveries = app(DeliveryService::class);

        Auth::login($poster);

        $delivery = $deliveries->createDraft($order, [
            'warehouse_id' => $order->warehouse_id,
            'delivery_date' => now()->subDays(2)->toDateString(),
            'receiver_name' => 'ฝ่ายอาคาร ชั้น 3',
            'vehicle_note' => 'รถบริษัท ทะเบียน 1กก-1234',
            'items' => [[
                'sales_order_item_id' => $line->id,
                'qty' => $half,
                // สินค้าที่ติดตาม serial ต้องเลือก serial ให้ครบ ตัวอย่างจึงข้ามไป
                'serial_numbers' => null,
            ]],
        ]);

        // สินค้าที่ติดตาม serial จะ post ไม่ผ่านถ้าไม่ระบุ serial — ปล่อยใบไว้เป็นร่างตามจริง
        if (! $line->product?->is_serialized) {
            $deliveries->post($delivery);
        }

        Auth::login($sales);
    }
}
