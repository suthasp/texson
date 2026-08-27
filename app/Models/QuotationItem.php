<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotationItemType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * บรรทัดในใบเสนอราคา
 *
 * Snapshot rule (spec 3.3): description, sku_snapshot, unit_price และ cost_snapshot
 * ถูกคัดลอกมาตอนออกใบ — แก้ราคาสินค้าภายหลังต้องไม่กระทบใบเก่า
 * ดังนั้นห้ามอ่านราคาจาก relation product ตอนแสดงผลหรือพิมพ์ PDF เด็ดขาด
 */
class QuotationItem extends Model
{
    /** @use HasFactory<\Database\Factories\QuotationItemFactory> */
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'line_no',
        'product_id',
        'item_type',
        'sku_snapshot',
        'description',
        'uom',
        'unit_price',
        'cost_snapshot',
        'qty',
        'discount_percent',
        'discount_amount',
        'line_total',
        'lead_time_days',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'item_type' => QuotationItemType::class,
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'cost_snapshot' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'lead_time_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * สินค้าที่อ้างถึง — ใช้ดูสต็อกและลิงก์ไปหน้าสินค้าเท่านั้น ห้ามใช้ดึงราคา
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * มูลค่าก่อนหักส่วนลดบรรทัด
     */
    public function grossTotal(): string
    {
        return Money::multiply((string) $this->qty, (string) $this->unit_price);
    }

    /**
     * ต้นทุนรวมของบรรทัดนี้ตาม snapshot
     */
    public function costTotal(): string
    {
        return Money::multiply((string) $this->qty, (string) $this->cost_snapshot);
    }

    public function marginAmount(): string
    {
        return Money::sub((string) $this->line_total, $this->costTotal());
    }

    public function marginPercent(): string
    {
        return Money::percentage($this->marginAmount(), (string) $this->line_total);
    }

    /**
     * margin ต่ำกว่าเกณฑ์ → หน้าจอแสดงเป็นสีแดง (spec 4.5)
     */
    public function isLowMargin(string $threshold = '10.00'): bool
    {
        return $this->item_type->isMonetary()
            && ! Money::isZero((string) $this->cost_snapshot)
            && Money::lessThan($this->marginPercent(), $threshold);
    }
}
