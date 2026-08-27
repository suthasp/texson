<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotationItemType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * บรรทัดในใบสั่งขาย
 *
 * qty_ordered  = ที่ลูกค้าสั่ง
 * qty_reserved = ที่จองได้จริงในคลัง — น้อยกว่า qty_ordered ได้ (backorder, spec 4.4)
 * qty_delivered = ที่ส่งออกไปแล้วจริงตามใบส่งของที่ post แล้ว
 *
 * @property-read QuotationItemType $item_type
 */
class SalesOrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderItemFactory> */
    use HasFactory;

    /** ทศนิยมของจำนวน ตรงกับ decimal(15,3) */
    private const SCALE = 3;

    protected $fillable = [
        'sales_order_id',
        'line_no',
        'quotation_item_id',
        'product_id',
        'item_type',
        'sku_snapshot',
        'description',
        'uom',
        'unit_price',
        'cost_snapshot',
        'discount_percent',
        'discount_amount',
        'line_total',
        'qty_ordered',
        'qty_reserved',
        'qty_delivered',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'item_type' => QuotationItemType::class,
            'unit_price' => 'decimal:2',
            'cost_snapshot' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'qty_ordered' => 'decimal:3',
            'qty_reserved' => 'decimal:3',
            'qty_delivered' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<QuotationItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    /**
     * สินค้าที่อ้างถึง — ใช้ตัดสต็อกและดูยอดคงเหลือ ห้ามใช้ดึงราคา (ราคาเป็น snapshot)
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<DeliveryItem, $this> */
    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /**
     * บรรทัดนี้มีของให้ตัดสต็อกหรือไม่
     *
     * ค่าแรง ค่าขนส่ง และข้อความไม่มีของ — ส่งมอบได้แต่ไม่กระทบ ledger
     */
    public function isStockable(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * บรรทัดนี้ต้องนับความคืบหน้าการส่งของหรือไม่ — บรรทัดข้อความไม่ต้อง
     */
    public function isDeliverable(): bool
    {
        return $this->item_type->isMonetary()
            && bccomp((string) $this->qty_ordered, '0', self::SCALE) > 0;
    }

    /**
     * ของที่ยังส่งไม่ครบ
     */
    public function outstandingQty(): string
    {
        $remaining = bcsub((string) $this->qty_ordered, (string) $this->qty_delivered, self::SCALE);

        return bccomp($remaining, '0', self::SCALE) > 0
            ? $remaining
            : bcadd('0', '0', self::SCALE);
    }

    /**
     * ของที่จองไม่ได้เพราะสต็อกไม่พอ — backorder (spec 4.4)
     */
    public function shortageQty(): string
    {
        if (! $this->isStockable()) {
            return bcadd('0', '0', self::SCALE);
        }

        $shortage = bcsub((string) $this->qty_ordered, (string) $this->qty_reserved, self::SCALE);

        return bccomp($shortage, '0', self::SCALE) > 0
            ? $shortage
            : bcadd('0', '0', self::SCALE);
    }

    public function hasOutstanding(): bool
    {
        return $this->isDeliverable() && bccomp($this->outstandingQty(), '0', self::SCALE) > 0;
    }

    public function isFullyDelivered(): bool
    {
        return bccomp((string) $this->qty_delivered, (string) $this->qty_ordered, self::SCALE) >= 0;
    }

    public function costTotal(): string
    {
        return Money::multiply((string) $this->qty_ordered, (string) $this->cost_snapshot);
    }
}
