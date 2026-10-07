param(
    [int]$WebPort = 8889,
    [int]$IcePort = 8189
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$binary = Join-Path $PSScriptRoot 'mediamtx\mediamtx.exe'
$cameraConfigPath = Join-Path $projectRoot 'computer_vision\camera_config.json'
$runtimeConfigPath = Join-Path $projectRoot 'storage\mediamtx-test.yml'
$logPath = Join-Path $projectRoot 'storage\mediamtx-test.log'
$errorLogPath = Join-Path $projectRoot 'storage\mediamtx-test-error.log'

if (-not (Test-Path -LiteralPath $binary)) {
    throw 'MediaMTX is not installed under tools\mediamtx.'
}
if (-not (Test-Path -LiteralPath $cameraConfigPath)) {
    throw 'The local camera_config.json file does not exist.'
}

$camera = Get-Content -LiteralPath $cameraConfigPath -Raw | ConvertFrom-Json
if (-not $camera.host -or -not $camera.username -or -not $camera.password) {
    throw 'The saved Tapo host or Camera Account credentials are incomplete.'
}

$username = [Uri]::EscapeDataString([string]$camera.username)
$password = [Uri]::EscapeDataString([string]$camera.password)
$stream = if ([string]$camera.stream -eq 'stream1') { 'stream1' } else { 'stream2' }
$rtspUrl = "rtsp://${username}:${password}@$($camera.host):554/$stream"

$localAddresses = @('127.0.0.1')
[Net.Dns]::GetHostAddresses([Net.Dns]::GetHostName()) |
    Where-Object {
        $_.AddressFamily -eq [Net.Sockets.AddressFamily]::InterNetwork `
            -and $_.IPAddressToString -notlike '169.254.*' `
            -and $_.IPAddressToString -ne '127.0.0.1'
    } |
    ForEach-Object { $localAddresses += $_.IPAddressToString }
$localAddresses = $localAddresses | Sort-Object -Unique
$additionalHosts = ($localAddresses | ForEach-Object { "  - $_" }) -join "`n"

$yaml = @"
logLevel: info
rtsp: false
rtmp: false
hls: false
srt: false
playback: false
api: false
metrics: false
pprof: false
webrtc: true
webrtcAddress: :$WebPort
webrtcLocalUDPAddress: :$IcePort
webrtcAdditionalHosts:
$additionalHosts

paths:
  tapo-test:
    source: "$rtspUrl"
    rtspTransport: tcp
    sourceOnDemand: true
    sourceOnDemandStartTimeout: 15s
    sourceOnDemandCloseAfter: 10s
"@

[IO.File]::WriteAllText($runtimeConfigPath, $yaml, [Text.UTF8Encoding]::new($false))

$existing = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -eq 'mediamtx.exe' -and $_.CommandLine -like "*$runtimeConfigPath*" }
if (-not $existing) {
    Start-Process -FilePath $binary `
        -ArgumentList @($runtimeConfigPath) `
        -WorkingDirectory (Split-Path -Parent $binary) `
        -RedirectStandardOutput $logPath `
        -RedirectStandardError $errorLogPath `
        -WindowStyle Hidden
}

Write-Output "WebRTC test: http://127.0.0.1:$WebPort/tapo-test"
Write-Output "LAN test: http://$($localAddresses | Where-Object { $_ -ne '127.0.0.1' } | Select-Object -First 1):$WebPort/tapo-test"
