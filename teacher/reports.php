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
    $_SESSION['user_id'] = 2;
    $_SESSION['tipo_usuario'] = 'docente';
    $_SESSION['nombres'] = 'Docente';
    $_SESSION['apellidos'] = 'Temporal';
    $_SESSION['dni'] = '12345678';
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
$username = 'iespaltohuallaga_user_regaux'; // Ajustar según tu configuración
$password = ')wBRCeID[ldb%b^K';     // Ajustar según tu configuración

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
            $_SESSION['tipo_usuario'] = 'docente';
            $_SESSION['nombres'] = 'Docente';
            $_SESSION['apellidos'] = 'Temporal';
        }
    } catch (PDOException $e) {
        $_SESSION['tipo_usuario'] = 'docente';
        $_SESSION['nombres'] = 'Docente';
        $_SESSION['apellidos'] = 'Temporal';
    }
}

// Verificar permisos básicos
$allowed_types = array('docente', 'super_admin');
if (!in_array($_SESSION['tipo_usuario'], $allowed_types)) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>
    <body>
        <h2>Acceso Denegado</h2>
        <p><a href="../dashboard.php">Volver al Dashboard</a> | <a href="?test=1">Modo Testing</a></p>
    </body></html>
    <?php
    exit();
}

// Funciones simplificadas
function getCoursesByTeacher($pdo, $teacher_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ud.id, ud.nombre, ud.codigo, ud.periodo_lectivo, ud.periodo_academico,
                   pe.nombre as programa_nombre
            FROM unidades_didacticas ud
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            WHERE ud.docente_id = ? AND ud.estado = 'activo'
            ORDER BY ud.periodo_lectivo DESC
        ");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getCourseById($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ud.*, pe.nombre as programa_nombre
            FROM unidades_didacticas ud
            LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
            WHERE ud.id = ?
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

function getStudentsWithGrades($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.dni,
                u.nombres,
                u.apellidos,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo
            FROM matriculas m
            JOIN usuarios u ON m.estudiante_id = u.id
            WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getLearningIndicators($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, numero_indicador, nombre, peso
            FROM indicadores_logro
            WHERE unidad_didactica_id = ?
            ORDER BY numero_indicador
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getStudentGrades($pdo, $course_id, $student_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                il.id as indicador_id,
                il.numero_indicador,
                AVG(es.calificacion) as promedio
            FROM indicadores_logro il
            LEFT JOIN indicadores_evaluacion ie ON ie.indicador_logro_id = il.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id AND es.estudiante_id = ?
            WHERE il.unidad_didactica_id = ?
            GROUP BY il.id, il.numero_indicador
            ORDER BY il.numero_indicador
        ");
        $stmt->execute([$student_id, $course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getSessionsByCourse($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, numero_sesion, titulo, fecha, descripcion, estado
            FROM sesiones
            WHERE unidad_didactica_id = ?
            ORDER BY numero_sesion ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getAttendanceData($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id as estudiante_id,
                u.dni,
                u.nombres,
                u.apellidos,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                COUNT(s.id) as total_sesiones,
                COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) as asistencias,
                COUNT(CASE WHEN a.estado = 'falta' THEN 1 END) as faltas,
                COUNT(CASE WHEN a.estado = 'permiso' THEN 1 END) as permisos,
                ROUND((COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) / NULLIF(COUNT(s.id), 0)) * 100, 1) as porcentaje_asistencia
            FROM matriculas m
            JOIN usuarios u ON m.estudiante_id = u.id
            LEFT JOIN sesiones s ON s.unidad_didactica_id = m.unidad_didactica_id AND s.estado = 'realizada'
            LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
            WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            GROUP BY u.id, u.dni, u.nombres, u.apellidos
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getAttendanceBySession($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.id as sesion_id,
                s.numero_sesion,
                s.titulo,
                s.fecha,
                u.id as estudiante_id,
                u.dni,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                COALESCE(a.estado, 'sin_registro') as estado_asistencia
            FROM sesiones s
            CROSS JOIN (
                SELECT u.id, u.dni, u.nombres, u.apellidos
                FROM matriculas m
                JOIN usuarios u ON m.estudiante_id = u.id
                WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            ) u
            LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
            WHERE s.unidad_didactica_id = ? AND s.estado = 'realizada'
            ORDER BY s.numero_sesion, u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id, $course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getConsolidatedData($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id as estudiante_id,
                u.dni,
                u.nombres,
                u.apellidos,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                -- Asistencias
                COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) as asistencias,
                COUNT(CASE WHEN a.estado = 'falta' THEN 1 END) as faltas,
                COUNT(CASE WHEN a.estado = 'permiso' THEN 1 END) as permisos,
                COUNT(s.id) as total_sesiones,
                ROUND((COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) / NULLIF(COUNT(s.id), 0)) * 100, 1) as porcentaje_asistencia,
                -- Evaluaciones
                AVG(es.calificacion) as promedio_evaluaciones,
                COUNT(es.calificacion) as total_evaluaciones
            FROM matriculas m
            JOIN usuarios u ON m.estudiante_id = u.id
            LEFT JOIN sesiones s ON s.unidad_didactica_id = m.unidad_didactica_id AND s.estado = 'realizada'
            LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
            LEFT JOIN indicadores_evaluacion ie ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id AND es.estudiante_id = u.id
            WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            GROUP BY u.id, u.dni, u.nombres, u.apellidos
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getEvaluationsByIndicator($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                il.numero_indicador,
                il.nombre as indicador_nombre,
                il.peso as indicador_peso,
                u.id as estudiante_id,
                u.dni,
                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                ie.nombre as evaluacion_nombre,
                s.numero_sesion,
                s.titulo as sesion_titulo,
                es.calificacion,
                es.fecha_evaluacion
            FROM indicadores_logro il
            LEFT JOIN indicadores_evaluacion ie ON ie.indicador_logro_id = il.id
            LEFT JOIN sesiones s ON s.id = ie.sesion_id
            LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
            LEFT JOIN usuarios u ON u.id = es.estudiante_id
            LEFT JOIN matriculas m ON m.estudiante_id = u.id AND m.unidad_didactica_id = ?
            WHERE il.unidad_didactica_id = ? AND (m.estado = 'activo' OR m.estado IS NULL)
            ORDER BY il.numero_indicador, u.apellidos, u.nombres, s.numero_sesion
        ");
        $stmt->execute([$course_id, $course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function calculateFinalGrade($grades, $indicators) {
    $total = 0;
    $count = 0;
    
    foreach ($grades as $grade) {
        if ($grade['promedio'] !== null && $grade['promedio'] > 0) {
            $total += $grade['promedio'];
            $count++;
        }
    }
    
    return $count > 0 ? $total / $count : 0;
}

function getCondition($grade) {
    if ($grade >= 18) return 'EXCELENTE';
    if ($grade >= 15) return 'APROBADO';
    if ($grade >= 12.5) return 'APROBADO';
    if ($grade >= 9.5) return 'EN PROCESO';
    return 'DESAPROBADO';
}

// Variables principales
$myCourses = getCoursesByTeacher($pdo, $user_id);
$selectedCourse = null;
$students = array();
$indicators = array();
$sessions = array();
$attendanceData = array();
$attendanceBySession = array();
$consolidatedData = array();
$evaluationsByIndicator = array();
$user_name = ($_SESSION['nombres'] ?? 'Docente') . ' ' . ($_SESSION['apellidos'] ?? 'Temporal');

if (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    $selectedCourse = getCourseById($pdo, $course_id);
    
    if ($selectedCourse) {
        $students = getStudentsWithGrades($pdo, $course_id);
        $indicators = getLearningIndicators($pdo, $course_id);
        $sessions = getSessionsByCourse($pdo, $course_id);
        $attendanceData = getAttendanceData($pdo, $course_id);
        $attendanceBySession = getAttendanceBySession($pdo, $course_id);
        $consolidatedData = getConsolidatedData($pdo, $course_id);
        $evaluationsByIndicator = getEvaluationsByIndicator($pdo, $course_id);
    }
}

// Exportar CSV si se solicita
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $selectedCourse) {
    $report_type = $_GET['report'] ?? 'academic';
    
    header('Content-Type: text/csv; charset=utf-8');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($report_type) {
        case 'academic':
            if (!empty($students)) {
                header('Content-Disposition: attachment; filename="ficha_evaluacion_academica_' . $selectedCourse['codigo'] . '_' . date('Y-m-d') . '.csv"');
                
                // Encabezados del archivo
                fputcsv($output, array('FICHA DE EVALUACIÓN ACADÉMICA'), ';');
                fputcsv($output, array(''), ';');
                fputcsv($output, array('Curso:', $selectedCourse['nombre']), ';');
                fputcsv($output, array('Programa:', $selectedCourse['programa_nombre'] ?? 'N/A'), ';');
                fputcsv($output, array('Período:', $selectedCourse['periodo_lectivo'] . ' - ' . $selectedCourse['periodo_academico']), ';');
                fputcsv($output, array('Docente:', $user_name), ';');
                fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
                fputcsv($output, array(''), ';');
                
                // Encabezados de tabla
                $headers = array('N°', 'DNI', 'APELLIDOS Y NOMBRES');
                foreach ($indicators as $indicator) {
                    $headers[] = 'IND-' . $indicator['numero_indicador'];
                }
                $headers[] = 'PROMEDIO FINAL';
                $headers[] = 'CONDICIÓN';
                
                fputcsv($output, $headers, ';');
                
                // Datos de estudiantes
                $contador = 1;
                foreach ($students as $student) {
                    $grades = getStudentGrades($pdo, $course_id, $student['id']);
                    $final_grade = calculateFinalGrade($grades, $indicators);
                    $condition = getCondition($final_grade);
                    
                    $row = array(
                        str_pad($contador, 2, '0', STR_PAD_LEFT),
                        $student['dni'],
                        $student['nombre_completo']
                    );
                    
                    // Calificaciones por indicador
                    foreach ($indicators as $indicator) {
                        $found = false;
                        foreach ($grades as $grade) {
                            if ($grade['numero_indicador'] == $indicator['numero_indicador']) {
                                $row[] = $grade['promedio'] ? number_format($grade['promedio'], 1) : '00';
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $row[] = '00';
                        }
                    }
                    
                    $row[] = $final_grade > 0 ? number_format($final_grade, 1) : '00';
                    $row[] = $condition;
                    
                    fputcsv($output, $row, ';');
                    $contador++;
                }
            }
            break;
            
        case 'attendance':
            if (!empty($attendanceData)) {
                header('Content-Disposition: attachment; filename="ficha_asistencia_' . $selectedCourse['codigo'] . '_' . date('Y-m-d') . '.csv"');
                
                fputcsv($output, array('FICHA DE ASISTENCIA'), ';');
                fputcsv($output, array(''), ';');
                fputcsv($output, array('Curso:', $selectedCourse['nombre']), ';');
                fputcsv($output, array('Programa:', $selectedCourse['programa_nombre'] ?? 'N/A'), ';');
                fputcsv($output, array('Período:', $selectedCourse['periodo_lectivo'] . ' - ' . $selectedCourse['periodo_academico']), ';');
                fputcsv($output, array('Docente:', $user_name), ';');
                fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
                fputcsv($output, array(''), ';');
                
                $headers = array('N°', 'DNI', 'APELLIDOS Y NOMBRES', 'TOTAL SESIONES', 'ASISTENCIAS', 'FALTAS', 'PERMISOS', '% ASISTENCIA', 'CONDICIÓN');
                fputcsv($output, $headers, ';');
                
                $contador = 1;
                foreach ($attendanceData as $attendance) {
                    $condition_attendance = $attendance['porcentaje_asistencia'] >= 70 ? 'CUMPLE' : 'DPI';
                    
                    $row = array(
                        str_pad($contador, 2, '0', STR_PAD_LEFT),
                        $attendance['dni'],
                        $attendance['nombre_completo'],
                        $attendance['total_sesiones'],
                        $attendance['asistencias'],
                        $attendance['faltas'],
                        $attendance['permisos'],
                        $attendance['porcentaje_asistencia'] . '%',
                        $condition_attendance
                    );
                    
                    fputcsv($output, $row, ';');
                    $contador++;
                }
            }
            break;
            
        case 'consolidated':
            if (!empty($consolidatedData)) {
                header('Content-Disposition: attachment; filename="consolidado_general_' . $selectedCourse['codigo'] . '_' . date('Y-m-d') . '.csv"');
                
                fputcsv($output, array('CONSOLIDADO GENERAL'), ';');
                fputcsv($output, array(''), ';');
                fputcsv($output, array('Curso:', $selectedCourse['nombre']), ';');
                fputcsv($output, array('Programa:', $selectedCourse['programa_nombre'] ?? 'N/A'), ';');
                fputcsv($output, array('Período:', $selectedCourse['periodo_lectivo'] . ' - ' . $selectedCourse['periodo_academico']), ';');
                fputcsv($output, array('Docente:', $user_name), ';');
                fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
                fputcsv($output, array(''), ';');
                
                $headers = array('N°', 'DNI', 'APELLIDOS Y NOMBRES', 'ASISTENCIAS', 'FALTAS', 'PERMISOS', '% ASISTENCIA', 'PROMEDIO NOTAS', 'TOTAL EVALUACIONES', 'ESTADO FINAL');
                fputcsv($output, $headers, ';');
                
                $contador = 1;
                foreach ($consolidatedData as $data) {
                    $estado_final = ($data['porcentaje_asistencia'] >= 70 && $data['promedio_evaluaciones'] >= 12.5) ? 'APROBADO' : 'DESAPROBADO';
                    
                    $row = array(
                        str_pad($contador, 2, '0', STR_PAD_LEFT),
                        $data['dni'],
                        $data['nombre_completo'],
                        $data['asistencias'],
                        $data['faltas'],
                        $data['permisos'],
                        $data['porcentaje_asistencia'] . '%',
                        $data['promedio_evaluaciones'] ? number_format($data['promedio_evaluaciones'], 1) : '00',
                        $data['total_evaluaciones'],
                        $estado_final
                    );
                    
                    fputcsv($output, $row, ';');
                    $contador++;
                }
            }
            break;
            
        case 'auxiliary':
            if (!empty($evaluationsByIndicator)) {
                header('Content-Disposition: attachment; filename="auxiliar_evaluacion_' . $selectedCourse['codigo'] . '_' . date('Y-m-d') . '.csv"');
                
                fputcsv($output, array('FICHA AUXILIAR DE EVALUACIÓN'), ';');
                fputcsv($output, array(''), ';');
                fputcsv($output, array('Curso:', $selectedCourse['nombre']), ';');
                fputcsv($output, array('Programa:', $selectedCourse['programa_nombre'] ?? 'N/A'), ';');
                fputcsv($output, array('Período:', $selectedCourse['periodo_lectivo'] . ' - ' . $selectedCourse['periodo_academico']), ';');
                fputcsv($output, array('Docente:', $user_name), ';');
                fputcsv($output, array('Fecha:', date('d/m/Y H:i')), ';');
                fputcsv($output, array(''), ';');
                
                $headers = array('INDICADOR', 'ESTUDIANTE', 'DNI', 'SESIÓN', 'EVALUACIÓN', 'CALIFICACIÓN', 'FECHA');
                fputcsv($output, $headers, ';');
                
                foreach ($evaluationsByIndicator as $eval) {
                    if ($eval['estudiante_id']) {
                        $row = array(
                            'IND-' . $eval['numero_indicador'] . ': ' . $eval['indicador_nombre'],
                            $eval['nombre_completo'],
                            $eval['dni'],
                            'Sesión ' . $eval['numero_sesion'] . ': ' . $eval['sesion_titulo'],
                            $eval['evaluacion_nombre'],
                            $eval['calificacion'] ? number_format($eval['calificacion'], 1) : 'Sin calificar',
                            $eval['fecha_evaluacion'] ? date('d/m/Y', strtotime($eval['fecha_evaluacion'])) : 'N/A'
                        );
                        
                        fputcsv($output, $row, ';');
                    }
                }
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
    <title>Reportes - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 20px;
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
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-excel {
            background: #107C41;
            color: white;
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-back:hover {
            background: #667eea;
            color: white;
        }
        
        .report-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .report-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .report-card .icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .report-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .report-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .course-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px solid #2196f3;
        }
        
        .course-info h3 {
            color: #1976d2;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .course-info p {
            margin: 8px 0;
            font-weight: 500;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: white;
        }
        
        .preview-table th,
        .preview-table td {
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .preview-table th {
            background: linear-gradient(135deg, #667eea 0%, #5a67d8 100%);
            color: white;
            font-weight: 600;
            font-size: 11px;
        }
        
        .preview-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .preview-table tr:hover {
            background-color: #e3f2fd;
        }
        
        .preview-table .student-name {
            text-align: left;
            font-weight: 500;
            min-width: 200px;
        }
        
        .condition-excellent {
            background-color: #d1ecf1 !important;
            color: #0c5460;
            font-weight: bold;
        }
        
        .condition-approved {
            background-color: #d4edda !important;
            color: #155724;
            font-weight: bold;
        }
        
        .condition-process {
            background-color: #fff3cd !important;
            color: #856404;
            font-weight: bold;
        }
        
        .condition-dpi {
            background-color: #f8d7da !important;
            color: #721c24;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #5a67d8 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .export-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0fff0 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px solid #28a745;
            text-align: center;
        }
        
        .export-section h3 {
            color: #28a745;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        @media print {
            .header, .btn, .export-section {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .preview-table {
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
            
            .report-options {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-chart-line"></i> Reportes y Consolidados</h1>
            <a href="../dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['test']) && $_GET['test'] == '1'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-flask"></i>
                <strong>MODO TESTING ACTIVADO:</strong> Sesión temporal creada.
                <a href="?" style="color: #856404; text-decoration: underline; margin-left: 10px;">Quitar modo testing</a>
            </div>
        <?php endif; ?>
        
        <!-- Selección de Curso -->
        <div class="card">
            <h2><i class="fas fa-book"></i> Seleccionar Unidad Didáctica</h2>
            <?php if (empty($myCourses)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No tiene unidades didácticas asignadas. Contacte al administrador.
                </div>
            <?php else: ?>
                <form method="GET">
                    <?php if (isset($_GET['test'])): ?>
                        <input type="hidden" name="test" value="1">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="course_id"><i class="fas fa-graduation-cap"></i> Unidad Didáctica:</label>
                        <select name="course_id" id="course_id" onchange="this.form.submit()" required>
                            <option value="">Seleccione una unidad didáctica...</option>
                            <?php foreach ($myCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['id']); ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombre']) . ' - ' . htmlspecialchars($c['periodo_lectivo']) . ' (' . htmlspecialchars($c['periodo_academico']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($selectedCourse): ?>
            <!-- Estadísticas del curso -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?php echo count($students); ?></h4>
                    <p><i class="fas fa-users"></i> Estudiantes</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo count($indicators); ?></h4>
                    <p><i class="fas fa-target"></i> Indicadores</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo count($sessions); ?></h4>
                    <p><i class="fas fa-calendar-alt"></i> Sesiones</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo count(array_filter($evaluationsByIndicator, function($item) { return $item['calificacion'] !== null; })); ?></h4>
                    <p><i class="fas fa-clipboard-check"></i> Evaluaciones</p>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-file-alt"></i> Reportes Disponibles</h2>
                <div class="course-info">
                    <h3><?php echo htmlspecialchars($selectedCourse['nombre']); ?></h3>
                    <p><strong><i class="fas fa-graduation-cap"></i> Programa:</strong> <?php echo htmlspecialchars($selectedCourse['programa_nombre'] ?? 'N/A'); ?></p>
                    <p><strong><i class="fas fa-calendar"></i> Período:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo']) . ' - ' . htmlspecialchars($selectedCourse['periodo_academico']); ?></p>
                    <p><strong><i class="fas fa-user-tie"></i> Docente:</strong> <?php echo htmlspecialchars($user_name); ?></p>
                </div>
                
                <div class="report-options">
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Ficha de Evaluación Académica</h3>
                        <p>Evaluaciones detalladas por indicadores con promedio final de cada estudiante</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=academic<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                    
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3>Ficha de Asistencia</h3>
                        <p>Control de asistencias, faltas y permisos por estudiante con porcentajes</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=attendance<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                    
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>Consolidado General</h3>
                        <p>Resumen completo de notas y asistencias con estado final del estudiante</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=consolidated<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                    
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>Auxiliar de Evaluación</h3>
                        <p>Detalle de todas las evaluaciones por indicador y sesión</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=auxiliary<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                    
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3>Reporte de Sesiones</h3>
                        <p>Resumen de sesiones realizadas con fechas y asistencia por sesión</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=sessions<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                    
                    <div class="report-card">
                        <div class="icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3>Análisis de Indicadores</h3>
                        <p>Estadísticas y análisis de rendimiento por indicador de logro</p>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=indicators<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Ver Reporte
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_GET['report'])): ?>
                <div class="card">
                    <?php if ($_GET['report'] == 'academic'): ?>
                        <?php if (!empty($students) && !empty($indicators)): ?>
                            <!-- Exportar CSV -->
                            <div class="export-section">
                                <h3><i class="fas fa-file-excel"></i> Exportar Ficha de Evaluación Académica</h3>
                                <p>Descargue la ficha completa en formato CSV (compatible con Excel)</p>
                                <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=academic&export=csv<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                                    <i class="fas fa-download"></i> Descargar CSV
                                </a>
                                <button onclick="window.print()" class="btn btn-success">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-chart-line"></i> Ficha de Evaluación Académica</h2>
                        </div>
                        
                        <?php if (empty($students)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay estudiantes matriculados en esta unidad didáctica.
                            </div>
                        <?php elseif (empty($indicators)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                No hay indicadores de logro configurados para esta unidad didáctica.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">N°</th>
                                            <th rowspan="2">DNI</th>
                                            <th rowspan="2">APELLIDOS Y NOMBRES</th>
                                            <?php foreach ($indicators as $indicator): ?>
                                                <th>PROMEDIO-<?php echo htmlspecialchars($indicator['numero_indicador']); ?></th>
                                            <?php endforeach; ?>
                                            <th rowspan="2">PROMEDIO<br>FINAL</th>
                                            <th rowspan="2">CONDICIÓN</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($indicators as $indicator): ?>
                                                <th style="font-size: 9px; max-width: 80px;">
                                                    <?php echo htmlspecialchars(substr($indicator['nombre'], 0, 25)) . (strlen($indicator['nombre']) > 25 ? '...' : ''); ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $contador = 1;
                                        foreach ($students as $student): 
                                            $grades = getStudentGrades($pdo, $course_id, $student['id']);
                                            $final_grade = calculateFinalGrade($grades, $indicators);
                                            $condition = getCondition($final_grade);
                                            
                                            // Determinar clase CSS
                                            $condition_class = 'condition-dpi';
                                            if ($condition == 'EXCELENTE') $condition_class = 'condition-excellent';
                                            elseif ($condition == 'LOGRO PREVISTO' || $condition == 'APROBADO') $condition_class = 'condition-approved';
                                            elseif ($condition == 'EN PROCESO') $condition_class = 'condition-process';
                                        ?>
                                            <tr>
                                                <td><?php echo str_pad($contador, 2, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($student['dni']); ?></td>
                                                <td class="student-name"><?php echo htmlspecialchars($student['nombre_completo']); ?></td>
                                                
                                                <?php foreach ($indicators as $indicator): ?>
                                                    <td>
                                                        <?php
                                                        $found = false;
                                                        foreach ($grades as $grade) {
                                                            if ($grade['numero_indicador'] == $indicator['numero_indicador']) {
                                                                echo $grade['promedio'] ? number_format($grade['promedio'], 1) : '00';
                                                                $found = true;
                                                                break;
                                                            }
                                                        }
                                                        if (!$found) echo '00';
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td style="font-weight: bold;">
                                                    <?php echo $final_grade > 0 ? number_format($final_grade, 1) : '00'; ?>
                                                </td>
                                                <td class="<?php echo $condition_class; ?>">
                                                    <?php echo $condition; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Leyenda de Condiciones:</strong>
                                    <ul style="margin: 10px 0; padding-left: 20px;">
                                        <li><strong>EXCELENTE:</strong> 18-20 puntos</li>
                                        <li><strong>LOGRO PREVISTO:</strong> 15-17 puntos</li>
                                        <li><strong>APROBADO:</strong> 12.5-14 puntos</li>
                                        <li><strong>EN PROCESO:</strong> 9.5-12 puntos</li>
                                        <li><strong>DESAPROBADO:</strong> Menor a 9.5 puntos</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($_GET['report'] == 'attendance'): ?>
                        <?php if (!empty($attendanceData)): ?>
                            <div class="export-section">
                                <h3><i class="fas fa-file-excel"></i> Exportar Ficha de Asistencia</h3>
                                <p>Descargue el reporte de asistencias en formato CSV</p>
                                <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=attendance&export=csv<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                                    <i class="fas fa-download"></i> Descargar CSV
                                </a>
                                <button onclick="window.print()" class="btn btn-success">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-user-check"></i> Ficha de Asistencia</h2>
                        </div>
                        
                        <?php if (empty($attendanceData)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay datos de asistencia disponibles.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>DNI</th>
                                            <th>APELLIDOS Y NOMBRES</th>
                                            <th>TOTAL<br>SESIONES</th>
                                            <th>ASISTENCIAS</th>
                                            <th>FALTAS</th>
                                            <th>PERMISOS</th>
                                            <th>% ASISTENCIA</th>
                                            <th>CONDICIÓN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $contador = 1;
                                        foreach ($attendanceData as $attendance): 
                                            $condition_attendance = $attendance['porcentaje_asistencia'] >= 70 ? 'CUMPLE' : 'DPI';
                                            $condition_class = $attendance['porcentaje_asistencia'] >= 70 ? 'condition-approved' : 'condition-dpi';
                                        ?>
                                            <tr>
                                                <td><?php echo str_pad($contador, 2, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($attendance['dni']); ?></td>
                                                <td class="student-name"><?php echo htmlspecialchars($attendance['nombre_completo']); ?></td>
                                                <td><?php echo $attendance['total_sesiones']; ?></td>
                                                <td><?php echo $attendance['asistencias']; ?></td>
                                                <td><?php echo $attendance['faltas']; ?></td>
                                                <td><?php echo $attendance['permisos']; ?></td>
                                                <td><?php echo $attendance['porcentaje_asistencia']; ?>%</td>
                                                <td class="<?php echo $condition_class; ?>"><?php echo $condition_attendance; ?></td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Nota:</strong> Se requiere mínimo 70% de asistencia para cumplir con los requisitos académicos.
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($_GET['report'] == 'consolidated'): ?>
                        <?php if (!empty($consolidatedData)): ?>
                            <div class="export-section">
                                <h3><i class="fas fa-file-excel"></i> Exportar Consolidado General</h3>
                                <p>Descargue el consolidado completo en formato CSV</p>
                                <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=consolidated&export=csv<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                                    <i class="fas fa-download"></i> Descargar CSV
                                </a>
                                <button onclick="window.print()" class="btn btn-success">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-chart-bar"></i> Consolidado General</h2>
                        </div>
                        
                        <?php if (empty($consolidatedData)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay datos consolidados disponibles.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>DNI</th>
                                            <th>APELLIDOS Y NOMBRES</th>
                                            <th>ASISTENCIAS</th>
                                            <th>FALTAS</th>
                                            <th>PERMISOS</th>
                                            <th>% ASISTENCIA</th>
                                            <th>PROMEDIO<br>NOTAS</th>
                                            <th>TOTAL<br>EVALUACIONES</th>
                                            <th>ESTADO FINAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $contador = 1;
                                        foreach ($consolidatedData as $data): 
                                            $estado_final = ($data['porcentaje_asistencia'] >= 70 && $data['promedio_evaluaciones'] >= 12.5) ? 'APROBADO' : 'DESAPROBADO';
                                            $estado_class = $estado_final == 'APROBADO' ? 'condition-approved' : 'condition-dpi';
                                        ?>
                                            <tr>
                                                <td><?php echo str_pad($contador, 2, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($data['dni']); ?></td>
                                                <td class="student-name"><?php echo htmlspecialchars($data['nombre_completo']); ?></td>
                                                <td><?php echo $data['asistencias']; ?></td>
                                                <td><?php echo $data['faltas']; ?></td>
                                                <td><?php echo $data['permisos']; ?></td>
                                                <td><?php echo $data['porcentaje_asistencia']; ?>%</td>
                                                <td><?php echo $data['promedio_evaluaciones'] ? number_format($data['promedio_evaluaciones'], 1) : '00'; ?></td>
                                                <td><?php echo $data['total_evaluaciones']; ?></td>
                                                <td class="<?php echo $estado_class; ?>"><?php echo $estado_final; ?></td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Criterios de Aprobación:</strong> Mínimo 70% de asistencia y 12.5 de promedio en evaluaciones.
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($_GET['report'] == 'auxiliary'): ?>
                        <?php if (!empty($evaluationsByIndicator)): ?>
                            <div class="export-section">
                                <h3><i class="fas fa-file-excel"></i> Exportar Auxiliar de Evaluación</h3>
                                <p>Descargue el detalle de evaluaciones por indicador</p>
                                <a href="?course_id=<?php echo $selectedCourse['id']; ?>&report=auxiliary&export=csv<?php echo isset($_GET['test']) ? '&test=1' : ''; ?>" class="btn btn-excel">
                                    <i class="fas fa-download"></i> Descargar CSV
                                </a>
                                <button onclick="window.print()" class="btn btn-success">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-clipboard-list"></i> Auxiliar de Evaluación</h2>
                        </div>
                        
                        <?php if (empty($evaluationsByIndicator)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay evaluaciones registradas.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>INDICADOR</th>
                                            <th>ESTUDIANTE</th>
                                            <th>DNI</th>
                                            <th>SESIÓN</th>
                                            <th>EVALUACIÓN</th>
                                            <th>CALIFICACIÓN</th>
                                            <th>FECHA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($evaluationsByIndicator as $eval): ?>
                                            <?php if ($eval['estudiante_id']): ?>
                                                <tr>
                                                    <td>IND-<?php echo $eval['numero_indicador']; ?>: <?php echo htmlspecialchars(substr($eval['indicador_nombre'], 0, 30)); ?></td>
                                                    <td class="student-name"><?php echo htmlspecialchars($eval['nombre_completo']); ?></td>
                                                    <td><?php echo htmlspecialchars($eval['dni']); ?></td>
                                                    <td>Sesión <?php echo $eval['numero_sesion']; ?>: <?php echo htmlspecialchars(substr($eval['sesion_titulo'], 0, 20)); ?></td>
                                                    <td><?php echo htmlspecialchars($eval['evaluacion_nombre']); ?></td>
                                                    <td style="font-weight: bold;"><?php echo $eval['calificacion'] ? number_format($eval['calificacion'], 1) : 'Sin calificar'; ?></td>
                                                    <td><?php echo $eval['fecha_evaluacion'] ? date('d/m/Y', strtotime($eval['fecha_evaluacion'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($_GET['report'] == 'sessions'): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-calendar-day"></i> Reporte de Sesiones</h2>
                            <button onclick="window.print()" class="btn btn-success">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                        
                        <?php if (empty($sessions)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay sesiones registradas para esta unidad didáctica.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>N° SESIÓN</th>
                                            <th>TÍTULO</th>
                                            <th>FECHA</th>
                                            <th>DESCRIPCIÓN</th>
                                            <th>ESTADO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td><?php echo $session['numero_sesion']; ?></td>
                                                <td class="student-name"><?php echo htmlspecialchars($session['titulo']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($session['fecha'])); ?></td>
                                                <td><?php echo htmlspecialchars(substr($session['descripcion'] ?? '', 0, 50)) . (strlen($session['descripcion'] ?? '') > 50 ? '...' : ''); ?></td>
                                                <td class="<?php echo $session['estado'] == 'realizada' ? 'condition-approved' : 'condition-process'; ?>">
                                                    <?php echo strtoupper($session['estado']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Total de sesiones:</strong> <?php echo count($sessions); ?> | 
                                <strong>Realizadas:</strong> <?php echo count(array_filter($sessions, function($s) { return $s['estado'] == 'realizada'; })); ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($_GET['report'] == 'indicators'): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><i class="fas fa-chart-pie"></i> Análisis de Indicadores</h2>
                            <button onclick="window.print()" class="btn btn-success">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                        
                        <?php if (empty($indicators)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No hay indicadores de logro configurados.
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>N° INDICADOR</th>
                                            <th>NOMBRE</th>
                                            <th>PESO (%)</th>
                                            <th>EVALUACIONES<br>REGISTRADAS</th>
                                            <th>PROMEDIO<br>GENERAL</th>
                                            <th>ESTUDIANTES<br>EVALUADOS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($indicators as $indicator): ?>
                                            <?php
                                            // Calcular estadísticas del indicador
                                            $indicator_evaluations = array_filter($evaluationsByIndicator, function($eval) use ($indicator) {
                                                return $eval['numero_indicador'] == $indicator['numero_indicador'] && $eval['calificacion'] !== null;
                                            });
                                            
                                            $total_evaluations = count($indicator_evaluations);
                                            $students_evaluated = count(array_unique(array_column($indicator_evaluations, 'estudiante_id')));
                                            $average = $total_evaluations > 0 ? array_sum(array_column($indicator_evaluations, 'calificacion')) / $total_evaluations : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $indicator['numero_indicador']; ?></td>
                                                <td class="student-name"><?php echo htmlspecialchars($indicator['nombre']); ?></td>
                                                <td><?php echo $indicator['peso']; ?>%</td>
                                                <td><?php echo $total_evaluations; ?></td>
                                                <td style="font-weight: bold;"><?php echo $average > 0 ? number_format($average, 1) : '00'; ?></td>
                                                <td><?php echo $students_evaluated; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Total de indicadores:</strong> <?php echo count($indicators); ?> | 
                                <strong>Estudiantes matriculados:</strong> <?php echo count($students); ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animaciones para las tarjetas
            const cards = document.querySelectorAll('.report-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
            
            // Mostrar mensaje de carga al exportar
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
                        const successMsg = document.createElement('div');
                        successMsg.className = 'alert alert-success';
                        successMsg.style.position = 'fixed';
                        successMsg.style.top = '20px';
                        successMsg.style.right = '20px';
                        successMsg.style.zIndex = '9999';
                        successMsg.innerHTML = '<i class="fas fa-check-circle"></i> Archivo descargado exitosamente';
                        
                        document.body.appendChild(successMsg);
                        
                        setTimeout(() => {
                            successMsg.remove();
                        }, 3000);
                    }, 2000);
                });
            });
            
            // Mejorar experiencia de impresión
            const printButtons = document.querySelectorAll('button[onclick*="print"]');
            printButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Ocultar botones antes de imprimir
                    const buttons = document.querySelectorAll('.btn');
                    const exportSections = document.querySelectorAll('.export-section');
                    
                    buttons.forEach(btn => btn.style.display = 'none');
                    exportSections.forEach(section => section.style.display = 'none');
                    
                    setTimeout(() => {
                        window.print();
                        
                        // Restaurar botones después de imprimir
                        setTimeout(() => {
                            buttons.forEach(btn => btn.style.display = '');
                            exportSections.forEach(section => section.style.display = '');
                        }, 1000);
                    }, 100);
                });
            });
            
            // Agregar tooltips informativos
            const reportCards = document.querySelectorAll('.report-card');
            reportCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.icon i');
                    icon.style.transform = 'scale(1.1) rotate(5deg)';
                    icon.style.transition = 'all 0.3s ease';
                });
                
                card.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.icon i');
                    icon.style.transform = 'scale(1) rotate(0deg)';
                });
            });
        });
        
        // Función para mostrar estadísticas rápidas
        function showQuickStats() {
            const students = <?php echo count($students); ?>;
            const sessions = <?php echo count($sessions); ?>;
            const indicators = <?php echo count($indicators); ?>;
            
            alert(`Estadísticas del Curso:\n\n• Estudiantes: ${students}\n• Sesiones: ${sessions}\n• Indicadores: ${indicators}`);
        }
    </script>
</body>
</html>