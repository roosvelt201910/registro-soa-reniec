<?php
require_once '../config/database.php';
requirePermission('docente');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$message = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            if ($_POST['action'] == 'create_course') {
                $nombre = sanitizeInput($_POST['nombre']);
                $codigo = sanitizeInput($_POST['codigo']);
                $programa_id = (int)$_POST['programa_id'];
                $periodo_lectivo = sanitizeInput($_POST['periodo_lectivo']);
                $periodo_academico = sanitizeInput($_POST['periodo_academico']);
                
                if (empty($nombre) || empty($codigo) || !$programa_id || empty($periodo_lectivo) || empty($periodo_academico)) {
                    $message = '<div class="alert alert-error">Todos los campos son obligatorios.</div>';
                } else {
                    // Crear curso con el docente actual
                    $query = "INSERT INTO unidades_didacticas (nombre, codigo, programa_id, periodo_lectivo, periodo_academico, docente_id) 
                             VALUES (:nombre, :codigo, :programa_id, :periodo_lectivo, :periodo_academico, :docente_id)";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':codigo', $codigo);
                    $stmt->bindParam(':programa_id', $programa_id);
                    $stmt->bindParam(':periodo_lectivo', $periodo_lectivo);
                    $stmt->bindParam(':periodo_academico', $periodo_academico);
                    $stmt->bindParam(':docente_id', $user_id);
                    
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Curso creado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error">Error al crear el curso. Verifique que el código no esté duplicado.</div>';
                    }
                }
            } elseif ($_POST['action'] == 'update_course_status') {
                $course_id = (int)$_POST['course_id'];
                $status = sanitizeInput($_POST['status']);
                
                // Verificar que el curso pertenece al docente
                $query = "UPDATE unidades_didacticas SET estado = :estado WHERE id = :id AND docente_id = :docente_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':estado', $status);
                $stmt->bindParam(':id', $course_id);
                $stmt->bindParam(':docente_id', $user_id);
                
                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $message = '<div class="alert alert-success">Estado del curso actualizado.</div>';
                } else {
                    $message = '<div class="alert alert-error">Error al actualizar el estado del curso.</div>';
                }
            }
        } catch(PDOException $e) {
            $message = '<div class="alert alert-error">Error de base de datos: ' . $e->getMessage() . '</div>';
        }
    }
}

