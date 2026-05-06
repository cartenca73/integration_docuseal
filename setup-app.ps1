# Setup script for the integration_docuseal Nextcloud app inside the
# nc_docuseal container. Run from the repo root after `docker-compose up -d`.

$ErrorActionPreference = 'Stop'

$container = 'nc_docuseal'
$appId     = 'integration_docuseal'
$repoRoot  = $PSScriptRoot
if ([string]::IsNullOrEmpty($repoRoot)) {
    $repoRoot = (Get-Location).Path
}

Write-Host "Waiting for Nextcloud to finish installing..." -ForegroundColor Cyan
$maxAttempts = 60
$attempt     = 0
while ($true) {
    $attempt++
    try {
        $status = docker exec --user www-data $container php occ status 2>$null
    } catch {
        $status = $null
    }
    if ($status -and ($status -join "`n") -match 'installed:\s*true') {
        Write-Host "Nextcloud is installed." -ForegroundColor Green
        break
    }
    if ($attempt -ge $maxAttempts) {
        throw "Timed out waiting for Nextcloud to install (after $maxAttempts attempts)."
    }
    Start-Sleep -Seconds 5
}

Write-Host "Copying app source into ${container}:/var/www/html/custom_apps/$appId/ ..." -ForegroundColor Cyan
docker exec $container mkdir -p "/var/www/html/custom_apps/$appId"
docker cp "$repoRoot/." "${container}:/var/www/html/custom_apps/$appId/"

Write-Host "Fixing ownership..." -ForegroundColor Cyan
docker exec $container chown -R www-data:www-data /var/www/html/custom_apps/

Write-Host "Enabling app $appId ..." -ForegroundColor Cyan
docker exec --user www-data $container php occ app:enable $appId

Write-Host ""
Write-Host "Done." -ForegroundColor Green
Write-Host "Nextcloud: http://localhost:9191  (admin / admin123)" -ForegroundColor Yellow
Write-Host "DocuSeal:  http://localhost:4040" -ForegroundColor Yellow
