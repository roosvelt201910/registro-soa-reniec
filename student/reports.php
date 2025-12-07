<?php
// student/reports.php - Versión simplificada sin Evaluation.php
require_once '../config/database.php';
require_once '../classes/Auth.php';

// Test inicial
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicializar clases básicas
$auth = new Auth();
$user = new User();

$student_data = null;
$student_courses = [];
$search_dni = '';
$error_message = '';

// Si es estudiante logueado, usar su ID
if ($auth->isLoggedIn() && $_SESSION['user_type'] == 'estudiante') {
    $student_data = $user->getStudentByDni($_SESSION['user_dni']);
} elseif (isset($_POST['dni']) || isset($_GET['dni'])) {
    // Consulta por DNI sin login
    $search_dni = $_POST['dni'] ?? $_GET['dni'];
    $search_dni = sanitizeInput($search_dni);
    
    if (strlen($search_dni) == 8 && is_numeric($search_dni)) {
        $student_data = $user->getStudentByDni($search_dni);
        
        if (!$student_data) {
            $error_message = "No se encontró ningún estudiante con el DNI ingresado.";
        }
    } elseif (!empty($search_dni)) {
        $error_message = "El DNI debe tener exactamente 8 dígitos numéricos.";
    }
}

// Obtener cursos básicos si existe Course.php
if ($student_data && file_exists('../classes/Course.php')) {
    try {
        require_once '../classes/Course.php';
        $course = new Course();
        $student_courses = $course->getStudentCourses($student_data['id']);
    } catch (Exception $e) {
        // Si hay error con Course, usar consulta básica
        $database = new Database();
        $conn = $database->getConnection();
        $stmt = $conn->prepare("
            SELECT ud.*, pe.nombre as programa_nombre, 
                   CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre
            FROM matriculas m
            JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
            JOIN programas_estudio pe ON ud.programa_id = pe.id
            JOIN usuarios u ON ud.docente_id = u.id
            WHERE m.estudiante_id = ? AND m.estado = 'activo'
        ");
        $stmt->execute([$student_data['id']]);
        $student_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Académicos - Sistema Académico</title>
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
            text-decoration: none;
            display: inline-block;
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
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .navigation-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
        }
        
        .tab:not(.active) {
            background: #e9ecef;
            color: #666;
        }
        
        .tab:hover {
            transform: translateY(-2px);
        }
        
        .status-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
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
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reportes Académicos</h1>
        <p>Instituto de Educación Superior Público "Alto Huallaga" - Tocache</p>
    </div>
    
    <div class="container">
        <?php if ($auth->isLoggedIn()): ?>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        <?php endif; ?>
        
        <!-- Navegación de pestañas -->
        <div class="navigation-tabs">
            <a href="reports.php<?php echo $search_dni ? '?dni=' . $search_dni : ''; ?>" 
               class="tab active">
                📊 Reportes
            </a>
            <a href="attendance.php<?php echo $search_dni ? '?dni=' . $search_dni : ''; ?>" 
               class="tab">
                📋 Asistencias
            </a>
        </div>
        
        <!-- Formulario de búsqueda -->
        <?php if (!$auth->isLoggedIn() || $_SESSION['user_type'] != 'estudiante'): ?>
            <div class="search-card">
                <h2>Consultar Reportes por DNI</h2>
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
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
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
            
            <!-- Estado del sistema -->
            <div class="status-card">
                <h3>📋 Estado del Sistema de Reportes</h3>
                <p><strong>✅ Sistema Base:</strong> Funcionando correctamente</p>
                <p><strong>📚 Cursos Matriculados:</strong> <?php echo count($student_courses); ?> cursos encontrados</p>
                <p><strong>⚠️ Sistema de Evaluaciones:</strong> En desarrollo - Próximamente disponible</p>
            </div>
            
            <?php if (empty($student_courses)): ?>
                <div class="no-data">
                    <h3>No hay cursos matriculados</h3>
                    <p>El estudiante no está matriculado en ningún curso actualmente.</p>
                </div>
            <?php else: ?>
                <!-- Listado de cursos -->
                <h3>📚 Cursos Matriculados</h3>
                <?php foreach ($student_courses as $curso): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                            <div class="course-info">
                                <strong>Código:</strong> <?php echo htmlspecialchars($curso['codigo']); ?><br>
                                <strong>Programa:</strong> <?php echo htmlspecialchars($curso['programa_nombre']); ?><br>
                                <strong>Periodo:</strong> <?php echo htmlspecialchars($curso['periodo_lectivo']); ?> - <?php echo htmlspecialchars($curso['periodo_academico']); ?><br>
                                <strong>Docente:</strong> <?php echo htmlspecialchars($curso['docente_nombre']); ?>
                            </div>
                        </div>
                        
                        <div class="status-card">
                            <p><strong>Estado:</strong> Matriculado ✅</p>
                            <p><strong>Evaluaciones:</strong> Sistema en desarrollo</p>
                            <p><strong>Asistencias:</strong> 
                                <a href="attendance.php?course_id=<?php echo $curso['id']; ?><?php echo $search_dni ? '&dni=' . $search_dni : ''; ?>">
                                    Ver asistencias →
                                </a>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Información adicional -->
            <div class="alert alert-info">
                <h3>🚀 Próximamente en el Sistema de Reportes:</h3>
                <ul style="text-align: left; margin-top: 10px;">
                    <li>✨ Calificaciones por curso y evaluación</li>
                    <li>📊 Promedios ponderados automáticos</li>
                    <li>📈 Gráficos de rendimiento académico</li>
                    <li>📋 Reportes consolidados por periodo</li>
                    <li>🎯 Análisis de indicadores de logro</li>
                </ul>
            </div>
            
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
        
        // Mensaje de bienvenida
        console.log('🎓 Sistema de Reportes Académicos cargado correctamente');
    </script>
</body>
</html>