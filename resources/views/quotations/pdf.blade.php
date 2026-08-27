@php
    /**
     * ใบเสนอราคา A4 สำหรับ dompdf (spec 5)
     *
     * ข้อจำกัดที่ทำให้เทมเพลตนี้หน้าตาไม่เหมือนหน้าเว็บ
     *  - dompdf ไม่รองรับ flex/grid → จัดวางด้วยตารางล้วน
     *  - ฟอนต์ sarabun ถูก register จาก QuotationPdfService ไม่ใช่ @font-face
     *  - เลขหน้าใช้ counter(page)/counter(pages) ใน element ที่ position: fixed
     *
     * @var App\Models\Quotation $quotation
     * @var array<string, string|null> $company
     */
    use App\Enums\QuotationItemType;

    $customer = $quotation->customer;
    $money = static fn ($value): string => number_format((float) $value, 2);
    $qty = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->displayNo() }}</title>
    <style>
        @page { margin: 26mm 14mm 22mm 14mm; }

        * { font-family: sarabun, sans-serif; }

        body {
            margin: 0;
            font-size: 10.5pt;
            color: #1f2937;
            line-height: 1.45;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .muted { color: #6b7280; }
        .small { font-size: 9pt; }
        .xsmall { font-size: 8pt; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .navy { color: #1B2A4A; }
        .nowrap { white-space: nowrap; }

        /* ── หัวกระดาษ: ซ้ำทุกหน้าโดย position: fixed ── */
        .page-head {
            position: fixed;
            top: -22mm; left: 0; right: 0;
            height: 20mm;
        }

        .page-foot {
            position: fixed;
            bottom: -16mm; left: 0; right: 0;
            height: 12mm;
            font-size: 8pt;
            color: #9ca3af;
        }

        .page-number:after {
            content: counter(page) "/" counter(pages);
        }

        .doc-title {
            font-size: 17pt;
            font-weight: bold;
            color: #1B2A4A;
            letter-spacing: 0.5pt;
        }

        .meta-box {
            border: 0.6pt solid #cbd5e1;
            border-radius: 2pt;
            padding: 4pt 6pt;
        }

        .party {
            border: 0.6pt solid #cbd5e1;
            border-radius: 2pt;
            padding: 6pt 8pt;
        }

        /* ── ตารางรายการ ── */
        .items th {
            background: #1B2A4A;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            padding: 5pt 4pt;
            text-align: left;
        }

        .items td {
            border-bottom: 0.5pt solid #e5e7eb;
            padding: 5pt 4pt;
            font-size: 9.5pt;
        }

        .items tr.note-row td {
            background: #f8fafc;
            color: #475569;
            font-style: italic;
        }

        .totals td {
            padding: 3pt 4pt;
            font-size: 10pt;
        }

        .totals .grand td {
            border-top: 1pt solid #1B2A4A;
            border-bottom: 2.5pt double #1B2A4A;
            font-size: 11.5pt;
            font-weight: bold;
            color: #1B2A4A;
        }

        .words {
            background: #f1f5f9;
            border-radius: 2pt;
            padding: 5pt 8pt;
        }

        .terms {
            border: 0.6pt solid #e5e7eb;
            border-radius: 2pt;
            padding: 6pt 8pt;
            font-size: 9pt;
        }

        .sign-box {
            border-top: 0.6pt dotted #94a3b8;
            padding-top: 4pt;
            font-size: 9pt;
        }

        .watermark {
            position: fixed;
            top: 90mm; left: 0; right: 0;
            text-align: center;
            font-size: 52pt;
            font-weight: bold;
            color: #e5e7eb;
            transform: rotate(-20deg);
        }
    </style>
</head>
<body>

{{-- ── หัวกระดาษที่ซ้ำทุกหน้า ── --}}
<div class="page-head">
    <table>
        <tr>
            <td style="width: 62%;">
                <span class="bold navy small">{{ $company['name'] }}</span>
                <span class="xsmall muted"> · {{ __('เลขประจำตัวผู้เสียภาษี') }} {{ $company['tax_id'] ?: '-' }}</span>
            </td>
            <td class="right small muted" style="width: 38%;">
                {{ __('ใบเสนอราคา') }} {{ $quotation->displayNo() }}
            </td>
        </tr>
    </table>
    <div style="border-bottom: 0.8pt solid #29B6D8; margin-top: 2pt;"></div>
</div>

<div class="page-foot">
    <table>
        <tr>
            <td>{{ $company['name'] }}@if ($company['phone']) · {{ __('โทร') }} {{ $company['phone'] }}@endif</td>
            <td class="right page-number">{{ __('หน้า') }} </td>
        </tr>
    </table>
</div>

@if ($quotation->status === App\Enums\QuotationStatus::Draft)
    <div class="watermark">{{ __('ฉบับร่าง') }}</div>
@elseif ($quotation->isSuperseded())
    <div class="watermark">{{ __('ถูกแทนที่แล้ว') }}</div>
@elseif ($quotation->status === App\Enums\QuotationStatus::Cancelled)
    <div class="watermark">{{ __('ยกเลิกแล้ว') }}</div>
@endif

{{-- ── ส่วนหัวเอกสาร ── --}}
<table style="margin-bottom: 8pt;">
    <tr>
        <td style="width: 58%;">
            @if ($company['logo'])
                <img src="{{ $company['logo'] }}" alt="" style="max-height: 46px; margin-bottom: 4pt;">
            @endif
            <div class="bold navy" style="font-size: 12.5pt;">{{ $company['name'] }}</div>
            @if ($company['address'])
                <div class="small muted" style="white-space: pre-line;">{{ $company['address'] }}</div>
            @endif
            <div class="small muted">
                @if ($company['phone']){{ __('โทร') }} {{ $company['phone'] }}@endif
                @if ($company['email']) · {{ $company['email'] }}@endif
                @if ($company['website']) · {{ $company['website'] }}@endif
            </div>
            <div class="small muted">
                {{ __('เลขประจำตัวผู้เสียภาษี') }} {{ $company['tax_id'] ?: '-' }}
                @if ($company['branch_code'])
                    ({{ $company['branch_code'] === '00000' ? __('สำนักงานใหญ่') : __('สาขา :code', ['code' => $company['branch_code']]) }})
                @endif
            </div>
        </td>

        <td style="width: 42%;" class="right">
            <div class="doc-title">{{ __('ใบเสนอราคา') }}</div>
            <div class="small muted" style="margin-bottom: 5pt;">{{ $isThai ? 'QUOTATION' : 'ใบเสนอราคา' }}</div>

            <table class="meta-box small">
                <tr>
                    <td class="muted">{{ __('เลขที่') }}</td>
                    <td class="right bold">{{ $quotation->displayNo() }}</td>
                </tr>
                <tr>
                    <td class="muted">{{ __('วันที่') }}</td>
                    <td class="right">{{ $quotation->issue_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="muted">{{ __('ยืนราคาถึง') }}</td>
                    <td class="right">{{ $quotation->valid_until->format('d/m/Y') }}</td>
                </tr>
                @if ($quotation->revision > 0)
                    <tr>
                        <td class="muted">{{ __('ฉบับแก้ไขครั้งที่') }}</td>
                        <td class="right">{{ $quotation->revision }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- ── ลูกค้า ── --}}
<table style="margin-bottom: 8pt;">
    <tr>
        <td style="width: 60%; padding-right: 4pt;">
            <div class="party">
                <div class="xsmall muted">{{ __('เรียน') }}</div>
                <div class="bold">{{ $customer->name_th }}</div>
                @if ($customer->name_en && ! $isThai)
                    <div class="small muted">{{ $customer->name_en }}</div>
                @endif
                <div class="small">{{ $customer->fullAddress() ?: '-' }}</div>
                <div class="small muted">
                    {{ __('เลขประจำตัวผู้เสียภาษี') }} {{ $customer->tax_id ?: '-' }} ({{ $customer->branchLabel() }})
                </div>
                @if ($quotation->contact)
                    <div class="small">
                        {{ __('ผู้ติดต่อ') }}: {{ $quotation->contact->name }}
                        @if ($quotation->contact->phone) · {{ $quotation->contact->phone }}@endif
                    </div>
                @endif
                @if ($quotation->site)
                    <div class="small">{{ __('หน้างาน') }}: {{ $quotation->site->site_name }}</div>
                @endif
            </div>
        </td>

        <td style="width: 40%; padding-left: 4pt;">
            <div class="party">
                <table class="small">
                    <tr>
                        <td class="muted" style="width: 44%;">{{ __('เงื่อนไขชำระเงิน') }}</td>
                        <td>{{ $quotation->payment_terms ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('เงื่อนไขส่งมอบ') }}</td>
                        <td>{{ $quotation->delivery_terms ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('ระยะเวลาส่งของ') }}</td>
                        <td>{{ $quotation->lead_time_note ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('ผู้เสนอราคา') }}</td>
                        <td>{{ $quotation->salesUser->name }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── รายการ ── --}}
<table class="items">
    <thead>
        <tr>
            <th style="width: 5%;" class="center">{{ __('ลำดับ') }}</th>
            <th style="width: 15%;">{{ __('รหัส') }}</th>
            <th style="width: 34%;">{{ __('รายละเอียด') }}</th>
            <th style="width: 8%;" class="right">{{ __('จำนวน') }}</th>
            <th style="width: 8%;">{{ __('หน่วย') }}</th>
            <th style="width: 12%;" class="right">{{ __('ราคา/หน่วย') }}</th>
            <th style="width: 8%;" class="right">{{ __('ส่วนลด') }}</th>
            <th style="width: 12%;" class="right">{{ __('จำนวนเงิน') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quotation->items as $item)
            @if ($item->item_type === QuotationItemType::Note)
                <tr class="note-row">
                    <td class="center muted">{{ $item->line_no }}</td>
                    <td colspan="7">{{ $item->description }}</td>
                </tr>
            @else
                <tr>
                    <td class="center muted">{{ $item->line_no }}</td>
                    <td class="nowrap">{{ $item->sku_snapshot ?: '-' }}</td>
                    <td>
                        {{ $item->description }}
                        @if ($item->lead_time_days !== null)
                            <div class="xsmall muted">{{ __('ส่งของภายใน :days วัน', ['days' => $item->lead_time_days]) }}</div>
                        @endif
                    </td>
                    <td class="right">{{ $qty($item->qty) }}</td>
                    <td>{{ $item->uom }}</td>
                    <td class="right">{{ $money($item->unit_price) }}</td>
                    <td class="right">{{ (float) $item->discount_amount > 0 ? $money($item->discount_amount) : '-' }}</td>
                    <td class="right bold">{{ $money($item->line_total) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

{{-- ── สรุปยอด ── --}}
<table style="margin-top: 8pt;">
    <tr>
        <td style="width: 55%; padding-right: 6pt;">
            <div class="words small">
                <span class="muted">{{ __('จำนวนเงินรวมทั้งสิ้น') }}</span><br>
                <span class="bold navy" style="font-size: 10.5pt;">
                    @if ($amountInWords)
                        ({{ $amountInWords }})
                    @else
                        {{ $money($quotation->grand_total) }} {{ $quotation->currency }}
                    @endif
                </span>
            </div>

            {{-- หัก ณ ที่จ่าย 3% แสดงเป็นข้อมูลประกอบเท่านั้น ไม่หักจาก grand_total (spec 4.2) --}}
            @if ((float) $withholding['base'] > 0)
                <div class="xsmall muted" style="margin-top: 4pt;">
                    {{ __('หมายเหตุ: รายการค่าบริการมูลค่า :base บาท เข้าข่ายหักภาษี ณ ที่จ่าย 3% เป็นเงิน :amount บาท (ไม่ได้หักจากยอดสุทธิข้างต้น)', [
                        'base' => $money($withholding['base']),
                        'amount' => $money($withholding['amount']),
                    ]) }}
                </div>
            @endif
        </td>

        <td style="width: 45%;">
            <table class="totals">
                <tr>
                    <td class="muted">{{ __('รวมเป็นเงิน') }}</td>
                    <td class="right">{{ $money($quotation->subtotal) }}</td>
                </tr>
                @if ((float) $quotation->discount_amount > 0)
                    <tr>
                        <td class="muted">{{ __('ส่วนลดท้ายบิล') }}</td>
                        <td class="right">-{{ $money($quotation->discount_amount) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('ยอดหลังหักส่วนลด') }}</td>
                        <td class="right">{{ $money($quotation->after_discount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="muted">{{ __('ภาษีมูลค่าเพิ่ม :rate%', ['rate' => rtrim(rtrim(number_format((float) $quotation->vat_rate, 2), '0'), '.')]) }}</td>
                    <td class="right">{{ $money($quotation->vat_amount) }}</td>
                </tr>
                <tr class="grand">
                    <td>{{ __('ยอดสุทธิ') }}</td>
                    <td class="right">{{ $money($quotation->grand_total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── เงื่อนไข ── --}}
@if ($terms || $quotation->customer_note)
    <div class="terms" style="margin-top: 8pt;">
        @if ($terms)
            <div class="bold xsmall" style="margin-bottom: 2pt;">{{ __('เงื่อนไข') }}</div>
            <div style="white-space: pre-line;">{{ $terms }}</div>
        @endif
        @if ($quotation->customer_note)
            <div style="margin-top: 4pt; white-space: pre-line;">{{ $quotation->customer_note }}</div>
        @endif
    </div>
@endif

{{-- ── ลงนามสองฝั่ง ── --}}
<table style="margin-top: 16pt;">
    <tr>
        <td style="width: 50%; padding-right: 12pt;">
            <div style="height: 34pt;"></div>
            <div class="sign-box center">
                <div>{{ __('ผู้สั่งซื้อ / ผู้อนุมัติ') }}</div>
                <div class="xsmall muted" style="margin-top: 10pt;">{{ __('วันที่') }} ______/______/______</div>
            </div>
        </td>
        <td style="width: 50%; padding-left: 12pt;">
            <div class="center" style="height: 34pt;">
                @if ($company['signature'])
                    <img src="{{ $company['signature'] }}" alt="" style="max-height: 32pt;">
                @endif
            </div>
            <div class="sign-box center">
                <div>{{ $company['signer_name'] ?: $quotation->salesUser->name }}</div>
                <div class="xsmall muted">{{ $company['signer_position'] ?: __('ผู้เสนอราคา') }}</div>
                <div class="xsmall muted" style="margin-top: 4pt;">{{ __('วันที่') }} {{ $quotation->issue_date->format('d/m/Y') }}</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
