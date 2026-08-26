<#
    เริ่ม MariaDB แบบ portable สำหรับเครื่องพัฒนา (ไม่ต้องใช้สิทธิ์ admin, ไม่ลง Windows service)
    ใช้คู่กับ scripts\db-stop.ps1 — รายละเอียดอยู่ที่ docs/DEV-SETUP.md
#>

$ErrorActionPreference = 'Stop'

$MariaBin = Join-Path $env:LOCALAPPDATA 'Programs\mariadb-11.4.5-winx64\bin'
$DataRoot = Join-Path $env:LOCALAPPDATA 'texson-mariadb'
$MyIni    = Join-Path $DataRoot 'my.ini'

if (-not (Test-Path "$MariaBin\mariadbd.exe")) {
    throw "ไม่พบ MariaDB ที่ $MariaBin — ดูขั้นตอนติดตั้งที่ docs/DEV-SETUP.md"
}
if (-not (Test-Path $MyIni)) {
    throw "ไม่พบไฟล์ตั้งค่า $MyIni — ดูขั้นตอนติดตั้งที่ docs/DEV-SETUP.md"
}

if (Get-Process mariadbd -ErrorAction SilentlyContinue) {
    Write-Host 'MariaDB ทำงานอยู่แล้ว' -ForegroundColor Yellow
    exit 0
}

Start-Process -FilePath "$MariaBin\mariadbd.exe" -ArgumentList "--defaults-file=`"$MyIni`"" -WindowStyle Hidden

for ($i = 1; $i -le 30; $i++) {
    Start-Sleep -Milliseconds 500
    & "$MariaBin\mariadb-admin.exe" --defaults-file="$MyIni" -h 127.0.0.1 -u root -ptexson_dev_2026 ping *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host 'MariaDB พร้อมใช้งานที่ 127.0.0.1:3306' -ForegroundColor Green
        exit 0
    }
}

throw "MariaDB เริ่มไม่ขึ้น — ดู log ที่ $DataRoot\error.log"
