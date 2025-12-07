<?php
// Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// MODO DE TESTING RÁPIDO - REMOVER EN PRODUCCIÓN
if (isset($_GET['test']) && $_GET['test'] == '1') {
    $_SESSION['user_id'] = 1;
    $_SESSION['tipo_usuario'] = 'super_admin';
    $_SESSION['nombres'] = 'Administrador';
    $_SESSION['apellidos'] = 'Principal';
    $_SESSION['dni'] = '12345678';
    $_SESSION['email'] = 'admin@instituto.edu.pe';
}

// Verificación básica de sesión
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Error</title></head>
    <body>
        <h2>Error de Sesión</h2>
        <p><a href="?test=1">Usar Modo Testing</a> | <a href="../login.php">Iniciar Sesión</a></p>
    </body></html>
    <?php
    exit();
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'iespaltohuallaga_regauxiliar_bd';
$username = 'iespaltohuallaga_user_regaux';
$password = ')wBRCeID[ldb%b^K';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Función para completar datos de usuario
if (!isset($_SESSION['tipo_usuario'])) {
    try {
        $stmt = $pdo->prepare("SELECT tipo_usuario, nombres, apellidos FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
            $_SESSION['nombres'] = $user['nombres'];
            $_SESSION['apellidos'] = $user['apellidos'];
        } else {
            $_SESSION['tipo_usuario'] = 'super_admin';
            $_SESSION['nombres'] = 'Administrador';
            $_SESSION['apellidos'] = 'Principal';
        }
    } catch (PDOException $e) {
        $_SESSION['tipo_usuario'] = 'super_admin';
        $_SESSION['nombres'] = 'Administrador';
        $_SESSION['apellidos'] = 'Principal';
    }
}

// Verificar permisos - Solo super_admin puede acceder
$allowed_types = array('super_admin');
if (!in_array($_SESSION['tipo_usuario'], $allowed_types)) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>
    <body>
        <h2>Acceso Denegado</h2>
        <p>Solo los administradores pueden acceder a este módulo.</p>
        <p><a href="../dashboard.php">Volver al Dashboard</a> | <a href="?test=1">Modo Testing</a></p>
    </body></html>
    <?php
    exit();
}

// Funciones para obtener datos del sistema
function getSystemOverview($pdo) {
    try {
        $overview = array();
        
        // Estadísticas generales
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') as total_usuarios,
                (SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'estudiante' AND estado = 'activo') as total_estudiantes,
                (SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'docente' AND estado = 'activo') as total_docentes,
                (SELECT COUNT(*) FROM programas_estudio WHERE estado = 'activo') as total_programas,
                (SELECT COUNT(*) FROM unidades_didacticas WHERE estado = 'activo') as total_cursos,
                (SELECT COUNT(*) FROM matriculas WHERE estado = 'activo') as total_matriculas,
                (SELECT COUNT(*) FROM sesiones WHERE estado = 'realizada') as total_sesiones,
                (SELECT COUNT(*) FROM evaluaciones_sesion) as total_evaluaciones
        ");
        $overview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $overview;
    } catch (PDOException $e) {
        return array(
            'total_usuarios' => 0, 'total_estudiantes' => 0, 'total_docentes' => 0,
            'total_programas' => 0, 'total_cursos' => 0, 'total_matriculas' => 0,
            'total_sesiones' => 0, 'total_evaluaciones' => 0
        );
    }
}

