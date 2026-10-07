param(
    [Parameter(Mandatory = $true)][string]$PythonExe,
    [Parameter(Mandatory = $true)][string]$DetectScript,
    [Parameter(Mandatory = $true)][string]$WorkingDirectory,
    [Parameter(Mandatory = $true)][string]$LogFile,
    [Parameter(Mandatory = $true)][ValidateSet('uploaded_video', 'tapo_camera', 'phone_camera')][string]$SourceType,
    [string]$CalibrationProfile = ''
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $PythonExe -PathType Leaf)) {
    throw "Python executable not found."
}
if (-not (Test-Path -LiteralPath $DetectScript -PathType Leaf)) {
    throw "Detector script not found."
}

# A venv launch can appear as both a launcher and child Python process. If any
# detector command already targets this exact script, join it instead of
# opening another RTSP session or competing for Flask port 5000.
$normalizedDetectScript = [IO.Path]::GetFullPath($DetectScript)
$existingDetector = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
        ($_.Name -eq 'python.exe' -or $_.Name -eq 'pythonw.exe') -and
        $_.CommandLine -and
        $_.CommandLine.IndexOf($normalizedDetectScript, [StringComparison]::OrdinalIgnoreCase) -ge 0
    } |
    Sort-Object CreationDate |
    Select-Object -First 1
if ($existingDetector) {
    Write-Output $existingDetector.ProcessId
    exit 0
}

$logDirectory = Split-Path -Parent $LogFile
if (-not (Test-Path -LiteralPath $logDirectory -PathType Container)) {
    New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
}

$errorLog = [IO.Path]::Combine(
    $logDirectory,
    ([IO.Path]::GetFileNameWithoutExtension($LogFile) + '_error.log')
)
$arguments = @('-u', ('"' + $DetectScript + '"'), '--source-type', $SourceType)
if ($CalibrationProfile) {
    $arguments += @('--calibration-profile', ('"' + $CalibrationProfile + '"'))
}

$process = Start-Process `
    -FilePath $PythonExe `
    -ArgumentList $arguments `
    -WorkingDirectory $WorkingDirectory `
    -RedirectStandardOutput $LogFile `
    -RedirectStandardError $errorLog `
    -WindowStyle Hidden `
    -PassThru

Write-Output $process.Id
