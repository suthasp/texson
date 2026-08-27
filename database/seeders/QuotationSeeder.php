<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuotationItemType;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * ใบเสนอราคาตัวอย่างสำหรับเครื่องพัฒนา — ครอบคลุมทุกสถานะที่มีบนหน้าจอ
 *
 * ทุกใบสร้างผ่าน QuotationService เหมือนที่ผู้ใช้กดจริง (เหตุผลเดียวกับ ADR-007)
 * ยอดเงินและเลขที่เอกสารจึงถูกต้องตามกฎธุรกิจ ไม่ใช่ตัวเลขที่ยัดลงตารางเอง
 */
class QuotationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $sales = User::role(RoleName::Sales->value)->first();
        $manager = User::role(RoleName::SalesManager->value)->first();
        $customers = Customer::query()->with('contacts')->take(4)->get();
        $products = Product::query()->active()->inRandomOrder()->take(12)->get();

        if ($sales === null || $customers->isEmpty() || $products->count() < 4) {
            return;
        }

        Auth::login($sales);

        $service = app(QuotationService::class);

        // 1. ใบร่างธรรมดา
        $service->createDraft($this->payload($customers[0], $products->slice(0, 3)));

        // 2. ใบที่ส่งให้ลูกค้าแล้ว
        $sent = $service->createDraft($this->payload($customers[1 % $customers->count()], $products->slice(3, 2)));
        $this->send($sent, $sales, $manager);

        // 3. ใบที่ลูกค้าตอบรับ — SalesOrderSeeder แปลงใบเหล่านี้เป็นใบสั่งขายต่อ
        //    ทำสามใบเพื่อให้เห็นใบสั่งขายครบทุกสถานะบนเครื่องพัฒนา
        foreach (range(0, 2) as $index) {
            $accepted = $service->createDraft($this->payload(
                $customers[$index % $customers->count()],
                $products->slice(5 + $index, 2),
            ));

            $this->send($accepted, $sales, $manager);
            $service->accept($accepted);
        }

        // 4. ใบที่เข้าเกณฑ์ต้องอนุมัติ (ส่วนลด 25% เกินเกณฑ์ 15%)
        $needsApproval = $service->createDraft($this->payload(
            $customers[3 % $customers->count()],
            $products->slice(7, 3),
            discountPercent: '25',
        ));
        $service->submit($needsApproval);

        if ($manager !== null) {
            Auth::login($manager);
            $service->approve($needsApproval, $manager);
            Auth::login($sales);
        }

        // 5. ใบที่ถูกแก้เป็นฉบับใหม่ — ใบเดิมถูกประทับ superseded_at
        $revised = $service->createDraft($this->payload($customers[0], $products->slice(10, 2)));
        $this->send($revised, $sales, $manager);
        $service->revise($revised);

        Auth::logout();
    }

    /**
     * ส่งใบให้ลูกค้า โดยพาผ่านการอนุมัติก่อนถ้าใบนั้นเข้าเกณฑ์
     *
     * ราคาสินค้าตัวอย่างสุ่มมา ใบจึงติดเกณฑ์ยอดเกิน 500,000 ได้เป็นปกติ
     * ต้องเดินตามขั้นตอนจริงเหมือนที่ผู้ใช้ทำ ไม่ใช่ข้ามกฎไปตั้งสถานะเอง
     */
    private function send(Quotation $quotation, User $sales, ?User $manager): void
    {
        $service = app(QuotationService::class);

        if ($service->requiresApproval($quotation)) {
            $service->submit($quotation);

            // ไม่มีผู้จัดการในระบบก็อนุมัติไม่ได้ ปล่อยใบค้างอยู่ที่รออนุมัติตามความจริง
            if ($manager === null) {
                return;
            }

            Auth::login($manager);
            $service->approve($quotation, $manager);
            Auth::login($sales);
        }

        $service->send($quotation);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function payload(Customer $customer, $products, string $discountPercent = '0'): array
    {
        $items = $products->values()->map(fn (Product $product, int $index): array => [
            'item_type' => QuotationItemType::Product->value,
            'product_id' => $product->id,
            'description' => $product->displayName(),
            'qty' => (string) ($index + 1),
            'discount_percent' => $discountPercent,
        ])->all();

        // ปิดท้ายด้วยค่าแรงติดตั้ง เพื่อให้เห็นการแสดงหัก ณ ที่จ่าย 3% บนใบจริง
        $items[] = [
            'item_type' => QuotationItemType::Labour->value,
            'description' => 'ค่าแรงติดตั้งและทดสอบระบบ (2 คน x 1 วัน)',
            'qty' => '1',
            'unit_price' => '12000',
            'uom' => 'งาน',
        ];

        return [
            'customer_id' => $customer->id,
            'customer_contact_id' => $customer->contacts->first()?->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'price_tier' => $customer->price_tier->value,
            'vat_rate' => '7.00',
            'discount_amount' => '0',
            'payment_terms' => $customer->payment_terms ?: 'เครดิต 30 วัน นับจากวันที่ส่งมอบ',
            'delivery_terms' => 'ส่งมอบ ณ สถานที่ของลูกค้า (กรุงเทพฯ และปริมณฑล)',
            'lead_time_note' => 'ตามที่ระบุในแต่ละรายการ นับจากวันที่ได้รับใบสั่งซื้อ',
            'items' => $items,
        ];
    }
}
