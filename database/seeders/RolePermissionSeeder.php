<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * สร้าง role และ permission ทั้งหมดของ Phase 1
 *
 * รันซ้ำได้ — ใช้ firstOrCreate และ syncPermissions ทุกครั้ง
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($this->matrix() as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * สิทธิ์ของแต่ละ role
     *
     * admin ได้ทุกสิทธิ์แบบระบุชัด ไม่ใช้ Gate::before เพื่อให้ข้อห้ามใน Policy
     * (เช่น ห้ามลบบัญชีตัวเอง) ยังทำงานอยู่
     *
     * @return array<string, array<int, string>>
     */
    private function matrix(): array
    {
        $all = PermissionName::values();

        // ลบถาวรตาม PDPA สงวนไว้ให้ admin เท่านั้น จึงตัดออกจากชุดของฝ่ายขาย
        $customerWithoutForceDelete = array_values(array_diff(
            PermissionName::forResource('customer'),
            [PermissionName::CustomerForceDelete->value],
        ));

        $salesCore = [
            ...$customerWithoutForceDelete,
            ...PermissionName::readOnlyForResource('product'),
            ...PermissionName::readOnlyForResource('supplier'),
            PermissionName::CategoryViewAny->value,
            PermissionName::BrandViewAny->value,
            // ฝ่ายขายต้องเห็นยอดคงเหลือตอนออกใบเสนอราคา แต่ไม่ได้แตะเอกสารคลัง
            PermissionName::StockViewAny->value,
            PermissionName::SerialViewAny->value,
        ];

        // งานคลังทั้งหมด: ยอดคงเหลือ ledger และเอกสารคลังสามใบ
        $warehouseOperations = [
            PermissionName::StockViewAny->value,
            PermissionName::StockViewLedger->value,
            ...PermissionName::forResource('goods_receipt'),
            ...PermissionName::forResource('stock_transfer'),
            ...PermissionName::forResource('stock_adjustment'),
            ...PermissionName::forResource('serial'),
        ];

        return [
            RoleName::Admin->value => $all,

            // ผู้จัดการฝ่ายขาย: งานขายทั้งหมด + เห็นราคาทุนเพื่อตรวจ margin ก่อนอนุมัติ
            RoleName::SalesManager->value => [
                ...$salesCore,
                PermissionName::ProductViewCost->value,
                PermissionName::StockViewLedger->value,
                PermissionName::ActivityViewAny->value,
            ],

            RoleName::Sales->value => $salesCore,

            // คลัง: ดูแลข้อมูลสินค้า ผู้ขาย และงานคลังทั้งหมด แต่ไม่ยุ่งกับข้อมูลลูกค้าเชิงลึก
            RoleName::Warehouse->value => [
                ...PermissionName::forResource('product'),
                ...PermissionName::forResource('supplier'),
                ...PermissionName::forResource('category'),
                ...PermissionName::forResource('brand'),
                ...PermissionName::forResource('warehouse'),
                ...$warehouseOperations,
                PermissionName::CustomerViewAny->value,
                PermissionName::CustomerView->value,
            ],

            // วิศวกร: ดูข้อมูลและเบิกของจากสต็อกรถเพื่อเตรียมงานหน้างาน
            RoleName::Engineer->value => [
                ...PermissionName::readOnlyForResource('product'),
                ...PermissionName::readOnlyForResource('customer'),
                PermissionName::WarehouseViewAny->value,
                PermissionName::CategoryViewAny->value,
                PermissionName::BrandViewAny->value,
                PermissionName::StockViewAny->value,
                PermissionName::StockViewLedger->value,
                ...PermissionName::readOnlyForResource('serial'),
                ...PermissionName::readOnlyForResource('stock_transfer'),
            ],

            RoleName::Viewer->value => [
                ...PermissionName::readOnlyForResource('product'),
                ...PermissionName::readOnlyForResource('customer'),
                ...PermissionName::readOnlyForResource('supplier'),
                PermissionName::CategoryViewAny->value,
                PermissionName::BrandViewAny->value,
                PermissionName::WarehouseViewAny->value,
                PermissionName::StockViewAny->value,
                ...PermissionName::readOnlyForResource('serial'),
                ...PermissionName::readOnlyForResource('goods_receipt'),
                ...PermissionName::readOnlyForResource('stock_transfer'),
                ...PermissionName::readOnlyForResource('stock_adjustment'),
            ],
        ];
    }
}
