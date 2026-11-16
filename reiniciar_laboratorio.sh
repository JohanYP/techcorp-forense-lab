#!/usr/bin/env bash
# Deja el laboratorio como recién instalado y lo vuelve a preparar para la presentación:
# borra contenedores, BD, logs (incluida la línea base) y resultados, y ejecuta
# "docker compose up -d --build", que puebla la BD, simula la semana y levanta Jupyter.
# (Los logs se borran con el portal APAGADO; si se borran con Apache encendido, Apache
#  sigue escribiendo en el archivo eliminado y la evidencia se pierde.)
#
# Para repetir solo el ataque NO hace falta este script: basta con ejecutar de nuevo
# notebooks/00_ataque_en_vivo.ipynb desde el principio.
set -euo pipefail
cd "$(dirname "$0")"

[ -f .env ] || cp .env.example .env

docker compose down -v --remove-orphans
rm -f logs/web/*.jsonl logs/app/*.jsonl logs/db/*.jsonl logs/fim/*.jsonl logs/.linea_base.json
rm -f simulacion/*.json
rm -rf analisis/salida/lake analisis/salida/figuras analisis/salida/*.csv analisis/salida/*.json analisis/salida/*.html

echo "Preparando el laboratorio (la primera vez tarda unos minutos: se simula la semana)…"
docker compose up -d --build

# Lee un valor del .env (sin "source": UID es de solo lectura en bash).
valor() { sed -n "s/^$1=\([^ #]*\).*/\1/p" .env | tail -n1; }
JP=$(valor JUPYTER_PORT); JT=$(valor JUPYTER_TOKEN); PP=$(valor PORTAL_PORT); LP=$(valor LAB_PASSWORD)
echo
echo "Listo."
echo "  Jupyter: http://127.0.0.1:${JP:-8888}/lab?token=${JT:-forense2026}"
echo "  Portal:  http://127.0.0.1:${PP:-8080}   (demo / ${LP:-TechCorp2026*})"
