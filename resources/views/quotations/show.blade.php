@php
    /** @var App\Models\Quotation $quotation */
    use App\Enums\QuotationStatus;
    use App\Support\BahtText;

    $withholding = $quotation->withholding();
    $canSeeCost = auth()->user()->can(App\Enums\PermissionName::ProductViewCost->value);
@endphp

<x-app-layout>
    <x-slot name="title">{{ $quotation->displayNo() }}</x-slot>

    <x-page-header :title="$quotation->displayNo()"
                   :subtitle="__(':customer · ออกเมื่อ :date', [
                       'customer' => $quotation->customer->name_th,
                       'date' => $quotation->issue_date->translatedFormat('d M Y'),
                   ])"
                   :back="route('quotations.index')">
        <x-slot name="actions">
            <x-link-button :href="route('quotations.pdf', $quotation)" variant="secondary" target="_blank" rel="noopener">
                {{ __('PDF ไทย') }}
            </x-link-button>
            <x-link-button :href="route('quotations.pdf', [$quotation, 'lang' => 'en'])" variant="secondary" target="_blank" rel="noopener">
                {{ __('PDF EN') }}
            </x-link-button>

            @can('update', $quotation)
                <x-link-button :href="route('quotations.edit', $quotation)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan

            @can('revise', $quotation)
                <form method="POST" action="{{ route('quotations.revise', $quotation) }}"
                      onsubmit="return confirm(@js(__('สร้างฉบับแก้ไขใหม่จากใบนี้? ใบเดิมจะถูกเก็บไว้เป็นประวัติและแก้ไม่ได้อีก')))">
                    @csrf
                    <x-secondary-button type="submit">{{ __('สร้างฉบับแก้ไข') }}</x-secondary-button>
                </form>
            @endcan

            @can('convertToSalesOrder', $quotation)
                <button type="button" x-data @click="$dispatch('open-modal', 'convert-to-so')"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500">
                    {{ __('สร้างใบสั่งขาย') }}
                </button>
            @endcan
        </x-slot>
    </x-page-header>

    {{-- ── แถบสถานะและการกระทำถัดไป ── --}}
    <x-card class="mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :color="$quotation->status->badgeColor()" class="text-sm">{{ $quotation->status->label() }}</x-badge>

            @if ($quotation->approved_at)
                <x-badge color="green">
                    {{ __('อนุมัติโดย :name เมื่อ :at', [
                        'name' => $quotation->approver?->name ?? '—',
                        'at' => $quotation->approved_at->translatedFormat('d M Y H:i'),
                    ]) }}
                </x-badge>
            @endif

            @if ($quotation->isSuperseded())
                <x-badge color="gray">{{ __('ถูกแทนที่ด้วยฉบับแก้ไขแล้ว') }}</x-badge>
            @endif

            @if ($quotation->salesOrder)
                <a href="{{ route('sales-orders.show', $quotation->salesOrder) }}"
                   class="tabular inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 transition hover:bg-emerald-200">
                    {{ __('แปลงเป็นใบสั่งขาย :no แล้ว', ['no' => $quotation->salesOrder->so_no]) }}
                </a>
            @endif

            @if ($quotation->status === QuotationStatus::Sent && $quotation->isPastValidity())
                <x-badge color="amber">{{ __('เลยวันยืนราคาแล้ว — จะเปลี่ยนเป็นหมดอายุเช้าวันถัดไป') }}</x-badge>
            @endif

            <div class="ms-auto flex flex-wrap items-center gap-2">
                @can('submit', $quotation)
                    <form method="POST" action="{{ route('quotations.submit', $quotation) }}">
                        @csrf
                        <x-secondary-button type="submit">{{ __('ส่งขออนุมัติ') }}</x-secondary-button>
                    </form>
                @endcan

                @can('approve', $quotation)
                    <form method="POST" action="{{ route('quotations.approve', $quotation) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500">
                            {{ __('อนุมัติ') }}
                        </button>
                    </form>

                    <button type="button" x-data @click="$dispatch('open-modal', 'return-quotation')"
                            class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50">
                        {{ __('ตีกลับ') }}
                    </button>
                @endcan

                @can('send', $quotation)
                    <button type="button" x-data @click="$dispatch('open-modal', 'send-quotation')"
                            class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('ส่งให้ลูกค้า') }}
                    </button>
                @endcan

                @can('decide', $quotation)
                    <form method="POST" action="{{ route('quotations.accept', $quotation) }}"
                          onsubmit="return confirm(@js(__('ยืนยันว่าลูกค้าตอบรับใบนี้?')))">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500">
                            {{ __('ลูกค้าตอบรับ') }}
                        </button>
                    </form>

                    <button type="button" x-data @click="$dispatch('open-modal', 'reject-quotation')"
                            class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                        {{ __('ลูกค้าปฏิเสธ') }}
                    </button>
                @endcan

                @can('cancel', $quotation)
                    <button type="button" x-data @click="$dispatch('open-modal', 'cancel-quotation')"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">
                        {{ __('ยกเลิกใบ') }}
                    </button>
                @endcan
            </div>
        </div>

        @if ($approvalReasons !== [])
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-medium text-amber-900">{{ __('ใบนี้เข้าเกณฑ์ต้องอนุมัติก่อนส่ง') }}</p>
                <ul class="mt-1 list-inside list-disc text-sm text-amber-800">
                    @foreach ($approvalReasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- ── รายการ ── --}}
            <x-card :title="__('รายการ')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell w-10">#</th>
                                <th scope="col" class="table-head-cell">{{ __('รหัส / รายละเอียด') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ราคา/หน่วย') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ส่วนลด') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวนเงิน') }}</th>
                                @if ($canSeeCost)
                                    <th scope="col" class="table-head-cell text-end">{{ __('margin') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($quotation->items as $item)
                                <tr class="{{ $item->item_type->isMonetary() ? '' : 'bg-gray-50/60' }}">
                                    <td class="table-cell-base tabular text-gray-400">{{ $item->line_no }}</td>
                                    <td class="table-cell-base">
                                        <div class="flex items-start gap-2">
                                            @unless ($item->item_type === App\Enums\QuotationItemType::Product)
                                                <x-badge color="navy" class="mt-0.5 shrink-0">{{ $item->item_type->label() }}</x-badge>
                                            @endunless
                                            <div class="min-w-0">
                                                @if ($item->sku_snapshot)
                                                    <p class="tabular text-xs font-medium text-navy-900">{{ $item->sku_snapshot }}</p>
                                                @endif
                                                <p class="max-w-md whitespace-pre-line">{{ $item->description }}</p>
                                                @if ($item->lead_time_days !== null)
                                                    <p class="text-xs text-gray-400">{{ __('ส่งของภายใน :days วัน', ['days' => $item->lead_time_days]) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    @if ($item->item_type->isMonetary())
                                        <td class="table-cell-base tabular text-end">{{ number_format((float) $item->qty, 3) }} {{ $item->uom }}</td>
                                        <td class="table-cell-base tabular text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="table-cell-base tabular text-end text-rose-600">
                                            {{ (float) $item->discount_amount > 0 ? '-'.number_format((float) $item->discount_amount, 2) : '—' }}
                                            @if ((float) $item->discount_percent > 0)
                                                <span class="block text-xs text-gray-400">{{ rtrim(rtrim(number_format((float) $item->discount_percent, 2), '0'), '.') }}%</span>
                                            @endif
                                        </td>
                                        <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $item->line_total, 2) }}</td>
                                        @if ($canSeeCost)
                                            <td class="table-cell-base tabular text-end {{ $item->isLowMargin($minMargin) ? 'font-semibold text-rose-600' : 'text-gray-500' }}">
                                                {{ (float) $item->cost_snapshot > 0 ? number_format((float) $item->marginPercent(), 2).'%' : '—' }}
                                            </td>
                                        @endif
                                    @else
                                        <td class="table-cell-base" colspan="{{ $canSeeCost ? 5 : 4 }}"></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- ── สรุปยอด ── --}}
            <x-card :title="__('สรุปยอด')">
                <dl class="ms-auto max-w-md space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ __('รวมเป็นเงิน') }}</dt>
                        <dd class="tabular">{{ number_format((float) $quotation->subtotal, 2) }}</dd>
                    </div>
                    @if ((float) $quotation->discount_amount > 0)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">{{ __('ส่วนลดท้ายบิล') }}</dt>
                            <dd class="tabular text-rose-600">-{{ number_format((float) $quotation->discount_amount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">{{ __('หลังหักส่วนลด') }}</dt>
                            <dd class="tabular">{{ number_format((float) $quotation->after_discount, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ __('ภาษีมูลค่าเพิ่ม :rate%', ['rate' => rtrim(rtrim(number_format((float) $quotation->vat_rate, 2), '0'), '.')]) }}</dt>
                        <dd class="tabular">{{ number_format((float) $quotation->vat_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t-2 border-navy-900 pt-2">
                        <dt class="font-semibold text-navy-900">{{ __('ยอดสุทธิ') }}</dt>
                        <dd class="tabular text-lg font-bold text-navy-900">{{ number_format((float) $quotation->grand_total, 2) }}</dd>
                    </div>
                    <p class="text-end text-xs text-gray-500">({{ BahtText::convert((string) $quotation->grand_total) }})</p>

                    @if ($quotation->hasWithholding())
                        <div class="flex justify-between gap-4 border-t border-dashed border-gray-200 pt-2 text-xs text-gray-500">
                            <dt>{{ __('หัก ณ ที่จ่าย 3% จากฐาน :base (ข้อมูลประกอบ ไม่หักจากยอดสุทธิ)', ['base' => number_format((float) $withholding['base'], 2)]) }}</dt>
                            <dd class="tabular">{{ number_format((float) $withholding['amount'], 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($quotation->terms_and_conditions || $quotation->customer_note)
                <x-card :title="__('เงื่อนไขและหมายเหตุ')">
                    @if ($quotation->terms_and_conditions)
                        <p class="whitespace-pre-line text-sm text-gray-700">{{ $quotation->terms_and_conditions }}</p>
                    @endif
                    @if ($quotation->customer_note)
                        <p class="mt-3 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-700">{{ $quotation->customer_note }}</p>
                    @endif
                </x-card>
            @endif
        </div>

        {{-- ── แผงข้อมูล ── --}}
        <div class="space-y-4">
            <x-card :title="__('ข้อมูลเอกสาร')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        __('ลูกค้า') => $quotation->customer->name_th,
                        __('ผู้ติดต่อ') => $quotation->contact?->name,
                        __('หน้างาน') => $quotation->site?->site_name,
                        __('ระดับราคา') => $quotation->price_tier->label(),
                        __('ยืนราคาถึง') => $quotation->valid_until->translatedFormat('d M Y'),
                        __('เงื่อนไขชำระเงิน') => $quotation->payment_terms,
                        __('เงื่อนไขส่งมอบ') => $quotation->delivery_terms,
                        __('ระยะเวลาส่งของ') => $quotation->lead_time_note,
                        __('ผู้ขาย') => $quotation->salesUser->name,
                        __('ผู้สร้าง') => $quotation->creator?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    @if ($quotation->sent_at)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('ส่งให้ลูกค้าเมื่อ') }}</dt>
                            <dd class="tabular text-gray-900">{{ $quotation->sent_at->translatedFormat('d M Y H:i') }}</dd>
                        </div>
                    @endif

                    @if ($quotation->lost_reason)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('เหตุผล') }}</dt>
                            <dd class="text-gray-900">{{ $quotation->lost_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($canSeeCost)
                <x-card :title="__('margin')">
                    @php $marginLow = (float) $quotation->marginPercent() < (float) $minMargin && (float) $quotation->cost_total > 0; @endphp
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-600">{{ __('ต้นทุนรวม') }}</span>
                            <span class="tabular">{{ number_format((float) $quotation->cost_total, 2) }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-600">{{ __('กำไรขั้นต้น') }}</span>
                            <span class="tabular font-medium">{{ number_format((float) $quotation->marginAmount(), 2) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 rounded-md px-2 py-1.5 {{ $marginLow ? 'bg-rose-50' : 'bg-emerald-50' }}">
                            <span class="text-xs text-gray-600">{{ __('margin') }}</span>
                            <span class="tabular text-lg font-bold {{ $marginLow ? 'text-rose-700' : 'text-emerald-700' }}">
                                {{ number_format((float) $quotation->marginPercent(), 2) }}%
                            </span>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-gray-100 pt-2 text-xs text-gray-500">
                            <span>{{ __('ส่วนลดรวม') }}</span>
                            <span class="tabular">{{ number_format((float) $quotation->totalDiscountPercent(), 2) }}%</span>
                        </div>
                    </div>
                </x-card>
            @endif

            @if ($quotation->parent || $quotation->revisions->isNotEmpty())
                <x-card :title="__('ประวัติการแก้ไข')">
                    <ul class="space-y-2 text-sm">
                        @if ($quotation->parent)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('quotations.show', $quotation->parent) }}" class="tabular text-aqua-600 hover:underline">
                                    {{ $quotation->parent->displayNo() }}
                                </a>
                                <span class="text-xs text-gray-500">{{ __('ฉบับก่อนหน้า') }}</span>
                            </li>
                        @endif
                        @foreach ($quotation->revisions as $revision)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('quotations.show', $revision) }}" class="tabular text-aqua-600 hover:underline">
                                    {{ $revision->displayNo() }}
                                </a>
                                <x-badge :color="$revision->status->badgeColor()">{{ $revision->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($quotation->internal_note)
                <x-card :title="__('บันทึกภายใน')">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $quotation->internal_note }}</p>
                </x-card>
            @endif
        </div>
    </div>

    {{-- ── Modal: ส่งให้ลูกค้า ── --}}
    @can('send', $quotation)
        <x-modal name="send-quotation" focusable>
            <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="space-y-4 p-6"
                  x-data="{ byEmail: true }">
                @csrf
                <h2 class="text-base font-semibold text-navy-900">{{ __('ส่งใบ :no ให้ลูกค้า', ['no' => $quotation->displayNo()]) }}</h2>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="send_email" value="1" x-model="byEmail"
                           class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                    {{ __('ส่งอีเมลพร้อมไฟล์ PDF แนบ') }}
                </label>

                <div x-show="byEmail" x-cloak class="space-y-3">
                    <x-form.input name="email" type="email" :label="__('อีเมลผู้รับ')"
                                  :value="$quotation->contact?->email ?? $quotation->customer->email" />

                    <x-form.select name="locale" :label="__('ภาษาของไฟล์แนบ')"
                                   :options="['th' => __('ไทย'), 'en' => __('อังกฤษ')]" selected="th" />

                    <x-form.textarea name="note" :label="__('ข้อความเพิ่มเติมในอีเมล')" rows="3" />
                </div>

                <p x-show="! byEmail" x-cloak class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ __('ระบบจะบันทึกว่าส่งแล้วโดยไม่ส่งอีเมล — ใช้กรณีส่งทาง LINE หรือยื่นเอกสารตัวจริง') }}
                </p>

                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('ยกเลิก') }}</x-secondary-button>
                    <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                        {{ __('ส่ง') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endcan

    {{-- ── Modal: สร้างใบสั่งขาย (spec 4.3) ── --}}
    @can('convertToSalesOrder', $quotation)
        <x-modal name="convert-to-so" focusable>
            <form method="POST" action="{{ route('quotations.convert', $quotation) }}" enctype="multipart/form-data" class="space-y-4 p-6">
                @csrf
                <h2 class="text-base font-semibold text-navy-900">{{ __('สร้างใบสั่งขายจาก :no', ['no' => $quotation->displayNo()]) }}</h2>

                <p class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ __('รายการและราคาทั้งหมดยกมาจากใบนี้ · ใบเสนอราคาหนึ่งใบสร้างใบสั่งขายได้ครั้งเดียว') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="warehouse_id" :label="__('คลังที่จ่ายของ')"
                                   :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all()"
                                   :selected="$warehouses->firstWhere('is_default', true)?->id"
                                   :help="__('คลังที่จะจองของเมื่อยืนยันใบ')" />

                    <x-form.input name="required_date" type="date" :label="__('วันที่ต้องการรับของ')" />

                    <x-form.input name="customer_po_no" :label="__('เลขที่ใบสั่งซื้อของลูกค้า')" />

                    <div class="space-y-1">
                        <label for="customer_po_file" class="block text-sm font-medium text-gray-700">{{ __('ไฟล์ใบสั่งซื้อ') }}</label>
                        <input id="customer_po_file" name="customer_po_file" type="file" accept="application/pdf,image/png,image/jpeg"
                               class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                        <x-input-error :messages="$errors->get('customer_po_file')" />
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('ยกเลิก') }}</x-secondary-button>
                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500">
                        {{ __('สร้างใบสั่งขาย') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endcan

    {{-- ── Modal: การกระทำที่ต้องระบุเหตุผล ── --}}
    @foreach ([
        'reject-quotation' => ['route' => 'quotations.reject', 'title' => __('ลูกค้าปฏิเสธใบนี้'), 'help' => __('เหตุผลที่แพ้ดีล เช่น ราคาสูงกว่าคู่แข่ง'), 'can' => 'decide'],
        'cancel-quotation' => ['route' => 'quotations.cancel', 'title' => __('ยกเลิกใบนี้'), 'help' => __('เหตุผลในการยกเลิก'), 'can' => 'cancel'],
        'return-quotation' => ['route' => 'quotations.return', 'title' => __('ตีกลับให้ฝ่ายขายแก้'), 'help' => __('สิ่งที่ต้องแก้ก่อนอนุมัติ'), 'can' => 'approve'],
    ] as $modalName => $config)
        @can($config['can'], $quotation)
            <x-modal :name="$modalName" focusable>
                <form method="POST" action="{{ route($config['route'], $quotation) }}" class="space-y-4 p-6">
                    @csrf
                    <h2 class="text-base font-semibold text-navy-900">{{ $config['title'] }}</h2>
                    <x-form.textarea name="reason" :label="__('เหตุผล')" rows="3" :help="$config['help']" />
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('ยกเลิก') }}</x-secondary-button>
                        <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                            {{ __('ยืนยัน') }}
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endforeach
</x-app-layout>
