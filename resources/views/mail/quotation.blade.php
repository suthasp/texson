@php
    /** @var App\Models\Quotation $quotation */
@endphp

<x-mail::message>
# {{ __('ใบเสนอราคา :no', ['no' => $quotation->displayNo()]) }}

{{ __('เรียน :name', ['name' => $quotation->contact?->name ?? $quotation->customer->name_th]) }}

{{ __('บริษัทขอส่งใบเสนอราคาตามรายละเอียดในไฟล์แนบ') }}

<x-mail::table>
| {{ __('รายการ') }} | {{ __('รายละเอียด') }} |
|:---|:---|
| {{ __('เลขที่') }} | {{ $quotation->displayNo() }} |
| {{ __('วันที่') }} | {{ $quotation->issue_date->format('d/m/Y') }} |
| {{ __('ยืนราคาถึง') }} | {{ $quotation->valid_until->format('d/m/Y') }} |
| {{ __('ยอดสุทธิ') }} | {{ number_format((float) $quotation->grand_total, 2) }} {{ $quotation->currency }} |
</x-mail::table>

@if (filled($note))
{{ $note }}
@endif

{{ __('หากมีข้อสงสัยกรุณาติดต่อ :name โทร :phone', [
    'name' => $quotation->salesUser->name,
    'phone' => $quotation->salesUser->phone ?: '-',
]) }}

{{ __('ขอแสดงความนับถือ') }}<br>
{{ config('app.name') }}
</x-mail::message>
