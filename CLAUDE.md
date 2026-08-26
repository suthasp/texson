# PROMPT สำหรับ Claude Code — TEXSON Service & Parts Platform (Phase 1)

> วิธีใช้: สร้างโฟลเดอร์โปรเจกต์เปล่า → `claude` → วางไฟล์นี้ทั้งไฟล์เป็นข้อความแรก
> หรือบันทึกเป็น `CLAUDE.md` ในราก repo เพื่อให้ Claude Code อ่านเป็น context ถาวร

---

## 0. ROLE & OBJECTIVE

คุณคือ Senior Laravel Engineer ที่สร้างระบบหลังบ้านให้ **TEXSON** — บริษัทที่ปรึกษา Data Center Facility
ทำงาน Audit / Preventive & Corrective Maintenance / Training และ **จำหน่ายอุปกรณ์และอะไหล่** (UPS, แบตเตอรี่, CRAC/Precision Air, Rack, PDU, ATS/Generator, Monitoring, Fire Suppression)

**เป้าหมาย Phase 1:** ระบบ **Spare Part Inventory + Quotation/Sales** ที่ใช้งานจริงได้ในองค์กร (internal-first, ยังไม่ทำ customer portal)

**สำคัญ:** ออกแบบ schema และ domain ให้ต่อยอด Phase 2 (Work Order PM/CM, Asset Register, Contract/SLA) ได้โดย **ไม่ต้อง refactor ตาราง** — ดูหัวข้อ 9 (Future Hooks)

---

## 1. TECH STACK (ห้ามเปลี่ยนโดยไม่ถาม)

| ส่วน | เลือกใช้ |
|---|---|
| Framework | Laravel 11.x |
| PHP | 8.3 (`declare(strict_types=1);` ทุกไฟล์ใน `app/`) |
| DB | MySQL 8.0 / MariaDB 10.6+ · `utf8mb4_unicode_ci` |
| UI | Blade + Tailwind CSS 3 + Alpine.js · Laravel Breeze (Blade stack) |
| Auth | Breeze + `spatie/laravel-permission` |
| PDF | `barryvdh/laravel-dompdf` + ฟอนต์ **Sarabun** (ต้องแสดงภาษาไทยถูกต้อง มีสระบน–ล่าง ไม่เพี้ยน) |
| Excel | `maatwebsite/excel` |
| Audit | `spatie/laravel-activitylog` |
| Test | Pest 3 + `RefreshDatabase` |
| Style | Laravel Pint (preset laravel) — รัน pint ก่อน commit ทุกครั้ง |

**ห้าม:** ใช้ raw SQL ที่ต่อ string กับ input, ใช้ `DB::statement` กับค่าจากผู้ใช้, hardcode credentials, commit `.env`

---

## 2. ARCHITECTURE RULES

```
app/
├── Models/                 # Eloquent + relations + scopes เท่านั้น (ไม่มี business logic หนัก)
├── Services/               # Business logic ทั้งหมด (StockService, QuotationService, NumberSequenceService...)
├── Http/
│   ├── Controllers/Web/    # Blade CRUD
│   ├── Controllers/Api/    # REST (v1)
│   ├── Requests/           # FormRequest ทุก endpoint ที่รับ input (ห้าม validate ใน controller)
│   └── Resources/          # API Resource ทุก response
├── Policies/               # Authorization ทุก model
├── Enums/                  # PHP 8.1 backed enum ทุก status field
├── Actions/                # Single-purpose action (ConvertQuotationToSalesOrder ฯลฯ)
└── Exceptions/Domain/      # InsufficientStockException, InvalidStatusTransitionException
```

**กฎเหล็ก**
1. Controller บาง: validate (FormRequest) → เรียก Service → return view/resource
2. ทุกงานที่แตะสต็อกหรือเอกสารหลายตาราง ต้องอยู่ใน `DB::transaction()`
3. เลขที่เอกสารต้องออกผ่าน `NumberSequenceService` ที่ใช้ `lockForUpdate()` เท่านั้น (กันเลขชนตอนหลายคนกดพร้อมกัน)
4. ทุก money field: `decimal(15,2)` — ห้ามใช้ float; คำนวณเงินด้วย integer satang หรือ `bcmath` ใน service
5. ทุก enum status → PHP Enum + method `canTransitionTo()`
6. ทุก mutation ต้องบันทึก activity log (ใคร ทำอะไร ก่อน/หลัง)

