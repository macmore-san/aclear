# Support-visit check: lists any shipped code file that was edited or deleted since release.
#   powershell -ExecutionPolicy Bypass -File verify-release.ps1
Set-Location $PSScriptRoot
$changed = Get-Content release-manifest.sha256 | ForEach-Object {
    $hash, $path = $_ -split '  ', 2
    if (-not (Test-Path $path)) { "MISSING   $path" }
    elseif ((Get-FileHash $path -Algorithm SHA256).Hash -ne $hash) { "MODIFIED  $path" }
}
if ($changed) { $changed; exit 1 }
'All shipped files match the release manifest.'
