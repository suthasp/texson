<#
    Stop the portable MariaDB cleanly (flushes buffers first).
    Never kill mariadbd from Task Manager - InnoDB will need recovery on the next start.

    NOTE: keep this file pure ASCII - see the comment in db-start.ps1.
#>

$ErrorActionPreference = 'Stop'

$MariaBin = Join-Path $env:LOCALAPPDATA 'Programs\mariadb-11.4.5-winx64\bin'
$MyIni    = Join-Path $env:LOCALAPPDATA 'texson-mariadb\my.ini'

if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
    Write-Host 'MariaDB is not running' -ForegroundColor Yellow
    exit 0
}

& "$MariaBin\mariadb-admin.exe" --defaults-file="$MyIni" -h 127.0.0.1 -u root -ptexson_dev_2026 shutdown

for ($i = 1; $i -le 30; $i++) {
    Start-Sleep -Milliseconds 500
    if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
        Write-Host 'MariaDB stopped' -ForegroundColor Green
        exit 0
    }
}

throw 'MariaDB did not stop within 15 seconds'
