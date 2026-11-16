"""Tráfico LEGÍTIMO (y ruido) de una semana en el portal de TechCorp.

Incluye, además de los empleados, varias "falsas alarmas" deliberadas que el análisis forense
debe saber descartar:
  · Escaneo de vulnerabilidades AUTORIZADO del SOC interno (martes de madrugada).
  · Exportaciones masivas LEGÍTIMAS de clientes por Finanzas y Gerencia.
  · Escaneo externo + intento de fuerza bruta SIN éxito (sábado).
  · Ruido de fondo de Internet (bots que buscan /wp-login.php, /.env, …).
  · Monitoreo (Zabbix cada minuto, Nagios cada 5 min) y turnos de noche del área de soporte.
"""
from __future__ import annotations

import random
import re
import struct
import zlib
from dataclasses import dataclass, field
from datetime import date, datetime, time, timedelta
from typing import Callable

from comun import CFG, COL, conectar_bd
from http_sim import Http

ASSETS = ["/assets/css/portal.css", "/assets/js/portal.js", "/assets/img/logo.svg", "/favicon.ico"]

NAVEGADORES = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0",
]
SUBRED = {"comercial": 1, "soporte": 2, "finanzas": 3, "rrhh": 4, "ti": 5, "gerencia": 6}

PESOS = {
    "comercial": {"dashboard": 6, "clientes": 10, "buscar": 14, "cliente": 30, "autocompletar": 8, "directorio": 3,
                  "empleado": 2, "tickets": 6, "ticket": 5, "nuevo_ticket": 4, "reportes": 3, "perfil": 1},
    "soporte": {"dashboard": 6, "clientes": 4, "buscar": 10, "cliente": 14, "autocompletar": 6, "directorio": 3,
                "empleado": 2, "tickets": 20, "ticket": 25, "nuevo_ticket": 10, "perfil": 1},
    "rrhh": {"dashboard": 8, "directorio": 30, "empleado": 40, "buscar": 3, "cliente": 3, "tickets": 4, "ticket": 3, "perfil": 2},
    "finanzas": {"dashboard": 8, "reportes": 22, "clientes": 14, "cliente": 20, "buscar": 10, "exportar_tickets": 2,
                 "tickets": 6, "ticket": 5, "directorio": 3, "empleado": 2},
    "ti": {"dashboard": 15, "tickets": 20, "ticket": 20, "directorio": 10, "empleado": 5, "cliente": 8, "buscar": 6,
           "reportes": 4, "perfil": 2},
    "gerencia": {"dashboard": 20, "reportes": 25, "clientes": 12, "cliente": 12, "directorio": 8, "empleado": 10,
                 "tickets": 5, "exportar_tickets": 2},
}
MARCADORES_VIEJOS = ["/intranet/inicio.php", "/clientes/buscar.php", "/portal/", "/rrhh/nomina.php", "/old/dashboard.php"]
ASUNTOS = ["Actualización de datos", "Solicitud de cupo", "Error en factura", "Consulta de saldo", "Cobro duplicado",
           "Certificado comercial", "Acuerdo de pago", "Renovación de contrato", "Cambio de dirección de envío"]
AREAS = ["Comercial", "Servicio al cliente", "Tecnología", "Talento humano", "Finanzas", "Gerencia"]


@dataclass
class Empleado:
    id: int
    usuario: str
    rol: str
    ua: str
    turno: str          # dia | tarde | noche
    remoto: bool
    ip_lan: str


@dataclass
class Datos:
    empleados: list[Empleado]
    clientes_por_ejecutivo: dict[int, list[int]]
    n_clientes: int
    max_ticket: int
    apellidos: list[str]
    ciudades: list[str]


@dataclass(order=True)
class Trabajo:
    inicio: datetime
    nombre: str = field(compare=False)
    fn: Callable[[], None] = field(compare=False)


