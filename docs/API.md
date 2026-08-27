# TEXSON Platform — REST API v1

> อ้างอิง [CLAUDE.md](../CLAUDE.md) หัวข้อ 6
> Base URL: `{APP_URL}/api/v1` · ยืนยันตัวตนด้วย Sanctum token · rate limit **60 ครั้ง/นาที**

---

## 1. เริ่มใช้งาน

### ขอ token

`POST /api/v1/auth/token` — endpoint เดียวที่ไม่ต้องมี token อยู่ก่อน จำกัด **5 ครั้ง/นาที/IP** ตาม [CLAUDE.md ข้อ 8](../CLAUDE.md)

```bash
curl -X POST http://localhost:8000/api/v1/auth/token \
  -H 'Accept: application/json' \
  -d 'email=sales1@texson.local' \
  -d 'password=texson1234' \
  -d 'device_name=เครื่องนับสต็อกคลัง A'
```

```json
{
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxx",
    "device_name": "เครื่องนับสต็อกคลัง A",
    "user": { "id": 3, "name": "...", "email": "...", "roles": ["sales"] }
  },
  "meta": { "token_type": "Bearer", "expires_at": null }
}
```

> ขอ token ด้วย `device_name` เดิมซ้ำ จะล้าง token ตัวเก่าของเครื่องนั้นทิ้งก่อนเสมอ — กัน token ค้างสะสม
> `expires_at` เป็น `null` เมื่อ `SANCTUM_EXPIRATION` ไม่ได้ตั้งค่า

### ใช้ token

```bash
curl http://localhost:8000/api/v1/products \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxx'
```

| Endpoint | ทำอะไร |
|---|---|
| `GET /auth/me` | ข้อมูลผู้ใช้ปัจจุบัน + `meta.permissions` (รายชื่อสิทธิ์ทั้งหมด) |
| `DELETE /auth/token` | เพิกถอนเฉพาะ token ที่ใช้เรียกครั้งนี้ ไม่แตะเครื่องอื่น |

---

## 2. รูปแบบ response

ทุก endpoint ตอบผ่าน API Resource เป็นรูป `{"data": ..., "meta": ...}` เสมอ

**รายการ (paginated)** — Laravel เติม `links` และ `meta` ของ pagination ให้

```json
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 25, "total": 42, "last_page": 2 }
}
```

**รายการเดียว** — `meta.can` บอกว่าผู้เรียกทำอะไรกับรายการนั้นได้บ้าง

```json
{
  "data": { "id": 7, "quote_no": "QT-202608-0007", ... },
  "meta": {
    "can": { "view": true, "update": true, "submit": true, "approve": false, "revise": false }
  }
}
```

> `meta.can` มีไว้ให้ client รู้ว่าควรโชว์ปุ่มไหน โดยไม่ต้องเขียนกฎธุรกิจซ้ำฝั่งตัวเองแล้วยิงไปโดน 403 ทีหลัง

**ตัวเลขทศนิยมเป็น string เสมอ** (`"53500.00"` ไม่ใช่ `53500.0`)
ทั้งฝั่งเซิร์ฟเวอร์และ client ต้องไม่แปลงเป็น float ระหว่างทาง — เงินจะปัดเศษเพี้ยนเงียบ ๆ
เงินทศนิยม 2 ตำแหน่ง · จำนวนสินค้า 3 ตำแหน่ง

---

## 3. รูปแบบ error

| Status | เมื่อไร | รูปแบบ |
|---|---|---|
| `401` | ไม่มี token / token ถูกเพิกถอน | `{"message": "..."}` |
| `403` | มี token แต่ไม่มีสิทธิ์ หรือเป็นใบของ sales คนอื่น | `{"message": "คุณไม่มีสิทธิ์ทำรายการนี้"}` |
| `404` | ไม่พบทรัพยากร | `{"message": "ไม่พบ Product ที่ระบุ"}` |
| `409` | เปลี่ยนสถานะข้ามขั้น | `{"message": "...", "document": "QT-...", "from": "...", "to": "..."}` |
| `409` | ส่งใบที่ต้องอนุมัติก่อนโดยยังไม่อนุมัติ | `{"message": "...", "approval_reasons": ["ส่วนลดรวม 20.00% เกิน 15.00%"]}` |
| `422` | validation ไม่ผ่าน | `{"message": "...", "errors": {"field": ["..."]}}` |
| `422` | ของในคลังไม่พอ | `{"message": "...", "shortages": [{"product_id", "sku", "warehouse_id", "warehouse_code", "requested", "available"}]}` |
| `429` | เกิน rate limit | `{"message": "Too Many Attempts."}` |

### 403 กับ 409 ต่างกันอย่างไร

แยกตามว่า "ผู้เรียก" ผิด หรือ "สถานะเอกสาร" ผิด