function getProgramsReport($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                pe.id,
                pe.nombre as programa_nombre,
                pe.codigo as programa_codigo,
                COUNT(DISTINCT ud.id) as total_cursos,
                COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
                COUNT(DISTINCT ud.docente_id) as total_docentes,
                AVG(es.calificacion) as promedio_general
            FROM programas_estudio pe
            LEFT JOIN unidades_didacticas ud ON pe.id = ud.programa_id AND ud.estado = 'activo'
            LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id IN (
                SELECT s.id FROM sesiones s WHERE s.unidad_didactica_id = ud.id
            )
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
            WHERE pe.estado = 'activo'
            GROUP BY pe.id, pe.nombre, pe.codigo
            ORDER BY pe.nombre
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getCoursesReport($pdo, $programa_id = null) {
    try {
        $where_clause = $programa_id ? "AND ud.programa_id = ?" : "";
        $params = $programa_id ? [$programa_id] : [];
        
        $stmt = $pdo->prepare("
            SELECT 
                ud.id,
                ud.nombre as curso_nombre,
                ud.codigo as curso_codigo,
                ud.periodo_lectivo,
                ud.periodo_academico,
                pe.nombre as programa_nombre,
                CONCAT(u.nombres, ' ', u.apellidos) as docente_nombre,
                COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
                COUNT(DISTINCT s.id) as total_sesiones,
                COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_realizadas,
                AVG(es.calificacion) as promedio_curso,
                COUNT(es.calificacion) as total_evaluaciones
            FROM unidades_didacticas ud
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            LEFT JOIN usuarios u ON ud.docente_id = u.id
            LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
            LEFT JOIN sesiones s ON ud.id = s.unidad_didactica_id
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
            WHERE ud.estado = 'activo' $where_clause
            GROUP BY ud.id, ud.nombre, ud.codigo, ud.periodo_lectivo, ud.periodo_academico, 
                     pe.nombre, u.nombres, u.apellidos
            ORDER BY ud.periodo_lectivo DESC, ud.nombre
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getStudentsPerformance($pdo, $programa_id = null) {
    try {
        $where_clause = $programa_id ? "AND ud.programa_id = ?" : "";
        $params = $programa_id ? [$programa_id] : [];
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id as estudiante_id,
                u.dni,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                pe.nombre as programa_nombre,
                COUNT(DISTINCT m.unidad_didactica_id) as cursos_matriculados,
                AVG(es.calificacion) as promedio_general,
                COUNT(es.calificacion) as total_evaluaciones,
                ROUND(AVG(CASE WHEN a.estado = 'presente' THEN 100 ELSE 0 END), 1) as porcentaje_asistencia
            FROM usuarios u
            JOIN matriculas m ON u.id = m.estudiante_id AND m.estado = 'activo'
            JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id AND ud.estado = 'activo'
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
            LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id AND es.estudiante_id = u.id
            WHERE u.tipo_usuario = 'estudiante' AND u.estado = 'activo' $where_clause
            GROUP BY u.id, u.dni, u.nombres, u.apellidos, pe.nombre
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getTeachersReport($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                u.id as docente_id,
                u.dni,
                CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo,
                u.email,
                COUNT(DISTINCT ud.id) as cursos_asignados,
                COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
                COUNT(DISTINCT s.id) as total_sesiones,
                COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_realizadas,
                AVG(es.calificacion) as promedio_evaluaciones
            FROM usuarios u
            LEFT JOIN unidades_didacticas ud ON u.id = ud.docente_id AND ud.estado = 'activo'
            LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
            LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
            WHERE u.tipo_usuario = 'docente' AND u.estado = 'activo'
            GROUP BY u.id, u.dni, u.nombres, u.apellidos, u.email
            ORDER BY u.apellidos, u.nombres
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getAttendanceReport($pdo, $programa_id = null) {
    try {
        $where_clause = $programa_id ? "AND ud.programa_id = ?" : "";
        $params = $programa_id ? [$programa_id] : [];
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id as estudiante_id,
                u.dni,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                pe.nombre as programa_nombre,
                ud.nombre as curso_nombre,
                COUNT(s.id) as total_sesiones,
                COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) as asistencias,
                COUNT(CASE WHEN a.estado = 'falta' THEN 1 END) as faltas,
                COUNT(CASE WHEN a.estado = 'permiso' THEN 1 END) as permisos,
                ROUND((COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) / NULLIF(COUNT(s.id), 0)) * 100, 1) as porcentaje_asistencia
            FROM usuarios u
            JOIN matriculas m ON u.id = m.estudiante_id AND m.estado = 'activo'
            JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id AND ud.estado = 'activo'
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id AND s.estado = 'realizada'
            LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
            WHERE u.tipo_usuario = 'estudiante' AND u.estado = 'activo' $where_clause
            GROUP BY u.id, u.dni, u.nombres, u.apellidos, pe.nombre, ud.id, ud.nombre
            HAVING total_sesiones > 0
            ORDER BY u.apellidos, u.nombres, ud.nombre
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getPeriodsComparison($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                ud.periodo_lectivo,
                COUNT(DISTINCT ud.id) as total_cursos,
                COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
                COUNT(DISTINCT ud.docente_id) as total_docentes,
                COUNT(DISTINCT s.id) as total_sesiones,
                AVG(es.calificacion) as promedio_general,
                COUNT(es.calificacion) as total_evaluaciones
            FROM unidades_didacticas ud
            LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
            LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
            WHERE ud.estado = 'activo'
            GROUP BY ud.periodo_lectivo
            ORDER BY ud.periodo_lectivo DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getEnrollmentStats($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                pe.nombre as programa_nombre,
                ud.periodo_lectivo,
                COUNT(m.id) as total_matriculas,
                COUNT(CASE WHEN m.estado = 'activo' THEN 1 END) as matriculas_activas,
                COUNT(CASE WHEN m.estado = 'retirado' THEN 1 END) as estudiantes_retirados
            FROM matriculas m
            JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            GROUP BY pe.id, pe.nombre, ud.periodo_lectivo
            ORDER BY ud.periodo_lectivo DESC, pe.nombre
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

// Variables principales
$user_name = ($_SESSION['nombres'] ?? 'Administrador') . ' ' . ($_SESSION['apellidos'] ?? 'Principal');
$systemOverview = getSystemOverview($pdo);
$programsReport = getProgramsReport($pdo);
$coursesReport = getCoursesReport($pdo);
$studentsPerformance = getStudentsPerformance($pdo);
$teachersReport = getTeachersReport($pdo);
$attendanceReport = getAttendanceReport($pdo);
$periodsComparison = getPeriodsComparison($pdo);
$enrollmentStats = getEnrollmentStats($pdo);

// Filtros
$selectedProgram = isset($_GET['programa_id']) && is_numeric($_GET['programa_id']) ? intval($_GET['programa_id']) : null;
if ($selectedProgram) {
    $coursesReport = getCoursesReport($pdo, $selectedProgram);
    $studentsPerformance = getStudentsPerformance($pdo, $selectedProgram);
    $attendanceReport = getAttendanceReport($pdo, $selectedProgram);
}

// Exportar CSV si se solicita
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $report_type = $_GET['report'] ?? 'overview';
    
    header('Content-Type: text/csv; charset=utf-8');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($report_type) {
        case 'overview':
            header('Content-Disposition: attachment; filename="reporte_general_sistema_' . date('Y-m-d') . '.csv"');
            
            fputcsv($output, array('REPORTE GENERAL DEL SISTEMA'), ';');
            fputcsv($output, array(''), ';');
            fputcsv($output, array('Fecha de generación:', date('d/m/Y H:i')), ';');
            fputcsv($output, array('Generado por:', $user_name), ';');
            fputcsv($output, array(''), ';');
            
            fputcsv($output, array('ESTADÍSTICAS GENERALES'), ';');
            fputcsv($output, array('Métrica', 'Valor'), ';');
            fputcsv($output, array('Total de Usuarios', $systemOverview['total_usuarios']), ';');
            fputcsv($output, array('Total de Estudiantes', $systemOverview['total_estudiantes']), ';');
            fputcsv($output, array('Total de Docentes', $systemOverview['total_docentes']), ';');
            fputcsv($output, array('Total de Programas', $systemOverview['total_programas']), ';');
            fputcsv($output, array('Total de Cursos', $systemOverview['total_cursos']), ';');
            fputcsv($output, array('Total de Matrículas', $systemOverview['total_matriculas']), ';');
            fputcsv($output, array('Total de Sesiones', $systemOverview['total_sesiones']), ';');
            fputcsv($output, array('Total de Evaluaciones', $systemOverview['total_evaluaciones']), ';');
            break;
            
        case 'programs':
            header('Content-Disposition: attachment; filename="reporte_programas_' . date('Y-m-d') . '.csv"');
            
            fputcsv($output, array('REPORTE DE PROGRAMAS DE ESTUDIO'), ';');
            fputcsv($output, array(''), ';');
            fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
            fputcsv($output, array(''), ';');
            
            $headers = array('Programa', 'Código', 'Total Cursos', 'Total Estudiantes', 'Total Docentes', 'Promedio General');
            fputcsv($output, $headers, ';');
            
            foreach ($programsReport as $program) {
                $row = array(
                    $program['programa_nombre'],
                    $program['programa_codigo'],
                    $program['total_cursos'],
                    $program['total_estudiantes'],
                    $program['total_docentes'],
                    $program['promedio_general'] ? number_format($program['promedio_general'], 1) : 'Sin datos'
                );
                fputcsv($output, $row, ';');
            }
            break;
            
        case 'students':
            header('Content-Disposition: attachment; filename="reporte_estudiantes_' . date('Y-m-d') . '.csv"');
            
            fputcsv($output, array('REPORTE DE RENDIMIENTO ESTUDIANTIL'), ';');
            fputcsv($output, array(''), ';');
            fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
            fputcsv($output, array(''), ';');
            
            $headers = array('DNI', 'Estudiante', 'Programa', 'Cursos Matriculados', 'Promedio General', 'Total Evaluaciones', '% Asistencia');
            fputcsv($output, $headers, ';');
            
            foreach ($studentsPerformance as $student) {
                $row = array(
                    $student['dni'],
                    $student['nombre_completo'],
                    $student['programa_nombre'],
                    $student['cursos_matriculados'],
                    $student['promedio_general'] ? number_format($student['promedio_general'], 1) : 'Sin datos',
                    $student['total_evaluaciones'],
                    $student['porcentaje_asistencia'] ? $student['porcentaje_asistencia'] . '%' : 'Sin datos'
                );
                fputcsv($output, $row, ';');
            }
            break;
            
        case 'teachers':
            header('Content-Disposition: attachment; filename="reporte_docentes_' . date('Y-m-d') . '.csv"');
            
            fputcsv($output, array('REPORTE DE DOCENTES'), ';');
            fputcsv($output, array(''), ';');
            fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
            fputcsv($output, array(''), ';');
            
            $headers = array('DNI', 'Docente', 'Email', 'Cursos Asignados', 'Total Estudiantes', 'Sesiones Realizadas', 'Promedio Evaluaciones');
            fputcsv($output, $headers, ';');
            
            foreach ($teachersReport as $teacher) {
                $row = array(
                    $teacher['dni'],
                    $teacher['nombre_completo'],
                    $teacher['email'],
                    $teacher['cursos_asignados'],
                    $teacher['total_estudiantes'],
                    $teacher['sesiones_realizadas'],
                    $teacher['promedio_evaluaciones'] ? number_format($teacher['promedio_evaluaciones'], 1) : 'Sin datos'
                );
                fputcsv($output, $row, ';');
            }
            break;
            
        case 'attendance':
            header('Content-Disposition: attachment; filename="reporte_asistencias_' . date('Y-m-d') . '.csv"');
            
            fputcsv($output, array('REPORTE GLOBAL DE ASISTENCIAS'), ';');
            fputcsv($output, array(''), ';');
            fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
            fputcsv($output, array(''), ';');
            
            $headers = array('DNI', 'Estudiante', 'Programa', 'Curso', 'Total Sesiones', 'Asistencias', 'Faltas', 'Permisos', '% Asistencia');
            fputcsv($output, $headers, ';');
            
            foreach ($attendanceReport as $attendance) {
                $row = array(
                    $attendance['dni'],
                    $attendance['nombre_completo'],
                    $attendance['programa_nombre'],
                    $attendance['curso_nombre'],
                    $attendance['total_sesiones'],
                    $attendance['asistencias'],
                    $attendance['faltas'],
                    $attendance['permisos'],
                    $attendance['porcentaje_asistencia'] . '%'
                );
                fputcsv($output, $row, ';');
            }
            break;
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Administrativos - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --shadow-hover: 0 4px 25px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }
        
        .card h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-color);
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, white, #f8f9fa);
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card .icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .stat-card .label {
            color: #666;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .report-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: translateX(-100%);
            transition: var(--transition);
        }
        
        .report-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .report-card:hover::before {
            transform: translateX(0);
        }
        
        .report-card .icon {
            font-size: 3.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .report-card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .report-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: var(--transition);
            line-height: 1;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-excel {
            background: #107C41;
            color: white;
        }
        
        .btn-excel:hover {
            background: #0e6b37;
        }
        
        .btn-back {
            background: var(--light-color);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-back:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: white;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 10px;
            text-align: left;
            border: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-align: center;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .data-table tr:hover {
            background-color: #e3f2fd;
        }
        
        .data-table .text-center {
            text-align: center;
        }
        
        .data-table .text-right {
            text-align: right;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border: 1px solid transparent;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .filters-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border: 2px solid #2196f3;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .tabs {
            display: flex;
            border-bottom: 3px solid var(--border-color);
            margin-bottom: 25px;
            background: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .tab {
            padding: 18px 25px;
            cursor: pointer;
            border: none;
            background: var(--light-color);
            color: #666;
            border-bottom: 4px solid transparent;
            transition: var(--transition);
            flex: 1;
            text-align: center;
            font-weight: 500;
            border-right: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab:last-child {
            border-right: none;
        }
        
        .tab:hover {
            color: var(--primary-color);
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .tab.active {
            color: var(--primary-color);
            background: white;
            border-bottom-color: var(--primary-color);
            font-weight: 600;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .export-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0fff0 100%);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border: 2px solid var(--success-color);
            text-align: center;
        }
        
        .export-section h3 {
            color: var(--success-color);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #666;
            background: var(--light-color);
            border-radius: var(--border-radius);
            border: 2px dashed var(--border-color);
        }
        
        .no-data i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        @media print {
            .header, .btn, .export-section, .filters-section {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .data-table {
                font-size: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .overview-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-chart-bar"></i> Reportes Administrativos</h1>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($user_name); ?></span>
                <a href="../dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['test']) && $_GET['test'] == '1'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-flask"></i>
                <strong>MODO TESTING ACTIVADO:</strong> Sesión temporal de administrador creada.
                <a href="?" style="color: #856404; text-decoration: underline; margin-left: 10px;">Quitar modo testing</a>
            </div>
        <?php endif; ?>
        
        <!-- Resumen General del Sistema -->
        <div class="card">
            <h2><i class="fas fa-tachometer-alt"></i> Resumen General del Sistema</h2>
            <div class="overview-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number"><?php echo $systemOverview['total_usuarios']; ?></div>
                    <div class="label">Total Usuarios</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="number"><?php echo $systemOverview['total_estudiantes']; ?></div>
                    <div class="label">Estudiantes</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="number"><?php echo $systemOverview['total_docentes']; ?></div>
                    <div class="label">Docentes</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="number"><?php echo $systemOverview['total_programas']; ?></div>
                    <div class="label">Programas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-book"></i></div>
                    <div class="number"><?php echo $systemOverview['total_cursos']; ?></div>
                    <div class="label">Unidades Didácticas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                    <div class="number"><?php echo $systemOverview['total_matriculas']; ?></div>
                    <div class="label">Matrículas Activas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="number"><?php echo $systemOverview['total_sesiones']; ?></div>
                    <div class="label">Sesiones Realizadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="number"><?php echo $systemOverview['total_evaluaciones']; ?></div>
                    <div class="label">Evaluaciones</div>
                </div>
            </div>
            
            <div class="export-section">
                <h3><i class="fas fa-download"></i> Exportar Resumen General</h3>
                <p>Descargue el resumen completo del sistema en formato CSV</p>
                <a href="?export=csv&report=overview<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                    <i class="fas fa-file-csv"></i> Descargar CSV
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <?php if (!empty($programsReport)): ?>
            <div class="filters-section">
                <h3><i class="fas fa-filter"></i> Filtros de Reportes</h3>
                <form method="GET" class="filters-grid">
                    <?php if (isset($_GET['test'])): ?>
                        <input type="hidden" name="test" value="1">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="programa_id"><i class="fas fa-graduation-cap"></i> Filtrar por Programa:</label>
                        <select name="programa_id" id="programa_id" onchange="this.form.submit()">
                            <option value="">Todos los programas</option>
                            <?php foreach ($programsReport as $program): ?>
                                <option value="<?php echo $program['id']; ?>" <?php echo $selectedProgram == $program['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($program['programa_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="location.href='?<?php echo isset($_GET['test']) ? 'test=1' : ''; ?>'" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpiar Filtros
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Reportes Detallados -->
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="showTab('programs')">
                    <i class="fas fa-graduation-cap"></i> Programas
                </button>
                <button class="tab" onclick="showTab('courses')">
                    <i class="fas fa-book"></i> Cursos
                </button>
                <button class="tab" onclick="showTab('students')">
                    <i class="fas fa-user-graduate"></i> Estudiantes
                </button>
                <button class="tab" onclick="showTab('teachers')">
                    <i class="fas fa-chalkboard-teacher"></i> Docentes
                </button>
                <button class="tab" onclick="showTab('attendance')">
                    <i class="fas fa-user-check"></i> Asistencias
                </button>
            </div>
            
            <!-- Tab: Programas -->
            <div id="programs" class="tab-content active">
                <div class="export-section">
                    <h3><i class="fas fa-graduation-cap"></i> Reporte de Programas de Estudio</h3>
                    <p>Estadísticas completas por programa académico</p>
                    <a href="?export=csv&report=programs<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                        <i class="fas fa-download"></i> Exportar CSV
                    </a>
                </div>
                
                <?php if (empty($programsReport)): ?>
                    <div class="no-data">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>No hay programas registrados</h3>
                        <p>Aún no se han creado programas de estudio en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Programa</th>
                                    <th>Código</th>
                                    <th class="text-center">Cursos</th>
                                    <th class="text-center">Estudiantes</th>
                                    <th class="text-center">Docentes</th>
                                    <th class="text-center">Promedio General</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programsReport as $program): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($program['programa_nombre']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($program['programa_codigo']); ?></td>
                                        <td class="text-center"><?php echo $program['total_cursos']; ?></td>
                                        <td class="text-center"><?php echo $program['total_estudiantes']; ?></td>
                                        <td class="text-center"><?php echo $program['total_docentes']; ?></td>
                                        <td class="text-center">
                                            <?php echo $program['promedio_general'] ? number_format($program['promedio_general'], 1) : 'Sin datos'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Cursos -->
            <div id="courses" class="tab-content">
                <div class="export-section">
                    <h3><i class="fas fa-book"></i> Reporte de Unidades Didácticas</h3>
                    <p>Estadísticas detalladas por curso <?php echo $selectedProgram ? '(Filtrado por programa)' : ''; ?></p>
                    <a href="?export=csv&report=courses<?php echo $selectedProgram ? '&programa_id=' . $selectedProgram : ''; ?><?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                        <i class="fas fa-download"></i> Exportar CSV
                    </a>
                </div>
                
                <?php if (empty($coursesReport)): ?>
                    <div class="no-data">
                        <i class="fas fa-book"></i>
                        <h3>No hay cursos registrados</h3>
                        <p>No se encontraron unidades didácticas<?php echo $selectedProgram ? ' para el programa seleccionado' : ''; ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Programa</th>
                                    <th>Docente</th>
                                    <th>Período</th>
                                    <th class="text-center">Estudiantes</th>
                                    <th class="text-center">Sesiones</th>
                                    <th class="text-center">Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coursesReport as $course): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($course['curso_nombre']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($course['curso_codigo']); ?></small></td>
                                        <td><?php echo htmlspecialchars($course['programa_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($course['docente_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($course['periodo_lectivo']); ?><br>
                                            <small><?php echo htmlspecialchars($course['periodo_academico']); ?></small></td>
                                        <td class="text-center"><?php echo $course['total_estudiantes']; ?></td>
                                        <td class="text-center"><?php echo $course['sesiones_realizadas']; ?>/<?php echo $course['total_sesiones']; ?></td>
                                        <td class="text-center">
                                            <?php echo $course['promedio_curso'] ? number_format($course['promedio_curso'], 1) : 'Sin datos'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Estudiantes -->
            <div id="students" class="tab-content">
                <div class="export-section">
                    <h3><i class="fas fa-user-graduate"></i> Reporte de Rendimiento Estudiantil</h3>
                    <p>Análisis completo del rendimiento académico <?php echo $selectedProgram ? '(Filtrado por programa)' : ''; ?></p>
                    <a href="?export=csv&report=students<?php echo $selectedProgram ? '&programa_id=' . $selectedProgram : ''; ?><?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                        <i class="fas fa-download"></i> Exportar CSV
                    </a>
                </div>
                
                <?php if (empty($studentsPerformance)): ?>
                    <div class="no-data">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No hay estudiantes registrados</h3>
                        <p>No se encontraron estudiantes<?php echo $selectedProgram ? ' para el programa seleccionado' : ''; ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Estudiante</th>
                                    <th>Programa</th>
                                    <th class="text-center">Cursos</th>
                                    <th class="text-center">Promedio</th>
                                    <th class="text-center">Evaluaciones</th>
                                    <th class="text-center">% Asistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsPerformance as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['dni']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['nombre_completo']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['programa_nombre']); ?></td>
                                        <td class="text-center"><?php echo $student['cursos_matriculados']; ?></td>
                                        <td class="text-center">
                                            <?php echo $student['promedio_general'] ? number_format($student['promedio_general'], 1) : 'Sin datos'; ?>
                                        </td>
                                        <td class="text-center"><?php echo $student['total_evaluaciones']; ?></td>
                                        <td class="text-center">
                                            <?php echo $student['porcentaje_asistencia'] ? $student['porcentaje_asistencia'] . '%' : 'Sin datos'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Docentes -->
            <div id="teachers" class="tab-content">
                <div class="export-section">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Reporte de Docentes</h3>
                    <p>Estadísticas de carga académica y rendimiento por docente</p>
                    <a href="?export=csv&report=teachers<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                        <i class="fas fa-download"></i> Exportar CSV
                    </a>
                </div>
                
                <?php if (empty($teachersReport)): ?>
                    <div class="no-data">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>No hay docentes registrados</h3>
                        <p>No se encontraron docentes en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Docente</th>
                                    <th>Email</th>
                                    <th class="text-center">Cursos</th>
                                    <th class="text-center">Estudiantes</th>
                                    <th class="text-center">Sesiones</th>
                                    <th class="text-center">Promedio Eval.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachersReport as $teacher): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($teacher['dni']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($teacher['nombre_completo']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                        <td class="text-center"><?php echo $teacher['cursos_asignados']; ?></td>
                                        <td class="text-center"><?php echo $teacher['total_estudiantes']; ?></td>
                                        <td class="text-center"><?php echo $teacher['sesiones_realizadas']; ?></td>
                                        <td class="text-center">
                                            <?php echo $teacher['promedio_evaluaciones'] ? number_format($teacher['promedio_evaluaciones'], 1) : 'Sin datos'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Asistencias -->
            <div id="attendance" class="tab-content">
                <div class="export-section">
                    <h3><i class="fas fa-user-check"></i> Reporte Global de Asistencias</h3>
                    <p>Control de asistencias por estudiante y curso <?php echo $selectedProgram ? '(Filtrado por programa)' : ''; ?></p>
                    <a href="?export=csv&report=attendance<?php echo $selectedProgram ? '&programa_id=' . $selectedProgram : ''; ?><?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                        <i class="fas fa-download"></i> Exportar CSV
                    </a>
                </div>
                
                <?php if (empty($attendanceReport)): ?>
                    <div class="no-data">
                        <i class="fas fa-user-check"></i>
                        <h3>No hay datos de asistencia</h3>
                        <p>No se encontraron registros de asistencia<?php echo $selectedProgram ? ' para el programa seleccionado' : ''; ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Estudiante</th>
                                    <th>Programa</th>
                                    <th>Curso</th>
                                    <th class="text-center">Sesiones</th>
                                    <th class="text-center">Asistencias</th>
                                    <th class="text-center">Faltas</th>
                                    <th class="text-center">% Asistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceReport as $attendance): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attendance['dni']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($attendance['nombre_completo']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($attendance['programa_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($attendance['curso_nombre']); ?></td>
                                        <td class="text-center"><?php echo $attendance['total_sesiones']; ?></td>
                                        <td class="text-center"><?php echo $attendance['asistencias']; ?></td>
                                        <td class="text-center"><?php echo $attendance['faltas']; ?></td>
                                        <td class="text-center">
                                            <span style="color: <?php echo $attendance['porcentaje_asistencia'] >= 70 ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $attendance['porcentaje_asistencia']; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Análisis Comparativo por Períodos -->
        <?php if (!empty($periodsComparison)): ?>
            <div class="card">
                <h2><i class="fas fa-chart-line"></i> Análisis Comparativo por Períodos</h2>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Período Lectivo</th>
                                <th class="text-center">Cursos</th>
                                <th class="text-center">Estudiantes</th>
                                <th class="text-center">Docentes</th>
                                <th class="text-center">Sesiones</th>
                                <th class="text-center">Evaluaciones</th>
                                <th class="text-center">Promedio General</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodsComparison as $period): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($period['periodo_lectivo']); ?></strong></td>
                                    <td class="text-center"><?php echo $period['total_cursos']; ?></td>
                                    <td class="text-center"><?php echo $period['total_estudiantes']; ?></td>
                                    <td class="text-center"><?php echo $period['total_docentes']; ?></td>
                                    <td class="text-center"><?php echo $period['total_sesiones']; ?></td>
                                    <td class="text-center"><?php echo $period['total_evaluaciones']; ?></td>
                                    <td class="text-center">
                                        <?php echo $period['promedio_general'] ? number_format($period['promedio_general'], 1) : 'Sin datos'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas de Matrículas -->
        <?php if (!empty($enrollmentStats)): ?>
            <div class="card">
                <h2><i class="fas fa-chart-pie"></i> Estadísticas de Matrículas</h2>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Programa</th>
                                <th>Período</th>
                                <th class="text-center">Total Matrículas</th>
                                <th class="text-center">Activas</th>
                                <th class="text-center">Retirados</th>
                                <th class="text-center">% Retención</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollmentStats as $stat): ?>
                                <?php 
                                $retencion = $stat['total_matriculas'] > 0 ? 
                                    round(($stat['matriculas_activas'] / $stat['total_matriculas']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['programa_nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($stat['periodo_lectivo']); ?></td>
                                    <td class="text-center"><?php echo $stat['total_matriculas']; ?></td>
                                    <td class="text-center"><?php echo $stat['matriculas_activas']; ?></td>
                                    <td class="text-center"><?php echo $stat['estudiantes_retirados']; ?></td>
                                    <td class="text-center">
                                        <span style="color: <?php echo $retencion >= 80 ? '#28a745' : ($retencion >= 60 ? '#ffc107' : '#dc3545'); ?>;">
                                            <?php echo $retencion; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function showTab(tabName) {
            // Ocultar todos los contenidos de tabs
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Quitar clase activa de todos los tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el tab clickeado
            const clickedTab = event.target.closest('.tab');
            if (clickedTab) {
                clickedTab.classList.add('active');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar event listeners a los tabs
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabText = this.textContent.trim();
                    if (tabText.includes('Programas')) {
                        showTab('programs');
                    } else if (tabText.includes('Cursos')) {
                        showTab('courses');
                    } else if (tabText.includes('Estudiantes')) {
                        showTab('students');
                    } else if (tabText.includes('Docentes')) {
                        showTab('teachers');
                    } else if (tabText.includes('Asistencias')) {
                        showTab('attendance');
                    }
                });
            });
            
            // Animaciones de entrada para las tarjetas de estadísticas
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animaciones para las tarjetas principales
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(30px)';
                    card.style.transition = 'all 0.8s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, 500 + (index * 200));
            });
            
            // Efecto hover para las filas de la tabla
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#e3f2fd';
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = '';
                });
            });
            
            // Indicador de carga para exportaciones
            const exportLinks = document.querySelectorAll('a[href*="export=csv"]');
            exportLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const button = this;
                    const originalText = button.innerHTML;
                    
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
                    button.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.style.pointerEvents = 'auto';
                        
                        // Mostrar mensaje de éxito
                        showNotification('Archivo CSV generado exitosamente', 'success');
                    }, 2000);
                });
            });
        });
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>