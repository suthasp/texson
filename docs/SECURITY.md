# SECURITY — TEXSON Service & Parts Platform

เอกสารนี้ตอบ checklist ความปลอดภัยข้อ 8 ของสเปกทีละข้อ พร้อมชี้ว่าโค้ดอยู่ไฟล์ไหน
และเทสต์ตัวไหนพิสูจน์ ผู้ตรวจสอบควรอ่านคู่กับผลของ `php artisan texson:security-check`

**ตรวจก่อนขึ้น production ทุกครั้ง**

```bash
php artisan texson:security-check --production
php artisan test
```

คำสั่งแรกคืน exit code 1 เมื่อไม่ผ่าน จึงเสียบเข้า deploy pipeline ได้ตรง ๆ

---

## สรุป checklist ข้อ 8

| # | ข้อกำหนด | สถานะ | หลักฐาน |
|---|---|---|---|
| 1 | Eloquent/Query Builder ทุก query | ✅ | สแกนทั้ง `app/` เหลือ raw 2 จุด ทั้งคู่เป็นสตริงคงที่ ไม่มี input · `SecurityTest` |
| 2 | Policy + `authorize()` ทุก action | ✅ | 16 Policy · `AuthorizationTest`, `*ScreensTest` |
| 3 | รหัสผ่าน ≥ 10 ตัว, hash ด้วย bcrypt | ✅ | `AppServiceProvider::configurePasswordPolicy()` · `HardeningTest` |
| 4 | Session secure/httponly/strict + regenerate | ✅ | `config/session.php` · `HardeningTest` |
| 5 | CSRF ทุก POST · Blade `{{ }}` เสมอ | ✅ | ไม่มี `{!! !!}` ทั้งโปรเจกต์ · `SecurityTest` |
| 6 | Upload: mime จริง ≤10MB ชื่อสุ่ม เก็บ private | ✅ | `SettingRequest`, `ConvertQuotationRequest` · `HardeningTest` |
| 7 | Security header + CSP | ✅ | `app/Http/Middleware/SecurityHeaders.php` · `SecurityHeadersTest` |
| 8 | Rate limit login 5/นาที/IP + lockout | ✅ | `LoginRequest::ensureIsNotRateLimited()` · `SecurityTest` |
| 9 | Error production เป็นข้อความกลาง | ✅ | `bootstrap/app.php` · `HardeningTest` |
| 10 | `.env` ใน `.gitignore` + `.env.example` ครบ | ✅ | `texson:security-check` เทียบคีย์สองไฟล์ให้ |
| 11 | PDPA: log เข้าถึง/แก้ไข, soft delete + ลบถาวร, ส่งออกรายคน | ✅ | `PersonalDataService` · `PdpaTest` (18 เทสต์) |
| 12 | Audit trail พร้อม before-after | ✅ | spatie/activitylog + `/activity` · `AuditTrailTest` |

---

## 1. การเข้าถึงฐานข้อมูล

ทุก query ผ่าน Eloquent หรือ Query Builder ซึ่ง bind ค่าให้อัตโนมัติ
ทั้งโปรเจกต์เหลือ raw SQL สองจุด และทั้งคู่เป็นสตริงคงที่ที่ไม่มีค่าจากผู้ใช้ปนอยู่:

- `StockLevel::scopeBelowMinimum()` — `whereRaw('(qty_on_hand - qty_reserved) < products.min_stock')`
- `DashboardController` — `orderByRaw('required_date is null, required_date')`

**ช่องค้นหาและการเรียงลำดับ** เป็นจุดที่รับ input ตรงที่สุด การเรียงใช้ whitelist
ผ่าน `SortsListings::applySort()` — คอลัมน์ที่ไม่อยู่ในรายการถูกทิ้งแล้วใช้ค่า default
ไม่ใช่ error `SecurityTest` ยิง payload 4 แบบ (`'; DROP TABLE products; --` ฯลฯ)
แล้วยืนยันว่าตารางยังอยู่และข้อมูลไม่หาย

---

## 2. Authorization

**16 Policy** ครอบทุกโมเดล และทุก action เรียก `authorize()` ก่อนแตะข้อมูลเสมอ
สิทธิ์ทั้งหมดมาจาก `PermissionName` enum ผูกกับ role ใน `RolePermissionSeeder`

`admin` ได้ทุกสิทธิ์แบบระบุชัด **ไม่ใช้ `Gate::before`** เพื่อให้ข้อห้ามใน Policy
(เช่น ห้ามลบบัญชีตัวเอง ห้ามอนุมัติใบของตัวเอง) ยังทำงานกับ admin ด้วย

**การมองเห็นข้อมูล** ฝ่ายขายเห็นเฉพาะใบของตัวเอง ผู้จัดการและ admin เห็นทั้งหมด
บังคับสองชั้นที่ต้องตรงกันเสมอ:

