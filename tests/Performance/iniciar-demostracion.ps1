param([Parameter(Mandatory)][string]$RaizPrivada,
      [ValidateRange(1,8)][int]$Horas = 4)
$ErrorActionPreference = 'Stop'
$esperada = Join-Path $env:LOCALAPPDATA 'DhacerRendimiento\f9b_001'
$raiz = (Resolve-Path -LiteralPath $RaizPrivada).Path
if ($raiz -ne $esperada) { throw 'Solo se permite la infraestructura f9b_001 autorizada.' }
$m = Get-Content -LiteralPath (Join-Path $raiz 'manifiesto.json') -Raw | ConvertFrom-Json
$checkout = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
if ($checkout -ne (Join-Path $raiz 'checkout') -or $checkout -ne $m.checkout -or
    $m.base -ne 'dhacer_eco_limp_demo_f9b_001' -or $m.mysql_puerto -ne 3313 -or $m.url -ne 'http://127.0.0.1:8089') {
    throw 'Checkout o destino distinto del autorizado. No ejecutar desde el proyecto operativo.'
}
foreach ($ruta in @($raiz,$m.checkout,$m.operativo,$m.mysql_datadir,$m.mysql_binario,$m.apache_binario,$m.apache_config)) {
    $actual = Get-Item -LiteralPath $ruta -Force
    while ($actual) {
        if (($actual.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'No se permiten enlaces ni junctions.' }
        $padre = [IO.Directory]::GetParent($actual.FullName)
        if (-not $padre) { break }
        $actual = Get-Item -LiteralPath $padre.FullName -Force
    }
}
$permitidos = @([Security.Principal.WindowsIdentity]::GetCurrent().User.Value, 'S-1-5-18')
foreach ($acceso in (Get-Acl -LiteralPath $raiz).Access) {
    if ($acceso.AccessControlType -eq 'Allow' -and $acceso.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value -notin $permitidos) {
        throw 'La carpeta privada concede acceso a una identidad no autorizada.'
    }
}
if ((Get-Content -LiteralPath (Join-Path $m.mysql_datadir 'auto.cnf') -Raw) -notmatch [regex]::Escape($m.mysql_uuid)) { throw 'UUID en disco diferente. No inicializar ni reparar.' }
if ((Get-FileHash -LiteralPath $m.apache_config -Algorithm SHA256).Hash.ToLowerInvariant() -ne $m.apache_config_sha256) { throw 'Configuración Apache diferente.' }
if ((Get-FileHash -LiteralPath (Join-Path $raiz 'mysql.ini') -Algorithm SHA256).Hash.ToLowerInvariant() -ne '7a17ea12cfebf2574d6c099b9791d81d7e57cf04ace15e3f6de34ea99f902854') { throw 'Configuración MySQL diferente.' }
foreach ($tipo in @('mysql','apache')) {
    $puerto = if ($tipo -eq 'mysql') {3313} else {8089}
    $binario = $m.($tipo+'_binario')
    $config = if ($tipo -eq 'mysql') {Join-Path $raiz 'mysql.ini'} else {$m.apache_config}
    $argumentos = if ($tipo -eq 'mysql') {"--defaults-file=$config"} else {"-f $config"}
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $puerto -ErrorAction SilentlyContinue)
    if ($listeners.Count -eq 0) {
        Start-Process -FilePath $binario -ArgumentList $argumentos -WindowStyle Hidden
        $limite = (Get-Date).AddSeconds(30)
        do {
            $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $puerto -ErrorAction SilentlyContinue)
            if (-not $listeners.Count) {Start-Sleep -Milliseconds 500}
        } while (-not $listeners.Count -and (Get-Date) -lt $limite)
    }
    if ($listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1') { throw 'Listener no autorizado.' }
    $proceso = Get-CimInstance Win32_Process -Filter ('ProcessId='+$listeners[0].OwningProcess)
    $normal = $proceso.CommandLine.Replace('\','/')
    $patron = '^"?'+[regex]::Escape($binario.Replace('\','/'))+'"?\s+'+[regex]::Escape($argumentos.Replace('\','/'))+'\s*$'
    if ($proceso.ExecutablePath -ne $binario -or $normal -notmatch $patron) { throw 'Ejecutable o argumentos efectivos distintos.' }
    $m.($tipo+'_pid') = [int]$proceso.ProcessId
    $sha = [Security.Cryptography.SHA256]::Create()
    try {$m.($tipo+'_argumentos_sha256') = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($proceso.CommandLine)))).Replace('-','').ToLowerInvariant()}
    finally {$sha.Dispose()}
}
$m.vence = (Get-Date).AddHours($Horas).ToString('o')
$m.perfil_propuesto.ram_minima_estres_mib = 3072
$m.perfil_propuesto.ram_minima_carga_mib = 4096
$archivo = Join-Path $raiz 'manifiesto.json'
[IO.File]::WriteAllText($archivo, ($m|ConvertTo-Json -Depth 6), [Text.UTF8Encoding]::new($false))
$hash = (Get-FileHash -LiteralPath $archivo -Algorithm SHA256).Hash.ToLowerInvariant()
$configPrivada = Join-Path $raiz 'privado.env'
$contenido = Get-Content -LiteralPath $configPrivada -Raw
$contenido = [regex]::Replace($contenido, '(?m)^RENDIMIENTO_MANIFIESTO_SHA256=.*$', ('RENDIMIENTO_MANIFIESTO_SHA256='+$hash))
[IO.File]::WriteAllText($configPrivada, $contenido, [Text.UTF8Encoding]::new($false))
$env:RENDIMIENTO_CONFIG = $configPrivada
Push-Location $checkout
try {
    & php -d ('auto_prepend_file='+(Join-Path $checkout 'tests/Performance/configuracion/privada-bootstrap.php')) tests/Performance/verificar-entorno.php verificar
    if ($LASTEXITCODE -ne 0) { throw 'Identidad no verificada. No continuar ni escribir datos.' }
    if ((Invoke-WebRequest -Uri 'http://127.0.0.1:8089/login' -MaximumRedirection 0).StatusCode -ne 200) { throw 'HTTP de demostración no disponible.' }
    Write-Output 'Demostración verificada en http://127.0.0.1:8089. No se ejecutaron migraciones ni seeders.'
} finally { Pop-Location }
