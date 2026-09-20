# Builds a stripped, client-ready release zip from a git tag (or any ref).
#   powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0
#   powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0-rc1 -Ref HEAD
param(
    [Parameter(Mandatory = $true)][string]$Version,
    [string]$Ref = "v$Version"
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$work = Join-Path $env:TEMP "aclear-release-$Version"
$dist = Join-Path $repo 'dist'
$zip = Join-Path $dist "aclear-v$Version.zip"

function Invoke-Step([string]$Label, [scriptblock]$Command) {
    Write-Host "==> $Label"
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed (exit $LASTEXITCODE)" }
}

if (Test-Path $work) { Remove-Item $work -Recurse -Force }
git -C $repo worktree prune
Invoke-Step "Checking out $Ref" { git -C $repo worktree add --detach $work $Ref }

try {
    Push-Location $work

    # The Vite build runs `php artisan wayfinder:generate`, which needs a bootable app.
    Copy-Item .env.example .env

    Invoke-Step 'composer install (no dev)' { composer install --no-dev --optimize-autoloader --no-interaction }
    Invoke-Step 'npm ci' { npm ci }
    Invoke-Step 'npm run build' { npm run build }

    Copy-Item scripts\update.bat, scripts\verify-release.ps1, scripts\run-update.ps1 -Destination .

    $strip = @(
        'node_modules', 'tests', '.github', '.git', 'scripts', 'resources\js', 'resources\css',
        '.env', '.env.example', 'CLAUDE.md', 'DESIGN.md', 'PRODUCT.md', 'README.md',
        'phpunit.xml', 'phpstan.neon', 'pint.json', 'vite.config.ts', 'tsconfig.json',
        'package.json', 'package-lock.json', 'components.json', 'skills-lock.json', '.editorconfig',
        '.gitattributes', '.gitignore', '.npmrc',
        '.impeccable', '.claude', '.agents', 'public\hot', 'public\fonts-manifest.dev.json'
    )
    foreach ($path in $strip) {
        if (Test-Path $path) { Remove-Item $path -Recurse -Force }
    }

    # config/app.php reads this, the Updates page compares against it, and the
    # uploaded zip's copy is what run-update.ps1 reports as the new version.
    Set-Content VERSION $Version -Encoding ascii -NoNewline

    # Hashes of the code a client could edit — `verify-release.ps1` flags any change on a support visit.
    $prefix = (Resolve-Path $work).Path.TrimEnd('\') + '\'
    Get-ChildItem app, bootstrap\app.php, config, routes, resources\views -Recurse -File |
        Get-FileHash -Algorithm SHA256 |
        ForEach-Object { '{0}  {1}' -f $_.Hash, $_.Path.Substring($prefix.Length).Replace('\', '/') } |
        Set-Content release-manifest.sha256 -Encoding ascii

    Pop-Location

    New-Item -ItemType Directory -Force $dist | Out-Null
    if (Test-Path $zip) { Remove-Item $zip -Force }
    # Windows' own bsdtar keeps dotfiles like public\.htaccess and handles long vendor paths. Full path:
    # a bare `tar` can resolve to Git's GNU tar, which reads `C:\...` as a remote host.
    $tar = Join-Path $env:SystemRoot 'System32\tar.exe'
    Invoke-Step 'Zipping' { & $tar -a -c -f $zip -C $work . }

    Write-Host "Release ready: $zip"
}
finally {
    if ((Get-Location).Path -eq $work) { Pop-Location }
    Remove-Item $work -Recurse -Force -ErrorAction SilentlyContinue
    git -C $repo worktree prune
}
