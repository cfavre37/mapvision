-- MapVision Analytics - Schema SQL Estándar
-- Archivo: sql/schema.sql
-- Descripción: Schema compatible con Oracle, PostgreSQL y SQLite
-- Sin triggers ni procedimientos almacenados - Lógica en PHP

-- ====================================================================
-- TABLAS PRINCIPALES
-- ====================================================================

-- Tabla de Usuarios (email como PK)
CREATE TABLE mv_usuarios (
    email VARCHAR(255) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    empresa VARCHAR(255),
    telefono VARCHAR(20),
    rol VARCHAR(20) NOT NULL CHECK (rol IN ('Administrador', 'Trial', 'Personal', 'Empresa')),
    activo INTEGER DEFAULT 1 CHECK (activo IN (0, 1)),
    email_verificado INTEGER DEFAULT 0 CHECK (email_verificado IN (0, 1)),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_acceso TIMESTAMP NULL,
    intentos_fallidos INTEGER DEFAULT 0,
    bloqueado_hasta TIMESTAMP NULL,
    conectado INTEGER DEFAULT 0 CHECK (conectado IN (0, 1)),
    tiempo_total_conexion INTEGER DEFAULT 0, -- en minutos
    ultima_actividad TIMESTAMP NULL
);

-- Tabla de Tokens
CREATE TABLE mv_tokens (
    id INTEGER PRIMARY KEY,
    usuario_email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('password_reset', 'email_verification')),
    usado INTEGER DEFAULT 0 CHECK (usado IN (0, 1)),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NOT NULL,
    FOREIGN KEY (usuario_email) REFERENCES mv_usuarios(email) ON DELETE CASCADE
);

-- Tabla de Sesiones
CREATE TABLE mv_sesiones (
    id INTEGER PRIMARY KEY,
    usuario_email VARCHAR(255) NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NOT NULL,
    fecha_fin TIMESTAMP NULL,
    activa INTEGER DEFAULT 1 CHECK (activa IN (0, 1)),
    duracion_minutos INTEGER DEFAULT 0,
    FOREIGN KEY (usuario_email) REFERENCES mv_usuarios(email) ON DELETE CASCADE
);

-- Tabla de Log de Accesos
CREATE TABLE mv_log_accesos (
    id INTEGER PRIMARY KEY,
    usuario_email VARCHAR(255),
    accion VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    detalles VARCHAR(1000),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exito INTEGER DEFAULT 1 CHECK (exito IN (0, 1))
);

-- Tabla de Estadísticas de Usuario (manejada por PHP)
CREATE TABLE mv_estadisticas_usuario (
    usuario_email VARCHAR(255) PRIMARY KEY,
    total_logins INTEGER DEFAULT 0,
    total_tiempo_conectado INTEGER DEFAULT 0, -- en minutos
    sesion_mas_larga INTEGER DEFAULT 0, -- en minutos
    promedio_sesion INTEGER DEFAULT 0, -- en minutos
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_email) REFERENCES mv_usuarios(email) ON DELETE CASCADE
);

-- ====================================================================
-- CONFIGURACIÓN ESPECÍFICA POR MOTOR DE BD
-- ====================================================================

-- Para Oracle: Secuencias
-- CREATE SEQUENCE mv_tokens_seq START WITH 1 INCREMENT BY 1;
-- CREATE SEQUENCE mv_sesiones_seq START WITH 1 INCREMENT BY 1;
-- CREATE SEQUENCE mv_log_accesos_seq START WITH 1 INCREMENT BY 1;

-- Para PostgreSQL: Secuencias automáticas
-- ALTER TABLE mv_tokens ALTER COLUMN id SET DEFAULT nextval('mv_tokens_id_seq'::regclass);
-- ALTER TABLE mv_sesiones ALTER COLUMN id SET DEFAULT nextval('mv_sesiones_id_seq'::regclass);
-- ALTER TABLE mv_log_accesos ALTER COLUMN id SET DEFAULT nextval('mv_log_accesos_id_seq'::regclass);

-- Para SQLite: AUTOINCREMENT se maneja automáticamente

-- ====================================================================
-- ÍNDICES PARA RENDIMIENTO
-- ====================================================================

CREATE INDEX idx_mv_usuarios_email ON mv_usuarios(email);
CREATE INDEX idx_mv_usuarios_activo ON mv_usuarios(activo);
CREATE INDEX idx_mv_usuarios_conectado ON mv_usuarios(conectado);
CREATE INDEX idx_mv_usuarios_rol ON mv_usuarios(rol);
CREATE INDEX idx_mv_usuarios_fecha_creacion ON mv_usuarios(fecha_creacion);

CREATE INDEX idx_mv_tokens_token ON mv_tokens(token);
CREATE INDEX idx_mv_tokens_usuario ON mv_tokens(usuario_email);
CREATE INDEX idx_mv_tokens_tipo ON mv_tokens(tipo);
CREATE INDEX idx_mv_tokens_usado ON mv_tokens(usado);
CREATE INDEX idx_mv_tokens_expiracion ON mv_tokens(fecha_expiracion);