- `scopeVisibleTo()` — กรองรายการในหน้า list และไฟล์ส่งออก
- `Policy::owns()` — กันการเปิดใบรายตัวด้วยการเดา URL

> สองอย่างนี้เคยไม่ตรงกันมาแล้ว (Phase 4) — เจ้าหน้าที่คลังได้ list ว่าง
> ทั้งที่เปิดใบรายตัวได้ ถ้าแก้อันหนึ่งต้องแก้อีกอันเสมอ

**403 กับ 409 แยกกันชัดเจน** (ADR-014): "ไม่มีสิทธิ์" ตอบ 403 ส่วน "สถานะไม่ถูกต้อง"
ตอบ 409 ทุก ability ที่ดูสถานะจึงมีคู่แฝด `...Any` ที่ดูแค่สิทธิ์กับความเป็นเจ้าของ

---

## 3. รหัสผ่านและการล็อกอิน

```php
Password::min(10)->letters()->numbers()   // + ->uncompromised() บน production
```

- hash ด้วยค่า default ของ Laravel (bcrypt, `BCRYPT_ROUNDS=12`)
- `uncompromised()` เปิดเฉพาะ production — ตรวจกับฐาน haveibeenpwned ผ่าน k-anonymity
  ไม่ส่งรหัสผ่านออกไป แต่ต้องต่อเน็ตได้ จึงปิดตอน dev/test
- **rate limit 5 ครั้ง/นาที** ต่อคู่ (อีเมล + IP) แล้ว lockout พร้อม event `Lockout`
- **บัญชีที่ปิดใช้งานล็อกอินไม่ได้** และได้ข้อความเดียวกับรหัสผิด เพื่อไม่ให้เดาได้ว่า
  อีเมลนี้มีอยู่จริง
- **session regenerate หลังล็อกอิน** กัน session fixation (Breeze default, มีเทสต์ยืนยัน)

---

## 4. Session

| ค่า | ตั้งไว้ | เหตุผล |
|---|---|---|
| `http_only` | `true` | JavaScript อ่าน cookie ไม่ได้ |
| `same_site` | `strict` | ไม่ส่ง cookie ไปกับ request ที่มาจากเว็บอื่นเลย |
| `secure` | `true` เมื่อ `APP_ENV=production` | ค่า default คำนวณจาก env ไม่ต้องรอให้คนตั้ง |
| `encrypt` | `true` | เนื้อใน session ถูกเข้ารหัสก่อนลงฐานข้อมูล |
| `driver` | `database` | เพิกถอน session ได้ทันทีเมื่อพนักงานลาออก |

`same_site=strict` แปลว่าลิงก์จากอีเมลเข้าหน้าใน ๆ จะเด้งไปหน้า login ก่อนเสมอ
นั่นคือพฤติกรรมที่ต้องการสำหรับระบบภายในองค์กร

---

## 5. XSS และ CSRF

- **ไม่มี `{!! !!}` ทั้งโปรเจกต์** ทุกค่าออกทาง `{{ }}` ซึ่ง escape ให้เสมอ
- CSRF middleware ผูกกับกลุ่ม `web` ทั้งกลุ่ม ทุกฟอร์มมี `@csrf`
- API ใช้ Sanctum token ไม่ใช้ cookie จึงไม่มีผิว CSRF

---

## 6. ไฟล์อัปโหลด

| เรื่อง | ทำอย่างไร |
|---|---|
| ตรวจชนิดไฟล์ | `mimetypes:` ของ Laravel อ่านเนื้อไฟล์จริงผ่าน `finfo` **ไม่ใช่นามสกุล** |
| ขนาด | โลโก้/ลายเซ็น ≤ 2 MB · ไฟล์ใบสั่งซื้อลูกค้า ≤ 10 MB |
| ชื่อไฟล์ | `store()` ตั้งชื่อสุ่ม (hash) ชื่อเดิมของผู้ใช้ไม่ถูกนำมาใช้เลย |
| ที่เก็บ | disk `private` → `storage/app/private` อยู่นอก document root |
| การเปิดอ่าน | ผ่าน controller ที่ `authorize()` ก่อนเสมอ — **ไม่มี `public/storage` symlink** |

> เทสต์เขียนไฟล์ PHP จริงลงดิสก์แล้วตั้งชื่อเป็น `.png` เพื่อพิสูจน์ว่ามีการตรวจ
> เนื้อไฟล์จริง — `UploadedFile::fake()` เดา mime จากนามสกุล ซึ่งจะทำให้เทสต์ผ่านฟรี ๆ

---

## 7. Security header

