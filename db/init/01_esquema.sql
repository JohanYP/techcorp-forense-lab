-- Esquema de la base de datos del portal interno de TechCorp (datos 100 % ficticios).
-- Los datos se cargan con el generador (Faker, locale es_CO): 500 empleados y 2000 clientes.
USE techcorp;

CREATE TABLE empleados (
  id             INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usuario        VARCHAR(40)  NOT NULL UNIQUE,
  nombres        VARCHAR(80)  NOT NULL,
  apellidos      VARCHAR(80)  NOT NULL,
  documento      VARCHAR(20)  NOT NULL,
  correo         VARCHAR(120) NOT NULL,
  telefono       VARCHAR(30),
  cargo          VARCHAR(80),
  departamento   VARCHAR(40),
  rol            ENUM('comercial','soporte','rrhh','finanzas','ti','gerencia') NOT NULL,
  salario        DECIMAL(12,0) NOT NULL,
  fecha_ingreso  DATE,
  password_hash  VARCHAR(100) NOT NULL,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  foto           VARCHAR(120) NULL,
  INDEX idx_emp_apellidos (apellidos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clientes (
  id                INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  tipo_documento    ENUM('CC','CE','NIT','PA') NOT NULL,
  numero_documento  VARCHAR(20)  NOT NULL,
  nombres           VARCHAR(80)  NOT NULL,
  apellidos         VARCHAR(80)  NOT NULL,
  razon_social      VARCHAR(120) NULL,
  correo            VARCHAR(120) NOT NULL,
  telefono          VARCHAR(30),
  direccion         VARCHAR(160),
  ciudad            VARCHAR(60),
  departamento      VARCHAR(60),
  fecha_nacimiento  DATE NULL,
  segmento          ENUM('Personas','Pyme','Empresarial','Premium') NOT NULL,
  cupo_credito      DECIMAL(14,0) NOT NULL DEFAULT 0,
  saldo             DECIMAL(14,0) NOT NULL DEFAULT 0,
  ejecutivo_id      INT UNSIGNED NULL,
  creado_en         DATETIME NOT NULL,
  INDEX idx_cli_apellidos (apellidos),
  INDEX idx_cli_ciudad (ciudad),
  INDEX idx_cli_ejecutivo (ejecutivo_id),
  CONSTRAINT fk_cli_ejecutivo FOREIGN KEY (ejecutivo_id) REFERENCES empleados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cliente_id   INT UNSIGNED NOT NULL,
  empleado_id  INT UNSIGNED NOT NULL,
  asunto       VARCHAR(160) NOT NULL,
  descripcion  TEXT,
  estado       ENUM('abierto','en_proceso','cerrado') NOT NULL DEFAULT 'abierto',
  prioridad    ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  creado_en    DATETIME NOT NULL,
  INDEX idx_tk_empleado (empleado_id, creado_en),
  INDEX idx_tk_cliente (cliente_id),
  CONSTRAINT fk_tk_cliente  FOREIGN KEY (cliente_id)  REFERENCES clientes(id),
  CONSTRAINT fk_tk_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
