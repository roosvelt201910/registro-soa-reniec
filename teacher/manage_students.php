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
            
            if ($_POST['action'] == 'enroll_student') {
                $estudiante_dni = sanitizeInput($_POST['estudiante_dni']);
                $unidad_didactica_id = (int)$_POST['unidad_didactica_id'];
                
                // Buscar estudiante por DNI
                $query = "SELECT id FROM usuarios WHERE dni = :dni AND tipo_usuario = 'estudiante' AND estado = 'activo'";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':dni', $estudiante_dni);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
                    $estudiante_id = $estudiante['id'];
                    
                    // Verificar que el curso pertenece al docente
                    $query = "SELECT id FROM unidades_didacticas WHERE id = :id AND docente_id = :docente_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $unidad_didactica_id);
                    $stmt->bindParam(':docente_id', $user_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Matricular estudiante
                        $query = "INSERT INTO matriculas (estudiante_id, unidad_didactica_id) VALUES (:estudiante_id, :unidad_didactica_id)";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':estudiante_id', $estudiante_id);
                        $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success">Estudiante matriculado exitosamente.</div>';
                        } else {
                            $message = '<div class="alert alert-error">Error al matricular estudiante. Puede que ya esté matriculado.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-error">No tiene permisos para matricular en este curso.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-error">No se encontró ningún estudiante con el DNI ingresado.</div>';
                }
            } elseif ($_POST['action'] == 'unenroll_student') {
                $matricula_id = (int)$_POST['matricula_id'];
                
                // Verificar que la matrícula pertenece a un curso del docente
                $query = "SELECT m.id FROM matriculas m 
                         JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id 
                         WHERE m.id = :matricula_id AND ud.docente_id = :docente_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':matricula_id', $matricula_id);
                $stmt->bindParam(':docente_id', $user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Cambiar estado a retirado
                    $query = "UPDATE matriculas SET estado = 'retirado' WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $matricula_id);
                    
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Estudiante desmatriculado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error">Error al desmatricular estudiante.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-error">No tiene permisos para esta acción.</div>';
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
    
    // Obtener cursos del docente
    $query = "SELECT ud.*, p.nombre as programa_nombre FROM unidades_didacticas ud 
             JOIN programas_estudio p ON ud.programa_id = p.id 
             WHERE ud.docente_id = :docente_id AND ud.estado = 'activo'
             ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':docente_id', $user_id);
    $stmt->execute();
    $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estudiantes matriculados en los cursos del docente
    $enrolledStudents = [];
    $selectedCourse = null;
    
    if (isset($_GET['course_id'])) {
        $course_id = (int)$_GET['course_id'];
        
        // Verificar que el curso pertenece al docente
        $query = "SELECT ud.*, p.nombre as programa_nombre FROM unidades_didacticas ud 
                 JOIN programas_estudio p ON ud.programa_id = p.id 
                 WHERE ud.id = :id AND ud.docente_id = :docente_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $course_id);
        $stmt->bindParam(':docente_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener estudiantes matriculados
            $query = "SELECT m.id as matricula_id, u.id as estudiante_id, u.dni, 
                            CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                            u.email, m.fecha_matricula, m.estado as estado_matricula,
                            COUNT(a.id) as total_asistencias,
                            SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as asistencias_presentes
                     FROM matriculas m
                     JOIN usuarios u ON m.estudiante_id = u.id
                     LEFT JOIN asistencias a ON a.estudiante_id = u.id
                     LEFT JOIN sesiones s ON a.sesion_id = s.id AND s.unidad_didactica_id = m.unidad_didactica_id
                     WHERE m.unidad_didactica_id = :course_id
                     GROUP BY m.id, u.id, u.dni, u.apellidos, u.nombres, u.email, m.fecha_matricula, m.estado
                     ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $myCourses = [];
    $enrolledStudents = [];
    $message = '<div class="alert alert-error">Error al cargar datos: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estudiantes - Sistema Académico</title>
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
            background: linear-gradient(135deg, #032a73ff 0%, #0526a8ff 100%);
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin-right: 10px;
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
        
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .students-table th,
        .students-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .students-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }
        
        .students-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-retirado {
            background: #f8d7da;
            color: #721c24;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .course-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .attendance-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            text-align: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            line-height: 20px;
        }
        
        .attendance-good {
            background: #28a745;
        }
        
        .attendance-warning {
            background: #ffc107;
        }
        
        .attendance-danger {
            background: #dc3545;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .students-table {
                font-size: 12px;
            }
            
            .students-table th,
            .students-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Gestión de Estudiantes</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Selección de Curso -->
        <div class="card">
            <h2>Seleccionar Curso</h2>
            <form method="GET">
                <div class="form-group">
                    <label for="course_id">Curso:</label>
                    <select name="course_id" id="course_id" onchange="this.form.submit()">
                        <option value="">Seleccione un curso...</option>
                        <?php foreach ($myCourses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['nombre']) . ' - ' . $course['periodo_lectivo']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if ($selectedCourse): ?>
            <!-- Información del Curso -->
            <div class="course-info">
                <h4>Curso: <?php echo htmlspecialchars($selectedCourse['nombre']); ?></h4>
                <p><strong>Programa:</strong> <?php echo htmlspecialchars($selectedCourse['programa_nombre']); ?></p>
                <p><strong>Periodo:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo']) . ' - ' . htmlspecialchars($selectedCourse['periodo_academico']); ?></p>
                <p><strong>Docente:</strong> <?php echo htmlspecialchars($user_name); ?></p>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($enrolledStudents, function($s) { return $s['estado_matricula'] == 'activo'; })); ?></div>
                    <div class="stat-label">Estudiantes Activos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($enrolledStudents, function($s) { return $s['estado_matricula'] == 'retirado'; })); ?></div>
                    <div class="stat-label">Estudiantes Retirados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($enrolledStudents); ?></div>
                    <div class="stat-label">Total Matriculados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $activeStudents = array_filter($enrolledStudents, function($s) { return $s['estado_matricula'] == 'activo'; });
                        if (!empty($activeStudents)) {
                            $avgAttendance = 0;
                            $count = 0;
                            foreach ($activeStudents as $student) {
                                if ($student['total_asistencias'] > 0) {
                                    $avgAttendance += ($student['asistencias_presentes'] / $student['total_asistencias']) * 100;
                                    $count++;
                                }
                            }
                            echo $count > 0 ? number_format($avgAttendance / $count, 1) . '%' : '0%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Promedio Asistencia</div>
                </div>
            </div>
            
            <!-- Matricular Nuevo Estudiante -->
            <div class="card">
                <h2>Matricular Nuevo Estudiante</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="enroll_student">
                    <input type="hidden" name="unidad_didactica_id" value="<?php echo $selectedCourse['id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="estudiante_dni">DNI del Estudiante:</label>
                            <input type="text" 
                                   id="estudiante_dni" 
                                   name="estudiante_dni" 
                                   maxlength="8" 
                                   pattern="[0-9]{8}" 
                                   placeholder="Ej: 12345678"
                                   required>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: end;">
                            <button type="submit" class="btn btn-success">Matricular Estudiante</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Estudiantes -->
            <div class="card">
                <h2>Estudiantes Matriculados (<?php echo count($enrolledStudents); ?>)</h2>
                
                <?php if (empty($enrolledStudents)): ?>
                    <p>No hay estudiantes matriculados en este curso.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Fecha Matrícula</th>
                                    <th>Estado</th>
                                    <th>Asistencias</th>
                                    <th>% Asistencia</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledStudents as $student): 
                                    $attendance_percentage = $student['total_asistencias'] > 0 ? 
                                        ($student['asistencias_presentes'] / $student['total_asistencias']) * 100 : 0;
                                    
                                    $attendance_class = 'attendance-danger';
                                    if ($attendance_percentage >= 80) $attendance_class = 'attendance-good';
                                    elseif ($attendance_percentage >= 70) $attendance_class = 'attendance-warning';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['dni']); ?></td>
                                        <td><?php echo htmlspecialchars($student['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?: 'No especificado'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($student['fecha_matricula'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $student['estado_matricula']; ?>">
                                                <?php echo ucfirst($student['estado_matricula']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $student['asistencias_presentes']; ?> / <?php echo $student['total_asistencias']; ?>
                                        </td>
                                        <td>
                                            <span class="attendance-indicator <?php echo $attendance_class; ?>">
                                                <?php echo number_format($attendance_percentage, 0); ?>
                                            </span>
                                            <?php echo number_format($attendance_percentage, 1); ?>%
                                        </td>
                                        <td>
                                            <a href="../student/grades.php?dni=<?php echo $student['dni']; ?>" 
                                               target="_blank" 
                                               class="btn btn-primary btn-small">
                                                Ver Notas
                                            </a>
                                            
                                            <a href="../student/attendance.php?dni=<?php echo $student['dni']; ?>" 
                                               target="_blank" 
                                               class="btn btn-success btn-small">
                                                Ver Asistencias
                                            </a>
                                            
                                            <?php if ($student['estado_matricula'] == 'activo'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de desmatricular a este estudiante?')">
                                                    <input type="hidden" name="action" value="unenroll_student">
                                                    <input type="hidden" name="matricula_id" value="<?php echo $student['matricula_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        Desmatricular
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones Rápidas -->
            <div class="card">
                <h2>Acciones Rápidas</h2>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="attendance.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                        📋 Registrar Asistencia
                    </a>
                    
                    <a href="evaluations.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                        📝 Registrar Evaluaciones
                    </a>
                    
                    <a href="reports.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                        📊 Ver Reportes
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Validación del DNI en tiempo real
        document.getElementById('estudiante_dni').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.substring(0, 8);
            }
            e.target.value = value;
        });
        
        // Auto-submit form when course is selected
        document.getElementById('course_id').addEventListener('change', function() {
            if (this.value) {
                this.form.submit();
            }
        });
        
        // Confirmación antes de desmatricular
        function confirmUnenroll(studentName) {
            return confirm('¿Está seguro de desmatricular a ' + studentName + '? Esta acción cambiará su estado a "retirado".');
        }
    </script>
</body>
</html>