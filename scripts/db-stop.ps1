<#
    หยุด MariaDB แบบ portable อย่างปลอดภัย (flush buffer ก่อนปิด — ห้าม kill process ตรง ๆ)
#>

$ErrorActionPreference = 'Stop'

$MariaBin = Join-Path $env:LOCALAPPDATA 'Programs\mariadb-11.4.5-winx64\bin'
$MyIni    = Join-Path $env:LOCALAPPDATA 'texson-mariadb\my.ini'

if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
    Write-Host 'MariaDB ไม่ได้ทำงานอยู่' -ForegroundColor Yellow
    exit 0
}

& "$MariaBin\mariadb-admin.exe" --defaults-file="$MyIni" -h 127.0.0.1 -u root -ptexson_dev_2026 shutdown

for ($i = 1; $i -le 30; $i++) {
    Start-Sleep -Milliseconds 500
    if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
        Write-Host 'MariaDB หยุดแล้ว' -ForegroundColor Green
        exit 0
    }
}

throw 'MariaDB ยังไม่หยุดภายใน 15 วินาที'
