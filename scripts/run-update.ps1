# Applies a staged AClear release. Launched detached by the admin Updates page;
# never run inside the web request, which this script's own extract would kill.
#
#   powershell -ExecutionPolicy Bypass -File run-update.ps1 -Zip <path> -App <path>
#
# scripts/update.bat remains the manual fallback for when the app won't boot far
# enough to reach the Updates page.
param(
    [Parameter(Mandatory = $true)][string]$Zip,
    [Parameter(Mandatory = $true)][string]$App
)

$ErrorActionPreference = 'Stop'

$stateDir = Join-Path $env:ProgramData 'AClear'
$statusFile = Join-Path $stateDir 'update-status.json'
$rollbackDir = Join-Path $stateDir 'rollback'
$stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$tar = Join-Path $env:SystemRoot 'System32\tar.exe'

New-Item -ItemType Directory -Force $stateDir, $rollbackDir | Out-Null

$version = ''
if (Test-Path (Join-Path $App 'VERSION')) { $version = (Get-Content (Join-Path $App 'VERSION') -Raw).Trim() }

$script:state = [ordered]@{
    state = 'running'; step = 0; total = 6; label = ''
    version = $version; started_at = (Get-Date).ToString('o'); finished_at = $null
    error = $null; backup = $null; rollback = $null
}

function Set-Status([int]$Step, [string]$Label) {
    $script:state.step = $Step
    $script:state.label = $Label
    $script:state | ConvertTo-Json | Set-Content $statusFile -Encoding utf8
}

function Stop-With([string]$Message) {
    $script:state.state = 'failed'
    $script:state.error = $Message
    $script:state.finished_at = (Get-Date).ToString('o')
    $script:state | ConvertTo-Json | Set-Content $statusFile -Encoding utf8
    # Always try to lift maintenance mode — a half-updated app the owner can still
    # reach beats a 503 with nobody on site.
    & php artisan up 2>&1 | Out-Null
    exit 1
}

function Invoke-Step([int]$Step, [string]$Label, [scriptblock]$Command) {
    Set-Status $Step $Label
    & $Command 2>&1 | Out-String | Write-Verbose
    if ($LASTEXITCODE -ne 0) { Stop-With "$Label failed (exit $LASTEXITCODE)" }
}

# The request that launched this is still finishing and may hold handles on files
# the extract is about to replace.
Start-Sleep -Seconds 3

Set-Location $App

try {
    Invoke-Step 1 'Putting AClear into maintenance mode...' { & php artisan down }

    # app:backup prints "Backup saved to <dir>" — keep that path in the status so a
    # failed update tells the operator exactly which backup to restore from.
    Set-Status 2 'Backing up the database...'
    $backupOutput = & php artisan app:backup 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) { Stop-With "Database backup failed: $($backupOutput.Trim())" }
    if ($backupOutput -match 'Backup saved to (.+)') { $script:state.backup = $Matches[1].Trim() }

    # Snapshot before any file is touched. update.bat backs up only the database, so
    # a bad release currently leaves no way back to the previous code — acceptable
    # when a developer ran it on site, not when the owner triggers it alone.
    $snapshot = Join-Path $rollbackDir "$stamp.zip"
    Invoke-Step 3 'Saving a rollback snapshot...' {
        & $tar -a -c -f $snapshot -C $App --exclude=storage --exclude=node_modules .
    }
    $script:state.rollback = $snapshot

    Invoke-Step 4 'Installing new files...' { & $tar -x -f $Zip -C $App }

    Invoke-Step 5 'Updating the database...' { & php artisan migrate --force }

    Invoke-Step 6 'Refreshing caches...' {
        & php artisan optimize:clear
        & php artisan optimize
    }

    & php artisan up
    if ($LASTEXITCODE -ne 0) { Stop-With 'Could not bring the app out of maintenance mode.' }

    Remove-Item $Zip -Force -ErrorAction SilentlyContinue

    $newVersion = $script:state.version
    if (Test-Path (Join-Path $App 'VERSION')) { $newVersion = (Get-Content (Join-Path $App 'VERSION') -Raw).Trim() }

    $script:state.state = 'done'
    $script:state.version = $newVersion
    $script:state.label = 'Update complete.'
    $script:state.finished_at = (Get-Date).ToString('o')
    $script:state | ConvertTo-Json | Set-Content $statusFile -Encoding utf8
}
catch {
    Stop-With $_.Exception.Message
}
