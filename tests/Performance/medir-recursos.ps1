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
$previos=@{}
$nucleos=[Environment]::ProcessorCount
while ($generador) {
    $memoria = Get-CimInstance Win32_PerfFormattedData_PerfOS_Memory
    $cpu = Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'"
    $ids = @([int]$m.mysql_pid,[int]$m.apache_pid,$PidK6)
    $hijos = @(Get-CimInstance Win32_Process | Where-Object { $_.ParentProcessId -in $ids -and $_.Name -in @('httpd.exe','mysqld.exe','php.exe') })
    $ids += @($hijos.ProcessId)
    $procesos = @(Get-Process -Id ($ids | Select-Object -Unique) -ErrorAction SilentlyContinue)
    $ahora=[DateTime]::UtcNow
    foreach ($proceso in $procesos) {
        $porcentaje=0
        if ($previos.ContainsKey($proceso.Id)) {
            $previo=$previos[$proceso.Id]
            $segundos=($ahora-$previo.fecha).TotalSeconds
            if ($segundos -gt 0) { $porcentaje=100*($proceso.CPU-$previo.cpu)/($segundos*$nucleos) }
        }
        [pscustomobject]@{fecha_utc=$ahora.ToString('o');pid=$proceso.Id;proceso=$proceso.ProcessName;cpu_proceso_pct=$porcentaje;
            cpu_equipo_pct=$cpu.PercentProcessorTime;memoria_disponible_mb=$memoria.AvailableMBytes;
            working_set_bytes=$proceso.WorkingSet64;memoria_privada_bytes=$proceso.PrivateMemorySize64;cpu_acumulada_segundos=$proceso.CPU
        } | Export-Csv -LiteralPath $SalidaNueva -Append -NoTypeInformation -Encoding utf8
        $previos[$proceso.Id]=@{fecha=$ahora;cpu=$proceso.CPU}
    }
    if ($memoria.AvailableMBytes -lt 1024) { $bajaMemoria++ } else { $bajaMemoria=0 }
    $generador = Get-CimInstance Win32_Process -Filter ('ProcessId='+$PidK6)
    if ($bajaMemoria -ge 5 -or ([DateTime]::UtcNow-$inicio).TotalSeconds -gt 600) {
        if ($generador -and $generador.Name -eq 'k6.exe' -and $generador.CreationDate -eq $creacion) { Stop-Process -Id $PidK6; throw 'ENSAYO INTERRUMPIDO: menos de 1024 MiB libres durante cinco muestras o límite de 10 minutos. Conservar la evidencia.' }
    }
    if ($generador -and $generador.CreationDate -ne $creacion) { throw 'PID reutilizado; no se detendrá ningún proceso.' }
    Start-Sleep -Seconds 2
}
