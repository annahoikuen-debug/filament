# Log viewer script - tail laravel.log
Set-Location $PSScriptRoot\..

$logFile = "storage\logs\laravel.log"
if (-not (Test-Path $logFile)) {
    New-Item -ItemType File -Path $logFile -Force | Out-Null
}

Write-Host "Monitoring Laravel log: $logFile (Ctrl+C to stop)" -ForegroundColor Cyan
Get-Content -Path $logFile -Tail 30 -Wait
