<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * รายการสิทธิ์ทั้งหมดของ Phase 1
 *
 * ค่าเหล่านี้ถูก seed ลงตาราง permissions ของ spatie/laravel-permission
 * และถูกอ้างถึงใน Policy ทุกตัว — ห้ามใช้ string ดิบใน Policy หรือ Controller
 */
enum PermissionName: string
{
    // ── ลูกค้า (รวมผู้ติดต่อและหน้างาน) ──
    case CustomerViewAny = 'customer.viewAny';
    case CustomerView = 'customer.view';
    case CustomerCreate = 'customer.create';
    case CustomerUpdate = 'customer.update';
    case CustomerDelete = 'customer.delete';
    /** ส่งออกข้อมูลลูกค้ารายคนตามสิทธิ์ PDPA (spec 8) */
    case CustomerExport = 'customer.export';
    /** ลบถาวรหลัง soft delete — เฉพาะ admin ตามข้อกำหนด PDPA */
    case CustomerForceDelete = 'customer.forceDelete';

    // ── สินค้า / อะไหล่ ──
    case ProductViewAny = 'product.viewAny';
    case ProductView = 'product.view';
    case ProductCreate = 'product.create';
    case ProductUpdate = 'product.update';
    case ProductDelete = 'product.delete';
    /** เห็นราคาทุนและ margin — ไม่ใช่ทุก role ควรเห็น */
    case ProductViewCost = 'product.viewCost';

    // ── ผู้ขาย / ซัพพลายเออร์ ──
    case SupplierViewAny = 'supplier.viewAny';
    case SupplierView = 'supplier.view';
    case SupplierCreate = 'supplier.create';
    case SupplierUpdate = 'supplier.update';
    case SupplierDelete = 'supplier.delete';

    // ── หมวดหมู่ ──
    case CategoryViewAny = 'category.viewAny';
    case CategoryCreate = 'category.create';
    case CategoryUpdate = 'category.update';
    case CategoryDelete = 'category.delete';

    // ── ยี่ห้อ ──
    case BrandViewAny = 'brand.viewAny';
    case BrandCreate = 'brand.create';
    case BrandUpdate = 'brand.update';
    case BrandDelete = 'brand.delete';

    // ── คลังสินค้า ──
    case WarehouseViewAny = 'warehouse.viewAny';
    case WarehouseCreate = 'warehouse.create';
    case WarehouseUpdate = 'warehouse.update';
    case WarehouseDelete = 'warehouse.delete';

    // ── ผู้ใช้งานระบบ ──
    case UserViewAny = 'user.viewAny';
    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';

    // ── สต็อก (ยอดคงเหลือและ ledger) ──
    case StockViewAny = 'stock.viewAny';
    /** ดูประวัติการเคลื่อนไหวย้อนหลัง */
    case StockViewLedger = 'stock.viewLedger';

    // ── ใบรับสินค้า ──
    case GoodsReceiptViewAny = 'goods_receipt.viewAny';
    case GoodsReceiptView = 'goods_receipt.view';
    case GoodsReceiptCreate = 'goods_receipt.create';
    case GoodsReceiptUpdate = 'goods_receipt.update';
    /** บันทึกใบเข้าสต็อกจริง — แยกจากสิทธิ์แก้ไขเพราะย้อนกลับไม่ได้ */
    case GoodsReceiptPost = 'goods_receipt.post';
    case GoodsReceiptDelete = 'goods_receipt.delete';

    // ── ใบโอนคลัง ──
    case StockTransferViewAny = 'stock_transfer.viewAny';
    case StockTransferView = 'stock_transfer.view';
    case StockTransferCreate = 'stock_transfer.create';
    case StockTransferUpdate = 'stock_transfer.update';
    case StockTransferPost = 'stock_transfer.post';
    case StockTransferDelete = 'stock_transfer.delete';

    // ── ใบปรับปรุงสต็อก ──
    case StockAdjustmentViewAny = 'stock_adjustment.viewAny';
    case StockAdjustmentView = 'stock_adjustment.view';
    case StockAdjustmentCreate = 'stock_adjustment.create';
    case StockAdjustmentUpdate = 'stock_adjustment.update';
    case StockAdjustmentPost = 'stock_adjustment.post';
    case StockAdjustmentDelete = 'stock_adjustment.delete';

    // ── Serial number ──
    case SerialViewAny = 'serial.viewAny';
    case SerialView = 'serial.view';
    case SerialUpdate = 'serial.update';

    // ── ประวัติการใช้งาน ──
    case ActivityViewAny = 'activity.viewAny';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * สิทธิ์ทั้งหมดของทรัพยากรหนึ่ง เช่น prefix 'product' → product.* ทุกตัว
     *
     * @return array<int, string>
     */
    public static function forResource(string $prefix): array
    {
        return array_values(array_filter(
            self::values(),
            static fn (string $value): bool => str_starts_with($value, $prefix.'.'),
        ));
    }

    /**
     * สิทธิ์แบบอ่านอย่างเดียวของทรัพยากรหนึ่ง (viewAny / view)
     *
     * @return array<int, string>
     */
    public static function readOnlyForResource(string $prefix): array
    {
        return array_values(array_filter(
            self::forResource($prefix),
            static fn (string $value): bool => str_ends_with($value, '.viewAny') || str_ends_with($value, '.view'),
        ));
    }
}
