"""Puebla la base de datos del portal con datos FICTICIOS (Faker, locale es_CO).

500 empleados, 2000 clientes y un histórico de tickets. Es reproducible (semilla fija).
"""
from __future__ import annotations

import random
import unicodedata
from datetime import date, datetime, timedelta

import bcrypt
from faker import Faker

from comun import CFG, conectar_bd

N_EMPLEADOS = 500
N_CLIENTES = 2000
N_TICKETS = 1800

ROLES = [  # rol, peso, departamento, cargos, rango salarial (COP)
    ("comercial", 45, "Comercial", ["Ejecutivo comercial", "Asesor comercial", "Coordinador comercial"], (2_300_000, 6_500_000)),
    ("soporte", 28, "Servicio al cliente", ["Agente de soporte", "Analista de soporte", "Líder de soporte"], (1_800_000, 4_200_000)),
    ("ti", 8, "Tecnología", ["Desarrollador", "Administrador de sistemas", "Analista de seguridad"], (4_000_000, 12_000_000)),
    ("rrhh", 6, "Talento humano", ["Analista de nómina", "Psicólogo de selección", "Jefe de talento humano"], (3_000_000, 8_500_000)),
    ("finanzas", 9, "Finanzas", ["Analista financiero", "Jefe de cartera", "Contador"], (3_500_000, 10_000_000)),
    ("gerencia", 4, "Gerencia", ["Gerente regional", "Director de área", "Gerente general"], (12_000_000, 28_000_000)),
]

CIUDADES = [  # ciudad, departamento, peso
    ("Bogotá", "Cundinamarca", 30), ("Medellín", "Antioquia", 14), ("Cali", "Valle del Cauca", 11),
    ("Barranquilla", "Atlántico", 8), ("Cartagena", "Bolívar", 5), ("Bucaramanga", "Santander", 5),
    ("Pereira", "Risaralda", 3), ("Manizales", "Caldas", 3), ("Popayán", "Cauca", 4), ("Pasto", "Nariño", 3),
    ("Ipiales", "Nariño", 2), ("Cúcuta", "Norte de Santander", 3), ("Ibagué", "Tolima", 2), ("Neiva", "Huila", 2),
    ("Villavicencio", "Meta", 2), ("Santa Marta", "Magdalena", 2), ("Montería", "Córdoba", 1),
    ("Armenia", "Quindío", 1), ("Tunja", "Boyacá", 1), ("Valledupar", "Cesar", 1),
]

ASUNTOS = [
    "Actualización de datos de contacto", "Solicitud de aumento de cupo", "Error en factura del mes",
    "Consulta de saldo", "Reclamo por cobro duplicado", "Cambio de ejecutivo asignado",
    "Solicitud de certificado comercial", "Problema de acceso al portal de clientes", "Acuerdo de pago",
    "Renovación de contrato", "Información de nuevos productos", "Queja por tiempo de respuesta",
]


def ascii_min(s: str) -> str:
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return "".join(c for c in s.lower() if c.isalnum())


SOCIEDADES = ["S.A.S.", "S.A.S.", "S.A.S.", "Ltda.", "S.A.", "& Cía. S.A.S."]
RUBROS = ["Distribuciones", "Inversiones", "Comercializadora", "Servicios", "Construcciones", "Logística", "Agroindustrias",
          "Tecnología", "Importaciones", "Soluciones", "Textiles", "Ferretería", "Transportes", "Alimentos"]


def nombre_persona(fake: Faker, rnd: random.Random, prob_doble: float) -> str:
    """Nombre de pila (uno o dos) del mismo género."""
    f = fake.first_name_female if rnd.random() < 0.5 else fake.first_name_male
    n = f()
    if rnd.random() < prob_doble:
        otro = f()
        if otro != n:
            n += " " + otro
    return n


def razon_social(fake: Faker, rnd: random.Random) -> str:
    if rnd.random() < 0.5:
        return f"{rnd.choice(RUBROS)} {fake.last_name()} {rnd.choice(SOCIEDADES)}"
    return f"{fake.last_name()} {rnd.choice(['y', '&'])} {fake.last_name()} {rnd.choice(SOCIEDADES)}"