---

## 3. DATABASE SCHEMA (Phase 1)

สร้าง migration แยกไฟล์ตามลำดับนี้ พร้อม FK, index, และ soft deletes ที่ระบุ

### 3.1 Master Data

**users** (Breeze default +) `employee_code`, `phone`, `is_active`, `last_login_at`
Roles: `admin`, `sales`, `warehouse`, `engineer`, `viewer`

**customers** — softDeletes
`code`(unique), `name_th`, `name_en`, `tax_id`(13), `branch_code`(default '00000' = สำนักงานใหญ่), `address_line`, `subdistrict`, `district`, `province`, `postcode`, `phone`, `email`, `credit_term_days`(default 30), `payment_terms`, `price_tier`(enum: standard/dealer/project), `notes`, `is_active`
Index: `code`, `tax_id`, `name_th`

**customer_contacts**
`customer_id`, `name`, `position`, `phone`, `email`, `line_id`, `is_primary`

**customer_sites** ← *ใช้เต็มที่ใน Phase 2 แต่สร้างตอนนี้*
`customer_id`, `site_code`, `site_name` (เช่น "DC ชั้น 3 อาคาร A"), `address_line`, `province`, `access_note`, `primary_contact_id`

**suppliers** — `code`, `name`, `tax_id`, `contact_name`, `phone`, `email`, `lead_time_days`, `notes`, `is_active`

**categories** — `name_th`, `name_en`, `parent_id`(nullable, self FK), `sort_order`
Seed: Power & Backup (UPS, Battery, ATS, Generator, MDB) · Cooling (Precision Air, In-row, Containment) · Rack & Infra (Rack, PDU, Cable, Raised Floor) · Monitoring & Safety (Temp/Humidity/Water-leak Sensor, Fire Suppression, Access Control, CCTV) · Consumable & Spare (Filter, Fan, Capacitor, Belt)

**brands** — `name`, `is_active`

**products** — softDeletes
`sku`(unique), `name_th`, `name_en`, `category_id`, `brand_id`, `model`, `part_number`,
`uom`(pcs/set/box/roll/m), `cost_price`, `list_price`, `dealer_price`, `project_price`,
`is_serialized`(bool — แบตเตอรี่/UPS = true), `track_lot`(bool),
`min_stock`, `reorder_qty`, `lead_time_days`, `warranty_months`,
`spec`(json — เช่น `{"kva":10,"phase":"3P","voltage":380}`), `image_path`, `description`, `is_active`
Index: `sku`, `part_number`, fulltext(`name_th`,`name_en`,`model`)

