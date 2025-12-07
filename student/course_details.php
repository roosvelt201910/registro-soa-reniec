<?php
require_once '../config/database.php';

// Verificar sesión y tipo de usuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

// Verificar que se haya proporcionado un ID de curso
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_courses.php");
    exit();
}

$course_id = intval($_GET['id']);
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? 'Estudiante';

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar que el estudiante esté matriculado en el curso
    $sql_check = "
        SELECT m.id 
        FROM matriculas m 
        WHERE m.estudiante_id = :student_id 
        AND m.unidad_didactica_id = :course_id 
        AND m.estado = 'activo'
    ";
    
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() == 0) {
        header("Location: my_courses.php");
        exit();
    }
    
    // Obtener información del curso
    $sql_course = "
        SELECT 
            ud.id,
            ud.nombre,
            ud.codigo,
            ud.periodo_lectivo,
            ud.periodo_academico,
            pe.nombre as programa_nombre,
            CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre,
            u.email as docente_email
        FROM unidades_didacticas ud
        INNER JOIN programas_estudio pe ON ud.programa_id = pe.id
        INNER JOIN usuarios u ON ud.docente_id = u.id
        WHERE ud.id = :course_id
    ";
    
    $stmt_course = $conn->prepare($sql_course);
    $stmt_course->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt_course->execute();
    $course_info = $stmt_course->fetch(PDO::FETCH_ASSOC);
    
    if (!$course_info) {
        header("Location: my_courses.php");
        exit();
    }
    
    // Obtener todas las sesiones del curso
    $sql_sessions = "
        SELECT 
            s.id,
            s.numero_sesion,
            s.titulo,
            s.fecha,
            s.descripcion,
            s.estado,
            a.estado as asistencia_estado
        FROM sesiones s
        LEFT JOIN asistencias a ON s.id = a.sesion_id AND a.estudiante_id = :student_id
        WHERE s.unidad_didactica_id = :course_id
        ORDER BY s.numero_sesion ASC
    ";
    
    $stmt_sessions = $conn->prepare($sql_sessions);
    $stmt_sessions->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_sessions->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt_sessions->execute();
    $sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener indicadores de logro con evaluaciones
    $sql_indicators = "
        SELECT 
            il.id,
            il.numero_indicador,
            il.nombre,
            il.descripcion,
            il.peso
        FROM indicadores_logro il
        WHERE il.unidad_didactica_id = :course_id
        ORDER BY il.numero_indicador ASC
    ";
    
    $stmt_indicators = $conn->prepare($sql_indicators);
    $stmt_indicators->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt_indicators->execute();
    $indicators = $stmt_indicators->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada indicador, obtener sus evaluaciones
    $indicator_evaluations = [];
    foreach ($indicators as $indicator) {
        $sql_evaluations = "
            SELECT 
                ie.id,
                ie.nombre as evaluacion_nombre,
                ie.descripcion as evaluacion_descripcion,
                s.numero_sesion,
                s.titulo as sesion_titulo,
                es.calificacion,
                es.fecha_evaluacion
            FROM indicadores_evaluacion ie
            INNER JOIN sesiones s ON ie.sesion_id = s.id
            LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id 
                AND es.estudiante_id = :student_id
            WHERE ie.indicador_logro_id = :indicator_id
            ORDER BY s.numero_sesion ASC
        ";
        
        $stmt_eval = $conn->prepare($sql_evaluations);
        $stmt_eval->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_eval->bindParam(':indicator_id', $indicator['id'], PDO::PARAM_INT);
        $stmt_eval->execute();
        $indicator_evaluations[$indicator['id']] = $stmt_eval->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Calcular estadísticas generales
    $total_sessions = count($sessions);
    $completed_sessions = 0;
    $attended_sessions = 0;
    $missed_sessions = 0;
    
    foreach ($sessions as $session) {
        if ($session['estado'] == 'realizada') {
            $completed_sessions++;
            if ($session['asistencia_estado'] == 'presente') {
                $attended_sessions++;
            } elseif ($session['asistencia_estado'] == 'falta') {
                $missed_sessions++;
            }
        }
    }
    
    $attendance_percentage = $completed_sessions > 0 ? 
        ($attended_sessions / $completed_sessions) * 100 : 0;
    
    // Calcular promedio general
    $total_grade = 0;
    $grade_count = 0;
    $indicator_averages = [];
    
    foreach ($indicators as $indicator) {
        $indicator_sum = 0;
        $indicator_count = 0;
        
        foreach ($indicator_evaluations[$indicator['id']] as $eval) {
            if ($eval['calificacion'] !== null) {
                $indicator_sum += $eval['calificacion'];
                $indicator_count++;
            }
        }
        
        if ($indicator_count > 0) {
            $average = $indicator_sum / $indicator_count;
            $indicator_averages[$indicator['id']] = $average;
            $total_grade += $average;
            $grade_count++;
        } else {
            $indicator_averages[$indicator['id']] = null;
        }
    }
    
    $overall_average = $grade_count > 0 ? $total_grade / $grade_count : 0;
    $course_condition = calculateCondition($overall_average);
    $attendance_condition = calculateAttendanceCondition($attendance_percentage);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Función para obtener color según estado
function getStatusColor($status) {
    switch($status) {
        case 'realizada': return '#10B981';
        case 'programada': return '#3B82F6';
        case 'cancelada': return '#EF4444';
        default: return '#6B7280';
    }
}

// Función para obtener icono de asistencia
function getAttendanceIcon($status) {
    switch($status) {
        case 'presente': return 'check-circle';
        case 'falta': return 'times-circle';
        case 'permiso': return 'exclamation-circle';
        default: return 'question-circle';
    }
}

// Función para obtener color de asistencia
function getAttendanceColor($status) {
    switch($status) {
        case 'presente': return '#10B981';
        case 'falta': return '#EF4444';
        case 'permiso': return '#F59E0B';
        default: return '#6B7280';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course_info['nombre']); ?> - Detalles del Curso</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --primary-light: #818CF8;
            --secondary: #06B6D4;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #1F2937;
            --gray: #6B7280;
            --light: #F9FAFB;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%);
            z-index: -1;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title h1 {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .header-title i {
            font-size: 1.5rem;
            color: white;
        }
        
        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .header-user-info {
            text-align: right;
        }
        
        .header-user-name {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .header-user-role {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .header-user-avatar {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .breadcrumb a:hover {
            opacity: 0.8;
        }
        
        .course-hero {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }
        
        .course-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .course-hero-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 2rem;
        }
        
        .course-title {
            flex: 1;
        }
        
        .course-title h1 {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .course-code {
            display: inline-block;
            background: var(--primary-light);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .course-condition-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.125rem;
            text-align: center;
        }
        
        .condition-excelente {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }
        
        .condition-aprobado {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: white;
        }
        
        .condition-proceso {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }
        
        .condition-desaprobado {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }
        
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: var(--dark);
            font-weight: 600;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 16px;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tabs-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
        }
        
        .tabs-header {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--light);
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }
        
        .tab-button:hover {
            color: var(--primary);
        }
        
        .tab-button.active {
            color: var(--primary);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .sessions-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .session-card {
            border: 1px solid var(--light);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .session-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(5px);
        }
        
        .session-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }
        
        .session-card.realizada::before {
            background: var(--success);
        }
        
        .session-card.programada::before {
            background: var(--primary);
        }
        
        .session-card.cancelada::before {
            background: var(--danger);
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .session-number {
            background: var(--primary-light);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .session-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .session-meta {
            display: flex;
            gap: 1.5rem;
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .session-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .indicators-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .indicator-card {
            border: 2px solid var(--light);
            border-radius: 16px;
            padding: 1.5rem;
            background: var(--light);
        }
        
        .indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .indicator-number {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .indicator-title {
            flex: 1;
            margin: 0 1rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .indicator-average {
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .evaluations-table {
            width: 100%;
            margin-top: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .evaluations-table th,
        .evaluations-table td {
            padding: 0.75rem;
            text-align: left;
            font-size: 0.875rem;
        }
        
        .evaluations-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .evaluations-table tr:nth-child(even) {
            background: var(--light);
        }
        
        .evaluations-table tr:hover {
            background: #E5E7EB;
        }
        
        .grade-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 700;
            display: inline-block;
            min-width: 50px;
            text-align: center;
        }
        
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .attendance-card {
            background: white;
            border: 2px solid var(--light);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .attendance-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .attendance-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .attendance-session {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .attendance-date {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .progress-chart {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .progress-bar-container {
            margin-bottom: 1.5rem;
        }
        
        .progress-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .progress-bar {
            height: 20px;
            background: var(--light);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            transition: width 1s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-5px);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .course-hero {
                padding: 1.5rem;
            }
            
            .course-hero-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .course-title h1 {
                font-size: 1.5rem;
            }
            
            .tabs-header {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .attendance-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-graduation-cap"></i>
                <h1>IESPH Alto Huallaga</h1>
            </div>
            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?php echo htmlspecialchars($student_name); ?></div>
                    <div class="header-user-role">Estudiante</div>
                </div>
                <div class="header-user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <nav class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <a href="my_courses.php">Mis Cursos</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($course_info['nombre']); ?></span>
        </nav>
        
        <a href="my_courses.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Volver a Mis Cursos
        </a>
        
        <!-- Hero del curso -->
        <div class="course-hero">
            <div class="course-hero-header">
                <div class="course-title">
                    <h1><?php echo htmlspecialchars($course_info['nombre']); ?></h1>
                    <span class="course-code"><?php echo htmlspecialchars($course_info['codigo']); ?></span>
                </div>
                <div class="course-condition-badge condition-<?php echo strtolower(str_replace(' ', '-', $course_condition)); ?>">
                    <?php echo $course_condition; ?>
                </div>
            </div>
            
            <div class="course-info-grid">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Programa</div>
                        <div class="info-value"><?php echo htmlspecialchars($course_info['programa_nombre']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Periodo</div>
                        <div class="info-value"><?php echo htmlspecialchars($course_info['periodo_lectivo'] . ' - ' . $course_info['periodo_academico']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Docente</div>
                        <div class="info-value"><?php echo htmlspecialchars($course_info['docente_nombre']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Email Docente</div>
                        <div class="info-value"><?php echo htmlspecialchars($course_info['docente_email'] ?? 'No disponible'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value" style="color: <?php echo $overall_average >= 13 ? '#10B981' : ($overall_average >= 10 ? '#F59E0B' : '#EF4444'); ?>">
                        <?php echo number_format($overall_average, 1); ?>
                    </div>
                    <div class="stat-label">Promedio</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" style="color: <?php echo $attendance_percentage >= 70 ? '#10B981' : '#EF4444'; ?>">
                        <?php echo number_format($attendance_percentage, 0); ?>%
                    </div>
                    <div class="stat-label">Asistencia</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" style="color: var(--primary);">
                        <?php echo $completed_sessions; ?>/<?php echo $total_sessions; ?>
                    </div>
                    <div class="stat-label">Sesiones</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" style="color: var(--secondary);">
                        <?php echo $grade_count; ?>/<?php echo count($indicators); ?>
                    </div>
                    <div class="stat-label">Indicadores</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" style="color: var(--success);">
                        <?php echo $attended_sessions; ?>
                    </div>
                    <div class="stat-label">Presentes</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" style="color: var(--danger);">
                        <?php echo $missed_sessions; ?>
                    </div>
                    <div class="stat-label">Faltas</div>
                </div>
            </div>
        </div>
        
        <!-- Tabs de contenido -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="showTab('sessions')">
                    <i class="fas fa-calendar-alt"></i> Sesiones
                </button>
                <button class="tab-button" onclick="showTab('indicators')">
                    <i class="fas fa-bullseye"></i> Indicadores de Logro
                </button>
                <button class="tab-button" onclick="showTab('attendance')">
                    <i class="fas fa-user-check"></i> Asistencia
                </button>
                <button class="tab-button" onclick="showTab('progress')">
                    <i class="fas fa-chart-line"></i> Progreso
                </button>
            </div>
            
            <!-- Tab de Sesiones -->
            <div id="sessions" class="tab-content active">
                <h3 style="margin-bottom: 1.5rem; color: var(--dark);">
                    <i class="fas fa-list"></i> Lista de Sesiones
                </h3>
                
                <?php if (!empty($sessions)): ?>
                    <div class="sessions-list">
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-card <?php echo $session['estado']; ?>">
                                <div class="session-header">
                                    <div>
                                        <span class="session-number">Sesión <?php echo $session['numero_sesion']; ?></span>
                                        <h4 class="session-title"><?php echo htmlspecialchars($session['titulo']); ?></h4>
                                    </div>
                                    <?php if ($session['asistencia_estado']): ?>
                                        <i class="fas fa-<?php echo getAttendanceIcon($session['asistencia_estado']); ?>" 
                                           style="color: <?php echo getAttendanceColor($session['asistencia_estado']); ?>; font-size: 1.5rem;"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($session['descripcion']): ?>
                                    <p style="color: var(--gray); margin-bottom: 1rem;">
                                        <?php echo htmlspecialchars($session['descripcion']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="session-meta">
                                    <div class="session-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($session['fecha'])); ?>
                                    </div>
                                    <div class="session-meta-item">
                                        <i class="fas fa-circle" style="color: <?php echo getStatusColor($session['estado']); ?>"></i>
                                        <?php echo ucfirst($session['estado']); ?>
                                    </div>
                                    <?php if ($session['asistencia_estado']): ?>
                                        <div class="session-meta-item">
                                            <i class="fas fa-user-check"></i>
                                            <?php echo ucfirst($session['asistencia_estado']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No hay sesiones registradas</h3>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab de Indicadores -->
            <div id="indicators" class="tab-content">
                <h3 style="margin-bottom: 1.5rem; color: var(--dark);">
                    <i class="fas fa-bullseye"></i> Indicadores de Logro y Evaluaciones
                </h3>
                
                <?php if (!empty($indicators)): ?>
                    <div class="indicators-grid">
                        <?php foreach ($indicators as $indicator): 
                            $avg = $indicator_averages[$indicator['id']];
                            $color = $avg !== null ? 
                                ($avg >= 18 ? '#10B981' : 
                                ($avg >= 13 ? '#3B82F6' : 
                                ($avg >= 10 ? '#F59E0B' : '#EF4444'))) : '#6B7280';
                        ?>
                            <div class="indicator-card">
                                <div class="indicator-header">
                                    <div class="indicator-number"><?php echo $indicator['numero_indicador']; ?></div>
                                    <div class="indicator-title"><?php echo htmlspecialchars($indicator['nombre']); ?></div>
                                    <div class="indicator-average" style="color: <?php echo $color; ?>">
                                        <?php echo $avg !== null ? number_format($avg, 1) : '-'; ?>
                                    </div>
                                </div>
                                
                                <?php if ($indicator['descripcion']): ?>
                                    <p style="color: var(--gray); margin-bottom: 1rem;">
                                        <?php echo htmlspecialchars($indicator['descripcion']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($indicator_evaluations[$indicator['id']])): ?>
                                    <table class="evaluations-table">
                                        <thead>
                                            <tr>
                                                <th>Sesión</th>
                                                <th>Evaluación</th>
                                                <th>Calificación</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($indicator_evaluations[$indicator['id']] as $eval): ?>
                                                <tr>
                                                    <td>Sesión <?php echo $eval['numero_sesion']; ?></td>
                                                    <td><?php echo htmlspecialchars($eval['evaluacion_nombre']); ?></td>
                                                    <td>
                                                        <?php if ($eval['calificacion'] !== null): ?>
                                                            <span class="grade-badge" style="background: <?php echo $eval['calificacion'] >= 18 ? '#10B981' : ($eval['calificacion'] >= 13 ? '#3B82F6' : ($eval['calificacion'] >= 10 ? '#F59E0B' : '#EF4444')); ?>; color: white;">
                                                                <?php echo number_format($eval['calificacion'], 1); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color: var(--gray);">Pendiente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $eval['fecha_evaluacion'] ? 
                                                            date('d/m/Y', strtotime($eval['fecha_evaluacion'])) : 
                                                            '-'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p style="color: var(--gray); text-align: center; padding: 1rem;">
                                        No hay evaluaciones registradas para este indicador
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullseye"></i>
                        <h3>No hay indicadores de logro registrados</h3>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab de Asistencia -->
            <div id="attendance" class="tab-content">
                <h3 style="margin-bottom: 1.5rem; color: var(--dark);">
                    <i class="fas fa-user-check"></i> Registro de Asistencia
                </h3>
                
                <div class="progress-chart">
                    <div class="progress-bar-container">
                        <div class="progress-bar-label">
                            <span>Porcentaje de Asistencia</span>
                            <strong><?php echo number_format($attendance_percentage, 0); ?>%</strong>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $attendance_percentage; ?>%"></div>
                        </div>
                    </div>
                    
                    <p style="color: var(--gray); margin-bottom: 1rem;">
                        Condición de asistencia: 
                        <strong style="color: <?php echo $attendance_condition == 'APROBADO' ? '#10B981' : '#EF4444'; ?>">
                            <?php echo $attendance_condition; ?>
                        </strong>
                    </p>
                </div>
                
                <?php if (!empty($sessions)): ?>
                    <div class="attendance-grid">
                        <?php foreach ($sessions as $session): 
                            if ($session['estado'] == 'realizada'):
                                $color = getAttendanceColor($session['asistencia_estado']);
                                $icon = getAttendanceIcon($session['asistencia_estado']);
                        ?>
                            <div class="attendance-card">
                                <div class="attendance-icon" style="color: <?php echo $color; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="attendance-session">Sesión <?php echo $session['numero_sesion']; ?></div>
                                <div class="attendance-date"><?php echo date('d/m/Y', strtotime($session['fecha'])); ?></div>
                                <div style="color: <?php echo $color; ?>; font-weight: 600; margin-top: 0.5rem;">
                                    <?php echo ucfirst($session['asistencia_estado'] ?? 'Sin registro'); ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>No hay registros de asistencia</h3>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab de Progreso -->
            <div id="progress" class="tab-content">
                <h3 style="margin-bottom: 1.5rem; color: var(--dark);">
                    <i class="fas fa-chart-line"></i> Resumen de Progreso
                </h3>
                
                <div class="progress-chart">
                    <div class="progress-bar-container">
                        <div class="progress-bar-label">
                            <span>Progreso del Curso</span>
                            <strong><?php echo round(($completed_sessions / max($total_sessions, 1)) * 100); ?>%</strong>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($completed_sessions / max($total_sessions, 1)) * 100; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-bar-container" style="margin-top: 1.5rem;">
                        <div class="progress-bar-label">
                            <span>Indicadores Evaluados</span>
                            <strong><?php echo round(($grade_count / max(count($indicators), 1)) * 100); ?>%</strong>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($grade_count / max(count($indicators), 1)) * 100; ?>%; background: linear-gradient(90deg, var(--secondary), var(--primary));"></div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem;">
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <i class="fas fa-trophy" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--dark); margin-bottom: 0.5rem;">Condición Académica</h4>
                        <p style="font-size: 1.25rem; font-weight: 700; color: <?php echo $overall_average >= 13 ? '#10B981' : ($overall_average >= 10 ? '#F59E0B' : '#EF4444'); ?>">
                            <?php echo $course_condition; ?>
                        </p>
                    </div>
                    
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--secondary); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--dark); margin-bottom: 0.5rem;">Condición de Asistencia</h4>
                        <p style="font-size: 1.25rem; font-weight: 700; color: <?php echo $attendance_condition == 'APROBADO' ? '#10B981' : '#EF4444'; ?>">
                            <?php echo $attendance_condition; ?>
                        </p>
                    </div>
                    
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <i class="fas fa-star" style="font-size: 2rem; color: var(--warning); margin-bottom: 0.5rem;"></i>
                        <h4 style="color: var(--dark); margin-bottom: 0.5rem;">Promedio Final</h4>
                        <p style="font-size: 1.25rem; font-weight: 700; color: <?php echo $overall_average >= 13 ? '#10B981' : ($overall_average >= 10 ? '#F59E0B' : '#EF4444'); ?>">
                            <?php echo number_format($overall_average, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Ocultar todos los tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Desactivar todos los botones
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el botón correspondiente
            event.target.closest('.tab-button').classList.add('active');
        }
        
        // Animación de las barras de progreso cuando se muestran
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>