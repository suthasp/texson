# TEXSON Service & Parts Platform

ระบบหลังบ้านสำหรับ **TEXSON** — ที่ปรึกษา Data Center Facility (Audit / PM / CM / Training) และจำหน่ายอุปกรณ์–อะไหล่ (UPS, แบตเตอรี่, CRAC/Precision Air, Rack, PDU, ATS/Generator, Monitoring, Fire Suppression)

**Phase 1 (ปัจจุบัน):** Spare Part Inventory + Quotation/Sales — ใช้งานภายในองค์กร
สายงานเต็มเส้น: ใบเสนอราคา → ใบสั่งขาย → ใบส่งของ → ตัดสต็อก พร้อม serial และ backorder
**Phase 2 (อนาคต):** Work Order PM/CM, Asset Register, Contract/SLA — ดู [docs/PHASE2-NOTES.md](docs/PHASE2-NOTES.md)

สเปกฉบับเต็มอยู่ที่ [CLAUDE.md](CLAUDE.md) · ERD อยู่ที่ [docs/ERD.md](docs/ERD.md) · REST API อยู่ที่ [docs/API.md](docs/API.md)

---

## Tech stack

| ส่วน | เลือกใช้ |
|---|---|
| Framework | Laravel 12.x ([เหตุผลที่ไม่ใช้ 11](docs/DECISIONS.md)) |
| PHP | 8.3 — `declare(strict_types=1);` ทุกไฟล์ใน `app/` |
| DB | MySQL 8.0 / MariaDB 10.6+ · `utf8mb4_unicode_ci` |
| UI | Blade + Tailwind CSS 3 + Alpine.js · Laravel Breeze (Blade stack) |
| Auth | Breeze + `spatie/laravel-permission` |
| PDF | `barryvdh/laravel-dompdf` + ฟอนต์ Sarabun |
| Excel | `maatwebsite/excel` |
| Audit | `spatie/laravel-activitylog` |
| API | Laravel Sanctum (REST v1) — ดู [docs/API.md](docs/API.md) |
| Test | Pest 3 + `RefreshDatabase` |
| Style | Laravel Pint (preset laravel) |

---

## เริ่มใช้งาน

### สิ่งที่ต้องมี

- PHP 8.3 พร้อม extension: `bcmath` `curl` `fileinfo` `gd` `intl` `mbstring` `openssl` `pdo_mysql` `zip`
- Composer 2.x
- MySQL 8.0 หรือ MariaDB 10.6+
- Node.js 20+ / npm

### ติดตั้ง

```bash
composer install
npm install

cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# แก้ DB_DATABASE / DB_USERNAME / DB_PASSWORD ใน .env ให้ตรงกับเครื่อง
php artisan migrate --seed

npm run build                 # หรือ npm run dev ระหว่างพัฒนา
php artisan serve
```

เปิด <http://localhost:8000>

### ฟอนต์ภาษาไทยของ PDF

ฟอนต์ **Sarabun** (สัญญาอนุญาต OFL) ถูก commit ไว้ที่ [resources/fonts/sarabun/](resources/fonts/sarabun/) แล้ว ไม่ต้องติดตั้งเพิ่ม
`QuotationPdfService` ลงทะเบียนฟอนต์กับ dompdf ตอนเรนเดอร์ เหตุผลอยู่ใน [ADR-011](docs/DECISIONS.md)

### งานตามเวลา

ใบเสนอราคาที่เลยวันยืนราคาต้องถูกเปลี่ยนเป็นหมดอายุทุกเช้า 06:00 — บน production ให้ตั้ง cron

```
* * * * * cd /path/to/texson && php artisan schedule:run >> /dev/null 2>&1
```

### บัญชีตัวอย่างหลัง `--seed`

`UserSeeder` สร้างผู้ใช้หนึ่งคนต่อหนึ่ง role ไว้ทดสอบสิทธิ์ — **รหัสผ่านทุกบัญชีคือ `texson1234`** และ seeder จะข้ามตัวเองอัตโนมัติเมื่อ `APP_ENV=production`