def cargar_datos(rnd: random.Random) -> Datos:
    con = conectar_bd()
    with con.cursor() as cur:
        cur.execute("SELECT id, usuario, rol FROM empleados WHERE usuario <> 'demo' ORDER BY id")
        filas = cur.fetchall()
        cur.execute("SELECT id, ejecutivo_id, apellidos, ciudad FROM clientes")
        clientes = cur.fetchall()
        cur.execute("SELECT MAX(id) AS m FROM tickets")
        max_ticket = cur.fetchone()["m"] or 1
    con.close()

    empleados = []
    usados: set[str] = {"10.10.5.20"}  # IP reservada al escáner del SOC
    for f in filas:
        turno = "dia"
        if f["rol"] == "soporte":
            turno = rnd.choices(["dia", "tarde", "noche"], weights=[85, 10, 5])[0]
        while True:
            ip = f"10.10.{SUBRED[f['rol']]}.{rnd.randint(21, 250)}"
            if ip not in usados:
                usados.add(ip)
                break
        empleados.append(Empleado(f["id"], f["usuario"], f["rol"], rnd.choice(NAVEGADORES), turno,
                                  rnd.random() < (0.6 if turno == "noche" else 0.2), ip))
    por_ejecutivo: dict[int, list[int]] = {}
    for c in clientes:
        por_ejecutivo.setdefault(c["ejecutivo_id"], []).append(c["id"])
    apellidos = sorted({c["apellidos"].split()[0] for c in clientes})
    ciudades = sorted({c["ciudad"] for c in clientes})
    return Datos(empleados, por_ejecutivo, len(clientes), max_ticket, apellidos, ciudades)


