<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Concerns\ThaiSheet;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * ประวัติการเคลื่อนไหวสต็อก (spec 5)
 *
 * เรียงจากเก่าไปใหม่เสมอ เพื่อให้คอลัมน์ "ยอดหลังรายการ" อ่านต่อกันเป็นลูกโซ่ได้
 * — เป็นวิธีที่ผู้ตรวจสอบใช้พิสูจน์ว่ายอดคงเหลือมาจากไหน
 *
 * @implements FromQuery<StockMovement>
 */
class StockLedgerExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use ThaiSheet;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly ?int $productId = null,
        private readonly ?int $warehouseId = null,
        private readonly ?string $type = null,
    ) {}

    public function title(): string
    {
        return __('ประวัติสต็อก');
    }

    /**
     * @return Builder<StockMovement>
     */
    public function query(): Builder
    {
        return StockMovement::query()
            ->with(['product:id,sku,name_th', 'warehouse:id,code', 'user:id,name'])
            ->whereBetween('moved_at', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->when($this->productId !== null, fn (Builder $q) => $q->where('product_id', $this->productId))
            ->when($this->warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $this->warehouseId))
            ->when($this->type !== null, fn (Builder $q) => $q->where('type', $this->type))
            ->orderBy('moved_at')
            ->orderBy('id');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            __('วันเวลา'),
            __('SKU'),
            __('ชื่อสินค้า'),
            __('คลัง'),
            __('ประเภท'),
            __('จำนวน'),
            __('ยอดหลังรายการ'),
            __('ราคาทุน/หน่วย'),
            __('Lot'),
            __('เอกสารอ้างอิง'),
            __('ผู้ทำรายการ'),
            __('หมายเหตุ'),
        ];
    }

    /**
     * @param  StockMovement  $movement
     * @return array<int, mixed>
     */
    public function map($movement): array
    {
        return [
            $movement->moved_at->format('Y-m-d H:i'),
            $movement->product->sku,
            $movement->product->name_th,
            $movement->warehouse->code,
            $movement->type->label(),
            (float) $movement->qty,
            (float) $movement->balance_after,
            $movement->unit_cost === null ? '' : (float) $movement->unit_cost,
            $movement->lot_no ?? '',
            $movement->ref_type === null ? '' : class_basename($movement->ref_type).' #'.$movement->ref_id,
            $movement->user?->name ?? '',
            $movement->note ?? '',
        ];
    }

    public function filename(): string
    {
        return $this->stampedFilename(sprintf(
            'texson_stock_ledger_%s_%s',
            $this->from->format('Ymd'),
            $this->to->format('Ymd'),
        ));
    }
}
