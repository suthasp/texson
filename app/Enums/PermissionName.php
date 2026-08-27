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

    // ── ใบเสนอราคา ──
    case QuotationViewAny = 'quotation.viewAny';
    case QuotationView = 'quotation.view';
    case QuotationCreate = 'quotation.create';
    case QuotationUpdate = 'quotation.update';
    case QuotationDelete = 'quotation.delete';
    /** ส่งใบเข้าคิวรออนุมัติ */
    case QuotationSubmit = 'quotation.submit';
    /** อนุมัติใบที่เข้าเกณฑ์ส่วนลด/margin/ยอด (spec 4.3) — แยกจากสิทธิ์แก้ไขโดยเจตนา */
    case QuotationApprove = 'quotation.approve';
    /** ส่งใบให้ลูกค้า (อีเมล) และเปลี่ยนสถานะเป็น sent */
    case QuotationSend = 'quotation.send';
    /** บันทึกผลจากลูกค้า — ตอบรับหรือปฏิเสธ */
    case QuotationDecide = 'quotation.decide';
    /** สร้าง revision ใหม่จากใบที่ส่งไปแล้ว */
    case QuotationRevise = 'quotation.revise';

    // ── ใบสั่งขาย ──
    case SalesOrderViewAny = 'sales_order.viewAny';
    case SalesOrderView = 'sales_order.view';
    case SalesOrderCreate = 'sales_order.create';
    case SalesOrderUpdate = 'sales_order.update';
    case SalesOrderDelete = 'sales_order.delete';
    /** ยืนยันใบแล้วจองของในคลัง (spec 4.4) — แยกจากสิทธิ์แก้ไขเพราะไปกันสต็อกของคนอื่น */
    case SalesOrderConfirm = 'sales_order.confirm';
    /** ยกเลิกใบและคืนของที่จองไว้ */
    case SalesOrderCancel = 'sales_order.cancel';

    // ── ใบส่งของ ──
    case DeliveryViewAny = 'delivery.viewAny';
    case DeliveryView = 'delivery.view';
    case DeliveryCreate = 'delivery.create';
    case DeliveryUpdate = 'delivery.update';
    /** ตัดสต็อกจริงตามใบส่งของ — ย้อนกลับไม่ได้ */
    case DeliveryPost = 'delivery.post';
    case DeliveryDelete = 'delivery.delete';

    // ── ตั้งค่าระบบ (ข้อมูลบริษัท เงื่อนไขเอกสาร เกณฑ์อนุมัติ) ──
    case SettingViewAny = 'setting.viewAny';
    case SettingUpdate = 'setting.update';

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
