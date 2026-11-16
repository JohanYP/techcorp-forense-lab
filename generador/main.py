"""Generador del laboratorio TechCorp.

  python main.py todo [--forzar]     poblar la BD + simular la semana (con el incidente)
  python main.py poblar [--forzar]   solo poblar la BD con Faker
  python main.py trafico [--forzar]  solo simular el tráfico

Variables de entorno: SIM_INICIO, SIM_DIAS, INCIDENTE_INICIO, HILOS, ESCALA, SEMILLA.
"""
from __future__ import annotations

import json
import os
import random
import sys
import time
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta

import requests

import incidente
import poblar_bd
import trafico
from comun import CFG, COL
from http_sim import STATS


def esperar_portal() -> dict:
    for _ in range(90):
        try:
            r = requests.get(CFG.portal_url + "/_lab/estado.php", timeout=5)
            if r.ok:
                return r.json()
        except requests.RequestException:
            pass
        time.sleep(2)
    raise SystemExit(f"El portal no responde en {CFG.portal_url}")


def simular(forzar: bool) -> None:
    estado = esperar_portal()
    if estado.get("webshell") and not forzar:
        raise SystemExit(
            "✖ Este entorno YA contiene un incidente simulado (existe la webshell).\n"
            "  Para repetir desde cero:  docker compose down -v && rm -f logs/*/*.jsonl && docker compose up -d\n"
            "  (o use --forzar si sabe lo que hace: se mezclarían dos simulaciones en los logs)")

    rnd = random.Random(CFG.semilla)
    datos = trafico.cargar_datos(rnd)
    trabajos = trafico.trabajos_empleados(datos, rnd)
    exportaciones, reg_export = trafico.trabajos_exportaciones(datos, rnd)
    escaneos, reg_escaneos = trafico.trabajos_escaneos(rnd)
    trabajos += exportaciones + escaneos + trafico.trabajos_monitoreo(rnd) + trafico.trabajos_ruido(rnd)

    verdad: dict = {}
    sem_inc = rnd.getrandbits(32)
    # SIN_INCIDENTE=1 → genera solo la semana de tráfico de fondo (el incidente lo inyecta
    # luego el notebook 00_ataque_en_vivo.ipynb, para poder explicar el ataque paso a paso).
    sin_incidente = os.environ.get("SIN_INCIDENTE") == "1"

    def correr_incidente() -> None:
        verdad.update(incidente.ejecutar(CFG.incidente_inicio, sem_inc))

    if not sin_incidente and CFG.sim_inicio <= CFG.incidente_inicio < CFG.sim_fin:
        trabajos.append(trafico.Trabajo(CFG.incidente_inicio, "INCIDENTE", correr_incidente))

    por_dia: dict = defaultdict(list)
    for tr in trabajos:
        por_dia[tr.inicio.astimezone(COL).date()].append(tr)

    print(f"· Simulación: {CFG.sim_inicio:%Y-%m-%d %H:%M} → {CFG.sim_fin:%Y-%m-%d %H:%M} (UTC-5), "
          f"{len(trabajos)} trabajos, {CFG.hilos} hilos, escala {CFG.escala}")
    print("· Incidente: lo inyectará el notebook en vivo (SIN_INCIDENTE=1)" if sin_incidente
          else f"· Incidente programado: {CFG.incidente_inicio:%Y-%m-%d %H:%M} (UTC-5)")
    t_ini = time.time()

    def seguro(tr: trafico.Trabajo) -> None:
        try:
            tr.fn()
        except Exception as e:  # una sesión que falla no detiene la simulación
            STATS.error()
            if tr.nombre == "INCIDENTE":
                raise
            print(f"  ! {tr.nombre}: {type(e).__name__}: {e}", file=sys.stderr)

    for dia in sorted(por_dia):
        lote = sorted(por_dia[dia])
        antes = STATS.peticiones
        t_dia = time.time()
        with ThreadPoolExecutor(max_workers=CFG.hilos) as ex:
            for f in [ex.submit(seguro, tr) for tr in lote]:
                f.result()
        dur = time.time() - t_dia
        n = STATS.peticiones - antes
        print(f"  {dia:%a %d/%m}: {n:>7,} peticiones en {dur:5.0f} s ({n / max(dur, 0.1):5.0f}/s)"
              + ("   ← incidente" if any(tr.nombre == "INCIDENTE" for tr in lote) else ""), flush=True)

    total = time.time() - t_ini
    print(f"✔ {STATS.peticiones:,} peticiones HTTP en {total / 60:.1f} min · {STATS.bytes / 1e6:,.1f} MB · "
          f"códigos {dict(sorted(STATS.estados.items()))} · trabajos con error: {STATS.errores}")

    if verdad:
        verdad["falsas_alarmas"] = {"exportaciones_legitimas": reg_export, "escaneos": reg_escaneos,
                                    "monitoreo": ["10.10.0.5 (Zabbix)", "10.10.0.6 (Nagios)"]}
        verdad["generado"] = datetime.now(COL).isoformat(timespec="seconds")
        os.makedirs(CFG.salida, exist_ok=True)
        ruta = os.path.join(CFG.salida, "solucionario_incidente.json")
        with open(ruta, "w", encoding="utf-8") as f:
            json.dump(verdad, f, ensure_ascii=False, indent=2)
        print(f"✔ Solucionario (solo para el docente): simulacion/{os.path.basename(ruta)}")


def main() -> None:
    args = sys.argv[1:] or ["todo"]
    accion, forzar = args[0], "--forzar" in args
    if accion in ("todo", "poblar"):
        poblar_bd.poblar(forzar=forzar and accion == "poblar")
    if accion in ("todo", "trafico"):
        simular(forzar)
    if accion not in ("todo", "poblar", "trafico"):
        print(__doc__)
        sys.exit(2)


if __name__ == "__main__":
    main()