**product_supplier** (pivot) — `product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `lead_time_days`, `is_preferred`

**warehouses** — `code`, `name`, `address`, `is_default`, `is_active`
Seed: `HQ` คลังสำนักงานใหญ่, `VAN` สต็อกรถบริการ, `CONSIGN` สต็อกฝากหน้างานลูกค้า

### 3.2 Inventory

**stock_levels** — `product_id`, `warehouse_id`, `qty_on_hand`(decimal 15,3), `qty_reserved`
`unique(product_id, warehouse_id)` · `qty_available` = accessor (on_hand − reserved)

**stock_movements** *(append-only ledger — ห้าม update/delete)*
`product_id`, `warehouse_id`, `type`(enum: receive, issue, adjust_in, adjust_out, transfer_in, transfer_out, return_in), `qty`(signed decimal), `unit_cost`, `balance_after`,
`ref_type`/`ref_id`(polymorphic → Delivery, PurchaseReceipt, StockAdjustment), `lot_no`, `note`, `user_id`, `moved_at`
Index: `(product_id, warehouse_id, moved_at)`

**serial_numbers**
`product_id`, `serial_no`(unique per product), `warehouse_id`(nullable), `status`(enum: in_stock, reserved, sold, installed, rma, scrapped), `customer_id`(nullable), `customer_site_id`(nullable), `sales_order_id`(nullable), `warranty_start`, `warranty_end`, `note`
> ใช้ track แบตเตอรี่/UPS ต่อเนื่องไปถึงงาน PM ใน Phase 2

**stock_adjustments** — `adjust_no`, `warehouse_id`, `reason`(enum: stock_count, damaged, lost, found, opening), `adjusted_at`, `user_id`, `note`, `status`(draft/posted)
**stock_adjustment_items** — `adjustment_id`, `product_id`, `qty_system`, `qty_counted`, `qty_diff`, `lot_no`

### 3.3 Quotation & Sales

**number_sequences** — `doc_type`, `period`(YYYYMM), `last_no` · `unique(doc_type, period)`

**quotations** — softDeletes
`quote_no`(unique เช่น `QT-202608-0007`), `revision`(int default 0), `parent_quotation_id`(nullable — revision chain),
`customer_id`, `customer_contact_id`, `customer_site_id`(nullable), `sales_user_id`,
`issue_date`, `valid_until`, `currency`(default THB), `price_tier`,
`subtotal`, `discount_amount`, `after_discount`, `vat_rate`(default 7.00), `vat_amount`, `grand_total`,
`status`(enum: draft, pending_approval, sent, accepted, rejected, expired, cancelled),
`payment_terms`, `delivery_terms`, `lead_time_note`, `terms_and_conditions`(text), `customer_note`, `internal_note`,
`approved_by`, `approved_at`, `sent_at`, `decided_at`, `lost_reason`, `created_by`
Index: `quote_no`, `(customer_id, status)`, `issue_date`

**quotation_items**
`quotation_id`, `line_no`, `product_id`(nullable — ให้พิมพ์รายการอิสระได้ เช่น "ค่าแรงติดตั้ง"),
`item_type`(enum: product, service, labour, freight, note),
`description`(text — snapshot ตอนออกใบ), `qty`, `uom`, `unit_price`, `discount_percent`, `discount_amount`, `line_total`,
`cost_snapshot`(สำหรับคำนวณ margin), `lead_time_days`
> **Snapshot rule:** ราคาและชื่อสินค้าต้องถูก copy มาเก็บในบรรทัด — แก้ราคาสินค้าในภายหลังห้ามกระทบใบเสนอราคาเก่า

**sales_orders**
`so_no`, `quotation_id`(nullable), `customer_id`, `customer_site_id`, `customer_po_no`, `customer_po_file`,
`order_date`, `required_date`, `status`(enum: pending, reserved, partially_delivered, delivered, cancelled),
ยอดเงินชุดเดียวกับ quotation, `created_by`

**sales_order_items** — `sales_order_id`, `quotation_item_id`, `product_id`, `description`, `qty_ordered`, `qty_reserved`, `qty_delivered`, `unit_price`, `line_total`

**deliveries** — `delivery_no`, `sales_order_id`, `warehouse_id`, `delivery_date`, `status`(draft/posted/cancelled), `receiver_name`, `receiver_signature_path`, `vehicle_note`, `posted_at`, `posted_by`
**delivery_items** — `delivery_id`, `sales_order_item_id`, `product_id`, `qty`, `serial_numbers`(json), `lot_no`

### 3.4 Support

**attachments** (polymorphic) — `attachable_type/id`, `path`, `original_name`, `mime`, `size`, `uploaded_by`
**settings** — `key`, `value`(json), `group` (บริษัท: ชื่อ/ที่อยู่/เลขผู้เสียภาษี/โลโก้/ลายเซ็น, VAT rate, doc prefix, ค่าเริ่มต้นเงื่อนไข)
**activity_log** — จาก spatie

---

## 4. BUSINESS RULES (ต้องมี test ครอบทุกข้อ)

### 4.1 เลขที่เอกสาร
- รูปแบบ: `QT-YYYYMM-####`, `SO-YYYYMM-####`, `DN-YYYYMM-####`, `ADJ-YYYYMM-####`
- ออกผ่าน `NumberSequenceService::next(DocType $type)` → `DB::transaction` + `lockForUpdate` → running รีเซ็ตทุกเดือน
- **Test:** ยิงพร้อมกัน 20 ครั้ง ต้องไม่ได้เลขซ้ำ

