/**
 * ตัวแก้ไขบรรทัดของใบส่งของ
 *
 * ต่างจาก stockLines ตรงที่บรรทัดถูกกำหนดมาแล้วจากใบสั่งขาย — เลือกสินค้าใหม่ไม่ได้
 * ทำได้แค่ปรับจำนวนที่จะส่งจริง (ไม่เกินที่ยังค้างอยู่) และกรอก serial ให้ครบ
 */
export default function deliveryLines({ lines = [] }) {
    return {
        lines: lines.map((line) => ({ ...line, include: Number(line.qty) > 0 })),

        /** จำนวนที่ยังส่งได้อีกของบรรทัดนี้ */
        outstanding(line) {
            return Math.max(Number(line.qty_ordered) - Number(line.qty_delivered), 0);
        },

        /** ส่งเกินยอดที่ค้างอยู่ไม่ได้ — เตือนตั้งแต่บนหน้าจอ ไม่ต้องรอ 422 */
        isOverDelivering(line) {
            return Number(line.qty) > this.outstanding(line);
        },

        serialCount(line) {
            if (!line.serial_numbers) {
                return 0;
            }

            return line.serial_numbers
                .split(/[\r\n,]+/)
                .map((s) => s.trim())
                .filter((s) => s !== '').length;
        },

        /** สินค้าที่ติดตาม serial ต้องกรอก serial เท่ากับจำนวนที่ส่งพอดี (spec 4.4) */
        serialMismatch(line) {
            if (!line.is_serialized || !line.include) {
                return false;
            }

            const qty = Number(line.qty || 0);

            return qty > 0 && this.serialCount(line) !== qty;
        },

        get includedCount() {
            return this.lines.filter((line) => line.include).length;
        },

        hasProblem() {
            return this.lines.some(
                (line) => line.include && (this.serialMismatch(line) || this.isOverDelivering(line)),
            );
        },

        /** ติ๊กทุกบรรทัดแล้วเติมจำนวนที่ค้างให้ครบ — ปุ่ม "ส่งทั้งหมดที่ค้าง" */
        selectAllOutstanding() {
            this.lines.forEach((line) => {
                line.include = this.outstanding(line) > 0;
                line.qty = String(this.outstanding(line));
            });
        },

        qtyLabel(line) {
            return `${Number(line.qty_delivered).toLocaleString()} / ${Number(line.qty_ordered).toLocaleString()}`;
        },
    };
}
