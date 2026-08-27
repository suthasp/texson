/**
 * ตัวแก้ไขบรรทัดของใบเสนอราคา (spec 7)
 *
 * หน้าที่: เพิ่ม/ลบบรรทัด, ค้นหาสินค้าแบบพิมพ์แล้วขึ้น, เติมราคาตามระดับราคาของลูกค้า,
 * แสดงสต็อกคงเหลือข้างรายการ และสรุปยอด + margin สดที่มุมขวา
 *
 * ตัวเลขที่คำนวณตรงนี้ใช้เพื่อแสดงผลเท่านั้น — ยอดที่บันทึกจริงคำนวณใหม่ฝั่งเซิร์ฟเวอร์
 * ด้วย bcmath ผ่าน QuotationCalculator เสมอ ห้ามเชื่อค่าที่ browser ส่งมา
 */

/** ปัดเป็นสตางค์แบบครึ่งขึ้น ให้ตรงกับ Money::round ฝั่ง PHP เท่าที่ float ทำได้ */
function round2(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function toNumber(value) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

export default function quotationLines({
    products = [],
    customers = [],
    rows = [],
    blank = {},
    customerId = '',
    priceTier = 'standard',
    vatRate = '7.00',
    discountAmount = '0',
    minMargin = 10,
}) {
    return {
        products,
        customers,
        rows: rows.length ? rows : [{ ...blank }],
        blank,
        customerId: String(customerId || ''),
        priceTier,
        vatRate,
        discountAmount,
        minMargin: Number(minMargin),

        init() {
            // Ctrl+S / Cmd+S บันทึกได้เลย ไม่ต้องเลื่อนลงไปหาปุ่ม (spec 7)
            this.saveHandler = (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    this.$refs.form?.requestSubmit();
                }
            };

            window.addEventListener('keydown', this.saveHandler);
        },

        destroy() {
            window.removeEventListener('keydown', this.saveHandler);
        },

        // ── ลูกค้า ───────────────────────────────────────────

        get customer() {
            return this.customers.find((c) => String(c.id) === this.customerId) || null;
        },

        get contacts() {
            return this.customer ? this.customer.contacts : [];
        },

        get sites() {
            return this.customer ? this.customer.sites : [];
        },

        /**
         * เปลี่ยนลูกค้า → ใช้ระดับราคาของลูกค้ารายนั้นเป็นค่าตั้งต้น
         * ผู้ติดต่อและหน้างานที่เลือกไว้ต้องถูกล้าง เพราะเป็นของลูกค้ารายเดิม
         */
        onCustomerChange() {
            this.contactId = '';
            this.siteId = '';

            if (this.customer) {
                this.priceTier = this.customer.price_tier;
                this.applyTierPrices();
            }
        },

        contactId: '',
        siteId: '',

        /**
         * เปลี่ยนระดับราคา → เติมราคาใหม่เฉพาะบรรทัดที่ผู้ใช้ยังไม่ได้พิมพ์ทับ
         */
        applyTierPrices() {
            this.rows.forEach((row) => {
                if (row.price_overridden) {
                    return;
                }

                const product = this.productFor(row);

                if (product) {
                    row.unit_price = product.prices[this.priceTier] ?? row.unit_price;
                }
            });
        },

        // ── บรรทัด ───────────────────────────────────────────

        addRow(type = 'product') {
            this.rows.push({ ...this.blank, item_type: type });
        },

        removeRow(index) {
            this.rows.splice(index, 1);

            if (this.rows.length === 0) {
                this.addRow();
            }
        },

        moveRow(index, offset) {
            const target = index + offset;

            if (target < 0 || target >= this.rows.length) {
                return;
            }

            const [row] = this.rows.splice(index, 1);
            this.rows.splice(target, 0, row);
        },

        productFor(row) {
            return this.products.find((p) => String(p.id) === String(row.product_id)) || null;
        },

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
            row.description = product.name + (product.model ? ` ${product.model}` : '');
            row.uom = product.uom;
            row.unit_price = product.prices[this.priceTier] ?? '0.00';
            row.cost_price = product.cost_price;
            row.available = product.available;
            row.lead_time_days = product.lead_time_days;
            row.price_overridden = false;
            row.open = false;
        },

        onSearchInput(row) {
            row.open = true;

            const selected = this.productFor(row);

            if (selected && row.search !== `${selected.sku} — ${selected.name}`) {
                row.product_id = '';
                row.available = null;
                row.cost_price = '0';
            }
        },

        onPriceInput(row) {
            row.price_overridden = true;
        },

        /** บรรทัดชนิดข้อความไม่มีตัวเลขให้กรอก */
        isMonetary(row) {
            return row.item_type !== 'note';
        },

        /** เตือนเมื่อขายเกินยอดพร้อมขาย — ไม่ได้ห้าม เพราะสั่งของเพิ่มได้ (spec 4.4) */
        isOverStock(row) {
            if (row.item_type !== 'product' || row.available === null || row.available === undefined) {
                return false;
            }

            return toNumber(row.qty) > toNumber(row.available);
        },

        // ── ยอดเงิน ──────────────────────────────────────────

        lineGross(row) {
            if (!this.isMonetary(row)) {
                return 0;
            }

            return round2(toNumber(row.qty) * toNumber(row.unit_price));
        },

        lineDiscount(row) {
            if (!this.isMonetary(row)) {
                return 0;
            }

            const percent = toNumber(row.discount_percent);
            const discount = percent > 0
                ? round2((this.lineGross(row) * percent) / 100)
                : round2(toNumber(row.discount_amount));

            return Math.min(discount, this.lineGross(row));
        },

        lineTotal(row) {
            return round2(this.lineGross(row) - this.lineDiscount(row));
        },

        lineCost(row) {
            if (!this.isMonetary(row)) {
                return 0;
            }

            return round2(toNumber(row.qty) * toNumber(row.cost_price));
        },

        lineMarginPercent(row) {
            const total = this.lineTotal(row);

            if (total <= 0) {
                return null;
            }

            return round2(((total - this.lineCost(row)) / total) * 100);
        },

        isLowMargin(row) {
            const margin = this.lineMarginPercent(row);

            return margin !== null && toNumber(row.cost_price) > 0 && margin < this.minMargin;
        },

        get subtotal() {
            return round2(this.rows.reduce((sum, row) => sum + this.lineTotal(row), 0));
        },

        get headerDiscount() {
            return Math.min(round2(toNumber(this.discountAmount)), this.subtotal);
        },

        get afterDiscount() {
            return round2(this.subtotal - this.headerDiscount);
        },

        get vatAmount() {
            return round2((this.afterDiscount * toNumber(this.vatRate)) / 100);
        },

        get grandTotal() {
            return round2(this.afterDiscount + this.vatAmount);
        },

        get costTotal() {
            return round2(this.rows.reduce((sum, row) => sum + this.lineCost(row), 0));
        },

        get marginAmount() {
            return round2(this.afterDiscount - this.costTotal);
        },

        get marginPercent() {
            if (this.afterDiscount <= 0) {
                return 0;
            }

            return round2((this.marginAmount / this.afterDiscount) * 100);
        },

        get isMarginLow() {
            return this.costTotal > 0 && this.marginPercent < this.minMargin;
        },

        /** ฐานหัก ณ ที่จ่าย 3% — เฉพาะค่าบริการและค่าแรง (spec 4.2) */
        get withholdingBase() {
            return round2(this.rows
                .filter((row) => row.item_type === 'service' || row.item_type === 'labour')
                .reduce((sum, row) => sum + this.lineTotal(row), 0));
        },

        get withholdingAmount() {
            return round2((this.withholdingBase * 3) / 100);
        },

        get totalDiscountPercent() {
            const gross = round2(this.rows.reduce((sum, row) => sum + this.lineGross(row), 0));

            if (gross <= 0) {
                return 0;
            }

            const lineDiscounts = this.rows.reduce((sum, row) => sum + this.lineDiscount(row), 0);

            return round2(((lineDiscounts + this.headerDiscount) / gross) * 100);
        },

        money(value) {
            return Number(value).toLocaleString('th-TH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    };
}
