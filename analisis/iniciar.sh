#!/bin/sh
# Arranque de la estación del analista: deja la vista de presentación lista (los dos
# notebooks abiertos en pestañas, el del ataque primero) y levanta Jupyter Lab.
# Se reimporta en cada arranque: si durante una clase se cerraron pestañas, al reiniciar
# el contenedor la vista vuelve a quedar como al principio.
jupyter lab workspaces import /opt/techcorp/espacio_presentacion.json >/dev/null 2>&1 \
  || echo "aviso: no se pudo preparar la vista de presentación (se abre la vista normal)"
exec jupyter lab --ip=0.0.0.0 --port=8888 --no-browser \
  --ServerApp.root_dir=/home/analista --ServerApp.default_url=/lab
