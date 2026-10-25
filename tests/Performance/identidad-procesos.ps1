param([Parameter(Mandatory)][int]$PidMySql, [Parameter(Mandatory)][int]$PidApache,
      [Parameter(Mandatory)][int]$PuertoMySql, [Parameter(Mandatory)][int]$PuertoApache,
      [Parameter(Mandatory)][string]$Manifiesto)
$ErrorActionPreference = 'Stop'
$m = Get-Content -LiteralPath $Manifiesto -Raw | ConvertFrom-Json
foreach ($campo in @('checkout','operativo','mysql_datadir','mysql_binario','apache_binario','apache_config')) {
    $actual = Get-Item -LiteralPath $m.$campo -Force
    while ($actual) {
        if (($actual.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Ruta redirigida mediante enlace o junction no autorizada.' }
        $padre = [IO.Directory]::GetParent($actual.FullName)
        if (-not $padre) { break }
        $actual = Get-Item -LiteralPath $padre.FullName -Force
    }
}
$resultado = @{}
$procesos = @(Get-CimInstance Win32_Process -Filter ('ProcessId='+$PidMySql+' OR ProcessId='+$PidApache))
foreach ($entrada in @(@('mysql',$PidMySql,'mysqld.exe'),@('apache',$PidApache,'httpd.exe'))) {
    $proceso = $procesos | Where-Object { $_.ProcessId -eq $entrada[1] }
    if (-not $proceso -or $proceso.Name -ne $entrada[2] -or -not $proceso.ExecutablePath -or -not $proceso.CommandLine) { throw 'No se pudo verificar el proceso independiente.' }
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $huella = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($proceso.CommandLine))).Replace('-','').ToLowerInvariant() }
    finally { $sha.Dispose() }
    $resultado[$entrada[0]] = @{binario=$proceso.ExecutablePath;argumentos_sha256=$huella}
}
$conexiones = @(Get-NetTCPConnection -State Listen -LocalPort @($PuertoMySql,$PuertoApache))
foreach ($puerto in @(@($PuertoMySql,$PidMySql),@($PuertoApache,$PidApache))) {
    $listeners = @($conexiones | Where-Object { $_.LocalPort -eq $puerto[0] })
    if ($listeners.Count -eq 0) { throw 'No existe el listener autorizado.' }
    foreach ($listener in $listeners) {
        if ($listener.LocalAddress -ne '127.0.0.1') { throw 'Listener externo o proceso ajeno.' }
        if ($listener.OwningProcess -ne $puerto[1]) {
            $dueno = Get-CimInstance Win32_Process -Filter ('ProcessId='+$listener.OwningProcess)
            if (-not $dueno -or $dueno.ParentProcessId -ne $puerto[1]) { throw 'Listener externo o proceso ajeno.' }
        }
    }
}
$resultado | ConvertTo-Json -Compress
