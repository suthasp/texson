<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PermissionName;
use App\Exports\Concerns\ThaiSheet;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * รายงานใบเสนอราคาตามช่วงวันที่ (spec 5)
 *
 * @implements FromQuery<Quotation>
 */
class QuotationReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use ThaiSheet;

    public function __construct(
        private readonly User $user,
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly ?string $status = null,
    ) {}

    public function title(): string
    {
        return __('ใบเสนอราคา');
    }

    /**
     * @return Builder<Quotation>
     */
    public function query(): Builder
    {
        return Quotation::query()
            ->with(['customer:id,code,name_th', 'salesUser:id,name', 'salesOrder:id,quotation_id,so_no'])
            // sales ส่งออกได้เฉพาะใบของตัวเอง — กฎเดียวกับหน้าจอ (spec 8)
            ->visibleTo($this->user)
            ->whereBetween('issue_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($this->status !== null, fn (Builder $q) => $q->where('status', $this->status))
            ->orderBy('issue_date')
            ->orderBy('quote_no')
            ->orderBy('revision');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $headings = [
            __('เลขที่'),
            __('ฉบับแก้ไข'),
            __('วันที่ออก'),
            __('ยืนราคาถึง'),
            __('รหัสลูกค้า'),
            __('ลูกค้า'),
            __('ผู้ขาย'),
            __('สถานะ'),
            __('ถูกแทนที่'),
            __('รวมเป็นเงิน'),
            __('ส่วนลดท้ายบิล'),
            __('ภาษีมูลค่าเพิ่ม'),
            __('ยอดสุทธิ'),
            __('ใบสั่งขาย'),
            __('เหตุผลที่แพ้'),
        ];

        if ($this->canSeeCost()) {
            $headings[] = __('ต้นทุนรวม');
            $headings[] = __('กำไรขั้นต้น');
            $headings[] = __('margin %');
        }

        return $headings;
    }

    /**
     * @param  Quotation  $quotation
     * @return array<int, mixed>
     */
    public function map($quotation): array
    {
        $mapped = [
            $quotation->quote_no,
            $quotation->revision,
            $quotation->issue_date->format('Y-m-d'),
            $quotation->valid_until->format('Y-m-d'),
            $quotation->customer->code,
            $quotation->customer->name_th,
            $quotation->salesUser->name,
            $quotation->status->label(),
            $quotation->superseded_at !== null ? __('ใช่') : '',
            (float) $quotation->subtotal,
            (float) $quotation->discount_amount,
            (float) $quotation->vat_amount,
            (float) $quotation->grand_total,
            $quotation->salesOrder?->so_no ?? '',
            $quotation->lost_reason ?? '',
        ];

        if ($this->canSeeCost()) {
            $mapped[] = (float) $quotation->cost_total;
            $mapped[] = (float) $quotation->marginAmount();
            $mapped[] = (float) $quotation->marginPercent();
        }

        return $mapped;
    }

    private function canSeeCost(): bool
    {
        return $this->user->can(PermissionName::ProductViewCost->value);
    }

    public function filename(): string
    {
        return $this->stampedFilename(sprintf(
            'texson_quotations_%s_%s',
            $this->from->format('Ymd'),
            $this->to->format('Ymd'),
        ));
    }
}
