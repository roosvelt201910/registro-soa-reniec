-- =============================================
-- Sistema Académico - Backup de Base de Datos
-- Fecha: 2025-08-13 11:41:42
-- Base de datos: iespaltohuallaga_regauxiliar_bd
-- Host: localhost
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS `iespaltohuallaga_regauxiliar_bd` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `iespaltohuallaga_regauxiliar_bd`;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Estructura de tabla para `asistencias`
-- =============================================

DROP TABLE IF EXISTS `asistencias`;
CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sesion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `estado` enum('presente','falta','tarde','permiso') NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asistencia` (`sesion_id`,`estudiante_id`),
  KEY `estudiante_id` (`estudiante_id`),
  CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencias_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `asistencias`
-- =============================================

INSERT INTO `asistencias` (`id`, `sesion_id`, `estudiante_id`, `estado`, `observaciones`, `fecha_registro`) VALUES
("1","1","1","presente","","2025-08-05 10:19:15"),
("10","2","4","presente","xd","2025-08-06 22:47:36"),
("11","3","4","presente","xd","2025-08-06 22:47:43"),
("12","2","3","presente","","2025-08-07 16:31:31"),
("13","2","6","falta","","2025-08-07 16:31:31"),
("14","2","5","tarde","","2025-08-07 16:31:31"),
("16","3","3","presente","","2025-08-07 16:02:05"),
("17","3","6","tarde","","2025-08-07 16:02:05"),
("18","3","5","permiso","","2025-08-07 16:02:05"),
("19","1","3","presente","","2025-08-07 19:39:33");

-- =============================================
-- Estructura de tabla para `configuraciones`
-- =============================================

DROP TABLE IF EXISTS `configuraciones`;
CREATE TABLE `configuraciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=576 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `configuraciones`
-- =============================================

INSERT INTO `configuraciones` (`id`, `clave`, `valor`, `descripcion`, `fecha_actualizacion`) VALUES
("1","institute_name","Instituto de Educación Superior Tecnologico Público \"Alto Huallaga\"","Nombre de la institución","2025-08-13 08:51:45"),
("2","institute_location","Tocache","Ubicación de la institución","2025-08-05 10:30:43"),
("3","academic_year","2025","Año académico actual","2025-08-05 10:40:58"),
("4","semester_current","2025-II","Semestre actual","2025-08-05 10:32:30"),
("5","director_name","Walter Camones Henostroza","Nombre del director","2025-08-05 10:32:30"),
("6","phone","042470012","Teléfono institucional","2025-08-05 10:32:30"),
("7","email","admin@instituto.edu.pe","Email institucional","2025-08-05 10:30:43"),
("8","address","Carretera Fernando Belaunde Terry Km. 4.8","Dirección institucional","2025-08-13 08:51:45"),
("9","min_grade","0","Calificación mínima","2025-08-05 10:30:43"),
("10","max_grade","20","Calificación máxima","2025-08-05 10:30:43"),
("11","grade_excellent_min","18","Calificación mínima para excelente","2025-08-05 10:30:43"),
("12","grade_approved_min","12.5","Calificación mínima para aprobado","2025-08-05 10:30:43"),
("13","grade_process_min","9.5","Calificación mínima para en proceso","2025-08-05 10:30:43"),
("14","min_attendance_percentage","70","Porcentaje mínimo de asistencia","2025-08-05 10:30:43"),
("15","decimal_places","1","Decimales en calificaciones","2025-08-05 10:30:43"),
("16","system_name","Registro Auxiliar","Nombre del sistema","2025-08-05 23:10:04"),
("17","system_version","1.0","Versión del sistema","2025-08-05 10:30:43"),
("18","maintenance_mode","0","Modo mantenimiento","2025-08-05 10:30:43"),
("19","debug_mode","0","Modo debug","2025-08-05 10:30:43"),
("20","backup_enabled","1","Backup automático habilitado","2025-08-05 10:30:43"),
("21","session_timeout","3600","Tiempo de sesión en segundos","2025-08-05 10:30:43"),
("22","max_login_attempts","3","Máximo intentos de login","2025-08-05 10:30:43");

-- =============================================
-- Estructura de tabla para `evaluaciones_sesion`
-- =============================================

