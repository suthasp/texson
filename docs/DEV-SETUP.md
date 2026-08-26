# ตั้งค่าเครื่องพัฒนา (Windows, ไม่มีสิทธิ์ admin)

เครื่องพัฒนาเครื่องแรกของโปรเจกต์นี้ไม่มีสิทธิ์ admin จึงติดตั้งทุกอย่างแบบ **per-user / portable** — ไม่แตะ Program Files และไม่ลง Windows service

> ถ้าเครื่องคุณมีสิทธิ์ admin จะลง PHP/MySQL ตามปกติก็ได้ ขอแค่ให้ตรงตามเวอร์ชันในตารางด้านล่าง

---

## สิ่งที่ติดตั้งไปแล้ว

| ซอฟต์แวร์ | เวอร์ชัน | ตำแหน่ง |
|---|---|---|
| PHP (NTS x64) | 8.3.32 | `%LOCALAPPDATA%\Programs\php-8.3` |
| Composer | 2.10.2 | `%LOCALAPPDATA%\Programs\composer` |
| MariaDB (portable) | 11.4.5 | `%LOCALAPPDATA%\Programs\mariadb-11.4.5-winx64` |
| ข้อมูล MariaDB + `my.ini` | — | `%LOCALAPPDATA%\texson-mariadb` |

ทั้ง PHP และ Composer ถูกเพิ่มใน **user PATH** แล้ว — เปิด terminal ใหม่แล้วสั่ง `php -v` / `composer -V` ได้ทันที

> **หมายเหตุ:** ข้อมูล MariaDB อยู่ใน `%LOCALAPPDATA%` **โดยตั้งใจ ไม่ใช่ในโฟลเดอร์โปรเจกต์** เพราะโปรเจกต์อยู่ใต้ OneDrive — ถ้าปล่อยให้ OneDrive sync ไฟล์ InnoDB ขณะเซิร์ฟเวอร์เขียนอยู่ ฐานข้อมูลจะพัง

---

## เริ่ม / หยุดฐานข้อมูล

```powershell
.\scripts\db-start.ps1     # เริ่ม (รอจน ping ผ่านแล้วค่อยคืน prompt)
.\scripts\db-stop.ps1      # หยุดอย่างปลอดภัย (flush buffer ก่อนปิด)
```

MariaDB แบบ portable **ไม่เริ่มเองตอนบูตเครื่อง** — ต้องสั่ง `db-start.ps1` ทุกครั้งก่อนใช้งานหรือรันเทสต์

**อย่าปิดด้วย Task Manager** — ใช้ `db-stop.ps1` เสมอ ไม่งั้น InnoDB อาจต้อง recover ตอนเปิดครั้งถัดไป

---

## บัญชีฐานข้อมูล

| บัญชี | รหัสผ่าน | ใช้ทำอะไร |
|---|---|---|
| `root@127.0.0.1` | `texson_dev_2026` | ดูแลระบบเฉพาะเครื่องพัฒนา |
| `texson@127.0.0.1` | `texson_dev_2026` | บัญชีที่แอปใช้ (`.env`) |

ฐานข้อมูล: `texson` (ใช้งานจริง) และ `texson_test` (สำหรับ `php artisan test` — `RefreshDatabase` จะล้างตารางทุกครั้งที่รัน)

> รหัสผ่านชุดนี้ใช้ได้เฉพาะเครื่องพัฒนาที่ผูก `bind-address=127.0.0.1` เท่านั้น **ห้ามใช้ซ้ำบนเซิร์ฟเวอร์จริง**

---

## ต่อจากศูนย์บนเครื่องใหม่

<details>
<summary>ขั้นตอนติดตั้ง PHP + Composer + MariaDB แบบ portable</summary>

```powershell
# ── PHP 8.3 (NTS x64) ──
$php = "$env:LOCALAPPDATA\Programs\php-8.3"
Invoke-WebRequest 'https://windows.php.net/downloads/releases/archives/php-8.3.32-nts-Win32-vs16-x64.zip' -OutFile "$env:TEMP\php.zip"
Expand-Archive "$env:TEMP\php.zip" -DestinationPath $php
Copy-Item "$php\php.ini-development" "$php\php.ini"
# แก้ php.ini: ตั้ง extension_dir แบบ absolute แล้วเปิด extension เหล่านี้
#   bcmath curl fileinfo gd intl mbstring exif openssl pdo_mysql pdo_sqlite sqlite3 sodium zip mysqli
#   memory_limit = 512M · upload_max_filesize = 10M · post_max_size = 12M · date.timezone = Asia/Bangkok

# ── Composer ──
Invoke-WebRequest 'https://getcomposer.org/installer' -OutFile "$env:TEMP\composer-setup.php"
# ตรวจ sha384 กับ https://composer.github.io/installer.sig ก่อนรันเสมอ
php "$env:TEMP\composer-setup.php" --install-dir="$env:LOCALAPPDATA\Programs\composer" --filename=composer.phar

# ── MariaDB 11.4.5 portable ──
$root = "$env:LOCALAPPDATA\texson-mariadb"
Invoke-WebRequest 'https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip' -OutFile "$env:TEMP\mariadb.zip"
Expand-Archive "$env:TEMP\mariadb.zip" -DestinationPath "$env:LOCALAPPDATA\Programs"
& "$env:LOCALAPPDATA\Programs\mariadb-11.4.5-winx64\bin\mariadb-install-db.exe" --datadir="$root\data" --password=texson_dev_2026 --port=3306
```

**กับดักที่เจอมาแล้ว:** ใน `my.ini` ต้องเขียน path ด้วย **slash หน้า (`/`)** ไม่ใช่ backslash — MariaDB ตีความ `\t` ในคำว่า `\texson-mariadb` เป็นตัว Tab แล้วหา datadir ไม่เจอ

</details>

---

## รันเทสต์

```powershell
.\scripts\db-start.ps1
php artisan test
```

เทสต์ใช้ `texson_test` (ตั้งไว้ใน `phpunit.xml`) จึงไม่แตะข้อมูลใน `texson`

---

## ข้อควรระวังเรื่อง OneDrive

โปรเจกต์อยู่ใต้ `D:\OneDrive\...` ซึ่ง `vendor/` และ `node_modules/` มีไฟล์หลายหมื่นไฟล์ ทำให้ OneDrive sync ช้าและกิน CPU

แนะนำให้สั่ง OneDrive ข้ามสองโฟลเดอร์นี้ (คลิกขวาที่โฟลเดอร์ → *Always keep on this device* ปิด หรือใช้ **Settings → Sync and backup → Advanced settings** เพื่อยกเว้น) — ทั้งสองโฟลเดอร์อยู่ใน `.gitignore` อยู่แล้วและสร้างใหม่ได้ด้วย `composer install` / `npm install`