| อีเมล | บทบาท | เห็นอะไร |
|---|---|---|
| `admin@texson.local` | ผู้ดูแลระบบ | ทุกอย่าง รวมถึงจัดการผู้ใช้ ตั้งค่าระบบ และลบถาวรตาม PDPA |
| `manager@texson.local` | ผู้จัดการฝ่ายขาย | งานขายทั้งหมด · **อนุมัติใบเสนอราคา** · เห็นใบของ sales ทุกคน |
| `sales1@texson.local` | ฝ่ายขาย | ลูกค้าแบบเต็ม · ใบเสนอราคาและใบสั่งขายเฉพาะของตัวเอง · เห็นราคาทุนเพื่อคุม margin ([ADR-012](docs/DECISIONS.md)) |
| `warehouse@texson.local` | คลังสินค้า | สินค้า ผู้ขาย หมวดหมู่ ยี่ห้อ คลัง · เอกสารคลังและ ledger · **ออกและตัดสต็อกใบส่งของ** |
| `engineer@texson.local` | วิศวกร | ดูสินค้า ลูกค้า ยอดคงเหลือ และ ledger เพื่อเตรียมงานหน้างาน |
| `viewer@texson.local` | ผู้ดูอย่างเดียว | อ่านได้ทุกหน้าที่ได้รับสิทธิ์ แก้ไม่ได้เลย |

> ระบบนี้**ไม่เปิดให้สมัครสมาชิกเอง** — ผู้ใช้ถูกสร้างโดยผู้ดูแลระบบที่หน้า *ตั้งค่า → ผู้ใช้งาน* เท่านั้น

### เครื่องพัฒนาบน Windows (ไม่มีสิทธิ์ admin)

โปรเจกต์นี้ตั้งค่าให้ใช้ MariaDB แบบ portable (ไม่ลง service) — ดูขั้นตอนและสคริปต์ start/stop ที่ [docs/DEV-SETUP.md](docs/DEV-SETUP.md)

---

## คำสั่งที่ใช้บ่อย

| คำสั่ง | ทำอะไร |
|---|---|
| `php artisan serve` | รันเว็บที่ port 8000 |
| `npm run dev` | Vite dev server (hot reload) |
| `php artisan test` | รัน Pest ทั้งชุด (ใช้ DB `texson_test`) |
| `php artisan test --filter=Inventory` | รันเฉพาะเทสต์ที่ชื่อตรง |
| `vendor/bin/pint` | จัดรูปแบบโค้ด — **รันก่อน commit ทุกครั้ง** |
| `vendor/bin/pint --test` | ตรวจอย่างเดียว ไม่แก้ไฟล์ (ใช้ใน CI) |
| `php artisan migrate:fresh --seed` | ล้าง DB แล้วสร้างใหม่พร้อมข้อมูลตัวอย่าง |
| `php artisan quotations:expire` | เปลี่ยนใบเสนอราคาที่เลยวันยืนราคาเป็นหมดอายุ (ตั้งเวลาไว้ 06:00 ทุกวัน) |
| `php artisan schedule:work` | รัน scheduler บนเครื่องพัฒนา |
| `php artisan route:list --path=api` | ดู endpoint ทั้งหมดของ REST API |

---

## โครงสร้างโค้ด

```
app/
├── Models/                 # Eloquent + relations + scopes เท่านั้น
├── Services/               # Business logic ทั้งหมด
├── Http/
│   ├── Controllers/Web/    # Blade CRUD
│   ├── Controllers/Api/V1/ # REST v1 (Sanctum token)
│   ├── Requests/           # FormRequest ทุก endpoint ที่รับ input
│   └── Resources/          # API Resource ทุก response
├── Policies/               # Authorization ทุก model
├── Enums/                  # Backed enum ทุก status field
├── Actions/                # Single-purpose action
└── Exceptions/Domain/      # Domain exception
```

**กฎเหล็ก** — Controller บาง (validate → service → response) · งานที่แตะสต็อกหรือหลายตารางต้องอยู่ใน `DB::transaction()` · เลขเอกสารออกผ่าน `NumberSequenceService` เท่านั้น · money = `decimal(15,2)` ห้าม float · ทุก mutation เขียน activity log

---

## ความคืบหน้า

| เฟส | งาน | สถานะ |
|---|---|---|
| 0 | Scaffolding, Breeze, Pint, Pest, packages, `.env.example` | ✅ เสร็จ |
| 1 | Master data: users/roles, customers+contacts+sites, categories, brands, suppliers, warehouses, products | ✅ เสร็จ |
| 2 | Inventory: stock levels, ledger, รับเข้า, ปรับปรุง, โอนคลัง, serial, low-stock | ✅ เสร็จ |
| 3 | Quotation: CRUD, คำนวณ, lifecycle, revision, อนุมัติ, PDF ไทย/อังกฤษ, ส่งเมล | ✅ เสร็จ |
| — | REST API v1 (สเปกข้อ 6) — ครบทุก endpoint | ✅ เสร็จ |
| 4 | Sales Order → Delivery → ตัดสต็อก + serial + backorder | ✅ เสร็จ |
| 5 | Dashboard + รายงาน + Excel export | ⬜ |
| 6 | Hardening + `docs/SECURITY.md` + คู่มือผู้ใช้ | ⬜ |

---

## License

Proprietary — TEXSON Co., Ltd.