### 4.2 คำนวณเงิน (ลำดับตายตัว)
```
line_total   = qty × unit_price − line_discount
subtotal     = Σ line_total
after_discount = subtotal − header_discount
vat_amount   = after_discount × vat_rate / 100   (ปัดครึ่งขึ้น 2 ตำแหน่ง)
grand_total  = after_discount + vat_amount
```
- แสดง "หัก ณ ที่จ่าย 3%" เป็นข้อมูลประกอบท้ายใบเมื่อรายการมีค่าบริการ (ไม่หักจาก grand_total)
- แสดงยอดเป็น**ตัวอักษรภาษาไทย** ("หนึ่งแสนสองหมื่นบาทถ้วน") — เขียน helper `BahtText` + test เคส 0, 0.25, 1000000, 1234567.89

### 4.3 Quotation lifecycle
```
draft → pending_approval → sent → accepted | rejected | expired
draft → cancelled          sent → cancelled
```
- แก้ใบที่ `sent` แล้ว = สร้าง **revision ใหม่** (`revision+1`, `parent_quotation_id` ชี้ใบเดิม, ใบเดิม → `superseded` flag) ห้ามแก้ทับ
- `valid_until` เลยวันนี้ + ยัง `sent` → scheduled job เปลี่ยนเป็น `expired` ทุกเช้า 06:00
- ต้องอนุมัติก่อนส่ง ถ้า: ส่วนลดรวม > 15% **หรือ** margin < 10% **หรือ** grand_total > 500,000 (ค่าตั้งใน settings)
- `accepted` → ปุ่ม "สร้างใบสั่งขาย" (สร้าง SO ได้ครั้งเดียวต่อใบ)

### 4.4 สต็อก
- SO ยืนยัน → `qty_reserved += qty` (ไม่ลด on_hand) — ถ้าของไม่พอ ให้เตือนแต่**อนุญาต backorder** พร้อมบันทึก shortage
- Post delivery → `qty_on_hand -= qty`, `qty_reserved -= qty`, เขียน `stock_movements` (type=issue)
- ยกเลิก SO → คืน reserved
- ทุกการเปลี่ยน stock ต้องผ่าน `StockService` เท่านั้น + เขียน ledger เสมอ
- สินค้าที่ `is_serialized` → ตอน post delivery **บังคับ**เลือก serial ที่ status=`in_stock` ให้ครบจำนวน; หลัง post → `sold` + ตั้ง warranty_start = วันส่ง, warranty_end = +`warranty_months`
- `qty_available < min_stock` → ขึ้นใน Low Stock Dashboard + badge

### 4.5 ราคา
- เลือกจาก `price_tier` ของลูกค้า (standard/dealer/project) แล้ว sales แก้ทับได้ (แต่ log ทุกครั้ง)
- `cost_snapshot` เก็บทุกบรรทัดเพื่อคำนวณ margin แบบ real-time บนหน้าจอ (สีแดงถ้า < 10%)

---

## 5. PDF & EXPORT

**ใบเสนอราคา (A4, ภาษาไทย, ฟอนต์ Sarabun)** ต้องมี:
โลโก้ + ชื่อ/ที่อยู่/เลขประจำตัวผู้เสียภาษี TEXSON · ชื่อลูกค้า + สาขา + เลขผู้เสียภาษี · เลขที่/วันที่/ยืนราคาถึง · ตารางรายการ (ลำดับ, รหัส, รายละเอียด, จำนวน, หน่วย, ราคา/หน่วย, ส่วนลด, จำนวนเงิน) · สรุปยอด + VAT 7% + ยอดสุทธิ + ตัวอักษรไทย · เงื่อนไขการชำระเงิน/ส่งมอบ/ระยะเวลาส่งของ · ช่องลงนาม 2 ฝั่ง · เลขหน้า "x/y"
- ต้องมี **ฉบับภาษาอังกฤษ** สลับได้ (เว็บ TEXSON เป็น TH/EN อยู่แล้ว) — ใช้ Laravel localization `lang/th`, `lang/en`
- ไฟล์: `QT-202608-0007_rev1.pdf`

**Export Excel:** รายการสินค้า+สต็อกคงเหลือ, รายงานใบเสนอราคาตามช่วงวันที่, stock movement ledger

---

## 6. REST API (v1) — `routes/api.php`, Sanctum token