// Obtener datos necesarios
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener cursos del docente con estadísticas
    $query = "SELECT 
                ud.id,
                ud.nombre,
                ud.codigo,
                ud.periodo_lectivo,
                ud.periodo_academico,
                ud.estado,
                p.nombre as programa_nombre,
                COUNT(DISTINCT m.id) as total_estudiantes,
                COUNT(DISTINCT CASE WHEN m.estado = 'activo' THEN m.id END) as estudiantes_activos,
                COUNT(DISTINCT s.id) as total_sesiones,
                COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_realizadas,
                COUNT(DISTINCT il.id) as indicadores_logro
             FROM unidades_didacticas ud
             JOIN programas_estudio p ON ud.programa_id = p.id
             LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id
             LEFT JOIN sesiones s ON ud.id = s.unidad_didactica_id
             LEFT JOIN indicadores_logro il ON ud.id = il.unidad_didactica_id
             WHERE ud.docente_id = :docente_id
             GROUP BY ud.id, ud.nombre, ud.codigo, ud.periodo_lectivo, ud.periodo_academico, ud.estado, p.nombre
             ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':docente_id', $user_id);
    $stmt->execute();
    $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener programas de estudio disponibles
    $query = "SELECT * FROM programas_estudio WHERE estado = 'activo' ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener curso seleccionado para ver detalles
    $selectedCourse = null;
    $courseStudents = [];
    $courseSessions = [];
    $courseIndicators = [];
    
    if (isset($_GET['course_id'])) {
        $course_id = (int)$_GET['course_id'];
        
        // Verificar que el curso pertenece al docente
        $query = "SELECT ud.*, p.nombre as programa_nombre 
                 FROM unidades_didacticas ud 
                 JOIN programas_estudio p ON ud.programa_id = p.id 
                 WHERE ud.id = :id AND ud.docente_id = :docente_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $course_id);
        $stmt->bindParam(':docente_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener estudiantes del curso
            $query = "SELECT u.dni, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo, 
                            m.fecha_matricula, m.estado as estado_matricula
                     FROM matriculas m
                     JOIN usuarios u ON m.estudiante_id = u.id
                     WHERE m.unidad_didactica_id = :course_id
                     ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $courseStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener sesiones del curso
            $query = "SELECT * FROM sesiones WHERE unidad_didactica_id = :course_id ORDER BY numero_sesion";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $courseSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener indicadores de logro
            $query = "SELECT * FROM indicadores_logro WHERE unidad_didactica_id = :course_id ORDER BY numero_indicador";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $courseIndicators = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $myCourses = [];
    $programs = [];
    $message = '<div class="alert alert-error">Error al cargar datos: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cursos - Sistema Académico</title>
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
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-banner h2 {
            color: white;
            border: none;
            margin-bottom: 10px;
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
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .course-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid #667eea;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .course-header {
            margin-bottom: 15px;
        }
        
        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .course-code {
            font-size: 14px;
            color: #667eea;
            font-weight: 500;
        }
        
        .course-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .course-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .course-stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        
        .course-stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
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
            padding: 10px 20px;
            font-size: 14px;
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
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
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            border-bottom: 2px solid transparent;
            font-size: 14px;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .detail-section h4 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .list-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .list-item:last-child {
            border-bottom: none;
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
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-stats {
                grid-template-columns: 1fr;
            }
            
            .course-actions {
                flex-direction: column;
            }
            
            .btn {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Mis Cursos</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Banner de Bienvenida -->
        <div class="welcome-banner">
            <h2>¡Bienvenido, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p>Gestiona tus cursos, estudiantes y evaluaciones desde este panel</p>
        </div>
        
        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($myCourses); ?></div>
                <div class="stat-label">Total de Cursos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count(array_filter($myCourses, function($c) { return $c['estado'] == 'activo'; })); ?>
                </div>
                <div class="stat-label">Cursos Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo array_sum(array_column($myCourses, 'estudiantes_activos')); ?>
                </div>
                <div class="stat-label">Total Estudiantes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo array_sum(array_column($myCourses, 'sesiones_realizadas')); ?>
                </div>
                <div class="stat-label">Sesiones Realizadas</div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="showTab('courses')">Mis Cursos</button>
                <button class="tab" onclick="showTab('create')">Crear Nuevo Curso</button>
                <?php if ($selectedCourse): ?>
                    <button class="tab" onclick="showTab('details')">Detalles del Curso</button>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Mis Cursos -->
            <div id="courses" class="tab-content active">
                <h2>Cursos Asignados (<?php echo count($myCourses); ?>)</h2>
                
                <?php if (empty($myCourses)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3>No tienes cursos asignados</h3>
                        <p>Solicita al administrador que te asigne cursos o crea uno nuevo.</p>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($myCourses as $course): ?>
                            <div class="course-card">
                                <div class="course-header">
                                    <div class="course-title"><?php echo htmlspecialchars($course['nombre']); ?></div>
                                    <div class="course-code"><?php echo htmlspecialchars($course['codigo']); ?></div>
                                </div>
                                
                                <div class="course-info">
                                    <p><strong>Programa:</strong> <?php echo htmlspecialchars($course['programa_nombre']); ?></p>
                                    <p><strong>Periodo:</strong> <?php echo htmlspecialchars($course['periodo_lectivo']); ?> - <?php echo htmlspecialchars($course['periodo_academico']); ?></p>
                                    <p><strong>Estado:</strong> 
                                        <span class="status-badge status-<?php echo $course['estado']; ?>">
                                            <?php echo ucfirst($course['estado']); ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="course-stats">
                                    <div class="course-stat">
                                        <div class="course-stat-number"><?php echo $course['estudiantes_activos']; ?></div>
                                        <div class="course-stat-label">Estudiantes</div>
                                    </div>
                                    <div class="course-stat">
                                        <div class="course-stat-number"><?php echo $course['sesiones_realizadas']; ?>/<?php echo $course['total_sesiones']; ?></div>
                                        <div class="course-stat-label">Sesiones</div>
                                    </div>
                                    <div class="course-stat">
                                        <div class="course-stat-number"><?php echo $course['indicadores_logro']; ?></div>
                                        <div class="course-stat-label">Indicadores</div>
                                    </div>
                                </div>
                                
                                <div class="course-actions">
                                    <a href="?course_id=<?php echo $course['id']; ?>&tab=details" class="btn btn-primary">
                                        👁️ Ver Detalles
                                    </a>
                                    <a href="attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success">
                                        📋 Asistencia
                                    </a>
                                    <a href="evaluations.php?course_id=<?php echo $course['id']; ?>" class="btn btn-warning">
                                        📝 Evaluaciones
                                    </a>
                                    <a href="manage_students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                        👥 Estudiantes
                                    </a>
                                    <a href="reports.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success">
                                        📊 Reportes
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Crear Nuevo Curso -->
            <div id="create" class="tab-content">
                <h2>Crear Nuevo Curso</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_course">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="codigo">Código del Curso <span style="color: red;">*</span></label>
                            <input type="text" 
                                   id="codigo" 
                                   name="codigo" 
                                   placeholder="Ej: NCCIF001"
                                   style="text-transform: uppercase;"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="programa_id">Programa de Estudio <span style="color: red;">*</span></label>
                            <select name="programa_id" id="programa_id" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo $program['id']; ?>">
                                        <?php echo htmlspecialchars($program['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre del Curso <span style="color: red;">*</span></label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               placeholder="Ej: Normas de Control de calidad en la Industria Farmacéutica"
                               required>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="periodo_lectivo">Periodo Lectivo <span style="color: red;">*</span></label>
                            <input type="text" 
                                   id="periodo_lectivo" 
                                   name="periodo_lectivo" 
                                   placeholder="Ej: 2024-II"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="periodo_academico">Periodo Académico <span style="color: red;">*</span></label>
                            <input type="text" 
                                   id="periodo_academico" 
                                   name="periodo_academico" 
                                   placeholder="Ej: VI-A"
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 16px;">
                        Crear Curso
                    </button>
                </form>
            </div>
            
            <!-- Tab: Detalles del Curso -->
            <?php if ($selectedCourse): ?>
                <div id="details" class="tab-content">
                    <h2>Detalles: <?php echo htmlspecialchars($selectedCourse['nombre']); ?></h2>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>Código:</strong> <?php echo htmlspecialchars($selectedCourse['codigo']); ?>
                            </div>
                            <div>
                                <strong>Programa:</strong> <?php echo htmlspecialchars($selectedCourse['programa_nombre']); ?>
                            </div>
                            <div>
                                <strong>Periodo Lectivo:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo']); ?>
                            </div>
                            <div>
                                <strong>Periodo Académico:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_academico']); ?>
                            </div>
                            <div>
                                <strong>Estado:</strong> 
                                <span class="status-badge status-<?php echo $selectedCourse['estado']; ?>">
                                    <?php echo ucfirst($selectedCourse['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-grid">
                        <!-- Estudiantes -->
                        <div class="detail-section">
                            <h4>👥 Estudiantes Matriculados (<?php echo count($courseStudents); ?>)</h4>
                            <?php if (empty($courseStudents)): ?>
                                <p style="color: #666;">No hay estudiantes matriculados</p>
                            <?php else: ?>
                                <?php foreach (array_slice($courseStudents, 0, 5) as $student): ?>
                                    <div class="list-item">
                                        <span><?php echo htmlspecialchars($student['nombre_completo']); ?></span>
                                        <span class="status-badge status-<?php echo $student['estado_matricula']; ?>">
                                            <?php echo ucfirst($student['estado_matricula']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($courseStudents) > 5): ?>
                                    <div style="text-align: center; margin-top: 10px;">
                                        <a href="manage_students.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                                            Ver todos los estudiantes
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sesiones -->
                        <div class="detail-section">
                            <h4>📅 Sesiones de Clase (<?php echo count($courseSessions); ?>)</h4>
                            <?php if (empty($courseSessions)): ?>
                                <p style="color: #666;">No hay sesiones programadas</p>
                            <?php else: ?>
                                <?php foreach (array_slice($courseSessions, 0, 5) as $session): ?>
                                    <div class="list-item">
                                        <span>Sesión <?php echo $session['numero_sesion']; ?>: <?php echo htmlspecialchars($session['titulo']); ?></span>
                                        <span class="status-badge status-<?php echo $session['estado'] == 'realizada' ? 'active' : 'inactive'; ?>">
                                            <?php echo ucfirst($session['estado']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($courseSessions) > 5): ?>
                                    <div style="text-align: center; margin-top: 10px;">
                                        <a href="attendance.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                                            Ver todas las sesiones
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Indicadores de Logro -->
                        <div class="detail-section">
                            <h4>🎯 Indicadores de Logro (<?php echo count($courseIndicators); ?>)</h4>
                            <?php if (empty($courseIndicators)): ?>
                                <p style="color: #666;">No hay indicadores definidos</p>
                            <?php else: ?>
                                <?php foreach ($courseIndicators as $indicator): ?>
                                    <div class="list-item">
                                        <span>Indicador <?php echo $indicator['numero_indicador']; ?>: <?php echo htmlspecialchars($indicator['nombre']); ?></span>
                                        <span style="font-size: 12px; color: #666;"><?php echo $indicator['peso']; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div style="text-align: center; margin-top: 10px;">
                                <a href="evaluations.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                                    Gestionar Indicadores
                                </a>
                            </div>
                        </div>
                        
                        <!-- Acciones Rápidas -->
                        <div class="detail-section">
                            <h4>⚡ Acciones Rápidas</h4>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <a href="attendance.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-success">
                                    📋 Registrar Asistencia
                                </a>
                                <a href="evaluations.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-warning">
                                    📝 Registrar Evaluaciones
                                </a>
                                <a href="reports.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                                    📊 Ver Reportes
                                </a>
                                <a href="manage_students.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-success">
                                    👥 Gestionar Estudiantes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Auto-capitalize course code
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Check URL parameters to show appropriate tab
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            
            if (tab === 'details' && document.getElementById('details')) {
                showTab('details');
                // Update tab button
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelector('.tab:nth-child(3)').classList.add('active');
            } else if (tab === 'create') {
                showTab('create');
                // Update tab button
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelector('.tab:nth-child(2)').classList.add('active');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['codigo', 'nombre', 'programa_id', 'periodo_lectivo', 'periodo_academico'];
            let hasErrors = false;
            
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    hasErrors = true;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
            }
        });
    </script>
</body>
</html>