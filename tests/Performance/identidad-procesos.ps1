param([Parameter(Mandatory)][int]$PidMySql, [Parameter(Mandatory)][int]$PidApache,
      [Parameter(Mandatory)][int]$PuertoMySql, [Parameter(Mandatory)][int]$PuertoApache)
$ErrorActionPreference = 'Stop'
$resultado = @{}
foreach ($entrada in @(@('mysql',$PidMySql,'mysqld.exe'),@('apache',$PidApache,'httpd.exe'))) {
    $proceso = Get-CimInstance Win32_Process -Filter ('ProcessId='+$entrada[1])
    if (-not $proceso -or $proceso.Name -ne $entrada[2] -or -not $proceso.ExecutablePath -or -not $proceso.CommandLine) { throw 'No se pudo verificar el proceso independiente.' }
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $huella = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($proceso.CommandLine))).Replace('-','').ToLowerInvariant() }
    finally { $sha.Dispose() }
    $resultado[$entrada[0]] = @{binario=$proceso.ExecutablePath;argumentos_sha256=$huella}
}
foreach ($puerto in @(@($PuertoMySql,$PidMySql),@($PuertoApache,$PidApache))) {
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $puerto[0])
    if ($listeners.Count -eq 0) { throw 'No existe el listener autorizado.' }
    foreach ($listener in $listeners) {
        $dueno = Get-CimInstance Win32_Process -Filter ('ProcessId='+$listener.OwningProcess)
        if ($listener.LocalAddress -ne '127.0.0.1' -or
            ($listener.OwningProcess -ne $puerto[1] -and $dueno.ParentProcessId -ne $puerto[1])) { throw 'Listener externo o proceso ajeno.' }
    }
}
$resultado | ConvertTo-Json -Compress
