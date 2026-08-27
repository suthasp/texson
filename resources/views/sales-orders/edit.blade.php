@php
    /** @var App\Models\SalesOrder $order */
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('แก้ไข') }} {{ $order->so_no }}</x-slot>

    <x-page-header :title="$order->so_no"
                   :subtitle="__('แก้ไขหัวใบสั่งขาย · :customer', ['customer' => $order->customer->name_th])"
                   :back="route('sales-orders.show', $order)" />

    <form method="POST" action="{{ route('sales-orders.update', $order) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            {{ __('รายการและราคายกมาจากใบเสนอราคาที่ลูกค้าตอบรับแล้ว จึงแก้ที่นี่ไม่ได้ — ถ้าต้องเปลี่ยนราคาให้สร้างฉบับแก้ไขของใบเสนอราคาแล้วแปลงใหม่') }}
        </div>

        <x-card :title="__('ข้อมูลใบสั่งขาย')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.select name="warehouse_id" :label="__('คลังที่จ่ายของ')"
                               :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all()"
                               :selected="$order->warehouse_id"
                               :help="__('คลังที่จะจองของเมื่อกดยืนยัน')"
                               required />

                <x-form.select name="customer_site_id" :label="__('หน้างาน')"
                               :options="$order->customer->sites->pluck('site_name', 'id')->all()"
                               :selected="$order->customer_site_id"
                               :placeholder="__('— ไม่ระบุ —')" />

                <x-form.input name="required_date" type="date" :label="__('วันที่ต้องการรับของ')"
                              :value="optional($order->required_date)->format('Y-m-d')" />

                <x-form.input name="customer_po_no" :label="__('เลขที่ใบสั่งซื้อของลูกค้า')" :value="$order->customer_po_no" />

                <div class="space-y-1 sm:col-span-2">
                    <label for="customer_po_file" class="block text-sm font-medium text-gray-700">{{ __('ไฟล์ใบสั่งซื้อ') }}</label>

                    @if ($order->customer_po_file)
                        <p class="text-sm">
                            <a href="{{ route('sales-orders.po-file', $order) }}" target="_blank" rel="noopener"
                               class="text-aqua-600 hover:underline">{{ __('ไฟล์ที่แนบไว้แล้ว') }}</a>
                            <span class="text-xs text-gray-500">{{ __('— อัปโหลดใหม่เพื่อแทนที่') }}</span>
                        </p>
                    @endif

                    <input id="customer_po_file" name="customer_po_file" type="file" accept="application/pdf,image/png,image/jpeg"
                           class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                    <p class="text-xs text-gray-500">{{ __('PDF, PNG หรือ JPEG ขนาดไม่เกิน 10 MB — เก็บในโฟลเดอร์ private ไม่เปิดสาธารณะ') }}</p>
                    <x-input-error :messages="$errors->get('customer_po_file')" />
                </div>

                <x-form.input name="payment_terms" :label="__('เงื่อนไขการชำระเงิน')" :value="$order->payment_terms" />
                <x-form.input name="delivery_terms" :label="__('เงื่อนไขการส่งมอบ')" :value="$order->delivery_terms" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card :title="__('หมายเหตุ')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.textarea name="note" :label="__('หมายเหตุถึงลูกค้า')" :value="$order->note" rows="3" />
                <x-form.textarea name="internal_note" :label="__('บันทึกภายใน (ลูกค้าไม่เห็น)')" :value="$order->internal_note" rows="3" />
            </div>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-link-button :href="route('sales-orders.show', $order)" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
            <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                {{ __('บันทึกการแก้ไข') }}
            </button>
        </div>
    </form>
</x-app-layout>
