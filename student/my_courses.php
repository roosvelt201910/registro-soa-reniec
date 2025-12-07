<?php
// No necesitamos session_start() porque database.php ya lo incluye
require_once '../config/database.php';

// Verificar sesión y tipo de usuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

// Obtener datos del estudiante
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? 'Estudiante';

// Funciones específicas para esta página (con nombres únicos)
function getCourseStatus($average, $attendance) {
    if ($average >= 13 && $attendance >= 70) {
        return ['status' => 'good', 'text' => 'Buen Progreso', 'icon' => 'check-circle'];
    } elseif ($average >= 10 || $attendance >= 60) {
        return ['status' => 'warning', 'text' => 'Requiere Atención', 'icon' => 'exclamation-triangle'];
    } else {
        return ['status' => 'danger', 'text' => 'En Riesgo', 'icon' => 'times-circle'];
    }
}

function getGradeColor($grade) {
    if ($grade >= 18) return '#10B981';
    if ($grade >= 13) return '#3B82F6';
    if ($grade >= 10) return '#F59E0B';
    return '#EF4444';
}

// Conectar a la base de datos usando PDO
$database = new Database();
$conn = $database->getConnection();

try {
    // Consulta para obtener cursos del estudiante con información completa
    $sql_courses = "
        SELECT 
            ud.id,
            ud.nombre,
            ud.codigo,
            ud.periodo_lectivo,
            ud.periodo_academico,
            pe.nombre as programa_nombre,
            CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre
        FROM matriculas m
        INNER JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
        INNER JOIN programas_estudio pe ON ud.programa_id = pe.id
        INNER JOIN usuarios u ON ud.docente_id = u.id
        WHERE m.estudiante_id = :student_id 
        AND m.estado = 'activo' 
        AND ud.estado = 'activo'
        ORDER BY ud.periodo_lectivo DESC, ud.nombre ASC
    ";
    
    $stmt = $conn->prepare($sql_courses);
    $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt->execute();
    $my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Array para almacenar detalles de cada curso
    $course_details = [];
    
    foreach ($my_courses as $curso) {
        $course_id = $curso['id'];
        
        // Obtener sesiones del curso
        $sql_sessions = "
            SELECT id, numero_sesion, titulo, fecha, estado
            FROM sesiones
            WHERE unidad_didactica_id = :course_id
            ORDER BY numero_sesion ASC
        ";
        
        $stmt_sessions = $conn->prepare($sql_sessions);
        $stmt_sessions->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt_sessions->execute();
        $sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);
        
        $total_sessions = count($sessions);
        $completed_sessions = 0;
        $upcoming_sessions = [];
        
        foreach ($sessions as $sess) {
            if ($sess['estado'] == 'realizada') {
                $completed_sessions++;
            } elseif ($sess['estado'] == 'programada' && strtotime($sess['fecha']) >= strtotime('today')) {
                $upcoming_sessions[] = $sess;
            }
        }
        
        // Obtener indicadores de logro
        $sql_indicators = "
            SELECT id, numero_indicador, nombre
            FROM indicadores_logro
            WHERE unidad_didactica_id = :course_id
            ORDER BY numero_indicador ASC
        ";
        
        $stmt_indicators = $conn->prepare($sql_indicators);
        $stmt_indicators->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt_indicators->execute();
        $indicators = $stmt_indicators->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener calificaciones del estudiante
        $sql_grades = "
            SELECT 
                il.numero_indicador,
                AVG(es.calificacion) as promedio_indicador
            FROM evaluaciones_sesion es
            INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
            INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
            WHERE il.unidad_didactica_id = :course_id 
            AND es.estudiante_id = :student_id
            GROUP BY il.id, il.numero_indicador
        ";
        
        $stmt_grades = $conn->prepare($sql_grades);
        $stmt_grades->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt_grades->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_grades->execute();
        $grades = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);
        
        $average_grade = 0;
        $evaluated_indicators = count($grades);
        
        if (!empty($grades)) {
            $sum = 0;
            foreach ($grades as $grade) {
                $sum += $grade['promedio_indicador'];
            }
            $average_grade = $sum / count($grades);
        }
        
        // Obtener asistencia
        $sql_attendance = "
            SELECT 
                COUNT(*) as total_sesiones,
                SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as asistencias,
                SUM(CASE WHEN a.estado = 'falta' THEN 1 ELSE 0 END) as faltas
            FROM sesiones s
            LEFT JOIN asistencias a ON s.id = a.sesion_id AND a.estudiante_id = :student_id
            WHERE s.unidad_didactica_id = :course_id 
            AND s.estado = 'realizada'
        ";
        
        $stmt_attendance = $conn->prepare($sql_attendance);
        $stmt_attendance->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_attendance->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt_attendance->execute();
        $attendance_data = $stmt_attendance->fetch(PDO::FETCH_ASSOC);
        
        $attendance_percentage = 0;
        if ($attendance_data && $attendance_data['total_sesiones'] > 0) {
            $attendance_percentage = ($attendance_data['asistencias'] / $attendance_data['total_sesiones']) * 100;
        }
        
        // Calcular progreso del curso
        $progress = $total_sessions > 0 ? ($completed_sessions / $total_sessions) * 100 : 0;
        
        // Almacenar toda la información del curso
        $course_details[$course_id] = [
            'info' => $curso,
            'total_sessions' => $total_sessions,
            'completed_sessions' => $completed_sessions,
            'upcoming_sessions' => array_slice($upcoming_sessions, 0, 3),
            'indicators' => $indicators,
            'average_grade' => $average_grade,
            'evaluated_indicators' => $evaluated_indicators,
            'total_indicators' => count($indicators),
            'attendance_percentage' => $attendance_percentage,
            'total_attendance' => $attendance_data['asistencias'] ?? 0,
            'total_absences' => $attendance_data['faltas'] ?? 0,
            'progress' => $progress
        ];
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cursos - IESPH Alto Huallaga</title>
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
            animation: slideDown 0.6s ease-out;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
        
        .page-header {
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
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
        
        .page-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .page-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.125rem;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
            animation: fadeIn 0.8s ease-out 0.2s both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, var(--success), #059669);
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, var(--warning), #D97706);
        }
        
        .stat-icon.info {
            background: linear-gradient(135deg, var(--secondary), #0891B2);
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .course-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideUp 0.6s ease-out;
            position: relative;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .course-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }
        
        .course-card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .course-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .course-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            z-index: 1;
        }
        
        .course-status-badge.good {
            background: rgba(16, 185, 129, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .course-status-badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .course-status-badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .course-meta {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            font-size: 0.875rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .course-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-meta-item i {
            width: 16px;
            text-align: center;
        }
        
        .course-card-body {
            padding: 1.5rem;
        }
        
        .course-progress {
            margin-bottom: 1.5rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-label {
            font-size: 0.875rem;
            color: var(--gray);
            font-weight: 600;
        }
        
        .progress-value {
            font-size: 0.875rem;
            color: var(--primary);
            font-weight: 700;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--light);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
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
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .course-stat {
            text-align: center;
            padding: 1rem;
            background: var(--light);
            border-radius: 12px;
            transition: transform 0.3s ease, background 0.3s ease;
        }
        
        .course-stat:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, var(--light), white);
        }
        
        .course-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .course-stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .course-upcoming {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .upcoming-sessions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .session-item {
            padding: 0.75rem;
            background: var(--light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .session-item:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(5px);
        }
        
        .session-date {
            width: 50px;
            text-align: center;
            padding: 0.375rem;
            background: white;
            border-radius: 6px;
            border: 2px solid var(--primary);
        }
        
        .session-date-day {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }
        
        .session-date-month {
            font-size: 0.625rem;
            color: var(--gray);
            text-transform: uppercase;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.125rem;
        }
        
        .session-time {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .course-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 2rem;
        }
        
        .no-sessions {
            text-align: center;
            padding: 1rem;
            color: var(--gray);
            font-size: 0.875rem;
            font-style: italic;
        }
        
        .floating-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
            text-decoration: none;
        }
        
        .floating-button:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 15px 40px rgba(79, 70, 229, 0.5);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-user {
                width: 100%;
                justify-content: center;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .course-stats {
                grid-template-columns: 1fr;
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
        <div class="page-header">
            <nav class="breadcrumb">
                <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
                <i class="fas fa-chevron-right"></i>
                <span>Mis Cursos</span>
            </nav>
            <h1 class="page-title">Mis Cursos</h1>
            <p class="page-subtitle">Gestiona y da seguimiento a tus unidades didácticas</p>
        </div>
        
        <?php if (!empty($course_details)): ?>
            <!-- Resumen de estadísticas -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($course_details); ?></div>
                        <div class="stat-label">Cursos Matriculados</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php 
                            $total_avg = 0;
                            $count_with_grades = 0;
                            foreach ($course_details as $detail) {
                                if ($detail['average_grade'] > 0) {
                                    $total_avg += $detail['average_grade'];
                                    $count_with_grades++;
                                }
                            }
                            echo $count_with_grades > 0 ? number_format($total_avg / $count_with_grades, 1) : '0.0';
                            ?>
                        </div>
                        <div class="stat-label">Promedio General</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php 
                            $total_att = 0;
                            $count_att = 0;
                            foreach ($course_details as $detail) {
                                if ($detail['attendance_percentage'] > 0) {
                                    $total_att += $detail['attendance_percentage'];
                                    $count_att++;
                                }
                            }
                            echo $count_att > 0 ? number_format($total_att / $count_att, 0) : '0';
                            ?>%
                        </div>
                        <div class="stat-label">Asistencia Promedio</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php 
                            $total_sessions = 0;
                            $completed_sessions = 0;
                            foreach ($course_details as $detail) {
                                $total_sessions += $detail['total_sessions'];
                                $completed_sessions += $detail['completed_sessions'];
                            }
                            echo $completed_sessions . '/' . $total_sessions;
                            ?>
                        </div>
                        <div class="stat-label">Sesiones Completadas</div>
                    </div>
                </div>
            </div>
            
            <!-- Grid de cursos -->
            <div class="courses-grid">
                <?php foreach ($course_details as $course_id => $detail): 
                    $status = getCourseStatus($detail['average_grade'], $detail['attendance_percentage']);
                ?>
                    <div class="course-card">
                        <div class="course-card-header">
                            <div class="course-status-badge <?php echo $status['status']; ?>">
                                <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                <?php echo $status['text']; ?>
                            </div>
                            <h3 class="course-title"><?php echo htmlspecialchars($detail['info']['nombre']); ?></h3>
                            <div class="course-meta">
                                <div class="course-meta-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo htmlspecialchars($detail['info']['programa_nombre']); ?>
                                </div>
                                <div class="course-meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo htmlspecialchars($detail['info']['periodo_lectivo'] . ' - ' . $detail['info']['periodo_academico']); ?>
                                </div>
                                <div class="course-meta-item">
                                    <i class="fas fa-user-tie"></i>
                                    <?php echo htmlspecialchars($detail['info']['docente_nombre']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="course-card-body">
                            <!-- Progreso del curso -->
                            <div class="course-progress">
                                <div class="progress-header">
                                    <span class="progress-label">Progreso del Curso</span>
                                    <span class="progress-value"><?php echo round($detail['progress']); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $detail['progress']; ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- Estadísticas del curso -->
                            <div class="course-stats">
                                <div class="course-stat">
                                    <div class="course-stat-value" style="color: <?php echo getGradeColor($detail['average_grade']); ?>">
                                        <?php echo number_format($detail['average_grade'], 1); ?>
                                    </div>
                                    <div class="course-stat-label">Promedio</div>
                                </div>
                                <div class="course-stat">
                                    <div class="course-stat-value">
                                        <?php echo round($detail['attendance_percentage']); ?>%
                                    </div>
                                    <div class="course-stat-label">Asistencia</div>
                                </div>
                                <div class="course-stat">
                                    <div class="course-stat-value">
                                        <?php echo $detail['evaluated_indicators'] . '/' . $detail['total_indicators']; ?>
                                    </div>
                                    <div class="course-stat-label">Indicadores</div>
                                </div>
                            </div>
                            
                            <!-- Próximas sesiones -->
                            <div class="course-upcoming">
                                <h4 class="section-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    Próximas Sesiones
                                </h4>
                                <?php if (!empty($detail['upcoming_sessions'])): ?>
                                    <div class="upcoming-sessions">
                                        <?php foreach ($detail['upcoming_sessions'] as $session): 
                                            $date = new DateTime($session['fecha']);
                                            $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
                                        ?>
                                            <div class="session-item">
                                                <div class="session-date">
                                                    <div class="session-date-day"><?php echo $date->format('d'); ?></div>
                                                    <div class="session-date-month"><?php echo $months[$date->format('n') - 1]; ?></div>
                                                </div>
                                                <div class="session-info">
                                                    <div class="session-title">
                                                        Sesión <?php echo $session['numero_sesion']; ?>: <?php echo htmlspecialchars($session['titulo']); ?>
                                                    </div>
                                                    <div class="session-time">
                                                        <i class="fas fa-clock"></i> <?php echo $date->format('d/m/Y'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-sessions">
                                        No hay sesiones programadas próximamente
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Acciones del curso -->
                            <div class="course-actions">
                                <a href="grades.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i>
                                    Ver Notas
                                </a>
                                <a href="course_details.php?id=<?php echo $course_id; ?>" class="btn btn-outline">
                                    <i class="fas fa-info-circle"></i>
                                    Detalles
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Estado vacío -->
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>No tienes cursos matriculados</h3>
                <p>Cuando te matricules en unidades didácticas, aparecerán aquí para que puedas darles seguimiento.</p>
                <a href="../dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Botón flotante para consultar notas -->
    <a href="view_grades.php" class="floating-button" title="Consultar todas mis notas">
        <i class="fas fa-clipboard-list"></i>
    </a>
    
    <script>
        // Animación de las barras de progreso al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const progressFills = document.querySelectorAll('.progress-fill');
            progressFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 100);
            });
            
            // Animación de aparición escalonada para las tarjetas
            const cards = document.querySelectorAll('.course-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>