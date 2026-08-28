<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PermissionName;
use App\Exports\Concerns\ThaiSheet;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * รายการสินค้าพร้อมยอดคงเหลือแยกตามคลัง (spec 5)
 *
 * หนึ่งแถวต่อหนึ่งคู่ (สินค้า, คลัง) — สินค้าที่ยังไม่เคยมีของในคลังไหนเลย
 * จะมีแถวเดียวที่ยอดเป็นศูนย์ เพื่อให้ไฟล์ครบทุก SKU ไม่ใช่เฉพาะที่มีของ
 *
 * @implements FromCollection<int, array<string, mixed>>
 */
class ProductStockExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use ThaiSheet;

    public function __construct(
        private readonly User $user,
        private readonly ?int $warehouseId = null,
        private readonly bool $lowStockOnly = false,
    ) {}

    public function title(): string
    {
        return __('สินค้าและสต็อก');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $products = Product::query()
            ->with(['category:id,name_th', 'brand:id,name', 'stockLevels.warehouse:id,code,name'])
            ->orderBy('sku')
            ->get();

        return $products->flatMap(function (Product $product): Collection {
            $levels = $product->stockLevels
                ->when(
                    $this->warehouseId !== null,
                    fn (Collection $rows): Collection => $rows->where('warehouse_id', $this->warehouseId),
                );

            // สินค้าที่ยังไม่มีแถวยอดคงเหลือเลย ก็ต้องอยู่ในไฟล์ด้วย
            if ($levels->isEmpty()) {
                return $this->lowStockOnly || $this->warehouseId !== null
                    ? collect()
                    : collect([$this->row($product, null)]);
            }

            return $levels
                ->filter(fn (StockLevel $level): bool => ! $this->lowStockOnly || $level->isBelowMinimum())
                ->map(fn (StockLevel $level): array => $this->row($product, $level))
                ->values();
        })->values();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $headings = [
            __('SKU'),
            __('ชื่อสินค้า'),
            __('รุ่น'),
            __('หมวดหมู่'),
            __('ยี่ห้อ'),
            __('คลัง'),
            __('หน่วย'),
            __('คงเหลือ'),
            __('จอง'),
            __('พร้อมขาย'),
            __('สต็อกขั้นต่ำ'),
            __('ต่ำกว่าขั้นต่ำ'),
            __('ราคามาตรฐาน'),
        ];

        // ราคาทุนและมูลค่าสต็อกเป็นข้อมูลอ่อนไหว — ใส่เฉพาะคนที่มีสิทธิ์เห็น (ADR-012)
        if ($this->canSeeCost()) {
            $headings[] = __('ราคาทุน');
            $headings[] = __('มูลค่าตามทุน');
        }

        return $headings;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $mapped = [
            $row['sku'],
            $row['name'],
            $row['model'],
            $row['category'],
            $row['brand'],
            $row['warehouse'],
            $row['uom'],
            (float) $row['on_hand'],
            (float) $row['reserved'],
            (float) $row['available'],
            (float) $row['min_stock'],
            $row['is_low'] ? __('ใช่') : '',
            (float) $row['list_price'],
        ];

        if ($this->canSeeCost()) {
            $mapped[] = (float) $row['cost_price'];
            $mapped[] = (float) $row['stock_value'];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Product $product, ?StockLevel $level): array
    {
        $onHand = (string) ($level->qty_on_hand ?? '0.000');
        $reserved = (string) ($level->qty_reserved ?? '0.000');
        $available = $level?->qty_available ?? '0.000';

        return [
            'sku' => $product->sku,
            'name' => $product->name_th,
            'model' => $product->model,
            'category' => $product->category?->name_th,
            'brand' => $product->brand?->name,
            'warehouse' => $level?->warehouse?->code ?? '—',
            'uom' => $product->uom->label(),
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'min_stock' => (string) $product->min_stock,
            'is_low' => bccomp($available, (string) $product->min_stock, 3) < 0,
            'list_price' => (string) $product->list_price,
            'cost_price' => (string) $product->cost_price,
            'stock_value' => Money::multiply($onHand, (string) $product->cost_price),
        ];
    }

    private function canSeeCost(): bool
    {
        return $this->user->can(PermissionName::ProductViewCost->value);
    }

    public function filename(): string
    {
        return $this->stampedFilename('texson_products_stock');
    }
}