DROP TABLE IF EXISTS `evaluaciones_sesion`;
CREATE TABLE `evaluaciones_sesion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `indicador_evaluacion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `calificacion` decimal(4,2) NOT NULL CHECK (`calificacion` >= 0 and `calificacion` <= 20),
  `fecha_evaluacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_evaluacion` (`indicador_evaluacion_id`,`estudiante_id`),
  KEY `estudiante_id` (`estudiante_id`),
  CONSTRAINT `evaluaciones_sesion_ibfk_1` FOREIGN KEY (`indicador_evaluacion_id`) REFERENCES `indicadores_evaluacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluaciones_sesion_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `evaluaciones_sesion`
-- =============================================

INSERT INTO `evaluaciones_sesion` (`id`, `indicador_evaluacion_id`, `estudiante_id`, `calificacion`, `fecha_evaluacion`) VALUES
("1","1","3","16.00","2025-08-06 20:54:06"),
("3","2","3","13.00","2025-08-06 20:54:06"),
("24","3","3","14.00","2025-08-06 20:54:06"),
("53","4","3","14.00","2025-08-07 11:57:56"),
("54","5","3","16.00","2025-08-07 11:57:56"),
("55","6","3","13.00","2025-08-07 11:57:56"),
("56","7","3","16.00","2025-08-07 11:57:56"),
("57","8","3","16.00","2025-08-06 22:43:07"),
("58","9","3","18.00","2025-08-06 22:43:07"),
("59","10","3","19.00","2025-08-06 22:43:07"),
("60","11","3","20.00","2025-08-06 22:43:07"),
("61","12","3","5.00","2025-08-07 08:50:07"),
("62","13","3","12.00","2025-08-07 08:50:07"),
("63","14","3","12.00","2025-08-07 08:50:07"),
("64","15","3","2.00","2025-08-07 08:50:07"),
("66","4","6","16.00","2025-08-07 11:57:56"),
("67","4","5","13.00","2025-08-07 11:57:56"),
("69","5","6","13.00","2025-08-07 11:57:56"),
("70","5","5","12.00","2025-08-07 11:57:56"),
("72","6","6","13.00","2025-08-07 11:57:56"),
("73","6","5","19.00","2025-08-07 11:57:56"),
("75","7","6","5.00","2025-08-07 11:57:56"),
("76","7","5","17.00","2025-08-07 11:57:56"),
("77","16","3","12.00","2025-08-08 10:51:41"),
("78","17","3","13.00","2025-08-08 10:51:41"),
("79","18","3","14.00","2025-08-08 10:51:41"),
("80","19","3","12.00","2025-08-08 10:51:41");

-- =============================================
-- Estructura de tabla para `indicadores_evaluacion`
-- =============================================

DROP TABLE IF EXISTS `indicadores_evaluacion`;
CREATE TABLE `indicadores_evaluacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sesion_id` int(11) NOT NULL,
  `indicador_logro_id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `peso` decimal(5,2) DEFAULT 100.00,
  PRIMARY KEY (`id`),
  KEY `sesion_id` (`sesion_id`),
  KEY `indicador_logro_id` (`indicador_logro_id`),
  CONSTRAINT `indicadores_evaluacion_ibfk_1` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicadores_evaluacion_ibfk_2` FOREIGN KEY (`indicador_logro_id`) REFERENCES `indicadores_logro` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `indicadores_evaluacion`
-- =============================================

INSERT INTO `indicadores_evaluacion` (`id`, `sesion_id`, `indicador_logro_id`, `nombre`, `descripcion`, `peso`) VALUES
("1","1","1","Indicador de Evaluacion 1","Indicador de Evaluacion 1","100.00"),
("2","1","2","Indicador de Evaluación 3","Indicador de Evaluación 3","100.00"),
("3","1","1","Indicador de Evaluación 2","Indicador de Evaluación 2","30.00"),
("4","2","3","Utiliza las herramientas de las pestañas de diseño","","20.00"),
("5","2","3","Hace uso de las herramientas con propiedad","","20.00"),
("6","2","3","Desarrolla la actividad de manera eficiente","","30.00"),
("7","2","3","Demuestra interés por el tema tratado","","30.00"),
("8","3","3","Utiliza las herramientas de procesador de texto para la elabarocacion de un documento","","30.00"),
("9","3","3","Demuestra interés por el tema tratado","","30.00"),
("10","3","3","Desarrolla la actividad de manera eficiente","","30.00"),
("11","3","3","Ayuda al compañero","","10.00"),
("12","4","3","hace uso correcto de las herramientas def word","","30.00"),
("13","4","3","Crea documentos correctamente","","40.00"),
("14","4","3","Es puntual al momento de presentar sus actividades","","20.00"),
("15","4","3","Participa activamente en clases","","10.00"),
("16","5","3","hace uso correcto de las herramientas def word","","20.00"),
("17","5","3","IMPRIME COTRRECTAMNETE","","100.00"),
("18","5","3","Es puntual al momento de presentar sus actividades","","100.00"),
("19","5","3","Participa activamente en clases","","100.00");

-- =============================================
-- Estructura de tabla para `indicadores_logro`
-- =============================================

DROP TABLE IF EXISTS `indicadores_logro`;
CREATE TABLE `indicadores_logro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_didactica_id` int(11) NOT NULL,
  `numero_indicador` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `peso` decimal(5,2) DEFAULT 100.00,
  PRIMARY KEY (`id`),
  KEY `unidad_didactica_id` (`unidad_didactica_id`),
  CONSTRAINT `indicadores_logro_ibfk_1` FOREIGN KEY (`unidad_didactica_id`) REFERENCES `unidades_didacticas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `indicadores_logro`
