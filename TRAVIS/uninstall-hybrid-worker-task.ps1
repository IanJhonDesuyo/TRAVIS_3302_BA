$ErrorActionPreference = 'Stop'
foreach ($taskName in @('TRAVIS Hybrid Worker', 'TRAVIS ML Worker', 'TRAVIS CV Worker')) {
    if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
        Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Host "Removed: $taskName"
    }
}
