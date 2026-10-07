[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$buildRoot = Join-Path $PSScriptRoot 'build'
$packageRoot = Join-Path $buildRoot 'TRAVIS'
$archivePath = Join-Path $PSScriptRoot 'TRAVIS-staging.zip'

$resolvedDeploy = (Resolve-Path $PSScriptRoot).Path
$expectedBuildPrefix = $resolvedDeploy.TrimEnd('\') + '\build'
if (-not $buildRoot.StartsWith($expectedBuildPrefix, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Refusing to clean an unexpected build directory.'
}

if (Test-Path -LiteralPath $buildRoot) {
    Remove-Item -LiteralPath $buildRoot -Recurse -Force
}
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

$webDirectories = @('api', 'assets', 'config', 'css', 'js', 'Web_app')
foreach ($directory in $webDirectories) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $packageRoot -Recurse
}

$webRuntimeUploads = Join-Path $packageRoot 'Web_app\uploads'
if (Test-Path -LiteralPath $webRuntimeUploads) {
    Remove-Item -LiteralPath $webRuntimeUploads -Recurse -Force
}

Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination $packageRoot

$calibrationTarget = Join-Path $packageRoot 'computer_vision\calibration_profiles'
New-Item -ItemType Directory -Path $calibrationTarget -Force | Out-Null
Copy-Item -Path (Join-Path $projectRoot 'computer_vision\calibration_profiles\*') -Destination $calibrationTarget -Recurse
if (Test-Path -LiteralPath (Join-Path $calibrationTarget '.archive')) {
    Remove-Item -LiteralPath (Join-Path $calibrationTarget '.archive') -Recurse -Force
}

$storageTarget = Join-Path $packageRoot 'storage'
$cacheTarget = Join-Path $storageTarget 'cache'
New-Item -ItemType Directory -Path $cacheTarget -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot 'storage\.htaccess') -Destination $storageTarget

$forbiddenFiles = @(
    (Join-Path $packageRoot 'config\local.php'),
    (Join-Path $packageRoot 'storage\service_api.key')
)
foreach ($forbiddenFile in $forbiddenFiles) {
    if (Test-Path -LiteralPath $forbiddenFile) {
        throw "Secret file entered the deployment package: $forbiddenFile"
    }
}

Get-ChildItem -LiteralPath $packageRoot -Recurse -Directory -Filter '__pycache__' |
    Remove-Item -Recurse -Force
$forbiddenExtensions = @('.py', '.pyc', '.pt', '.pkl')
$forbiddenNames = @('.env', 'service_api.key', 'local.php')
Get-ChildItem -LiteralPath $packageRoot -Recurse -File |
    Where-Object {
        $forbiddenExtensions -contains $_.Extension.ToLowerInvariant() -or
        $forbiddenNames -contains $_.Name.ToLowerInvariant()
    } |
    ForEach-Object { throw "Forbidden deployment file found: $($_.FullName)" }

Compress-Archive -LiteralPath $packageRoot -DestinationPath $archivePath -CompressionLevel Optimal
Write-Host "Created $archivePath"
