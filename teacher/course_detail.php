<?php
require_once '../config/database.php';
requirePermission('docente');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$message = '';
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: my_courses.php');
    exit();
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            if ($_POST['action'] == 'update_course_status') {
                $status = sanitizeInput($_POST['status']);
                
                $query = "UPDATE unidades_didacticas SET estado = :estado WHERE id = :id AND docente_id = :docente_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':estado', $status);
                $stmt->bindParam(':id', $course_id);
                $stmt->bindParam(':docente_id', $user_id);
                
                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $message = '<div class="alert alert-success">Estado del curso actualizado exitosamente.</div>';
                } else {
                    $message = '<div class="alert alert-error">Error al actualizar el estado del curso.</div>';
                }
            } elseif ($_POST['action'] == 'update_session_status') {
                $session_id = (int)$_POST['session_id'];
                $status = sanitizeInput($_POST['session_status']);
                
                // Verificar que la sesión pertenece a un curso del docente
                $query = "UPDATE sesiones s 
                         JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                         SET s.estado = :estado 
                         WHERE s.id = :session_id AND ud.docente_id = :docente_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':estado', $status);
                $stmt->bindParam(':session_id', $session_id);
                $stmt->bindParam(':docente_id', $user_id);
                
                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $message = '<div class="alert alert-success">Estado de la sesión actualizado.</div>';
                } else {
                    $message = '<div class="alert alert-error">Error al actualizar el estado de la sesión.</div>';
                }
            }
        } catch(PDOException $e) {
            $message = '<div class="alert alert-error">Error de base de datos: ' . $e->getMessage() . '</div>';
        }
    }
}

// Obtener datos del curso
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar que el curso pertenece al docente y obtener información completa
    $query = "SELECT ud.*, p.nombre as programa_nombre 
             FROM unidades_didacticas ud 
             JOIN programas_estudio p ON ud.programa_id = p.id 
             WHERE ud.id = :id AND ud.docente_id = :docente_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $course_id);
    $stmt->bindParam(':docente_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header('Location: my_courses.php');
        exit();
    }
    
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas del curso
    $query = "SELECT 
                COUNT(DISTINCT m.id) as total_matriculas,
                COUNT(DISTINCT CASE WHEN m.estado = 'activo' THEN m.id END) as matriculas_activas,
                COUNT(DISTINCT s.id) as total_sesiones,
                COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_realizadas,
                COUNT(DISTINCT il.id) as total_indicadores,
                COUNT(DISTINCT ie.id) as total_evaluaciones
             FROM unidades_didacticas ud
             LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id
             LEFT JOIN sesiones s ON ud.id = s.unidad_didactica_id
             LEFT JOIN indicadores_logro il ON ud.id = il.unidad_didactica_id
             LEFT JOIN indicadores_evaluacion ie ON s.id = ie.sesion_id
             WHERE ud.id = :course_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener estudiantes matriculados con estadísticas de asistencia
    $query = "SELECT 
                u.id, u.dni, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                u.email, m.fecha_matricula, m.estado as estado_matricula,
                COUNT(a.id) as total_asistencias_registradas,
                SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as asistencias_presentes,
                SUM(CASE WHEN a.estado = 'falta' THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN a.estado = 'permiso' THEN 1 ELSE 0 END) as permisos,
                CASE 
                    WHEN COUNT(a.id) > 0 THEN 
                        ROUND((SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1)
                    ELSE 0
                END as porcentaje_asistencia
             FROM matriculas m
             JOIN usuarios u ON m.estudiante_id = u.id
             LEFT JOIN asistencias a ON u.id = a.estudiante_id
             LEFT JOIN sesiones s ON a.sesion_id = s.id AND s.unidad_didactica_id = m.unidad_didactica_id
             WHERE m.unidad_didactica_id = :course_id
             GROUP BY u.id, u.dni, u.apellidos, u.nombres, u.email, m.fecha_matricula, m.estado
             ORDER BY u.apellidos, u.nombres";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener sesiones del curso
    $query = "SELECT s.*, 
                COUNT(DISTINCT a.id) as total_asistencias,
                COUNT(DISTINCT CASE WHEN a.estado = 'presente' THEN a.id END) as presentes,
                COUNT(DISTINCT ie.id) as evaluaciones_configuradas
             FROM sesiones s
             LEFT JOIN asistencias a ON s.id = a.sesion_id
             LEFT JOIN indicadores_evaluacion ie ON s.id = ie.sesion_id
             WHERE s.unidad_didactica_id = :course_id
             GROUP BY s.id, s.numero_sesion, s.titulo, s.fecha, s.descripcion, s.estado
             ORDER BY s.numero_sesion";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener indicadores de logro
    $query = "SELECT il.*,
                COUNT(DISTINCT ie.id) as evaluaciones_asociadas,
                AVG(es.calificacion) as promedio_calificaciones
             FROM indicadores_logro il
             LEFT JOIN indicadores_evaluacion ie ON il.id = ie.indicador_logro_id
             LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id
             WHERE il.unidad_didactica_id = :course_id
             GROUP BY il.id, il.numero_indicador, il.nombre, il.descripcion, il.peso
             ORDER BY il.numero_indicador";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $indicators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener últimas evaluaciones registradas
    $query = "SELECT 
                s.numero_sesion, s.titulo as sesion_titulo,
                ie.nombre as evaluacion_nombre,
                COUNT(es.id) as evaluaciones_registradas,
                AVG(es.calificacion) as promedio_evaluacion,
                MAX(es.fecha_evaluacion) as ultima_evaluacion
             FROM indicadores_evaluacion ie
             JOIN sesiones s ON ie.sesion_id = s.id
             LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id
             WHERE s.unidad_didactica_id = :course_id
             GROUP BY s.numero_sesion, s.titulo, ie.nombre
             ORDER BY MAX(es.fecha_evaluacion) DESC, s.numero_sesion DESC
             LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $recent_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $message = '<div class="alert alert-error">Error al cargar datos del curso: ' . $e->getMessage() . '</div>';
    $course = null;
}

