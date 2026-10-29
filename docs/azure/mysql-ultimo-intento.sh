#!/usr/bin/env bash
set -Eeuo pipefail

# Ejecutar solo en Azure Cloud Shell Bash. No crea planes, redes o endpoints.
grupo=rg-dhacer-tesis
despliegue=jm-mysql-gp-final
servidor=dhacer-tesis-v1-gp
carpeta=$(cd "$(dirname "$0")" && pwd)
test -f "$carpeta/mysql-ultimo-intento.json"

az group show --name "$grupo" --query name --output tsv > /dev/null
existentes=$(az resource list --resource-group "$grupo" \
    --resource-type Microsoft.DBforMySQL/flexibleServers \
    --query "[?name=='$servidor'] | length(@)" --output tsv)
if [[ "$existentes" != 0 ]]; then
    echo 'El servidor ya existe: no se modifica ni se repite la creación.' >&2
    exit 1
fi
if az deployment group show --resource-group "$grupo" --name "$despliegue" \
    --output none 2>/dev/null; then
    echo 'Este intento ya tiene un registro. Revisarlo en el portal; no se repite automáticamente.' >&2
    exit 1
fi

if [[ -z "${mysql_clave:-}" ]]; then
    read -rsp 'Contraseña de MySQL (entrada oculta): ' mysql_clave
    printf '\n'
fi
if [[ -z "${mysql_clave:-}" ]]; then
    echo 'Contraseña vacía: no se envió ninguna creación.' >&2
    exit 1
fi
export mysql_clave
umask 077
parametros=$(mktemp)
trap 'rm -f -- "$parametros"; unset mysql_clave' EXIT
python3 - "$parametros" <<'PY'
import json, os, sys
with open(sys.argv[1], 'w') as archivo:
    json.dump({'adminPassword': {'value': os.environ['mysql_clave']}}, archivo)
PY

echo 'Único intento: West US 3, MySQL D2ds_v4, 32 GiB, acceso público desactivado.'
echo 'Cómputo MySQL estimado: USD 4.10/día; almacenamiento, app y red adicionales.'
echo 'La espera dura hasta 200 segundos; agotarla NO cancela una operación de Azure.'
az deployment group create --resource-group "$grupo" --name "$despliegue" \
    --mode Incremental --template-file "$carpeta/mysql-ultimo-intento.json" \
    --parameters "@$parametros" --no-wait --output none

if az deployment group wait --resource-group "$grupo" --name "$despliegue" \
    --created --interval 10 --timeout 200; then
    az resource show --resource-group "$grupo" --name "$servidor" \
        --resource-type Microsoft.DBforMySQL/flexibleServers --api-version 2024-12-30 \
        --query '{Servidor:name,Estado:properties.state,Host:properties.fullyQualifiedDomainName}' \
        --output table
else
    echo 'No se confirmó éxito dentro de la espera. Estado y errores reales:'
    az deployment group show --resource-group "$grupo" --name "$despliegue" \
        --query '{Estado:properties.provisioningState,Error:properties.error}' --output json
    az deployment operation group list --resource-group "$grupo" --name "$despliegue" \
        --query "[?properties.provisioningState=='Failed'].properties.statusMessage" --output json
    echo 'No repetir ni borrar automáticamente. Si sigue Running/Accepted, puede terminar y consumir crédito.'
    exit 1
fi
