-- Base de datos para Sistema de Registro Académico
CREATE DATABASE sistema_academico;
USE sistema_academico;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dni VARCHAR(8) UNIQUE NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('super_admin', 'docente', 'estudiante') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de programas de estudio
CREATE TABLE programas_estudio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
);

-- Tabla de unidades didácticas (cursos)
CREATE TABLE unidades_didacticas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    programa_id INT NOT NULL,
    periodo_lectivo VARCHAR(20) NOT NULL,
    periodo_academico VARCHAR(10) NOT NULL,
    docente_id INT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    FOREIGN KEY (programa_id) REFERENCES programas_estudio(id),
    FOREIGN KEY (docente_id) REFERENCES usuarios(id)
);

-- Tabla de matrículas (estudiantes en cursos)
CREATE TABLE matriculas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estudiante_id INT NOT NULL,
    unidad_didactica_id INT NOT NULL,
    fecha_matricula TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'retirado') DEFAULT 'activo',
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id),
    FOREIGN KEY (unidad_didactica_id) REFERENCES unidades_didacticas(id),
    UNIQUE KEY unique_matricula (estudiante_id, unidad_didactica_id)
);

-- Tabla de sesiones de clase
CREATE TABLE sesiones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unidad_didactica_id INT NOT NULL,
    numero_sesion INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    fecha DATE NOT NULL,
    descripcion TEXT,
    estado ENUM('programada', 'realizada', 'cancelada') DEFAULT 'programada',
    FOREIGN KEY (unidad_didactica_id) REFERENCES unidades_didacticas(id)
);

-- Tabla de asistencias
CREATE TABLE asistencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sesion_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    estado ENUM('presente', 'falta', 'permiso') NOT NULL,
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id),
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id),
    UNIQUE KEY unique_asistencia (sesion_id, estudiante_id)
);

-- Tabla de indicadores de logro
CREATE TABLE indicadores_logro (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unidad_didactica_id INT NOT NULL,
    numero_indicador INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    peso DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (unidad_didactica_id) REFERENCES unidades_didacticas(id)
);

-- Tabla de indicadores de evaluación por sesión
CREATE TABLE indicadores_evaluacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sesion_id INT NOT NULL,
    indicador_logro_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    peso DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id),
    FOREIGN KEY (indicador_logro_id) REFERENCES indicadores_logro(id)
);

-- Tabla de evaluaciones por sesión
CREATE TABLE evaluaciones_sesion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    indicador_evaluacion_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    calificacion DECIMAL(4,2) NOT NULL CHECK (calificacion >= 0 AND calificacion <= 20),
    fecha_evaluacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (indicador_evaluacion_id) REFERENCES indicadores_evaluacion(id),
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id),
    UNIQUE KEY unique_evaluacion (indicador_evaluacion_id, estudiante_id)
);

-- Insertar datos iniciales
INSERT INTO usuarios (dni, nombres, apellidos, email, password, tipo_usuario) VALUES
('12345678', 'Super', 'Administrador', 'admin@instituto.edu.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('87654321', 'Alejandro', 'Lizana Peña', 'docente@instituto.edu.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),
('11111111', 'Josue Arturo', 'Achulla Lopez', 'estudiante@instituto.edu.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante');

INSERT INTO programas_estudio (nombre, codigo) VALUES
('Técnica en Farmacia', 'FARM001');

INSERT INTO unidades_didacticas (nombre, codigo, programa_id, periodo_lectivo, periodo_academico, docente_id) VALUES
('Normas de Control de calidad en la Industria Farmacéutica', 'NCCIF001', 1, '2024-II', 'VI-A', 2),
('Operaciones Básicas y bioseguridad', 'OBB001', 1, '2025-I', 'I-A', 2);

-- Crear vista para consultas rápidas
CREATE VIEW vista_estudiantes_curso AS
SELECT 
    m.id as matricula_id,
    u.dni,
    CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
    ud.nombre as unidad_didactica,
    ud.id as unidad_didactica_id,
    m.estado as estado_matricula
FROM matriculas m
JOIN usuarios u ON m.estudiante_id = u.id
JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
WHERE u.tipo_usuario = 'estudiante' AND m.estado = 'activo';

CREATE VIEW vista_asistencias_resumen AS
SELECT 
    m.estudiante_id,
    ud.id as unidad_didactica_id,
    COUNT(a.id) as total_sesiones,
    SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as asistencias,
    SUM(CASE WHEN a.estado = 'falta' THEN 1 ELSE 0 END) as faltas,
    SUM(CASE WHEN a.estado = 'permiso' THEN 1 ELSE 0 END) as permisos,
    ROUND((SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as porcentaje_asistencia
FROM matriculas m
JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
JOIN sesiones s ON s.unidad_didactica_id = ud.id
LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = m.estudiante_id
WHERE m.estado = 'activo' AND s.estado = 'realizada'
GROUP BY m.estudiante_id, ud.id;