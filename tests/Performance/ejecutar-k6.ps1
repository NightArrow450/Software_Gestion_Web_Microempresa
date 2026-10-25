param([Parameter(Mandatory)][ValidateSet('humo','carga','estres')][string]$Escenario,
      [Parameter(Mandatory)][string]$SalidaPrivadaNueva)
$ErrorActionPreference='Stop'
foreach ($sufijo in @('', '.resumen.json', '.series.json', '.stdout.txt', '.stderr.txt', '.recursos.csv')) {
    if (Test-Path -LiteralPath ($SalidaPrivadaNueva+$sufijo)) { throw 'No se sobrescriben evidencias.' }
}
if (-not $env:RENDIMIENTO_CONFIG -or -not $env:RENDIMIENTO_MANIFIESTO -or -not $env:RENDIMIENTO_CLAVE_CONTROL -or -not $env:RENDIMIENTO_CUENTAS) { throw 'Faltan las variables privadas de autorización.' }
$bootstrap = Join-Path $PSScriptRoot 'configuracion/privada-bootstrap.php'
& php -d "auto_prepend_file=$bootstrap" (Join-Path $PSScriptRoot 'verificar-entorno.php') medir
if ($LASTEXITCODE -ne 0) { throw 'No se iniciará k6: identidad no verificada.' }
$generador=Get-Command k6 -ErrorAction Stop
$memoria=Get-CimInstance Win32_PerfFormattedData_PerfOS_Memory
$minimaMiB = switch ($Escenario) { 'humo' { 2048 }; 'carga' { 5120 }; 'estres' { 6144 } }
if ($memoria.AvailableMBytes -lt $minimaMiB) { throw "No se iniciará $Escenario con menos de $minimaMiB MiB libres. Requiere evaluar una alternativa sin reducir los VU." }
$env:K6_NO_USAGE_REPORT='true'
$env:HTTP_PROXY=''
$env:HTTPS_PROXY=''
$env:ALL_PROXY=''
$env:NO_PROXY='127.0.0.1,localhost'
$argumentos=@('run','--summary-export',('"'+$SalidaPrivadaNueva+'.resumen.json"'),'--out',('"json='+$SalidaPrivadaNueva+'.series.json"'),('"'+(Join-Path $PSScriptRoot "k6/$Escenario.js")+'"'))
$proceso = Start-Process -FilePath $generador.Source -ArgumentList $argumentos -WindowStyle Hidden -PassThru -RedirectStandardOutput ($SalidaPrivadaNueva+'.stdout.txt') -RedirectStandardError ($SalidaPrivadaNueva+'.stderr.txt')
$inicioProceso=$proceso.StartTime
try {
    & (Join-Path $PSScriptRoot 'medir-recursos.ps1') -Manifiesto $env:RENDIMIENTO_MANIFIESTO -PidK6 $proceso.Id -SalidaNueva ($SalidaPrivadaNueva+'.recursos.csv')
} catch {
    $actual=Get-Process -Id $proceso.Id -ErrorAction SilentlyContinue
    if ($actual -and $actual.ProcessName -eq 'k6' -and $actual.StartTime -eq $inicioProceso) { Stop-Process -Id $actual.Id }
    throw
}
$proceso.WaitForExit()
if ($proceso.ExitCode -ne 0) { throw 'k6 terminó con observaciones o interrupción; conservar todos los reportes.' }
