"""Utilidades compartidas: configuración, conexión a BD y reloj de la simulación."""
from __future__ import annotations

import os
import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone

import pymysql

COL = timezone(timedelta(hours=-5), name="America/Bogota")  # Colombia: UTC-5 todo el año


@dataclass(frozen=True)
class Config:
    portal_url: str = os.environ.get("PORTAL_URL", "http://portal")
    db_host: str = os.environ.get("DB_HOST", "db")
    db_name: str = os.environ.get("DB_NAME", "techcorp")
    db_admin_user: str = os.environ.get("DB_ADMIN_USER", "techcorp_admin")
    db_admin_password: str = os.environ.get("DB_ADMIN_PASSWORD", "")
    lab_password: str = os.environ.get("LAB_PASSWORD", "TechCorp2026*")
    sim_inicio: datetime = datetime.fromisoformat(os.environ.get("SIM_INICIO", "2026-11-03T00:00:00-05:00"))
    sim_dias: int = int(os.environ.get("SIM_DIAS", "7"))
    incidente_inicio: datetime = datetime.fromisoformat(os.environ.get("INCIDENTE_INICIO", "2026-11-06T01:52:00-05:00"))
    hilos: int = int(os.environ.get("HILOS", "12"))
    escala: float = float(os.environ.get("ESCALA", "1.0"))
    semilla: int = int(os.environ.get("SEMILLA", "2026"))
    salida: str = os.environ.get("SALIDA", "/salida")

    @property
    def sim_fin(self) -> datetime:
        return self.sim_inicio + timedelta(days=self.sim_dias)


CFG = Config()


def conectar_bd(intentos: int = 60) -> pymysql.connections.Connection:
    ultimo = None
    for _ in range(intentos):
        try:
            return pymysql.connect(
                host=CFG.db_host, user=CFG.db_admin_user, password=CFG.db_admin_password,
                database=CFG.db_name, charset="utf8mb4", autocommit=False,
                cursorclass=pymysql.cursors.DictCursor,
            )
        except pymysql.err.OperationalError as e:  # la BD todavía arranca
            ultimo = e
            time.sleep(2)
    raise SystemExit(f"No fue posible conectar con la base de datos: {ultimo}")


def iso_utc(dt: datetime) -> str:
    """Hora para la cabecera X-Sim-Time: UTC con milisegundos y sufijo Z."""
    u = dt.astimezone(timezone.utc)
    return u.strftime("%Y-%m-%dT%H:%M:%S.") + f"{u.microsecond // 1000:03d}Z"