def png_aleatorio(rnd: random.Random, lado: int = 128) -> bytes:
    """Foto de perfil sintética (ruido): ~50 KB que no se comprimen."""
    filas = b"".join(b"\x00" + rnd.randbytes(lado * 3) for _ in range(lado))

    def chunk(t: bytes, d: bytes) -> bytes:
        return struct.pack(">I", len(d)) + t + d + struct.pack(">I", zlib.crc32(t + d) & 0xFFFFFFFF)

    return (b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", lado, lado, 8, 2, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(filas, 1)) + chunk(b"IEND", b""))


# --------------------------------------------------------------------------- empleados
class SesionEmpleado:
    def __init__(self, emp: Empleado, datos: Datos, ip: str, rnd: random.Random) -> None:
        self.emp, self.datos, self.rnd = emp, datos, rnd
        self.h = Http(ip, emp.ua)
        self.ok = False

    def ms(self, t: datetime, a: int = 80, b: int = 900) -> datetime:
        return t + timedelta(milliseconds=self.rnd.randint(a, b))

    def assets(self, t: datetime) -> None:
        for a in ASSETS:
            t = self.ms(t, 20, 120)
            self.h.get(a, t, referer="/login.php")

    def login(self, t: datetime) -> datetime:
        self.h.navegar("/login.php", t)
        self.assets(t)
        t = t + timedelta(seconds=self.rnd.uniform(4, 15))
        if self.rnd.random() < 0.04:  # contraseña mal escrita
            self.h.post("/login.php", t, data={"usuario": self.emp.usuario, "password": "Techcorp2026"}, referer="/login.php")
            t = t + timedelta(seconds=self.rnd.uniform(5, 14))
        r, t = self.h.navegar("/login.php", t, metodo="POST", data={"usuario": self.emp.usuario, "password": CFG.lab_password})
        self.ok = r.status_code == 200 and "Hola," in r.text
        return t

    def cliente_id(self) -> int:
        propios = self.datos.clientes_por_ejecutivo.get(self.emp.id)
        if self.emp.rol == "comercial" and propios and self.rnd.random() < 0.7:
            return self.rnd.choice(propios)
        return self.rnd.randint(1, self.datos.n_clientes)

    def accion(self, nombre: str, t: datetime) -> None:
        r = self.rnd
        h = self.h
        if r.random() < 0.004:
            h.navegar(r.choice(MARCADORES_VIEJOS), t)
            return
        if r.random() < 0.0015 and self.emp.rol not in ("finanzas", "gerencia"):
            h.navegar("/reportes/exportar.php?tipo=clientes", t)
            return
        if nombre == "dashboard":
            h.navegar("/dashboard.php", t)
        elif nombre == "clientes":
            h.navegar(f"/clientes/?page={min(80, int(r.expovariate(1 / 6)) + 1)}", t)
        elif nombre == "buscar":
            q = r.choice(self.datos.apellidos) if r.random() < 0.75 else r.choice(self.datos.ciudades)
            resp, t = h.navegar(f"/clientes/?q={q}", t)
            ids = re.findall(r"/clientes/ver\.php\?id=(\d+)", resp.text)
            if ids and r.random() < 0.7:
                h.navegar(f"/clientes/ver.php?id={r.choice(ids[:5])}", t + timedelta(seconds=r.uniform(3, 25)))
        elif nombre == "cliente":
            h.navegar(f"/clientes/ver.php?id={self.cliente_id()}", t)
        elif nombre == "autocompletar":
            ap = r.choice(self.datos.apellidos)
            for k in range(2, min(len(ap), 5) + 1):
                t = self.ms(t, 150, 450)
                h.get(f"/api/buscar.php?q={ap[:k]}", t, referer=h.ultima)
        elif nombre == "directorio":
            if r.random() < 0.5:
                h.navegar(f"/empleados/?q={r.choice(AREAS)}", t)
            else:
                h.navegar(f"/empleados/?page={r.randint(1, 17)}", t)
        elif nombre == "empleado":
            h.navegar(f"/empleados/ver.php?id={r.randint(1, len(self.datos.empleados))}", t)
        elif nombre == "perfil":
            h.navegar(f"/empleados/ver.php?id={self.emp.id}", t)
        elif nombre == "tickets":
            h.navegar("/tickets/" + (r.choice(["", "?estado=abierto", "?estado=en_proceso", "?estado=cerrado"])), t)
        elif nombre == "ticket":
            h.navegar(f"/tickets/ver.php?id={r.randint(1, self.datos.max_ticket)}", t)
        elif nombre == "nuevo_ticket":
            cid = self.cliente_id()
            h.navegar(f"/tickets/nuevo.php?cliente_id={cid}", t)
            t = t + timedelta(seconds=r.uniform(20, 120))
            asunto = r.choice(ASUNTOS) if r.random() > 0.01 else ""
            h.navegar("/tickets/nuevo.php", t, metodo="POST", data={
                "cliente_id": cid, "asunto": asunto, "prioridad": r.choice(["baja", "media", "media", "alta"]),
                "descripcion": "Cliente se comunica por línea de atención. Se registra la solicitud para seguimiento."})
        elif nombre == "reportes":
            h.navegar("/reportes/", t)
        elif nombre == "exportar_tickets":
            h.navegar("/reportes/exportar.php?tipo=tickets", t)

    def subir_foto(self, t: datetime) -> None:
        self.h.navegar("/empleados/foto.php", t)
        t = t + timedelta(seconds=self.rnd.uniform(10, 40))
        self.h.navegar("/empleados/foto.php", t, metodo="POST",
                       files={"foto": ("foto.png", png_aleatorio(self.rnd), "image/png")})
        self.h.get(f"/uploads/avatars/emp_{self.emp.id}.png", self.ms(t, 300, 900), referer=f"/empleados/ver.php?id={self.emp.id}")

    def correr(self, inicio: datetime, fin: datetime, n_acciones: int) -> None:
        try:
            t = self.login(inicio)
            if not self.ok:
                return
            pesos = PESOS[self.emp.rol]
            nombres, w = list(pesos), list(pesos.values())
            span = max(60.0, (fin - t).total_seconds())
            tiempos = sorted(t + timedelta(seconds=self.rnd.uniform(5, span)) for _ in range(n_acciones))
            foto_en = self.rnd.randrange(n_acciones) if self.rnd.random() < 0.05 else -1
            for i, ti in enumerate(tiempos):
                if i == foto_en:
                    self.subir_foto(ti)
                else:
                    self.accion(self.rnd.choices(nombres, weights=w)[0], ti)
            if self.rnd.random() < 0.5:
                self.h.navegar("/logout.php", tiempos[-1] + timedelta(seconds=self.rnd.uniform(5, 60)))
        finally:
            self.h.cerrar()


def trabajos_empleados(datos: Datos, rnd: random.Random) -> list[Trabajo]:
    trabajos: list[Trabajo] = []
    ventanas = {"dia": [(7.0, 12.4), (13.7, 18.3)], "tarde": [(14.0, 18.0), (18.5, 22.0)], "noche": [(22.0, 25.6), (26.0, 29.6)]}
    for d in range(-1, CFG.sim_dias):  # d = -1: turno de noche que empezó la víspera
        dia = (CFG.sim_inicio + timedelta(days=d)).date()
        finde = dia.weekday() >= 5
        for emp in datos.empleados:
            if emp.turno == "noche":
                p = 0.8
            elif emp.turno == "tarde":
                p = 0.75 if not finde else 0.35
            else:
                p = 0.72 if dia.weekday() < 5 else (0.07 if dia.weekday() == 5 else 0.03)
            if rnd.random() > min(0.98, p * CFG.escala):
                continue
            ip = f"10.8.0.{rnd.randint(10, 250)}" if emp.remoto else emp.ip_lan
            vent = ventanas[emp.turno] if not (finde and emp.turno == "dia") else [(9.0, 12.5)]
            for k, (a, b) in enumerate(vent):
                if k > 0 and rnd.random() > 0.8:
                    continue
                ini = a + max(-0.5, min(2.0, rnd.gauss(0.5, 0.45)))
                fin = b + rnd.gauss(0, 0.35)
                if fin - ini < 0.4:
                    continue
                t0 = datetime.combine(dia, time(0), COL) + timedelta(hours=ini, seconds=rnd.uniform(0, 60))
                t1 = datetime.combine(dia, time(0), COL) + timedelta(hours=fin)
                if not CFG.sim_inicio <= t0 < CFG.sim_fin - timedelta(minutes=30):
                    continue
                n = max(2, int(rnd.gauss(9, 3)))
                sem = rnd.getrandbits(32)

                def fn(emp=emp, ip=ip, t0=t0, t1=t1, n=n, sem=sem):
                    SesionEmpleado(emp, datos, ip, random.Random(sem)).correr(t0, t1, n)

                trabajos.append(Trabajo(t0, f"sesion:{emp.usuario}", fn))
    return trabajos


# ------------------------------------------------------- exportaciones legítimas
def trabajos_exportaciones(datos: Datos, rnd: random.Random) -> tuple[list[Trabajo], list[dict]]:
    """Exportaciones masivas AUTORIZADAS (falsas alarmas por volumen de bytes)."""
    casos = [("finanzas", 1, time(10, 12)), ("gerencia", 6, time(16, 41))]  # (rol, día de la simulación, hora)
    trabajos, registro = [], []
    for rol, d, hora in casos:
        if d >= CFG.sim_dias:
            continue
        emp = next(e for e in datos.empleados if e.rol == rol and e.turno == "dia")
        t0 = datetime.combine((CFG.sim_inicio + timedelta(days=d)).date(), hora, COL)
        registro.append({"usuario": emp.usuario, "rol": rol, "ip": emp.ip_lan, "hora_local": t0.isoformat()})

        def fn(emp=emp, t0=t0, sem=rnd.getrandbits(32)):
            s = SesionEmpleado(emp, datos, emp.ip_lan, random.Random(sem))
            try:
                t = s.login(t0)
                t += timedelta(seconds=40)
                s.h.navegar("/reportes/", t)
                t += timedelta(seconds=25)
                s.h.navegar("/reportes/exportar.php?tipo=clientes", t)
                t += timedelta(seconds=50)
                s.h.navegar("/reportes/exportar.php?tipo=tickets", t)
                s.h.navegar("/logout.php", t + timedelta(minutes=3))
            finally:
                s.h.cerrar()

        trabajos.append(Trabajo(t0, f"exportacion:{emp.usuario}", fn))
    return trabajos, registro


# ------------------------------------------------------------------- monitoreo
def trabajos_monitoreo(rnd: random.Random) -> list[Trabajo]:
    trabajos = []
    horas = CFG.sim_dias * 24
    for hh in range(horas):
        base = CFG.sim_inicio + timedelta(hours=hh)

        def fn(base=base, sem=rnd.getrandbits(32)):
            r = random.Random(sem)
            zabbix = Http("10.10.0.5", "Zabbix")
            nagios = Http("10.10.0.6", "check_http/v2.3.3 (nagios-plugins 2.3.3)")
            try:
                for m in range(60):
                    zabbix.get("/api/health.php", base + timedelta(minutes=m, milliseconds=r.randint(0, 1500)))
                    if m % 5 == 0:
                        nagios.get("/legacy/status.php", base + timedelta(minutes=m, seconds=30, milliseconds=r.randint(0, 900)))
            finally:
                zabbix.cerrar()
                nagios.cerrar()

        trabajos.append(Trabajo(base, "monitoreo", fn))
    return trabajos


# --------------------------------------------------------- ruido de Internet
SONDAS = ["/wp-login.php", "/.env", "/xmlrpc.php", "/phpmyadmin/", "/.git/config", "/admin/", "/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php",
          "/cgi-bin/luci", "/boaform/admin/formLogin", "/HNAP1/", "/actuator/health", "/config.json", "/backup.zip", "/.aws/credentials",
          "/wp-content/plugins/", "/owa/auth/logon.aspx", "/solr/admin/info/system", "/.DS_Store", "/info.php", "/test.php"]
BOTS = ["Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)", "Go-http-client/1.1", "curl/7.88.1",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.112 Safari/537.36",
        "python-requests/2.28.1", "Mozilla/5.0 (compatible; Odin; https://docs.getodin.com/)", "masscan/1.3 (https://github.com/robertdavidgraham/masscan)"]


def ip_internet(rnd: random.Random) -> str:
    while True:
        ip = rnd.choice(["192.0.2.", "198.51.100."]) + str(rnd.randint(2, 254))
        if ip != "198.51.100.77":
            return ip


def trabajos_ruido(rnd: random.Random) -> list[Trabajo]:
    trabajos = []
    for hh in range(CFG.sim_dias * 24):
        for _ in range(_poisson(rnd, 0.6)):
            t0 = CFG.sim_inicio + timedelta(hours=hh, seconds=rnd.uniform(0, 3600))

            def fn(t0=t0, sem=rnd.getrandbits(32)):
                r = random.Random(sem)
                h = Http(ip_internet(r), r.choice(BOTS))
                try:
                    t = t0
                    if r.random() < 0.3:  # rastreador "educado"
                        h.get("/robots.txt", t)
                        h.navegar("/", t + timedelta(seconds=1))
                    for p in r.sample(SONDAS, r.randint(1, 5)):
                        t += timedelta(milliseconds=r.randint(200, 3000))
                        h.get(p, t)
                finally:
                    h.cerrar()

            trabajos.append(Trabajo(t0, "ruido_internet", fn))
    return trabajos


def _poisson(rnd: random.Random, lam: float) -> int:
    n, p, limite = 0, 1.0, pow(2.718281828, -lam)
    while True:
        p *= rnd.random()
        if p <= limite:
            return n
        n += 1


# ------------------------------------------- escaneos (autorizado y externo)
def lista_rutas(rnd: random.Random, n: int) -> list[str]:
    bases = ["admin", "administrator", "backup", "backups", "old", "test", "dev", "api", "config", "includes", "legacy", "uploads",
             "files", "upload", "tmp", "logs", "db", "sql", "phpmyadmin", "pma", "server-status", "cgi-bin", "install", "setup",
             "portal", "intranet", "panel", "cpanel", "dashboard", "login", "user", "users", "clientes", "empleados", "reportes",
             "tickets", "assets", "static", "vendor", "composer", "wp-admin", "wp-login", "info", "phpinfo", "status", "health",
             "console", "debug", "shell", "cmd", "upload", "export", "data", "private", "secret", "env", "git", "svn", "error"]
    ext = ["", "/", ".php", ".bak", ".zip", ".old", ".txt", ".sql", ".tar.gz"]
    rutas = sorted({f"/{b}{e}" for b in bases for e in ext})
    rnd.shuffle(rutas)
    return rutas[:n]


def trabajos_escaneos(rnd: random.Random) -> tuple[list[Trabajo], list[dict]]:
    trabajos, registro = [], []
    # 1) Escaneo AUTORIZADO del SOC (registrado en el control de cambios CAB-2026-114)
    t_soc = datetime.combine((CFG.sim_inicio).date(), time(3, 0), COL)
    ua_soc = "TechCorp-SOC-Scanner/3.2 (+https://soc.techcorp.local; CAB-2026-114)"
    registro.append({"tipo": "escaneo_autorizado", "ip": "10.10.5.20", "inicio_local": t_soc.isoformat(), "ua": ua_soc})

    def soc(sem=rnd.getrandbits(32)):
        r = random.Random(sem)
        h = Http("10.10.5.20", ua_soc)
        try:
            t = t_soc
            for p in lista_rutas(r, 460):
                t += timedelta(milliseconds=r.randint(400, 1400))
                h.get(p, t)
            for p in ["/clientes/ver.php?id=1%27", "/login.php?next=%2F%2Fevil.example", "/api/buscar.php?q=%3Cscript%3E"]:
                t += timedelta(seconds=2)
                h.get(p, t)
        finally:
            h.cerrar()

    trabajos.append(Trabajo(t_soc, "escaneo_soc", soc))

    # 2) Escaneo EXTERNO + fuerza bruta sin éxito (sábado por la tarde)
    if CFG.sim_dias > 4:
        t_ext = datetime.combine((CFG.sim_inicio + timedelta(days=4)).date(), time(15, 21), COL)
        registro.append({"tipo": "escaneo_externo_sin_exito", "ip": "198.51.100.77", "inicio_local": t_ext.isoformat()})

        def externo(sem=rnd.getrandbits(32)):
            r = random.Random(sem)
            h = Http("198.51.100.77", "Mozilla/5.0 zgrab/0.x")
            try:
                t = t_ext
                for p in lista_rutas(r, 190):
                    t += timedelta(milliseconds=r.randint(300, 1600))
                    h.get(p, t)
                for u, c in [("admin", "admin"), ("admin", "123456"), ("administrador", "admin123"), ("test", "test"),
                             ("soporte", "soporte"), ("root", "toor"), ("admin", "Admin2026"), ("techcorp", "techcorp"),
                             ("usuario", "usuario"), ("admin", "password"), ("demo", "demo"), ("guest", "guest")]:
                    t += timedelta(seconds=r.uniform(1.5, 4))
                    h.post("/login.php", t, data={"usuario": u, "password": c})
            finally:
                h.cerrar()

        trabajos.append(Trabajo(t_ext, "escaneo_externo", externo))
    return trabajos, registro
