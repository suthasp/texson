/**
 * ตัวแก้ไขบรรทัดสินค้าที่ใช้ร่วมกันในใบรับสินค้า ใบโอนคลัง และใบปรับปรุงสต็อก
 *
 * รายชื่อสินค้าถูกฝังมากับหน้าเลย ไม่ต้องยิง API — หน้าคลังใช้บนแท็บเล็ตที่สัญญาณไม่ดีได้
 * Phase 3 จะเปลี่ยนเป็นค้นหาแบบ async เมื่อจำนวน SKU มากขึ้น
 */
export default function stockLines({ products = [], rows = [], blank = {} }) {
    return {
        products,
        rows: rows.length ? rows : [{ ...blank }],
        blank,

        addRow() {
            this.rows.push({ ...this.blank });
        },

        removeRow(index) {
            this.rows.splice(index, 1);

            // เหลือศูนย์บรรทัดแล้วฟอร์มจะส่งไม่ผ่าน validation — เติมบรรทัดว่างคืนให้เสมอ
            if (this.rows.length === 0) {
                this.addRow();
            }
        },

        /** สินค้าที่เลือกไว้ในบรรทัดนี้ */
        productFor(row) {
            return this.products.find((p) => String(p.id) === String(row.product_id)) || null;
        },

        /** ตัวเลือกที่ตรงกับคำค้นของบรรทัดนี้ จำกัดไว้ 8 รายการให้เลื่อนไม่ยาว */
        matches(row) {
            const term = (row.search || '').trim().toLowerCase();

            if (term === '') {
                return this.products.slice(0, 8);
            }

            return this.products
                .filter((p) => `${p.sku} ${p.name} ${p.model || ''}`.toLowerCase().includes(term))
                .slice(0, 8);
        },

        choose(row, product) {
            row.product_id = product.id;
            row.search = `${product.sku} — ${product.name}`;
            row.uom = product.uom;
            row.is_serialized = product.is_serialized;
            row.open = false;

            if (Object.prototype.hasOwnProperty.call(row, 'unit_cost') && !row.unit_cost) {
                row.unit_cost = product.cost_price;
            }
        },

        /** ล้างสินค้าที่เลือกเมื่อผู้ใช้พิมพ์ใหม่ ป้องกันชื่อกับ id ไม่ตรงกัน */
        onSearchInput(row) {
            row.open = true;

            const selected = this.productFor(row);

            if (selected && row.search !== `${selected.sku} — ${selected.name}`) {
                row.product_id = '';
                row.is_serialized = false;
            }
        },

        /** จำนวน serial ที่กรอกในบรรทัดนี้ ใช้เตือนก่อนกดบันทึก */
        serialCount(row) {
            if (!row.serial_numbers) {
                return 0;
            }

            return row.serial_numbers
                .split(/[\r\n,]+/)
                .map((s) => s.trim())
                .filter((s) => s !== '').length;
        },

        /** สินค้าที่ติดตาม serial ต้องกรอก serial ให้ครบเท่าจำนวน */
        serialMismatch(row) {
            if (!row.is_serialized) {
                return false;
            }

            const qty = Number(row.qty || 0);

            return qty > 0 && this.serialCount(row) !== qty;
        },

        hasSerialProblem() {
            return this.rows.some((row) => this.serialMismatch(row));
        },
    };
}
