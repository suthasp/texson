<#
    Start the portable MariaDB used for development.
    No admin rights needed and no Windows service is installed.
    See docs/DEV-SETUP.md for the full setup.

    NOTE: keep this file pure ASCII. Windows PowerShell 5.1 decodes .ps1 files
    using the system ANSI codepage unless they carry a UTF-8 BOM, so non-ASCII
    text here turns into mojibake and breaks parsing on Thai-locale machines.
#>

$ErrorActionPreference = 'Stop'

$MariaBin = Join-Path $env:LOCALAPPDATA 'Programs\mariadb-11.4.5-winx64\bin'
$DataRoot = Join-Path $env:LOCALAPPDATA 'texson-mariadb'
$MyIni    = Join-Path $DataRoot 'my.ini'

if (-not (Test-Path "$MariaBin\mariadbd.exe")) {
    throw "MariaDB not found at $MariaBin - see docs/DEV-SETUP.md for install steps"
}
if (-not (Test-Path $MyIni)) {
    throw "Config file not found at $MyIni - see docs/DEV-SETUP.md for install steps"
}

if (Get-Process mariadbd -ErrorAction SilentlyContinue) {
    Write-Host 'MariaDB is already running' -ForegroundColor Yellow
    exit 0
}

Start-Process -FilePath "$MariaBin\mariadbd.exe" -ArgumentList "--defaults-file=`"$MyIni`"" -WindowStyle Hidden

# The first few pings are expected to fail while the server is still booting.
# ErrorActionPreference must be relaxed here: Windows PowerShell 5.1 turns a
# native command's stderr into a terminating error under 'Stop', which would
# abort the whole script on that first attempt even though the server is fine.
$ErrorActionPreference = 'Continue'

for ($i = 1; $i -le 30; $i++) {
    Start-Sleep -Milliseconds 500

    & "$MariaBin\mariadb-admin.exe" --defaults-file="$MyIni" -h 127.0.0.1 -u root -ptexson_dev_2026 ping 2>&1 | Out-Null

    if ($LASTEXITCODE -eq 0) {
        Write-Host 'MariaDB is ready on 127.0.0.1:3306' -ForegroundColor Green
        exit 0
    }
}

Write-Host "MariaDB failed to start - check the log at $DataRoot\error.log" -ForegroundColor Red
exit 1