def poblar(forzar: bool = False) -> None:
    fake = Faker("es_CO")
    Faker.seed(CFG.semilla)
    rnd = random.Random(CFG.semilla)

    con = conectar_bd()
    with con.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM empleados")
        if cur.fetchone()["n"] > 0 and not forzar:
            print("· La base de datos ya tiene datos; no se vuelve a poblar (use --forzar para rehacerla).")
            return
        if forzar:
            cur.execute("SET FOREIGN_KEY_CHECKS=0")
            for t in ("tickets", "clientes", "empleados"):
                cur.execute(f"TRUNCATE TABLE {t}")
            cur.execute("SET FOREIGN_KEY_CHECKS=1")

    # Un único hash (bcrypt, coste 8) para la contraseña común del laboratorio.
    h = bcrypt.hashpw(CFG.lab_password.encode(), bcrypt.gensalt(rounds=8)).decode().replace("$2b$", "$2y$", 1)

    print(f"· Generando {N_EMPLEADOS} empleados…")
    empleados, usados = [], set()
    pesos = [r[1] for r in ROLES]
    for i in range(N_EMPLEADOS - 1):
        rol, _, depto, cargos, (smin, smax) = rnd.choices(ROLES, weights=pesos)[0]
        nombres = nombre_persona(fake, rnd, 0.35)
        apellidos = f"{fake.last_name()} {fake.last_name()}"
        base = ascii_min(nombres.split()[0][0] + apellidos.split()[0])
        usuario, k = base, 2
        while usuario in usados:
            usuario, k = f"{base}{k}", k + 1
        usados.add(usuario)
        empleados.append((
            usuario, nombres, apellidos, str(rnd.randint(10_000_000, 1_299_999_999)),
            f"{usuario}@techcorp.com.co", f"+57 3{rnd.randint(0, 2)}{rnd.randint(0, 9)} {rnd.randint(100, 999)} {rnd.randint(1000, 9999)}",
            rnd.choice(cargos), depto, rol, round(rnd.randint(smin, smax), -4),
            fake.date_between(date(2012, 1, 1), date(2026, 9, 30)), h,
        ))
    empleados.append(("demo", "Usuario", "Demostración", "1000000000", "demo@techcorp.com.co", "+57 300 000 0000",
                      "Cuenta de demostración", "Tecnología", "ti", 5_000_000, date(2026, 1, 15), h))

    with con.cursor() as cur:
        cur.executemany(
            "INSERT INTO empleados (usuario,nombres,apellidos,documento,correo,telefono,cargo,departamento,rol,salario,fecha_ingreso,password_hash)"
            " VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)", empleados)
        cur.execute("SELECT id FROM empleados WHERE rol='comercial'")
        comerciales = [r["id"] for r in cur.fetchall()]
        cur.execute("SELECT id FROM empleados WHERE rol IN ('soporte','comercial')")
        atienden = [r["id"] for r in cur.fetchall()]

    print(f"· Generando {N_CLIENTES} clientes…")
    clientes = []
    pesos_c = [c[2] for c in CIUDADES]
    for _ in range(N_CLIENTES):
        segmento = rnd.choices(["Personas", "Pyme", "Empresarial", "Premium"], weights=[60, 22, 8, 10])[0]
        ciudad, depto, _ = rnd.choices(CIUDADES, weights=pesos_c)[0]
        nombres = nombre_persona(fake, rnd, 0.4)
        apellidos = f"{fake.last_name()} {fake.last_name()}"
        empresa = segmento in ("Pyme", "Empresarial")
        cupo = {"Personas": (1, 20), "Pyme": (20, 150), "Empresarial": (150, 900), "Premium": (40, 300)}[segmento]
        cupo_v = rnd.randint(*cupo) * 1_000_000
        correo = f"{ascii_min(nombres.split()[0])}.{ascii_min(apellidos.split()[0])}{rnd.randint(1, 99)}@{rnd.choice(['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'correo.com.co'])}"
        clientes.append((
            "NIT" if empresa else rnd.choices(["CC", "CE", "PA"], weights=[94, 4, 2])[0],
            f"{rnd.randint(800_000_000, 999_999_999)}-{rnd.randint(0, 9)}" if empresa else str(rnd.randint(1_000_000, 1_299_999_999)),
            nombres, apellidos, razon_social(fake, rnd) if empresa else None, correo,
            f"+57 3{rnd.randint(0, 2)}{rnd.randint(0, 9)} {rnd.randint(100, 999)} {rnd.randint(1000, 9999)}",
            fake.street_address(), ciudad, depto,
            None if empresa else fake.date_of_birth(minimum_age=19, maximum_age=80),
            segmento, cupo_v, round(cupo_v * rnd.uniform(0, 0.85), -3), rnd.choice(comerciales),
            fake.date_time_between(datetime(2019, 1, 1), datetime(2026, 10, 31)),
        ))
    with con.cursor() as cur:
        cur.executemany(
            "INSERT INTO clientes (tipo_documento,numero_documento,nombres,apellidos,razon_social,correo,telefono,direccion,ciudad,departamento,"
            "fecha_nacimiento,segmento,cupo_credito,saldo,ejecutivo_id,creado_en) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)", clientes)

    print(f"· Generando {N_TICKETS} tickets históricos…")
    fin = CFG.sim_inicio.replace(tzinfo=None) + timedelta(hours=5)  # UTC
    tickets = []
    for _ in range(N_TICKETS):
        creado = fin - timedelta(minutes=rnd.randint(60, 300 * 24 * 60))
        antiguedad = (fin - creado).days
        estado = "cerrado" if antiguedad > 20 or rnd.random() < 0.5 else rnd.choice(["abierto", "en_proceso"])
        tickets.append((rnd.randint(1, N_CLIENTES), rnd.choice(atienden), rnd.choice(ASUNTOS),
                        fake.paragraph(nb_sentences=3), estado, rnd.choices(["baja", "media", "alta"], weights=[3, 5, 2])[0], creado))
    tickets.sort(key=lambda t: t[-1])
    with con.cursor() as cur:
        cur.executemany(
            "INSERT INTO tickets (cliente_id,empleado_id,asunto,descripcion,estado,prioridad,creado_en) VALUES (%s,%s,%s,%s,%s,%s,%s)", tickets)
    con.commit()
    con.close()
    print(f"✔ Base de datos poblada: {N_EMPLEADOS} empleados, {N_CLIENTES} clientes, {N_TICKETS} tickets.")


if __name__ == "__main__":
    import sys
    poblar(forzar="--forzar" in sys.argv)