-- =============================================

INSERT INTO `indicadores_logro` (`id`, `unidad_didactica_id`, `numero_indicador`, `nombre`, `descripcion`, `peso`) VALUES
("1","2","1","Indicador de Logro 1 IL1","Indicador de Logro 1 IL1","100.00"),
("2","2","2","Indicador de Logro 2 IL2","Indicador de Logro 2 IL2","30.00"),
("3","3","1","C2.I1 Utiliza el procesador de textos, en la elaboración de documentos teniendo en cuenta los requerimientos del contexto laboral y los formatos vinculados al programa de estudios.","","50.00"),
("4","3","2","C2.I3 Realiza presentaciones de información sistematizada de calidad y vinculados al programa de estudios","","100.00");

-- =============================================
-- Estructura de tabla para `matriculas`
-- =============================================

DROP TABLE IF EXISTS `matriculas`;
CREATE TABLE `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `unidad_didactica_id` int(11) NOT NULL,
  `fecha_matricula` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activo','retirado') DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_matricula` (`estudiante_id`,`unidad_didactica_id`),
  KEY `unidad_didactica_id` (`unidad_didactica_id`),
  CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matriculas_ibfk_2` FOREIGN KEY (`unidad_didactica_id`) REFERENCES `unidades_didacticas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `matriculas`
-- =============================================

INSERT INTO `matriculas` (`id`, `estudiante_id`, `unidad_didactica_id`, `fecha_matricula`, `estado`) VALUES
("1","3","2","2025-08-05 10:14:18","activo"),
("4","3","3","2025-08-05 23:08:45","activo"),
("5","3","1","2025-08-06 22:21:06","activo"),
("6","5","3","2025-08-07 11:56:44","activo"),
("7","6","3","2025-08-07 11:56:52","activo"),
("8","3","6","2025-08-08 10:20:45","activo");

-- =============================================
-- Estructura de tabla para `programas_estudio`
-- =============================================

DROP TABLE IF EXISTS `programas_estudio`;
CREATE TABLE `programas_estudio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `descripcion` text DEFAULT NULL,
  `duracion_semestres` int(11) DEFAULT 6,
  `modalidad` enum('presencial','virtual','semipresencial') DEFAULT 'presencial',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `programas_estudio`
-- =============================================

INSERT INTO `programas_estudio` (`id`, `nombre`, `codigo`, `estado`, `descripcion`, `duracion_semestres`, `modalidad`) VALUES
("1","Técnica en Farmacia","FARM001","activo",NULL,"6","presencial"),
("2","Asistencia Administrativa","APIAPSTI","activo","programa Asistencia Administrativa","6","presencial"),
("3","Producción Agropecuaria","PA001","activo","programa de Estudio Producción Agropecuaria","6","presencial"),
("4","Arq. Plataformas y Servicios de TI.","APSTI001","activo","Programa de Estudio de Arquitectura de Plataformas y Servicios de TI.","6","presencial"),
("5","Farmacia Técnica","FTC001","activo","","6","presencial");

-- =============================================
-- Estructura de tabla para `sesiones`
-- =============================================

DROP TABLE IF EXISTS `sesiones`;
CREATE TABLE `sesiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_didactica_id` int(11) NOT NULL,
  `numero_sesion` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('programada','realizada','cancelada') DEFAULT 'programada',
  PRIMARY KEY (`id`),
  KEY `unidad_didactica_id` (`unidad_didactica_id`),
  CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`unidad_didactica_id`) REFERENCES `unidades_didacticas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `sesiones`
-- =============================================

