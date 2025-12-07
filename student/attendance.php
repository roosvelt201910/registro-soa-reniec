<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/Course.php';
require_once '../classes/Attendance.php';

// Permitir acceso tanto a estudiantes logueados como consulta por DNI
$auth = new Auth();
$course = new Course();
$attendance = new Attendance();

$student_data = null;
$student_courses = [];
$attendance_data = [];
$search_dni = '';

// Si es estudiante logueado, usar su ID
if ($auth->isLoggedIn() && $_SESSION['user_type'] == 'estudiante') {
    $user = new User();
    $student_data = $user->getStudentByDni($_SESSION['user_dni']);
    $student_courses = $course->getStudentCourses($_SESSION['user_id']);
} elseif (isset($_POST['dni']) || isset($_GET['dni'])) {
    // Consulta por DNI sin login
    $search_dni = $_POST['dni'] ?? $_GET['dni'];
    $search_dni = sanitizeInput($search_dni);
    
    if (strlen($search_dni) == 8 && is_numeric($search_dni)) {
        $user = new User();
        $student_data = $user->getStudentByDni($search_dni);
        
        if ($student_data) {
            $student_courses = $course->getStudentCourses($student_data['id']);
        }
    }
}

// Obtener datos de asistencia si hay datos del estudiante
if ($student_data && !empty($student_courses)) {
    foreach ($student_courses as $curso) {
        $attendance_summary = $attendance->getAttendanceSummary($curso['id']);
        
        // Filtrar solo el estudiante actual
        $student_attendance = array_filter($attendance_summary, function($item) use ($student_data) {
            return $item['estudiante_id'] == $student_data['id'];
        });
        
        $attendance_data[$curso['id']] = reset($student_attendance) ?: null;
        
        // Obtener detalle de asistencia por sesión
        $detailed_attendance = $attendance->getAttendanceByCourse($curso['id']);
        $attendance_data[$curso['id']]['detalle'] = array_filter($detailed_attendance, function($item) use ($student_data) {
            return $item['estudiante_id'] == $student_data['id'];
        });
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Asistencia - Sistema Académico</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 1rem 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .search-card h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            max-width: 400px;
            margin: 0 auto;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .student-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .student-info h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .student-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .course-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .course-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .course-header h3 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .course-info {
            color: #666;
            font-size: 14px;
        }
        
        .attendance-stats {
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
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .attendance-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .attendance-table .session-info {
            text-align: left;
        }
        
        .status-present {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
            border-radius: 4px;
            padding: 4px 8px;
        }
        
        .status-falta {
            background: #f8d7da;
            color: #721c24;
            font-weight: bold;
            border-radius: 4px;
            padding: 4px 8px;
        }
        
        .status-permiso {
            background: #fff3cd;
            color: #856404;
            font-weight: bold;
            border-radius: 4px;
            padding: 4px 8px;
        }
        
        .overall-status {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-dpi {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .search-form {
                flex-direction: column;
                max-width: none;
            }
            
            .attendance-table {
                font-size: 12px;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 8px 4px;
            }
            
            .attendance-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Consulta de Asistencia</h1>
        <p>Instituto de Educación Superior Público "Alto Huallaga" - Tocache</p>
    </div>
    
    <div class="container">
        <?php if ($auth->isLoggedIn()): ?>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        <?php endif; ?>
        
        <!-- Formulario de búsqueda -->
        <?php if (!$auth->isLoggedIn() || $_SESSION['user_type'] != 'estudiante'): ?>
            <div class="search-card">
                <h2>Consultar Asistencia por DNI</h2>
                <form method="POST" class="search-form">
                    <div class="form-group">
                        <label for="dni">Número de DNI:</label>
                        <input type="text" 
                               id="dni" 
                               name="dni" 
                               maxlength="8" 
                               pattern="[0-9]{8}" 
                               value="<?php echo htmlspecialchars($search_dni); ?>"
                               placeholder="Ingrese su DNI"
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary">Consultar</button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($search_dni && !$student_data): ?>
            <div class="alert alert-error">
                No se encontró ningún estudiante con el DNI ingresado.
            </div>
        <?php endif; ?>
        
        <?php if ($student_data): ?>
            <!-- Información del estudiante -->
            <div class="student-info">
                <h2>Información del Estudiante</h2>
                <div class="student-details">
                    <p><strong>Nombre Completo:</strong> <?php echo htmlspecialchars($student_data['apellidos'] . ', ' . $student_data['nombres']); ?></p>
                    <p><strong>DNI:</strong> <?php echo htmlspecialchars($student_data['dni']); ?></p>
                    <?php if ($student_data['email']): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student_data['email']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($student_courses)): ?>
                <div class="no-data">
                    <h3>No hay cursos matriculados</h3>
                    <p>El estudiante no está matriculado en ningún curso actualmente.</p>
                </div>
            <?php else: ?>
                <!-- Cursos y asistencias -->
                <?php foreach ($student_courses as $curso): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                            <div class="course-info">
                                <strong>Programa:</strong> <?php echo htmlspecialchars($curso['programa_nombre']); ?><br>
                                <strong>Periodo:</strong> <?php echo htmlspecialchars($curso['periodo_lectivo']); ?> - <?php echo htmlspecialchars($curso['periodo_academico']); ?><br>
                                <strong>Docente:</strong> <?php echo htmlspecialchars($curso['docente_nombre']); ?>
                            </div>
                        </div>
                        
                        <?php if (!$attendance_data[$curso['id']]): ?>
                            <div class="no-data">
                                <p>No hay registros de asistencia para este curso.</p>
                            </div>
                        <?php else: ?>
                            <?php $attendance_info = $attendance_data[$curso['id']]; ?>
                            
                            <!-- Estadísticas de asistencia -->
                            <div class="attendance-stats">
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $attendance_info['total_sesiones_registradas']; ?></div>
                                    <div class="stat-label">Total Sesiones</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $attendance_info['asistencias']; ?></div>
                                    <div class="stat-label">Asistencias</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $attendance_info['faltas']; ?></div>
                                    <div class="stat-label">Faltas</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $attendance_info['permisos']; ?></div>
                                    <div class="stat-label">Permisos</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo number_format($attendance_info['porcentaje_asistencia'], 1); ?>%</div>
                                    <div class="stat-label">% Asistencia</div>
                                </div>
                            </div>
                            
                            <!-- Detalle por sesión -->
                            <h4>Detalle de Asistencia por Sesión</h4>
                            <?php if (empty($attendance_info['detalle'])): ?>
                                <p>No hay detalles de sesiones registradas.</p>
                            <?php else: ?>
                                <table class="attendance-table">
                                    <thead>
                                        <tr>
                                            <th>Sesión</th>
                                            <th>Título</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_info['detalle'] as $detalle): ?>
                                            <tr>
                                                <td><?php echo $detalle['numero_sesion']; ?></td>
                                                <td class="session-info"><?php echo htmlspecialchars($detalle['titulo']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($detalle['fecha'])); ?></td>
                                                <td>
                                                    <?php if ($detalle['estado']): ?>
                                                        <span class="status-<?php echo $detalle['estado']; ?>">
                                                            <?php echo ucfirst($detalle['estado']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: #999;">Sin registrar</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($detalle['observaciones'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                            <!-- Estado general de asistencia -->
                            <?php
                            $condicion_asistencia = calculateAttendanceCondition($attendance_info['porcentaje_asistencia']);
                            $status_class = $attendance_info['porcentaje_asistencia'] >= 70 ? 'status-approved' : 'status-dpi';
                            ?>
                            <div class="overall-status <?php echo $status_class; ?>">
                                <strong>Condición de Asistencia: <?php echo $condicion_asistencia; ?></strong><br>
                                <small>
                                    <?php if ($attendance_info['porcentaje_asistencia'] >= 70): ?>
                                        ✅ Cumple con el mínimo de asistencia requerido (70%)
                                    <?php else: ?>
                                        ❌ No cumple con el mínimo de asistencia requerido (70%)
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Validación del DNI en tiempo real
        const dniInput = document.getElementById('dni');
        if (dniInput) {
            dniInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 8) {
                    value = value.substring(0, 8);
                }
                e.target.value = value;
            });
        }
    </script>
</body>
</html>