CREATE INDEX idx_mv_sesiones_token ON mv_sesiones(session_token);
CREATE INDEX idx_mv_sesiones_usuario ON mv_sesiones(usuario_email);
CREATE INDEX idx_mv_sesiones_activa ON mv_sesiones(activa);
CREATE INDEX idx_mv_sesiones_expiracion ON mv_sesiones(fecha_expiracion);
CREATE INDEX idx_mv_sesiones_ip ON mv_sesiones(ip_address);

CREATE INDEX idx_mv_log_fecha ON mv_log_accesos(fecha);
CREATE INDEX idx_mv_log_usuario ON mv_log_accesos(usuario_email);
CREATE INDEX idx_mv_log_accion ON mv_log_accesos(accion);
CREATE INDEX idx_mv_log_exito ON mv_log_accesos(exito);

CREATE INDEX idx_mv_estadisticas_usuario ON mv_estadisticas_usuario(usuario_email);

-- ====================================================================
-- VISTA PARA MONITOREO (Compatible con múltiples motores)
-- ====================================================================

CREATE VIEW vw_status_usuarios AS
SELECT 
    u.email,
    u.nombre,
    u.apellido,
    u.empresa,
    u.rol,
    u.activo,
    u.email_verificado,
    u.conectado,
    u.fecha_creacion,
    u.fecha_ultimo_acceso,
    u.ultima_actividad,
    u.tiempo_total_conexion,
    u.intentos_fallidos,
    u.bloqueado_hasta,
    COALESCE(s.total_logins, 0) as total_logins,
    COALESCE(s.total_tiempo_conectado, 0) as total_tiempo_conectado,
    COALESCE(s.sesion_mas_larga, 0) as sesion_mas_larga,
    COALESCE(s.promedio_sesion, 0) as promedio_sesion,
    CASE 
        WHEN u.conectado = 1 THEN 'Conectado'
        WHEN u.activo = 0 THEN 'Deshabilitado'
        WHEN u.bloqueado_hasta > CURRENT_TIMESTAMP THEN 'Bloqueado'
        WHEN u.email_verificado = 0 THEN 'No Verificado'
        ELSE 'Desconectado'
    END as estado_display
FROM mv_usuarios u
LEFT JOIN mv_estadisticas_usuario s ON u.email = s.usuario_email
ORDER BY u.fecha_ultimo_acceso DESC;

-- ====================================================================
-- DATOS INICIALES
-- ====================================================================

-- Insertar usuario administrador por defecto
-- Contraseña: admin123 (cambiar después del primer login)
INSERT INTO mv_usuarios (
    email, password_hash, nombre, apellido, rol, activo, email_verificado
) VALUES (
    'admin@mapvision.com',
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMaQJqjYS8J9JRMa4.iJo.ydim',
    'Administrador',
    'Sistema',
    'Administrador',
    1,
    1
);

-- ====================================================================
-- SCRIPTS ESPECÍFICOS POR MOTOR
-- ====================================================================

/* 
   ORACLE ESPECÍFICO:
   - Usar VARCHAR2 en lugar de VARCHAR
   - Usar NUMBER(1) en lugar de INTEGER para booleanos
   - Usar TO_TIMESTAMP para fechas
   - Crear secuencias manualmente
*/

/* 
   POSTGRESQL ESPECÍFICO:
   - Usar SERIAL para claves primarias auto-incrementales
   - Los booleanos pueden ser BOOLEAN en lugar de INTEGER
   - Soporte nativo para CURRENT_TIMESTAMP
*/

/* 
   SQLITE ESPECÍFICO:
   - INTEGER PRIMARY KEY es equivalente a AUTOINCREMENT
   - Los booleanos se manejan como INTEGER
   - Funciona bien con CURRENT_TIMESTAMP
*/

-- ====================================================================
-- NOTAS PARA LA MIGRACIÓN
-- ====================================================================

/*
CAMBIOS PRINCIPALES vs VERSIÓN ORACLE:
1. ❌ Eliminados: Triggers automáticos
2. ❌ Eliminados: Procedimientos almacenados
3. ✅ Agregado: Manejo de estadísticas en PHP
4. ✅ Agregado: Compatibilidad multi-motor
5. ✅ Agregado: Índices optimizados
6. ✅ Mantenido: Toda la funcionalidad core

BENEFICIOS:
- ✅ Portabilidad total entre Oracle, PostgreSQL y SQLite
- ✅ Lógica de negocio centralizada en PHP
- ✅ Más fácil debugging y mantenimiento
- ✅ Control total sobre transacciones
- ✅ Testing más sencillo

FUNCIONALIDADES QUE AHORA SE MANEJAN EN PHP:
- Actualización automática de estadísticas de usuario
- Limpieza automática de datos antiguos
- Cálculo de duración de sesiones
- Contadores de login
*/