INSERT INTO `sesiones` (`id`, `unidad_didactica_id`, `numero_sesion`, `titulo`, `fecha`, `descripcion`, `estado`) VALUES
("1","2","1","Cuidado ético y espiritual del enfermero en el area de emergencia de un hospital de Essalud1","2025-08-05","enfermero en el area de emergencia de un hospital de Essalud1","realizada"),
("2","3","1","Nº 01 Configuración de Trabajos, Utilizando pestaña Inicio e Insertar del procesador de textos Word","2025-08-07","\tFuente \r\n\tPárrafos\r\n\tEstilos\r\n\tTabla (disposición)\r\n\tEdición \r\n\tIlustraciones / Liezo Vínculos,\r\n\tComentarios y Encabezado Y Pie De Página.\r\n\tTexto","realizada"),
("3","3","2","SESION N° 02. Configuración de Trabajos, Utilizando pestaña Diseño, Formato Disposición y Referencias del procesador de textos Word","2025-08-14","","realizada"),
("4","3","3","SESION N° 03. Combinación de correspondencia Utilizando pestaña correspondencia del procesador de textos Word","2025-08-07","","programada"),
("5","3","4","SESION N° 04. Utilizando pestaña archivo del procesador de textos Word","2025-08-08","\tInformación\r\n\tImpresión\r\n\tExportar","programada");

-- =============================================
-- Estructura de tabla para `unidades_didacticas`
-- =============================================

DROP TABLE IF EXISTS `unidades_didacticas`;
CREATE TABLE `unidades_didacticas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `programa_id` int(11) NOT NULL,
  `periodo_lectivo` varchar(20) NOT NULL,
  `periodo_academico` varchar(10) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `programa_id` (`programa_id`),
  KEY `docente_id` (`docente_id`),
  CONSTRAINT `unidades_didacticas_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas_estudio` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unidades_didacticas_ibfk_2` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `unidades_didacticas`
-- =============================================

INSERT INTO `unidades_didacticas` (`id`, `nombre`, `codigo`, `programa_id`, `periodo_lectivo`, `periodo_academico`, `docente_id`, `estado`) VALUES
("1","Normas de Control de calidad en la Industria Farmacéutica","NCCIF001","1","2024-II","VI-A","2","activo"),
("2","Operaciones Básicas y bioseguridad","OBB001","1","2025-I","I-A","2","activo"),
("3","Ofimática","OF001","2","2025-II","II","2","activo"),
("4","Ofimática P.Agropecuaria","OFIAGRO02","3","2025-II","II","4","activo"),
("5","INNOVACIÓN TECNOLÓGICA","INNTEC001","5","2025-II","VI","8","activo"),
("6","CLASIFICACIÓN DE MEDICAMENTOS","CMED001","5","2025-II","II","9","activo"),
("7","ESTUDIO DE ENFERMEDADES","ESTENF001","5","2025-II","IV","9","activo");

-- =============================================
-- Estructura de tabla para `usuarios`
-- =============================================

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dni` varchar(8) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_usuario` enum('super_admin','docente','estudiante') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Volcado de datos para la tabla `usuarios`
-- =============================================

INSERT INTO `usuarios` (`id`, `dni`, `nombres`, `apellidos`, `email`, `password`, `tipo_usuario`, `estado`, `fecha_creacion`) VALUES
("1","12345678","Super","Administrador","admin@instituto.edu.pe","$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi","super_admin","activo","2025-08-05 09:37:43"),
("2","87654321","Docente ","Prueba","lizanap@iespaltohuallaga.edu.pe","$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi","docente","activo","2025-08-05 09:37:43"),
("3","11111111","Josue Arturo","Achulla Lopez","estudiante@instituto.edu.pe","$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi","estudiante","activo","2025-08-05 09:37:43"),
("4","47259954","Roosvelt","Enriquez gamez","tec.enrique21@gmail.com","$2y$10$nLyDoSI0R.OkgzL5ZN3JDO0F8Oc6XzgOkVPeu31EQfEPAI.sSfL.2","docente","activo","2025-08-05 23:07:57"),
("5","71882580","Michelle alexandra","Enriquez rodas","ali@piki.com","$2y$10$sm.dvRJs2lqnc.QPAFaqHuANYHz2m.AtI208a7HOtURwC1UGXExhi","estudiante","activo","2025-08-07 11:54:48"),
("6","01187174","Maximo francisco","Enriquez jara","info@nexustelecom.pe","$2y$10$qEsgNNEFT7mOayD/kJRWBu.rC8wY9DOHHLLZUdrs3h8YzGOkkMHTS","estudiante","activo","2025-08-07 11:55:59"),
("7","11122233","Juan","Perez","cpriale@gmail.com","$2y$10$f9IYGS.W1ChUBjcyYQXTFuiu6PGWpg6sed8cnKVD6CiQ./jJYWLwu","super_admin","activo","2025-08-07 20:17:28"),
("8","10070692","Cesar Augusto","Priale Farro","cpriale@gmail.com","$2y$10$iQ5eM6rgTOscg46ZBOhwI./sugdDPkHdyp5D57CLAj8BJ/ty6Pg/W","docente","activo","2025-08-08 08:24:56"),
("9","47089847","Roger","Diaz Villalobos","rogerdiazvillalobos17@gmail.com","$2y$10$qGUBenGJOrd8UPiIGbXNLO08J5c.zZXAqTQFuVEOv68pOZqtxILU.","docente","activo","2025-08-08 10:18:06");

