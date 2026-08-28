@php
    /**
     * เมนูหลักตาม spec 7 — ซ่อนรายการที่ผู้ใช้ไม่มีสิทธิ์เข้าถึงทั้งกลุ่ม
     * รายการที่เป็นของ Phase 2+ แสดงเป็น disabled เพื่อให้เห็นภาพรวมระบบ
     */
    $sections = [
        [
            'label' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => __('แดชบอร์ด'), 'icon' => 'home', 'can' => null],
            ],
        ],
        [
            'label' => __('ข้อมูลหลัก'),
            'items' => [
                ['route' => 'products.index', 'label' => __('สินค้า / อะไหล่'), 'icon' => 'cube', 'can' => ['viewAny', App\Models\Product::class]],
                ['route' => 'customers.index', 'label' => __('ลูกค้า'), 'icon' => 'users', 'can' => ['viewAny', App\Models\Customer::class]],
                ['route' => 'suppliers.index', 'label' => __('ผู้ขาย'), 'icon' => 'truck', 'can' => ['viewAny', App\Models\Supplier::class]],
            ],
        ],
        [
            'label' => __('งานขาย'),
            'items' => [
                ['route' => 'quotations.index', 'label' => __('ใบเสนอราคา'), 'icon' => 'document', 'can' => ['viewAny', App\Models\Quotation::class]],
                ['route' => 'sales-orders.index', 'label' => __('ใบสั่งขาย'), 'icon' => 'clipboard', 'can' => ['viewAny', App\Models\SalesOrder::class]],
                ['route' => 'deliveries.index', 'label' => __('ใบส่งของ'), 'icon' => 'truck', 'can' => ['viewAny', App\Models\Delivery::class]],
            ],
        ],
        [
            'label' => __('คลังสินค้า'),
            'items' => [
                ['route' => 'stock.index', 'label' => __('สต็อกคงเหลือ'), 'icon' => 'stack', 'can' => ['viewAny', App\Models\StockLevel::class]],
                ['route' => 'goods-receipts.index', 'label' => __('ใบรับสินค้า'), 'icon' => 'truck', 'can' => ['viewAny', App\Models\GoodsReceipt::class]],
                ['route' => 'stock-transfers.index', 'label' => __('ใบโอนคลัง'), 'icon' => 'stack', 'can' => ['viewAny', App\Models\StockTransfer::class]],
                ['route' => 'stock-adjustments.index', 'label' => __('ใบปรับปรุงสต็อก'), 'icon' => 'clipboard', 'can' => ['viewAny', App\Models\StockAdjustment::class]],
                ['route' => 'serial-numbers.index', 'label' => __('ทะเบียน Serial'), 'icon' => 'badge', 'can' => ['viewAny', App\Models\SerialNumber::class]],
                // spatie ลงทะเบียน permission เป็น Gate ให้อยู่แล้ว จึงเช็คด้วยชื่อสิทธิ์ตรง ๆ
                // ไม่ต้องสร้าง Policy ปลอมให้โมเดลที่ไม่มีอยู่จริง
                ['route' => 'reports.index', 'label' => __('รายงาน'), 'icon' => 'chart', 'can' => [App\Enums\PermissionName::ReportViewAny->value]],
            ],
        ],
        [
            'label' => __('ตั้งค่า'),
            'items' => [
                ['route' => 'categories.index', 'label' => __('หมวดหมู่'), 'icon' => 'tag', 'can' => ['viewAny', App\Models\Category::class]],
                ['route' => 'brands.index', 'label' => __('ยี่ห้อ'), 'icon' => 'badge', 'can' => ['viewAny', App\Models\Brand::class]],
                ['route' => 'warehouses.index', 'label' => __('คลังสินค้า'), 'icon' => 'building', 'can' => ['viewAny', App\Models\Warehouse::class]],
                ['route' => 'users.index', 'label' => __('ผู้ใช้งาน'), 'icon' => 'shield', 'can' => ['viewAny', App\Models\User::class]],
                ['route' => 'activity.index', 'label' => __('ประวัติการใช้งาน'), 'icon' => 'clipboard', 'can' => [App\Enums\PermissionName::ActivityViewAny->value]],
                ['route' => 'settings.index', 'label' => __('ตั้งค่าระบบ'), 'icon' => 'cog', 'can' => ['viewAny', App\Models\Setting::class]],
            ],
        ],
    ];
@endphp

<aside class="fixed inset-y-0 start-0 z-40 w-64 -translate-x-full overflow-y-auto bg-navy-900 transition-transform duration-200 ltr:left-0 rtl:right-0 lg:static lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    <div class="flex h-16 items-center gap-3 px-5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-aqua-400 text-lg font-bold text-navy-900">T</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">TEXSON</p>
            <p class="truncate text-[11px] text-navy-200">{{ __('Service & Parts') }}</p>
        </div>
    </div>

    <nav class="space-y-6 px-3 pb-8">
        @foreach ($sections as $section)
            @php
                $visible = collect($section['items'])->filter(function (array $item) {
                    return ! isset($item['can']) || $item['can'] === null || Gate::allows(...$item['can']);
                });
            @endphp

            @continue($visible->isEmpty())

            <div>
                @if ($section['label'])
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-navy-300">{{ $section['label'] }}</p>
                @endif

                <ul class="space-y-1">
                    @foreach ($visible as $item)
                        <li>
                            @if ($item['route'] === null)
                                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm text-navy-400"
                                      title="{{ __('จะเปิดใช้ใน Phase :phase', ['phase' => $item['phase']]) }}">
                                    <x-nav-icon :name="$item['icon']" />
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    <span class="rounded bg-navy-800 px-1.5 py-0.5 text-[10px] font-medium text-navy-300">P{{ $item['phase'] }}</span>
                                </span>
                            @else
                                @php $active = request()->routeIs(Str::before($item['route'], '.') . '.*'); @endphp
                                <a href="{{ route($item['route']) }}"
                                   @if ($active) aria-current="page" @endif
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition
                                          {{ $active ? 'bg-aqua-400 font-semibold text-navy-900' : 'text-navy-100 hover:bg-navy-800 hover:text-white' }}">
                                    <x-nav-icon :name="$item['icon']" />
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
