"""Cliente HTTP de la simulación.

Cada petición lleva dos cabeceras de laboratorio:
  - X-Sim-Time:      hora simulada (el portal la usa como hora del evento).
  - X-Forwarded-For: IP del "usuario" (el portal está detrás de un balanceador simulado).
"""
from __future__ import annotations

import threading
from collections import Counter
from datetime import datetime, timedelta

import requests
from requests.utils import requote_uri

from comun import CFG, iso_utc


class Estadisticas:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self.peticiones = 0
        self.estados: Counter[int] = Counter()
        self.bytes = 0
        self.errores = 0

    def sumar(self, estado: int, n_bytes: int) -> None:
        with self._lock:
            self.peticiones += 1
            self.estados[estado] += 1
            self.bytes += n_bytes

    def error(self) -> None:
        with self._lock:
            self.errores += 1


STATS = Estadisticas()


class Http:
    """Un "navegador" (o herramienta) con IP, User-Agent y cookies propias."""

    def __init__(self, ip: str, ua: str) -> None:
        self.ip = ip
        self.ua = ua
        self.s = requests.Session()
        self.ultima: str | None = None  # para la cabecera Referer

    def req(self, metodo: str, ruta: str, t: datetime, *, referer: str | None = None,
            cabeceras: dict | None = None, **kw) -> requests.Response:
        h = {
            "Host": "portal.techcorp.local",
            "X-Sim-Time": iso_utc(t),
            "X-Forwarded-For": self.ip,
            "User-Agent": self.ua,
        }
        if referer:  # como un navegador: la URL del Referer va codificada (solo ASCII)
            h["Referer"] = requote_uri("http://portal.techcorp.local" + referer)
        if cabeceras:
            h.update(cabeceras)
        r = self.s.request(metodo, CFG.portal_url + ruta, headers=h, allow_redirects=False, timeout=120, **kw)
        STATS.sumar(r.status_code, len(r.content))
        return r

    def get(self, ruta: str, t: datetime, **kw) -> requests.Response:
        return self.req("GET", ruta, t, **kw)

    def post(self, ruta: str, t: datetime, **kw) -> requests.Response:
        return self.req("POST", ruta, t, **kw)

    def navegar(self, ruta: str, t: datetime, metodo: str = "GET", **kw) -> tuple[requests.Response, datetime]:
        """Pide una página y sigue las redirecciones como un navegador (cada salto, +150 ms)."""
        r = self.req(metodo, ruta, t, referer=self.ultima, **kw)
        saltos = 0
        while r.status_code in (301, 302, 303) and saltos < 3:
            destino = r.headers.get("Location", "/")
            if destino.startswith("http"):
                destino = "/" + destino.split("/", 3)[-1]
            t = t + timedelta(milliseconds=150)
            r = self.req("GET", destino, t, referer=self.ultima)
            ruta = destino
            saltos += 1
        self.ultima = ruta
        return r, t

    def cerrar(self) -> None:
        self.s.close()
