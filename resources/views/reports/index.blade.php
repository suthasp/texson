@php
    /**
     * @var array<string, mixed> $sales
     * @var array<string, mixed> $quotations
     * @var array<string, mixed> $valuation
     */
    $money = static fn ($value): string => number_format((float) $value, 2);
    $peak = max(1, (int) $monthly->max(fn (array $row): float => (float) $row['total']));
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('รายงาน') }}</x-slot>

    <x-page-header :title="__('รายงาน')"
                   :subtitle="__('ช่วง :from ถึง :to', [
                       'from' => $from->translatedFormat('d M Y'),
                       'to' => $to->translatedFormat('d M Y'),
                   ])" />

    {{-- ── ตัวกรองช่วงวันที่ ── --}}
    <x-card class="mb-4">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="from" class="mb-1 block text-xs text-gray-600">{{ __('ตั้งแต่วันที่') }}</label>
                <input id="from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="to" class="mb-1 block text-xs text-gray-600">{{ __('ถึงวันที่') }}</label>
                <input id="to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-input-base text-sm">
            </div>

            <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                {{ __('ดูรายงาน') }}
            </button>

            @foreach ([
                __('เดือนนี้') => [now()->startOfMonth(), now()],
                __('เดือนที่แล้ว') => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                __('ปีนี้') => [now()->startOfYear(), now()],
            ] as $label => $range)
                <a href="{{ route('reports.index', ['from' => $range[0]->format('Y-m-d'), 'to' => $range[1]->format('Y-m-d')]) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                    {{ $label }}
                </a>
            @endforeach

            <x-input-error :messages="$errors->get('to')" class="w-full" />
        </form>
    </x-card>

    {{-- ── ตัวเลขหลัก ── --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $tiles = [
                ['label' => __('ยอดขายในช่วงนี้'), 'value' => $money($sales['ordered']), 'note' => __(':count ใบสั่งขาย', ['count' => $sales['order_count']])],
                ['label' => __('ส่งมอบครบแล้ว'), 'value' => $money($sales['delivered']), 'note' => __(':count ใบ', ['count' => $sales['delivered_count']])],
                ['label' => __('มูลค่าใบเสนอราคา'), 'value' => $money($quotations['quoted']), 'note' => __(':count ใบ', ['count' => $quotations['total']])],
                ['label' => __('win rate'), 'value' => number_format((float) $quotations['win_rate'], 2) . '%', 'note' => __('จาก :count ใบที่ลูกค้าตัดสินใจแล้ว', ['count' => $quotations['decided_count']])],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">{{ $tile['label'] }}</p>
                <p class="tabular mt-2 text-2xl font-semibold text-navy-900">{{ $tile['value'] }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        {{-- ── ยอดขายรายเดือน ── --}}
        <x-card :title="__('ยอดขาย 6 เดือนล่าสุด')" class="lg:col-span-2">
            <div class="space-y-2">
                @foreach ($monthly as $row)
                    @php $width = (int) round(((float) $row['total'] / $peak) * 100); @endphp
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-20 shrink-0 text-xs text-gray-500">{{ $row['label'] }}</span>
                        <div class="h-5 flex-1 overflow-hidden rounded bg-gray-100">
                            <div class="h-full rounded bg-aqua-400" style="width: {{ max($width, (float) $row['total'] > 0 ? 2 : 0) }}%"></div>
                        </div>
                        <span class="tabular w-32 shrink-0 text-end font-medium text-navy-900">{{ $money($row['total']) }}</span>
                        <span class="tabular w-12 shrink-0 text-end text-xs text-gray-400">{{ $row['count'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-gray-400">
                {{ __('นับตามวันที่สั่งซื้อ ไม่รวมใบที่ยกเลิก · ตัวเลขขวาสุดคือจำนวนใบ') }}
            </p>
        </x-card>

        {{-- ── ใบเสนอราคาและสต็อก ── --}}
        <div class="space-y-4">
            <x-card :title="__('ใบเสนอราคาในช่วงนี้')">
                <dl class="space-y-2 text-sm">
                    @foreach ([
                        __('ยังเปิดอยู่') => $quotations['open_count'],
                        __('ลูกค้าตอบรับ') => $quotations['accepted_count'],
                        __('ลูกค้าปฏิเสธ') => $quotations['rejected_count'],
                        __('หมดอายุ') => $quotations['expired_count'],
                    ] as $label => $count)
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-600">{{ $label }}</dt>
                            <dd class="tabular font-medium">{{ number_format($count) }}</dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between gap-2 border-t border-gray-100 pt-2">
                        <dt class="text-gray-600">{{ __('มูลค่าที่ยังเปิดอยู่') }}</dt>
                        <dd class="tabular font-medium">{{ $money($quotations['open']) }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card :title="__('มูลค่าสต็อก')">
                <dl class="space-y-2 text-sm">
                    @if ($canSeeCost)
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-600">{{ __('มูลค่าตามราคาทุน') }}</dt>
                            <dd class="tabular font-medium">{{ $money($valuation['value_at_cost']) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-600">{{ __('SKU ที่มีในคลัง') }}</dt>
                        <dd class="tabular">{{ number_format($valuation['sku_count']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-600">{{ __('รายการที่เหลือน้อย') }}</dt>
                        <dd class="tabular {{ $valuation['low_stock_count'] > 0 ? 'font-semibold text-amber-700' : '' }}">
                            {{ number_format($valuation['low_stock_count']) }}
                        </dd>
                    </div>
                </dl>
                @if ($canSeeCost)
                    <p class="mt-3 text-xs text-gray-400">
                        {{ __('คิดจากราคาทุนปัจจุบันของสินค้า ไม่ใช่ต้นทุนถัวเฉลี่ยรายล็อต — ใช้ดูภาพรวม ไม่ใช่มูลค่าทางบัญชี') }}
                    </p>
                @endif
            </x-card>
        </div>
    </div>

    {{-- ── อันดับ ── --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('ลูกค้าที่ซื้อมากที่สุด')" :padded="false">
            @forelse ($topCustomers as $row)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-navy-900">{{ $row['customer']->name_th }}</p>
                        <p class="tabular text-xs text-gray-400">{{ $row['customer']->code }} · {{ __(':count ใบ', ['count' => $row['count']]) }}</p>
                    </div>
                    <span class="tabular shrink-0 font-medium">{{ $money($row['total']) }}</span>
                </div>
            @empty
                <x-empty-state :message="__('ยังไม่มีใบสั่งขายในช่วงนี้')" />
            @endforelse
        </x-card>

        <x-card :title="__('สินค้าที่ขายดีที่สุด')" :padded="false">
            @forelse ($topProducts as $row)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="tabular text-xs font-medium text-navy-900">{{ $row['sku'] ?? '—' }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $row['description'] }}</p>
                    </div>
                    <div class="shrink-0 text-end">
                        <p class="tabular font-medium">{{ $money($row['total']) }}</p>
                        <p class="tabular text-xs text-gray-400">{{ rtrim(rtrim($row['qty'], '0'), '.') }} {{ __('หน่วย') }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state :message="__('ยังไม่มีรายการขายในช่วงนี้')" />
            @endforelse
        </x-card>
    </div>

    {{-- ── ส่งออก Excel (spec 5) ── --}}
    @if ($canExport)
        <x-card :title="__('ส่งออกเป็น Excel')" class="mt-4">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('ไฟล์ที่ได้กรองตามสิทธิ์ของคุณเหมือนที่เห็นบนหน้าจอ — ฝ่ายขายได้เฉพาะเอกสารของตัวเอง') }}
            </p>

            <div class="grid gap-4 md:grid-cols-3">
                {{-- สินค้า + สต็อก --}}
                <form method="GET" action="{{ route('reports.export.products') }}" class="space-y-3 rounded-lg border border-gray-200 p-4">
                    <div>
                        <h3 class="text-sm font-semibold text-navy-900">{{ __('สินค้าและสต็อกคงเหลือ') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('หนึ่งแถวต่อสินค้าหนึ่งรายการต่อคลัง') }}</p>
                    </div>

                    <div>
                        <label for="products_warehouse" class="mb-1 block text-xs text-gray-600">{{ __('คลัง') }}</label>
                        <select id="products_warehouse" name="warehouse_id" class="form-input-base text-sm">
                            <option value="">{{ __('ทุกคลัง') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="low_stock" value="1"
                               class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                        {{ __('เฉพาะที่เหลือน้อย') }}
                    </label>

                    <button type="submit" class="w-full rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                        {{ __('ดาวน์โหลด') }}
                    </button>
                </form>

                {{-- ใบเสนอราคา --}}
                <form method="GET" action="{{ route('reports.export.quotations') }}" class="space-y-3 rounded-lg border border-gray-200 p-4">
                    <div>
                        <h3 class="text-sm font-semibold text-navy-900">{{ __('ใบเสนอราคาตามช่วงวันที่') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('ใช้ช่วงวันที่ด้านบน') }}</p>
                    </div>

                    <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                    <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">

                    <div>
                        <label for="quotation_status" class="mb-1 block text-xs text-gray-600">{{ __('สถานะ') }}</label>
                        <select id="quotation_status" name="status" class="form-input-base text-sm">
                            <option value="">{{ __('ทุกสถานะ') }}</option>
                            @foreach ($quotationStatuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                        {{ __('ดาวน์โหลด') }}
                    </button>
                </form>

                {{-- ledger --}}
                @can('viewLedger', App\Models\StockLevel::class)
                    <form method="GET" action="{{ route('reports.export.ledger') }}" class="space-y-3 rounded-lg border border-gray-200 p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-navy-900">{{ __('ประวัติการเคลื่อนไหวสต็อก') }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500">{{ __('เรียงเก่าไปใหม่ พร้อมยอดหลังรายการ') }}</p>
                        </div>

                        <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                        <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="ledger_warehouse" class="mb-1 block text-xs text-gray-600">{{ __('คลัง') }}</label>
                                <select id="ledger_warehouse" name="warehouse_id" class="form-input-base text-sm">
                                    <option value="">{{ __('ทุกคลัง') }}</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="ledger_type" class="mb-1 block text-xs text-gray-600">{{ __('ประเภท') }}</label>
                                <select id="ledger_type" name="type" class="form-input-base text-sm">
                                    <option value="">{{ __('ทุกประเภท') }}</option>
                                    @foreach ($movementTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="w-full rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                            {{ __('ดาวน์โหลด') }}
                        </button>
                    </form>
                @endcan
            </div>
        </x-card>
    @endif
</x-app-layout>