```
GET    /api/v1/products?search=&category_id=&low_stock=1&page=
GET    /api/v1/products/{id}
GET    /api/v1/products/{id}/stock            # แยกตามคลัง
POST   /api/v1/stock/adjust                   # role: warehouse|admin
GET    /api/v1/customers?search=
GET    /api/v1/quotations?status=&customer_id=&from=&to=
POST   /api/v1/quotations
PUT    /api/v1/quotations/{id}                # เฉพาะ draft
POST   /api/v1/quotations/{id}/submit
POST   /api/v1/quotations/{id}/approve
POST   /api/v1/quotations/{id}/send
POST   /api/v1/quotations/{id}/accept|reject
POST   /api/v1/quotations/{id}/revise         # → revision ใหม่
GET    /api/v1/quotations/{id}/pdf
POST   /api/v1/quotations/{id}/convert-to-so
GET    /api/v1/sales-orders / {id}
POST   /api/v1/sales-orders/{id}/deliveries
POST   /api/v1/deliveries/{id}/post
GET    /api/v1/reports/sales-summary?from=&to=
GET    /api/v1/reports/low-stock
```
- Response: `{"data": ..., "meta": ...}` ผ่าน API Resource เสมอ
- Error: `422` validation (`{"message","errors"}`), `409` invalid status transition, `422` insufficient stock พร้อมรายการที่ขาด
- Rate limit: `throttle:60,1`

---

## 7. UI (Blade + Tailwind)

- Layout: sidebar ซ้าย (Dashboard, สินค้า/อะไหล่, สต็อก, ลูกค้า, ใบเสนอราคา, ใบสั่งขาย, ใบส่งของ, รายงาน, ตั้งค่า) + topbar (ค้นหา, สลับ TH/EN, โปรไฟล์)
- โทนสี: ตาม TEXSON — น้ำเงินเข้ม `#1B2A4A` เป็นหลัก, ฟ้า `#29B6D8` เป็น accent, พื้นขาว/เทาอ่อน, ฟอนต์ **IBM Plex Sans Thai** หรือ **Sarabun**
- หน้าสร้างใบเสนอราคา: เพิ่มบรรทัดแบบ dynamic (Alpine), ค้นหาสินค้าแบบพิมพ์แล้วขึ้น (SKU/ชื่อ/model), แสดง **สต็อกคงเหลือ** ข้างรายการ, สรุปยอด + margin สดที่มุมขวา, กด Ctrl+S บันทึก
- ตาราง list: ค้นหา + filter + sort + pagination (server-side) + จำ filter ใน query string
- Dashboard: ยอดขายเดือนนี้/ปีนี้, ใบเสนอราคารออนุมัติ, win rate, สินค้าต่ำกว่า min stock, ใบที่ใกล้หมดอายุใน 7 วัน
- ทุกฟอร์มมี CSRF, error inline ใต้ field, flash message, confirm dialog ก่อน destructive action
- Responsive: ใช้บนแท็บเล็ตในคลังได้

---

## 8. SECURITY & COMPLIANCE (ต้องผ่านทุกข้อก่อนถือว่า Phase เสร็จ)

- [ ] Eloquent/Query Builder ทุก query (ถ้าจำเป็นต้อง raw → binding เท่านั้น)
- [ ] Policy + `authorize()` ทุก action; sales เห็นเฉพาะใบของตัวเอง (admin/manager เห็นหมด)
- [ ] `password_hash` ผ่าน Laravel default (bcrypt/argon2id), บังคับรหัส ≥ 10 ตัว
- [ ] Session: `secure`, `httponly`, `same_site=strict`, regenerate ตอน login
- [ ] CSRF ทุก POST form; Blade `{{ }}` เสมอ (ห้าม `{!! !!}` กับ user input)
- [ ] Upload: validate mime ด้วย `finfo`, ขนาด ≤ 10MB, ตั้งชื่อสุ่ม, เก็บใน `storage/app/private` + serve ผ่าน controller ที่เช็คสิทธิ์
- [ ] Header: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, CSP พื้นฐาน (middleware)
- [ ] Rate limit login 5 ครั้ง/นาที/IP + lockout
- [ ] Error production = ข้อความกลาง, log เต็มฝั่ง server; `APP_DEBUG=false`
- [ ] `.env` ใน `.gitignore` + มี `.env.example` ครบทุก key
- [ ] **PDPA:** ข้อมูลผู้ติดต่อลูกค้าเป็นข้อมูลส่วนบุคคล → activity log การเข้าถึง/แก้ไข, มี soft delete + คำสั่งลบถาวรสำหรับ admin, มีหน้าส่งออกข้อมูลลูกค้ารายคน
- [ ] Audit trail: ทุกการเปลี่ยน status เอกสาร/ปรับสต็อก/แก้ราคา ต้องมีใน activity log พร้อม before-after