- **403** — ผู้เรียกไม่มีสิทธิ์นั้น หรือใบเป็นของ sales คนอื่น หรือพยายามอนุมัติใบของตัวเอง
- **409** — ผู้เรียกมีสิทธิ์ครบ แต่เอกสารอยู่ในสถานะที่ทำแบบนั้นไม่ได้

เช่น `PUT /quotations/{id}` บนใบที่ `sent` แล้ว → **409** (ต้องสร้าง revision) ไม่ใช่ 403

> ทุก request ใต้ `/api` ถูกบังคับให้ตอบเป็น JSON แม้ client จะลืมส่ง `Accept: application/json` มา

---

## 4. Endpoints

### สินค้า

| Method | Path | สิทธิ์ |
|---|---|---|
| `GET` | `/products` | `product.viewAny` |
| `GET` | `/products/{id}` | `product.view` |
| `GET` | `/products/{id}/stock` | `product.view` + `stock.viewAny` |

**Query ของ `/products`**

| พารามิเตอร์ | ความหมาย |
|---|---|
| `search` | ค้นจาก SKU, part number, model, ชื่อไทย/อังกฤษ |
| `category_id` | รวมสินค้าในหมวดย่อยด้วย |
| `brand_id` | กรองตามยี่ห้อ |
| `low_stock=1` | เฉพาะสินค้าที่ยอดพร้อมขายต่ำกว่า `min_stock` |
| `active_only=1` | เฉพาะสินค้าที่เปิดใช้งาน |
| `per_page` | สูงสุด 100 |

`prices.cost` จะมีเฉพาะผู้ที่มีสิทธิ์ `product.viewCost` (admin, ผู้จัดการฝ่ายขาย, ฝ่ายขาย — ดู [ADR-012](DECISIONS.md))

`GET /products/{id}/stock` คืนยอดแยกตามคลัง พร้อม `meta.total_on_hand` และ `meta.total_available`

---

### ลูกค้า

| Method | Path | สิทธิ์ |
|---|---|---|
| `GET` | `/customers?search=` | `customer.viewAny` |
| `GET` | `/customers/{id}` | `customer.view` |

> **PDPA:** ผู้ติดต่อและหน้างานมีเฉพาะใน `GET /customers/{id}` เท่านั้น ไม่ติดไปกับรายการทั้งหน้า

---

### สต็อก

| Method | Path | สิทธิ์ |
|---|---|---|
| `POST` | `/stock/adjust` | `stock_adjustment.create` (+ `stock_adjustment.post` เมื่อ post) |
| `GET` | `/stock/ledger` | `stock.viewLedger` |

`POST /stock/adjust` สร้างใบปรับปรุงแล้ว **post ให้ทันที** (ส่ง `post: false` เพื่อเก็บเป็นร่าง)

```json
{
  "warehouse_id": 1,
  "reason": "stock_count",
  "adjusted_at": "2026-08-27",
  "note": "นับสต็อกประจำเดือน",
  "items": [
    { "product_id": 12, "qty_counted": "7", "lot_no": null }
  ]
}
```

ตอบ `201` พร้อม `data.items[].qty_system` / `qty_counted` / `qty_diff`
`qty_system` ถูกอ่านใหม่ ณ เวลาที่ post ไม่ใช่ค่าที่ snapshot ไว้ตอนสร้างใบ

`reason` รับค่า: `stock_count` `damaged` `lost` `found` `opening`

**Query ของ `/stock/ledger`:** `product_id` `warehouse_id` `type` `from` `to` `per_page` (สูงสุด 200)

---

### ใบเสนอราคา

| Method | Path | สิทธิ์ |
|---|---|---|
| `GET` | `/quotations` | `quotation.viewAny` |
| `POST` | `/quotations` | `quotation.create` |
| `GET` | `/quotations/{id}` | `quotation.view` |
| `PUT` | `/quotations/{id}` | `quotation.update` — เฉพาะ draft |
| `DELETE` | `/quotations/{id}` | `quotation.delete` — เฉพาะ draft |
| `POST` | `/quotations/{id}/submit` | `quotation.submit` |
| `POST` | `/quotations/{id}/approve` | `quotation.approve` |
| `POST` | `/quotations/{id}/send` | `quotation.send` |
| `POST` | `/quotations/{id}/accept` | `quotation.decide` |
| `POST` | `/quotations/{id}/reject` | `quotation.decide` |
| `POST` | `/quotations/{id}/cancel` | `quotation.update` |
| `POST` | `/quotations/{id}/revise` | `quotation.revise` |
| `GET` | `/quotations/{id}/pdf?lang=th\|en` | `quotation.view` |

**Query ของ `/quotations`:** `search` `status` `customer_id` `from` `to` `expiring=1` `expiring_days` `exclude_superseded=1` `per_page`

**ตัวอย่าง `POST /quotations`**

