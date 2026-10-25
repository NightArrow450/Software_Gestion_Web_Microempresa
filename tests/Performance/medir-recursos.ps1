param([Parameter(Mandatory)][string]$Manifiesto, [Parameter(Mandatory)][int]$PidK6,
      [Parameter(Mandatory)][string]$SalidaNueva)
$ErrorActionPreference = 'Stop'
if (Test-Path -LiteralPath $SalidaNueva) { throw 'No se sobrescriben evidencias.' }
$m = Get-Content -LiteralPath $Manifiesto -Raw | ConvertFrom-Json
$generador = Get-CimInstance Win32_Process -Filter ('ProcessId='+$PidK6)
if (-not $generador -or $generador.Name -ne 'k6.exe') { throw 'PID del generador no válido.' }
$creacion = $generador.CreationDate
$bajaMemoria = 0
$inicio = [DateTime]::UtcNow
while ($generador) {
    $memoria = Get-CimInstance Win32_PerfFormattedData_PerfOS_Memory
    $cpu = Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'"
    $ids = @([int]$m.mysql_pid,[int]$m.apache_pid,$PidK6)
    $hijos = @(Get-CimInstance Win32_Process | Where-Object { $_.ParentProcessId -in $ids -and $_.Name -in @('httpd.exe','mysqld.exe','php.exe') })
    $ids += @($hijos.ProcessId)
    $procesos = @(Get-Process -Id ($ids | Select-Object -Unique) -ErrorAction SilentlyContinue)
    [pscustomobject]@{fecha_utc=[DateTime]::UtcNow.ToString('o');cpu_equipo_pct=$cpu.PercentProcessorTime;
        memoria_disponible_mb=$memoria.AvailableMBytes;memoria_procesos_bytes=($procesos.WorkingSet64|Measure-Object -Sum).Sum;
        cpu_procesos_segundos=($procesos.CPU|Measure-Object -Sum).Sum} | Export-Csv -LiteralPath $SalidaNueva -Append -NoTypeInformation -Encoding utf8
    if ($memoria.AvailableMBytes -lt 512) { $bajaMemoria++ } else { $bajaMemoria=0 }
    $generador = Get-CimInstance Win32_Process -Filter ('ProcessId='+$PidK6)
    if ($bajaMemoria -ge 15 -or ([DateTime]::UtcNow-$inicio).TotalSeconds -gt 600) {
        if ($generador -and $generador.Name -eq 'k6.exe' -and $generador.CreationDate -eq $creacion) { Stop-Process -Id $PidK6; throw 'ENSAYO INTERRUMPIDO: memoria agotada o límite de 10 minutos. Conservar la evidencia.' }
    }
    if ($generador -and $generador.CreationDate -ne $creacion) { throw 'PID reutilizado; no se detendrá ningún proceso.' }
    Start-Sleep -Seconds 2
}
