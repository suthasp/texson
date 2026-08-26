# TEXSON Service & Parts Platform

ระบบหลังบ้านสำหรับ **TEXSON** — ที่ปรึกษา Data Center Facility (Audit / PM / CM / Training) และจำหน่ายอุปกรณ์–อะไหล่ (UPS, แบตเตอรี่, CRAC/Precision Air, Rack, PDU, ATS/Generator, Monitoring, Fire Suppression)

**Phase 1 (ปัจจุบัน):** Spare Part Inventory + Quotation/Sales — ใช้งานภายในองค์กร
**Phase 2 (อนาคต):** Work Order PM/CM, Asset Register, Contract/SLA — ดู [docs/PHASE2-NOTES.md](docs/PHASE2-NOTES.md)

สเปกฉบับเต็มอยู่ที่ [CLAUDE.md](CLAUDE.md) · ERD อยู่ที่ [docs/ERD.md](docs/ERD.md)

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
| API | Laravel Sanctum (REST v1) |
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

### เครื่องพัฒนาบน Windows (ไม่มีสิทธิ์ admin)

โปรเจกต์นี้ตั้งค่าให้ใช้ MariaDB แบบ portable (ไม่ลง service) — ดูขั้นตอนและสคริปต์ start/stop ที่ [docs/DEV-SETUP.md](docs/DEV-SETUP.md)

---

## คำสั่งที่ใช้บ่อย

| คำสั่ง | ทำอะไร |
|---|---|
| `php artisan serve` | รันเว็บที่ port 8000 |
| `npm run dev` | Vite dev server (hot reload) |
| `php artisan test` | รัน Pest ทั้งชุด (ใช้ DB `texson_test`) |
| `php artisan test --filter=Quotation` | รันเฉพาะเทสต์ที่ชื่อตรง |
| `vendor/bin/pint` | จัดรูปแบบโค้ด — **รันก่อน commit ทุกครั้ง** |
| `vendor/bin/pint --test` | ตรวจอย่างเดียว ไม่แก้ไฟล์ (ใช้ใน CI) |
| `php artisan migrate:fresh --seed` | ล้าง DB แล้วสร้างใหม่พร้อมข้อมูลตัวอย่าง |

---

## โครงสร้างโค้ด

```
app/
├── Models/                 # Eloquent + relations + scopes เท่านั้น
├── Services/               # Business logic ทั้งหมด
├── Http/
│   ├── Controllers/Web/    # Blade CRUD
│   ├── Controllers/Api/V1/ # REST
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
| 1 | Master data: users/roles, customers, categories, brands, suppliers, warehouses, products | ⬜ |
| 2 | Inventory: stock levels, ledger, รับเข้า, ปรับปรุง, โอนคลัง, serial | ⬜ |
| 3 | Quotation: CRUD, คำนวณ, lifecycle, revision, อนุมัติ, PDF ไทย/อังกฤษ | ⬜ |
| 4 | Sales Order → Delivery → ตัดสต็อก + serial + backorder | ⬜ |
| 5 | Dashboard + รายงาน + Excel export | ⬜ |
| 6 | Hardening + `docs/SECURITY.md` + คู่มือผู้ใช้ | ⬜ |

---

## License

Proprietary — TEXSON Co., Ltd.
