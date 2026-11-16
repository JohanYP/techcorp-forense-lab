#!/usr/bin/env bash
# Deja el laboratorio como recién instalado: borra contenedores, BD, logs y resultados.
# (Los logs se borran con el portal APAGADO; si se borran con Apache encendido, Apache
#  sigue escribiendo en el archivo eliminado y la evidencia se pierde.)
set -euo pipefail
cd "$(dirname "$0")"
docker compose --profile simulacion down -v --remove-orphans
rm -f logs/web/*.jsonl logs/app/*.jsonl logs/db/*.jsonl logs/fim/*.jsonl simulacion/*.json
rm -rf analisis/salida/lake analisis/salida/figuras analisis/salida/*.csv analisis/salida/*.json analisis/salida/*.html
docker compose up -d
echo "Listo. Ahora:  docker compose --profile simulacion run --rm generador"