---

## 9. FUTURE HOOKS (สร้างเฉพาะ migration + model เปล่า อย่าเพิ่งทำ UI)

Phase 2 จะเพิ่ม PM/CM ดังนั้นตอนนี้ให้:
- ตาราง `customer_sites` ใช้งานได้จริงแล้ว (ใบเสนอราคาเลือก site ได้)
- `serial_numbers.customer_site_id` พร้อมผูกกับ asset ที่ติดตั้ง
- เตรียม enum `DocType` ให้เพิ่ม `WO`, `SR` (service report) ได้
- อย่าใส่ business logic ของ work order ลงใน service ของ sales
- เขียนใน `docs/PHASE2-NOTES.md` ว่าตารางที่จะเพิ่มคือ: `assets`, `asset_types`, `work_orders`, `wo_tasks`, `pm_schedules`, `service_reports`, `contracts`, `sla_terms`, `checklists` และจะเชื่อมกับตารางเดิมตรงไหน

---

## 10. แผนการทำงาน (ทำทีละเฟส หยุดให้ผมรีวิวทุกจบเฟส)

| เฟส | งาน | Definition of Done |
|---|---|---|
| **0** | `laravel new`, Breeze, Pint, Pest, spatie packages, `.env.example`, Docker/sail (ถ้าเหมาะ), README | `php artisan test` เขียว, หน้า login ใช้ได้ |
| **1** | Master data: users/roles, customers+contacts+sites, categories, brands, suppliers, warehouses, products | CRUD ครบ + seeder ตัวอย่าง 30 SKU จริงของ TEXSON + test |
| **2** | Inventory: stock_levels, movements ledger, รับเข้า, ปรับปรุง, โอนคลัง, serial, low-stock | ยอดคงเหลือ = ผลรวม ledger เสมอ (มี test พิสูจน์) |
| **3** | Quotation: CRUD + คำนวณ + lifecycle + revision + อนุมัติ + PDF ไทย/อังกฤษ + ส่งเมล | ออกใบจริงพิมพ์ได้ ตัวเลขถูก ภาษาไทยไม่เพี้ยน |
| **4** | Convert → Sales Order → Delivery → ตัดสต็อก + serial + backorder | end-to-end test: ใบเสนอราคา→ส่งของ→สต็อกลดถูกต้อง |
| **5** | Dashboard + รายงาน + Excel export | ตัวเลขตรงกับ query ตรวจมือ |
| **6** | Hardening ตามข้อ 8 + `docs/SECURITY.md` + คู่มือผู้ใช้ไทย | checklist ข้อ 8 ติ๊กครบ |

**ทุกเฟส:** migration + model + service + FormRequest + policy + controller + view + **Pest test** + `vendor/bin/pint` + git commit ข้อความไทย/อังกฤษสั้น ๆ

---

## 11. TESTING (อย่างน้อย)

- Feature: quotation lifecycle ทุก transition (รวมเคสที่ต้องล้มเหลว)
- Feature: convert quote → SO → delivery → ตรวจ stock_levels & movements
- Unit: `BahtText`, การคำนวณเงิน (ส่วนลดบรรทัด+ส่วนลดท้ายบิล+VAT), `NumberSequenceService` (concurrent)
- Unit: ตัดสต็อกไม่พอ → `InsufficientStockException`
- Policy: sales A เปิดใบของ sales B ไม่ได้ (403)
- Security: SQL injection payload ในช่องค้นหา, XSS ใน `description`, ยิง API ไม่มี token → 401

---

## 12. ก่อนเริ่มเขียนโค้ด

1. อ่าน spec นี้ทั้งหมด แล้วสรุปกลับให้ผม 10 บรรทัด + ถามคำถามที่ยังคลุมเครือ **สูงสุด 5 ข้อ**
2. เสนอ ERD (Mermaid) ของ Phase 1 ให้ผมอนุมัติก่อน
3. เริ่ม Phase 0 หลังผมตอบ "OK"

**ถ้าเจอทางเลือกที่กระทบ schema หรือ business rule — หยุดถามก่อน อย่าเดา**