-- =============================================
-- Estructura de tabla para `vista_asistencias_resumen`
-- =============================================

DROP TABLE IF EXISTS `vista_asistencias_resumen`;
CREATE ALGORITHM=UNDEFINED DEFINER=`iespaltohuallaga`@`localhost` SQL SECURITY DEFINER VIEW `vista_asistencias_resumen` AS select `m`.`estudiante_id` AS `estudiante_id`,`ud`.`id` AS `unidad_didactica_id`,count(`a`.`id`) AS `total_sesiones`,sum(case when `a`.`estado` = 'presente' then 1 else 0 end) AS `asistencias`,sum(case when `a`.`estado` = 'falta' then 1 else 0 end) AS `faltas`,sum(case when `a`.`estado` = 'permiso' then 1 else 0 end) AS `permisos`,round(sum(case when `a`.`estado` = 'presente' then 1 else 0 end) / count(`a`.`id`) * 100,2) AS `porcentaje_asistencia` from (((`matriculas` `m` join `unidades_didacticas` `ud` on(`m`.`unidad_didactica_id` = `ud`.`id`)) join `sesiones` `s` on(`s`.`unidad_didactica_id` = `ud`.`id`)) left join `asistencias` `a` on(`a`.`sesion_id` = `s`.`id` and `a`.`estudiante_id` = `m`.`estudiante_id`)) where `m`.`estado` = 'activo' and `s`.`estado` = 'realizada' group by `m`.`estudiante_id`,`ud`.`id`;

-- =============================================
-- Volcado de datos para la tabla `vista_asistencias_resumen`
-- =============================================

INSERT INTO `vista_asistencias_resumen` (`estudiante_id`, `unidad_didactica_id`, `total_sesiones`, `asistencias`, `faltas`, `permisos`, `porcentaje_asistencia`) VALUES
("3","2","1","1","0","0","100.00"),
("3","3","2","2","0","0","100.00"),
("5","3","2","0","0","1","0.00"),
("6","3","2","0","1","0","0.00");

-- =============================================
-- Estructura de tabla para `vista_estudiantes_curso`
-- =============================================

DROP TABLE IF EXISTS `vista_estudiantes_curso`;
CREATE ALGORITHM=UNDEFINED DEFINER=`iespaltohuallaga`@`localhost` SQL SECURITY DEFINER VIEW `vista_estudiantes_curso` AS select `m`.`id` AS `matricula_id`,`u`.`dni` AS `dni`,concat(`u`.`apellidos`,', ',`u`.`nombres`) AS `nombre_completo`,`ud`.`nombre` AS `unidad_didactica`,`ud`.`id` AS `unidad_didactica_id`,`m`.`estado` AS `estado_matricula` from ((`matriculas` `m` join `usuarios` `u` on(`m`.`estudiante_id` = `u`.`id`)) join `unidades_didacticas` `ud` on(`m`.`unidad_didactica_id` = `ud`.`id`)) where `u`.`tipo_usuario` = 'estudiante' and `m`.`estado` = 'activo';

-- =============================================
-- Volcado de datos para la tabla `vista_estudiantes_curso`
-- =============================================

INSERT INTO `vista_estudiantes_curso` (`matricula_id`, `dni`, `nombre_completo`, `unidad_didactica`, `unidad_didactica_id`, `estado_matricula`) VALUES
("1","11111111","Achulla Lopez, Josue Arturo","Operaciones Básicas y bioseguridad","2","activo"),
("4","11111111","Achulla Lopez, Josue Arturo","Ofimática","3","activo"),
("5","11111111","Achulla Lopez, Josue Arturo","Normas de Control de calidad en la Industria Farmacéutica","1","activo"),
("6","71882580","Enriquez rodas, Michelle alexandra","Ofimática","3","activo"),
("7","01187174","Enriquez jara, Maximo francisco","Ofimática","3","activo"),
("8","11111111","Achulla Lopez, Josue Arturo","CLASIFICACIÓN DE MEDICAMENTOS","6","activo");

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
