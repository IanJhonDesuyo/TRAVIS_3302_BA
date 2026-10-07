$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path $PSScriptRoot).Path
$pythonw = Join-Path $projectRoot '.venv\Scripts\pythonw.exe'
$worker = Join-Path $projectRoot 'tools\hybrid_worker.py'
$keyFile = Join-Path $projectRoot 'storage\service_api.key'
$legacyTaskName = 'TRAVIS Hybrid Worker'

foreach ($requiredPath in @($pythonw, $worker, $keyFile)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required file not found: $requiredPath"
    }
}

$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$settings = New-ScheduledTaskSettingsSet `
    -RestartCount 10 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable
$principal = New-ScheduledTaskPrincipal `
    -UserId $env:USERNAME `
    -LogonType Interactive `
    -RunLevel Limited

if (Get-ScheduledTask -TaskName $legacyTaskName -ErrorAction SilentlyContinue) {
    Stop-ScheduledTask -TaskName $legacyTaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $legacyTaskName -Confirm:$false
}

$workers = @(
    @{ Name = 'TRAVIS ML Worker'; Id = "$env:COMPUTERNAME-ml"; Types = 'ml_monthly,ml_hotspots' },
    @{ Name = 'TRAVIS CV Worker'; Id = "$env:COMPUTERNAME-cv"; Types = 'cv_start,cv_stop' }
)

foreach ($definition in $workers) {
    $arguments = '"' + $worker + '" --worker-id "' + $definition.Id + '" --types "' + $definition.Types + '"'
    $action = New-ScheduledTaskAction -Execute $pythonw -Argument $arguments -WorkingDirectory $projectRoot
    $task = New-ScheduledTask -Action $action -Trigger $trigger -Settings $settings -Principal $principal
    Register-ScheduledTask -TaskName $definition.Name -InputObject $task -Force | Out-Null
    Start-ScheduledTask -TaskName $definition.Name
    Write-Host "Installed and started: $($definition.Name)"
}

Write-Host "Worker log: $projectRoot\storage\hybrid-worker.log"
