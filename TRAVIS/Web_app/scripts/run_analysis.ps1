$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$WebAppDir = Split-Path -Parent $ScriptDir
$ProjectRoot = Split-Path -Parent $WebAppDir
$ApiDir = Join-Path $WebAppDir "api"
$CvDir = Join-Path $ProjectRoot "computer_vision"
$StatusFile = Join-Path $ApiDir "analysis_status.json"
$LogDir = Join-Path $WebAppDir "uploads\logs"
$LogFile = Join-Path $LogDir "analysis_latest.log"
$PythonExe = Join-Path $ProjectRoot ".venv\Scripts\python.exe"

if (!(Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

if (!(Test-Path $PythonExe)) {
    $PythonExe = "python"
}

function Write-AnalysisStatus {
    param (
        [string]$Status,
        [string]$Message
    )

    $payload = [ordered]@{
        analysis_status = $Status
        ai_status = $Status
        message = $Message
        pid = $PID
        current_frame = $null
        total_frames = $null
        percent_complete = $null
        updated_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
        updated_at_epoch = [int][double]::Parse((Get-Date -UFormat %s))
    }

    if ($Status -eq "Completed") {
        $payload.completed_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    }

    if ($Status -eq "Running") {
        $payload.started_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    }

    $payload | ConvertTo-Json | Set-Content -Path $StatusFile -Encoding UTF8
}

try {
    Write-AnalysisStatus "Running" "AI analysis is running."

    Push-Location $CvDir
    & $PythonExe "detect_video.py" *> $LogFile
    $exitCode = $LASTEXITCODE
    Pop-Location

    if ($exitCode -eq 0) {
        Write-AnalysisStatus "Completed" "AI analysis completed."
    } else {
        Write-AnalysisStatus "Error" "AI analysis stopped with exit code $exitCode."
    }
} catch {
    Write-AnalysisStatus "Error" $_.Exception.Message
    try {
        Pop-Location
    } catch {
    }
}
