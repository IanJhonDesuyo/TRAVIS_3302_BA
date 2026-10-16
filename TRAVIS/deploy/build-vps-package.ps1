[CmdletBinding()]
param(
    [string]$OutputDirectory = '',
    [string]$ZipPath = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$OutputDirectory = if ($OutputDirectory) { $OutputDirectory } else { Join-Path $PSScriptRoot 'build-vps\TRAVIS' }
$ZipPath = if ($ZipPath) { $ZipPath } else { Join-Path $PSScriptRoot 'build-vps\TRAVIS-vps.zip' }
$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
$zipFile = [System.IO.Path]::GetFullPath($ZipPath)
$buildRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot 'build-vps'))

if (-not $outputRoot.StartsWith($buildRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'OutputDirectory must remain inside deploy\build-vps.'
}

if (Test-Path -LiteralPath $outputRoot) {
    Remove-Item -LiteralPath $outputRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null

$appRoot = Join-Path $outputRoot 'app'
New-Item -ItemType Directory -Path $appRoot -Force | Out-Null

$sourceDirectories = @('api', 'assets', 'config', 'css', 'js', 'Web_app')
foreach ($directory in $sourceDirectories) {
    $source = Join-Path $projectRoot $directory
    $destination = Join-Path $appRoot $directory
    Copy-Item -LiteralPath $source -Destination $destination -Recurse -Force
}

Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination $appRoot -Force

# The hosted Random Forest and K-Means service is built as a small, isolated
# Python container. Include only its API, trained artifacts, result data, and
# VPS dependency list; the camera/YOLO stack remains on the gate computer.
$mlRoot = Join-Path $outputRoot 'Machine_Learning'
New-Item -ItemType Directory -Path $mlRoot -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot 'Machine_Learning\api.py') -Destination $mlRoot -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'Machine_Learning\requirements-vps.txt') -Destination $mlRoot -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'Machine_Learning\models') -Destination $mlRoot -Recurse -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'Machine_Learning\results') -Destination $mlRoot -Recurse -Force

# User-generated files belong in the persistent runtime mounts, not in the
# immutable application image or deployment archive.
$generatedUploadDirectories = @(
    (Join-Path $appRoot 'Web_app\uploads'),
    (Join-Path $appRoot 'assets\uploads\announcements')
)
foreach ($directory in $generatedUploadDirectories) {
    if (Test-Path -LiteralPath $directory) {
        Remove-Item -LiteralPath $directory -Recurse -Force
    }
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}

$sensitiveFiles = @(
    (Join-Path $appRoot 'config\local.php'),
    (Join-Path $appRoot 'storage\service_api.key')
)
foreach ($file in $sensitiveFiles) {
    if (Test-Path -LiteralPath $file) { Remove-Item -LiteralPath $file -Force }
}

$vpsSource = Join-Path $projectRoot 'deploy\vps'
$vpsDestination = Join-Path $outputRoot 'deploy\vps'
New-Item -ItemType Directory -Path (Split-Path -Parent $vpsDestination) -Force | Out-Null
Copy-Item -LiteralPath $vpsSource -Destination $vpsDestination -Recurse -Force
Copy-Item -LiteralPath (Join-Path $vpsSource 'compose.yaml') -Destination (Join-Path $outputRoot 'compose.yaml') -Force
Copy-Item -LiteralPath (Join-Path $vpsSource '.env.example') -Destination (Join-Path $outputRoot '.env.example') -Force
Copy-Item -LiteralPath (Join-Path $vpsSource '.dockerignore') -Destination (Join-Path $outputRoot '.dockerignore') -Force
Copy-Item -LiteralPath (Join-Path $vpsSource 'README.md') -Destination (Join-Path $outputRoot 'README.md') -Force

$databaseInit = Join-Path $outputRoot 'database-init'
New-Item -ItemType Directory -Path $databaseInit -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot 'database\travis.sql') -Destination (Join-Path $databaseInit '001-travis.sql') -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'database\hybrid_jobs.sql') -Destination (Join-Path $databaseInit '002-hybrid-jobs.sql') -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'database\public_contacts.sql') -Destination (Join-Path $databaseInit '003-public-contacts.sql') -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'database\camera_registry.sql') -Destination (Join-Path $databaseInit '004-camera-registry.sql') -Force

$runtimeDirectories = @(
    'runtime\storage\cache',
    'runtime\web-uploads\logs',
    'runtime\public-uploads',
    'runtime\cv-uploads\videos',
    'runtime\calibration-profiles'
)
foreach ($directory in $runtimeDirectories) {
    $path = Join-Path $outputRoot $directory
    New-Item -ItemType Directory -Path $path -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $path '.gitkeep') -Force | Out-Null
}

if (Test-Path -LiteralPath $zipFile) { Remove-Item -LiteralPath $zipFile -Force }
New-Item -ItemType Directory -Path (Split-Path -Parent $zipFile) -Force | Out-Null
$tar = Get-Command tar.exe -ErrorAction Stop
& $tar.Source -a -c -f $zipFile -C $outputRoot .
if ($LASTEXITCODE -ne 0) {
    throw "tar.exe failed to create the deployment ZIP (exit code $LASTEXITCODE)."
}

Write-Host "VPS package created: $outputRoot"
Write-Host "ZIP created: $zipFile"
Write-Host 'No .env, local.php, service API key, mobile source, or database password was included.'