if (!$course) {
    header('Location: my_courses.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Curso - Sistema Académico</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .breadcrumb {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .course-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .course-code {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .course-info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }
        
        .course-info-label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .course-info-value {
            font-size: 16px;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .stat-sublabel {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-realizada {
            background: #d4edda;
            color: #155724;
        }
        
        .status-programada {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-retirado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 10px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .progress-danger {
            background: linear-gradient(90deg, #dc3545, #e74c3c);
        }
        
        .progress-warning {
            background: linear-gradient(90deg, #ffc107, #f39c12);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .list-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .indicator-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        
        .indicator-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .indicator-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-info-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <div class="breadcrumb">
                    <a href="../dashboard.php">Dashboard</a> › 
                    <a href="my_courses.php">Mis Cursos</a> › 
                    Detalles del Curso
                </div>
                <h1>Detalles del Curso</h1>
            </div>
            <a href="my_courses.php" class="btn btn-back">← Volver a Mis Cursos</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Header del Curso -->
        <div class="course-header">
            <div class="course-title"><?php echo htmlspecialchars($course['nombre']); ?></div>
            <div class="course-code">Código: <?php echo htmlspecialchars($course['codigo']); ?></div>
            
            <div class="course-info-grid">
                <div class="course-info-item">
                    <div class="course-info-label">Programa de Estudio</div>
                    <div class="course-info-value"><?php echo htmlspecialchars($course['programa_nombre']); ?></div>
                </div>
                <div class="course-info-item">
                    <div class="course-info-label">Periodo Lectivo</div>
                    <div class="course-info-value"><?php echo htmlspecialchars($course['periodo_lectivo']); ?></div>
                </div>
                <div class="course-info-item">
                    <div class="course-info-label">Periodo Académico</div>
                    <div class="course-info-value"><?php echo htmlspecialchars($course['periodo_academico']); ?></div>
                </div>
                <div class="course-info-item">
                    <div class="course-info-label">Estado</div>
                    <div class="course-info-value">
                        <span class="status-badge status-<?php echo $course['estado']; ?>">
                            <?php echo ucfirst($course['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas del Curso -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['matriculas_activas']; ?></div>
                <div class="stat-label">Estudiantes Activos</div>
                <div class="stat-sublabel"><?php echo $stats['total_matriculas']; ?> total matriculados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['sesiones_realizadas']; ?></div>
                <div class="stat-label">Sesiones Realizadas</div>
                <div class="stat-sublabel"><?php echo $stats['total_sesiones']; ?> sesiones programadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_indicadores']; ?></div>
                <div class="stat-label">Indicadores de Logro</div>
                <div class="stat-sublabel"><?php echo $stats['total_evaluaciones']; ?> evaluaciones configuradas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $progress = $stats['total_sesiones'] > 0 ? 
                        round(($stats['sesiones_realizadas'] / $stats['total_sesiones']) * 100) : 0;
                    echo $progress;
                    ?>%
                </div>
                <div class="stat-label">Progreso del Curso</div>
                <div class="stat-sublabel">Avance de sesiones</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="main-content">
                <!-- Estudiantes Matriculados -->
                <div class="card">
                    <h2>👥 Estudiantes Matriculados (<?php echo count($students); ?>)</h2>
                    
                    <?php if (empty($students)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">
                            No hay estudiantes matriculados en este curso.
                            <br><br>
                            <a href="manage_students.php?course_id=<?php echo $course_id; ?>" class="btn btn-success">
                                Matricular Estudiantes
                            </a>
                        </p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>DNI</th>
                                    <th>Estado</th>
                                    <th>Asistencia</th>
                                    <th>% Asistencia</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($student['dni']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $student['estado_matricula']; ?>">
                                                <?php echo ucfirst($student['estado_matricula']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $student['asistencias_presentes']; ?> / 
                                            <?php echo $student['total_asistencias_registradas']; ?>
                                        </td>
                                        <td>
                                            <div class="progress-bar" style="height: 15px;">
                                                <div class="progress-fill <?php 
                                                    echo $student['porcentaje_asistencia'] >= 80 ? '' : 
                                                        ($student['porcentaje_asistencia'] >= 70 ? 'progress-warning' : 'progress-danger'); 
                                                ?>" 
                                                style="width: <?php echo $student['porcentaje_asistencia']; ?>%;">
                                                    <?php echo $student['porcentaje_asistencia']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="../student/grades.php?dni=<?php echo $student['dni']; ?>" 
                                               target="_blank" class="btn btn-primary btn-small">
                                                Ver Notas
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="manage_students.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                Gestionar Estudiantes
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sesiones de Clase -->
                <div class="card">
                    <h2>📅 Sesiones de Clase (<?php echo count($sessions); ?>)</h2>
                    
                    <?php if (empty($sessions)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">
                            No hay sesiones programadas para este curso.
                            <br><br>
                            <a href="attendance.php?course_id=<?php echo $course_id; ?>" class="btn btn-success">
                                Crear Primera Sesión
                            </a>
                        </p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sesión</th>
                                    <th>Título</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Asistencia</th>
                                    <th>Evaluaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><strong><?php echo $session['numero_sesion']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($session['titulo']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($session['fecha'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $session['estado']; ?>">
                                                <?php echo ucfirst($session['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['total_asistencias'] > 0): ?>
                                                <?php echo $session['presentes']; ?> / <?php echo $session['total_asistencias']; ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Sin registrar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['evaluaciones_configuradas'] > 0): ?>
                                                <span style="color: #28a745;">✓ <?php echo $session['evaluaciones_configuradas']; ?></span>
                                            <?php else: ?>
                                                <span style="color: #999;">Sin configurar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="attendance.php?course_id=<?php echo $course_id; ?>&session_id=<?php echo $session['id']; ?>" 
                                               class="btn btn-success btn-small">
                                                Asistencia
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="attendance.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                Gestionar Sesiones
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar">
                <!-- Acciones Rápidas -->
                <div class="card">
                    <h2>⚡ Acciones Rápidas</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="attendance.php?course_id=<?php echo $course_id; ?>" class="btn btn-success">
                            📋 Registrar Asistencia
                        </a>
                        <a href="evaluations.php?course_id=<?php echo $course_id; ?>" class="btn btn-warning">
                            📝 Gestionar Evaluaciones
                        </a>
                        <a href="manage_students.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                            👥 Gestionar Estudiantes
                        </a>
                        <a href="reports.php?course_id=<?php echo $course_id; ?>" class="btn btn-success">
                            📊 Ver Reportes
                        </a>
                    </div>
                </div>
                
                <!-- Indicadores de Logro -->
                <div class="card">
                    <h2>🎯 Indicadores de Logro</h2>
                    
                    <?php if (empty($indicators)): ?>
                        <p style="color: #666; font-size: 14px;">
                            No hay indicadores de logro definidos.
                        </p>
                        <a href="evaluations.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-small">
                            Crear Indicadores
                        </a>
                    <?php else: ?>
                        <?php foreach ($indicators as $indicator): ?>
                            <div class="indicator-card">
                                <div class="indicator-title">
                                    Indicador <?php echo $indicator['numero_indicador']; ?>
                                </div>
                                <div style="font-size: 14px; color: #666; margin-bottom: 8px;">
                                    <?php echo htmlspecialchars($indicator['nombre']); ?>
                                </div>
                                <div class="indicator-stats">
                                    <span>Peso: <?php echo $indicator['peso']; ?>%</span>
                                    <span>Evaluaciones: <?php echo $indicator['evaluaciones_asociadas']; ?></span>
                                    <?php if ($indicator['promedio_calificaciones']): ?>
                                        <span>Promedio: <?php echo number_format($indicator['promedio_calificaciones'], 1); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="evaluations.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-small">
                            Gestionar Indicadores
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Últimas Evaluaciones -->
                <div class="card">
                    <h2>📈 Últimas Evaluaciones</h2>
                    
                    <?php if (empty($recent_evaluations)): ?>
                        <p style="color: #666; font-size: 14px;">
                            No hay evaluaciones registradas.
                        </p>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_evaluations, 0, 5) as $evaluation): ?>
                            <div class="list-item">
                                <div style="font-size: 14px;">
                                    <strong>Sesión <?php echo $evaluation['numero_sesion']; ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($evaluation['evaluacion_nombre']); ?></small>
                                </div>
                                <div style="text-align: right; font-size: 12px;">
                                    <?php if ($evaluation['promedio_evaluacion']): ?>
                                        <div style="color: #667eea; font-weight: bold;">
                                            <?php echo number_format($evaluation['promedio_evaluacion'], 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="color: #666;">
                                        <?php echo $evaluation['evaluaciones_registradas']; ?> evaluaciones
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="evaluations.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-small">
                            Ver Todas las Evaluaciones
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Progreso del Curso -->
                <div class="card">
                    <h2>📊 Progreso del Curso</h2>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Sesiones Realizadas</span>
                            <span><?php echo $stats['sesiones_realizadas']; ?>/<?php echo $stats['total_sesiones']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['total_sesiones'] > 0 ? ($stats['sesiones_realizadas'] / $stats['total_sesiones']) * 100 : 0; ?>%;">
                                <?php echo $stats['total_sesiones'] > 0 ? round(($stats['sesiones_realizadas'] / $stats['total_sesiones']) * 100) : 0; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Estudiantes Activos</span>
                            <span><?php echo $stats['matriculas_activas']; ?>/<?php echo $stats['total_matriculas']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['total_matriculas'] > 0 ? ($stats['matriculas_activas'] / $stats['total_matriculas']) * 100 : 0; ?>%;">
                                <?php echo $stats['total_matriculas'] > 0 ? round(($stats['matriculas_activas'] / $stats['total_matriculas']) * 100) : 0; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div style="font-size: 14px; color: #666; text-align: center;">
                        <?php
                        $overall_progress = 0;
                        $progress_factors = 0;
                        
                        if ($stats['total_sesiones'] > 0) {
                            $overall_progress += ($stats['sesiones_realizadas'] / $stats['total_sesiones']) * 50;
                            $progress_factors += 50;
                        }
                        
                        if ($stats['total_indicadores'] > 0) {
                            $overall_progress += ($stats['total_evaluaciones'] > 0 ? 50 : 25);
                            $progress_factors += 50;
                        }
                        
                        $final_progress = $progress_factors > 0 ? round($overall_progress / $progress_factors * 100) : 0;
                        ?>
                        <strong>Progreso General: <?php echo $final_progress; ?>%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>