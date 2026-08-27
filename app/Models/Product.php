<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceTier;
use App\Enums\Uom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sku',
        'name_th',
        'name_en',
        'category_id',
        'brand_id',
        'model',
        'part_number',
        'uom',
        'cost_price',
        'list_price',
        'dealer_price',
        'project_price',
        'is_serialized',
        'track_lot',
        'min_stock',
        'reorder_qty',
        'lead_time_days',
        'warranty_months',
        'spec',
        'image_path',
        'description',
        'is_active',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'uom' => Uom::class,
            'cost_price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'dealer_price' => 'decimal:2',
            'project_price' => 'decimal:2',
            'is_serialized' => 'boolean',
            'track_lot' => 'boolean',
            'min_stock' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'lead_time_days' => 'integer',
            'warranty_months' => 'integer',
            'spec' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'sku', 'name_th', 'name_en', 'category_id', 'brand_id', 'model', 'part_number', 'uom',
                'cost_price', 'list_price', 'dealer_price', 'project_price',
                'is_serialized', 'track_lot', 'min_stock', 'reorder_qty',
                'lead_time_days', 'warranty_months', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<Supplier, $this> */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['supplier_sku', 'cost_price', 'lead_time_days', 'is_preferred'])
            ->withTimestamps();
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<SerialNumber, $this> */
    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    /**
     * ยอดคงเหลือรวมทุกคลัง — ใช้ stock_levels ที่ eager load มาแล้วถ้ามี
     */
    public function totalOnHand(): string
    {
        return $this->stockLevels->reduce(
            static fn (string $carry, StockLevel $level): string => bcadd($carry, (string) $level->qty_on_hand, 3),
            '0.000',
        );
    }

    public function totalAvailable(): string
    {
        return $this->stockLevels->reduce(
            static fn (string $carry, StockLevel $level): string => bcadd($carry, $level->qty_available, 3),
            '0.000',
        );
    }

    /**
     * ราคาตามระดับราคาของลูกค้า (spec 4.5)
     */
    public function priceFor(PriceTier $tier): string
    {
        return (string) $this->getAttribute($tier->priceColumn());
    }

    public function displayName(): string
    {
        return trim($this->name_th.' '.($this->model ?? ''));
    }

    /** @param  Builder<Product>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * ค้นหาแบบที่ sales ใช้จริง — พิมพ์ SKU, part number, ชื่อ หรือ model ก็เจอ
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('sku', 'like', "%{$term}%")
                ->orWhere('part_number', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('name_th', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%");
        });
    }

    /**
     * กรองตามหมวด โดยรวมหมวดย่อยด้วย — เลือกหมวดแม่แล้วต้องเห็นสินค้าในหมวดลูกทั้งหมด
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInCategory(Builder $query, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $query->where(function (Builder $q) use ($categoryId): void {
            $q->where('category_id', $categoryId)
                ->orWhereIn('category_id', Category::query()
                    ->where('parent_id', $categoryId)
                    ->select('id'));
        });
    }
}