ติดกับทุก response ทั้งเว็บและ API ผ่าน `SecurityHeaders` middleware (global)

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: same-origin
X-Permitted-Cross-Domain-Policies: none
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()
Strict-Transport-Security: max-age=31536000; includeSubDomains   (เฉพาะบน HTTPS)
```

**Content-Security-Policy**

```
default-src 'self'; script-src 'self' 'unsafe-eval';
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:;
connect-src 'self'; form-action 'self'; frame-ancestors 'none';
base-uri 'self'; object-src 'none'
```

ข้อผ่อนปรนสองข้อ และเหตุผล:

- **`unsafe-eval`** — Alpine 3 คอมไพล์ expression ด้วย `new Function()` ถอดออกได้ต้อง
  ย้ายไป Alpine CSP build ซึ่งต้องรื้อ Blade ทุกไฟล์ (ADR-025)
- **`unsafe-inline` เฉพาะ style** — ความสูงแท่งกราฟในหน้ารายงานคำนวณจากข้อมูล
  ค่าเหล่านั้นเป็นตัวเลขที่ระบบสร้างเอง ไม่ใช่ข้อความจากผู้ใช้

**`script-src` ไม่มี `unsafe-inline`** ซึ่งเป็นข้อที่สำคัญที่สุด และมีเทสต์เฝ้าไว้
พร้อมเทสต์ที่สแกน Blade ทุกไฟล์ว่าไม่มี inline event handler หลงเหลือ — ถ้ามีคนเขียน
`onclick=` กลับเข้ามา เบราว์เซอร์จะบล็อกเงียบ ๆ และปุ่มยืนยันก่อนลบจะหายไปโดยไม่มี error

**ตอน dev** CSP เปิดช่องให้ Vite dev server เฉพาะเมื่อมีไฟล์ `public/hot`
production ไม่มีบรรทัดนั้น

---

## 8. Error handling บน production

`APP_DEBUG=false` แล้ว Laravel แสดงหน้า error กลาง ส่วนรายละเอียดลง log ฝั่งเซิร์ฟเวอร์
(`LOG_STACK=daily`) ฝั่ง API `bootstrap/app.php` แปลง exception เป็น JSON รูปแบบคงที่:

| สถานการณ์ | HTTP | body |
|---|---|---|
| ไม่มี token | 401 | `{"message": "..."}` |
| ไม่มีสิทธิ์ | 403 | `{"message": "..."}` |
| ไม่พบข้อมูล | 404 | `{"message": "ไม่พบ X ที่ระบุ"}` |
| validation | 422 | `{"message": "...", "errors": {...}}` |
| ของไม่พอ | 422 | `{"message": "...", "shortages": [...]}` |
| เปลี่ยนสถานะข้ามขั้น | 409 | `{"message": "..."}` |

ทุกกรณีอ่าน `message` ได้จากที่เดียวเสมอ และไม่มี HTML หลุดออกไป

---

## 9. PDPA

### ข้อมูลส่วนบุคคลที่ระบบเก็บ

| ตาราง | ฟิลด์ |
|---|---|
| `customers` | ชื่อ, เลขผู้เสียภาษี, ที่อยู่, โทรศัพท์, อีเมล, บันทึกภายใน |
| `customer_contacts` | ชื่อ, ตำแหน่ง, โทรศัพท์, อีเมล, LINE ID |
| `customer_sites` | `access_note` (มักมีชื่อ รปภ./ผู้ดูแลอาคารปนอยู่) |
| `users` | ชื่อ, อีเมล, รหัสพนักงาน, โทรศัพท์ |

### สิทธิ์ของเจ้าของข้อมูล

**ขอเข้าถึง / ขอสำเนา** — หน้า `/customers/{id}/personal-data` แสดงทุกอย่างที่ระบบเก็บไว้
พร้อมปุ่มดาวน์โหลดเป็น JSON (UTF-8 อ่านภาษาไทยได้) ซึ่งเป็นรูปแบบที่นำไปใช้ต่อด้วย
เครื่องได้ตามที่กฎหมายกำหนด

**ขอให้ลบ** — ปุ่มในหน้าเดียวกัน ต้องพิมพ์รหัสลูกค้ายืนยันและระบุเหตุผล สงวนไว้ให้
`customer.forceDelete` (admin เท่านั้น) พฤติกรรมขึ้นกับว่ามีเอกสารผูกอยู่หรือไม่ (ADR-024):

- **ไม่มีเอกสาร** → ลบออกจากตารางจริง
- **มีเอกสาร** → ล้างข้อมูลส่วนบุคคลทั้งหมด เก็บ `code` ยอดเงิน และเลขที่เอกสารไว้
  เพราะประมวลรัษฎากรบังคับให้เก็บเอกสารภาษี 5 ปี · ชื่อในใบกลายเป็น `[ลบตามคำขอ PDPA]`

ทำแล้วย้อนไม่ได้ ลูกค้าที่ถูกล้างแล้วกู้คืนไม่ได้ และ activity log บันทึกแต่ *จำนวน*
ที่ถูกลบ ไม่ได้บันทึกค่า — ไม่งั้นก็เท่ากับไม่ได้ลบ

### บันทึกการเข้าถึงและแก้ไข

| การกระทำ | บันทึก | ความถี่ |
|---|---|---|
| เปิดหน้าลูกค้าที่มีผู้ติดต่อ | `pdpa` / `accessed` | ยุบวันละครั้งต่อคนต่อลูกค้า (ADR-026) |
| ดาวน์โหลดสำเนา | `pdpa` / `exported` | ทุกครั้ง |
| ลบตามคำขอ | `pdpa` / `anonymized` | ทุกครั้ง พร้อมเหตุผล |
| แก้ไขข้อมูลลูกค้า/ผู้ติดต่อ | `default` / `updated` | ทุกครั้ง พร้อมค่าก่อน-หลัง |

ทุกรายการเก็บ IP ของผู้เข้าถึงไว้ด้วย และดูได้ที่ท้ายหน้า `/customers/{id}/personal-data`

### soft delete

`customers` และ `products`, `quotations` ใช้ soft delete ลูกค้าที่ถูกลบยังกู้กลับได้
คนที่มีสิทธิ์ลบถาวรเห็นตัวกรอง "ที่ถูกลบแล้ว" ในหน้ารายการเพื่อค้นหาเมื่อคำขอมาถึงทีหลัง

---

## 10. Audit trail

spatie/activitylog บันทึก 17 โมเดล ด้วย `logOnlyDirty()` — บันทึกเฉพาะฟิลด์ที่เปลี่ยนจริง
พร้อมค่า `old` และ `attributes` ครบทั้งคู่

หน้าอ่านอยู่ที่ **`/activity`** (สิทธิ์ `activity.viewAny` — admin และผู้จัดการฝ่ายขาย)
กรองได้ตามประเภทบันทึก การกระทำ ประเภทข้อมูล ผู้ทำรายการ และช่วงเวลา
ตารางแสดงค่าก่อน/หลังทีละฟิลด์

**อ่านอย่างเดียวเสมอ** — ไม่มี route ที่เป็น POST/PUT/DELETE ใต้ `/activity` เลย
และมีเทสต์เฝ้าไว้ ไม่งั้น audit trail ก็เชื่อถือไม่ได้

สิ่งที่สเปกบังคับให้มี และมีจริง:

- เปลี่ยนสถานะเอกสาร (ใบเสนอราคา ใบสั่งขาย ใบส่งของ เอกสารคลัง)
- ปรับสต็อก (นอกเหนือจาก `stock_movements` ledger ที่ append-only อยู่แล้ว)
- แก้ราคา (`list_price`, `cost_price`, ราคาต่อ tier)

---

## 11. REST API

- ยืนยันตัวตนด้วย Sanctum token (`Authorization: Bearer …`)
- `throttle:60,1` ทุก endpoint · `throttle:5,1` เฉพาะ endpoint ออก token
- token ผูกกับ ability ตาม role ของผู้ใช้ และ Policy ยังทำงานเหมือนฝั่งเว็บทุกประการ
- `ForceJsonResponse` บังคับให้ทุก request ใต้ `/api` ได้ JSON เสมอ ไม่หลุด HTML หน้า login

---

## 12. รายการที่ยังไม่ได้ทำ

โปร่งใสไว้ก่อน — ข้อเหล่านี้ยังเปิดอยู่ ไม่ได้บังคับในสเปกข้อ 8 แต่ควรพิจารณา:

| เรื่อง | สถานะ | หมายเหตุ |
|---|---|---|
| ฟอนต์ Prompt โหลดจาก Google Fonts | เปิดอยู่ | IP ของพนักงานถึง Google · แก้ได้ด้วยการ self-host แล้วตัดสองโดเมนออกจาก CSP |
| 2FA | ยังไม่มี | สเปกไม่ได้ขอ · แนะนำเมื่อระบบเปิดออกนอกอินทราเน็ต |
| นโยบายเก็บ activity log | ยังไม่มี | ตารางจะโตเรื่อย ๆ · ควรมีนโยบายลบ/ย้ายเมื่อขึ้นใช้จริง |
| สแกน dependency อัตโนมัติ | ยังไม่มี | `composer audit` + `npm audit` ควรอยู่ใน CI |
| สำรองข้อมูลและซ้อมกู้คืน | นอกขอบเขต | เป็นงานฝั่ง infrastructure |

---

## แจ้งช่องโหว่

พบปัญหาด้านความปลอดภัย กรุณาแจ้งผู้ดูแลระบบภายในองค์กรโดยตรง **อย่าเปิดเป็น issue สาธารณะ**