```json
{
  "customer_id": 4,
  "customer_contact_id": 9,
  "customer_site_id": null,
  "issue_date": "2026-08-27",
  "valid_until": "2026-09-26",
  "price_tier": "standard",
  "vat_rate": "7.00",
  "discount_amount": "0",
  "payment_terms": "เครดิต 30 วัน",
  "items": [
    {
      "item_type": "product",
      "product_id": 12,
      "description": "UPS 10 kVA",
      "qty": "2",
      "unit_price": "25000",
      "discount_percent": "0"
    },
    {
      "item_type": "labour",
      "description": "ค่าแรงติดตั้ง",
      "qty": "1",
      "unit_price": "12000"
    }
  ]
}
```

`item_type` รับค่า: `product` `service` `labour` `freight` `note`
บรรทัด `product` ต้องมี `product_id` · บรรทัด `note` ไม่มีมูลค่า (ค่าที่ส่งมาถูกบังคับเป็นศูนย์)

**สิ่งที่เซิร์ฟเวอร์กำหนดเอง ไม่รับจาก payload**

- `cost_snapshot` — อ่านจาก `products.cost_price` เสมอ ([ADR-013](DECISIONS.md)) ไม่งั้นปลอม margin เพื่อเลี่ยงการอนุมัติได้
- `quote_no` — ออกผ่าน `NumberSequenceService` เท่านั้น
- ยอดเงินทั้งหมด — คำนวณใหม่ฝั่งเซิร์ฟเวอร์ด้วย bcmath ตามลำดับใน [CLAUDE.md ข้อ 4.2](../CLAUDE.md)

**`POST /quotations/{id}/send`** — ระบุ `email` เพื่อส่งเมลพร้อม PDF แนบ (เลือก `locale` เป็น `th`/`en` และใส่ `note` ได้) ไม่ระบุ = แค่บันทึกว่าส่งแล้ว

**`POST /quotations/{id}/revise`** — ตอบ `201` พร้อมใบใหม่ (`quote_no` เดิม, `revision` +1) และ `meta.superseded_quotation_id`
ใบเดิมยังคงสถานะเดิมไว้ แค่ถูกประทับ `superseded_at` ([ADR-002](DECISIONS.md))

**`accept` / `reject` / `cancel`** รับ `reason` (ไม่บังคับ) ซึ่งถูกเก็บลง `lost_reason`

---

### รายงาน

| Method | Path | สิทธิ์ |
|---|---|---|
| `GET` | `/reports/low-stock` | `stock.viewAny` |
| `GET` | `/reports/sales-summary?from=&to=` | `quotation.viewAny` |

`/reports/sales-summary` ไม่ระบุช่วง = เดือนปัจจุบันถึงวันนี้

```json
{
  "data": {
    "period": { "from": "2026-08-01", "to": "2026-08-27" },
    "quotations": { "total": 6, "open": 3, "accepted": 2, "rejected": 1, "expired": 0 },
    "amounts": {
      "quoted": "280000.00", "open": "30000.00",
      "accepted": "200000.00", "rejected": "50000.00",
      "accepted_margin": "66915.88"
    },
    "win_rate_percent": "66.67"
  },
  "meta": {
    "basis": "quotations",
    "currency": "THB",
    "amounts_include_vat": true,
    "win_rate_excludes_open": true
  }
}
```

> **`meta.basis`:** ตอนนี้คิดจากใบเสนอราคา เพราะตาราง `sales_orders` อยู่ใน Phase 4
> เมื่อ Phase 4 เสร็จจะ **เพิ่ม** ชุดตัวเลขจากใบสั่งขายเข้ามาเป็นฟิลด์ใหม่ ไม่แก้ความหมายของฟิลด์เดิม
> `win_rate` นับเฉพาะใบที่ลูกค้าตัดสินใจแล้ว (accepted + rejected) ใบที่ยังเปิดอยู่ไม่ถูกนับ

---

## 5. การมองเห็นข้อมูล

`sales` เห็นและแก้ได้เฉพาะใบเสนอราคาของตัวเอง — กรองที่ระดับ query ทั้ง `index`, `show` และรายงาน
`admin` และ `sales_manager` เห็นใบของทุกคน ([CLAUDE.md ข้อ 8](../CLAUDE.md))

การอนุมัติต้องมีคนที่สองเสมอ: ผู้อนุมัติต้องไม่ใช่เจ้าของใบ ยกเว้น `admin` (กันระบบล็อกตายในองค์กรเล็ก)

---

## 6. ยังไม่เปิด — รอ Phase 4

endpoint เหล่านี้อยู่ใน [CLAUDE.md ข้อ 6](../CLAUDE.md) แต่ต้องรอตาราง `sales_orders` / `deliveries` ที่จะสร้างใน Phase 4

```
POST   /api/v1/quotations/{id}/convert-to-so
GET    /api/v1/sales-orders
GET    /api/v1/sales-orders/{id}
POST   /api/v1/sales-orders/{id}/deliveries
POST   /api/v1/deliveries/{id}/post
```

เรียกตอนนี้จะได้ `404` เหมือน endpoint ที่ไม่มีอยู่จริง
