<?php
// Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debug: Mostrar información de la sesión (remover en producción)
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Información de sesión:\n";
    print_r($_SESSION);
    echo "</pre>";
}

// MODO DE TESTING RÁPIDO - REMOVER EN PRODUCCIÓN
if (isset($_GET['test']) && $_GET['test'] == '1') {
    $_SESSION['user_id'] = 2;
    $_SESSION['tipo_usuario'] = 'docente';
    $_SESSION['nombres'] = 'Docente';
    $_SESSION['apellidos'] = 'Temporal';
    $_SESSION['dni'] = '12345678';
    
    echo "<div style='padding: 15px; background: #fff3cd; color: #856404; margin: 20px; border-radius: 5px; border: 1px solid #ffeaa7;'>";
    echo "<strong><i class='fas fa-flask'></i> MODO TESTING ACTIVADO:</strong> Sesión temporal creada. ";
    echo "<a href='?' style='color: #856404; text-decoration: underline;'>Continuar sin parámetros de testing</a>";
    echo "</div>";
}

// Verificación básica de sesión
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Sesión - Sistema Académico</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .error-container { max-width: 600px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .error-icon { text-align: center; color: #dc3545; font-size: 4rem; margin-bottom: 20px; }
            h1 { color: #dc3545; text-align: center; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: all 0.3s; }
            .btn-primary { background: #007bff; color: white; }
            .btn-success { background: #28a745; color: white; }
            .btn-warning { background: #ffc107; color: #212529; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 10px rgba(0,0,0,0.2); }
            .options { text-align: center; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>Sesión No Encontrada</h1>
            <p><strong>No se detectó una sesión activa.</strong></p>
            
            <div class="options">
                <h3>Opciones para continuar:</h3>
                <a href="../login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
                <a href="?test=1" class="btn btn-success">
                    <i class="fas fa-flask"></i> Modo Testing
                </a>
                <a href="?debug=1" class="btn btn-warning">
                    <i class="fas fa-bug"></i> Ver Debug
                </a>
            </div>
            
            <?php if (isset($_GET['debug'])): ?>
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                    <h4><i class="fas fa-info-circle"></i> Información de Debugging:</h4>
                    <pre style="background: #e9ecef; padding: 15px; border-radius: 5px; overflow-x: auto;">
<?php 
echo "Sesión actual:\n";
print_r($_SESSION);
echo "\nVariables GET:\n";
print_r($_GET);
?>
                    </pre>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'michelle_arqos';
$username = 'michelle_arqos'; // Ajustar según tu configuración
$password = '$[sTJWL]CEkSIHMs';     // Ajustar según tu configuración

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$message = '';

// Función para completar datos de usuario desde la base de datos
function completeUserData($pdo, $user_id) {
    if (!isset($_SESSION['tipo_usuario']) || !isset($_SESSION['nombres'])) {
        try {
            $stmt = $pdo->prepare("SELECT tipo_usuario, nombres, apellidos, dni FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                $_SESSION['nombres'] = $user['nombres'];
                $_SESSION['apellidos'] = $user['apellidos'];
                $_SESSION['dni'] = $user['dni'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al obtener datos del usuario: " . $e->getMessage());
            return false;
        }
    }
    return true;
}

// Completar datos del usuario si es necesario
completeUserData($pdo, $user_id);

// Verificar permisos de usuario
$allowed_types = ['docente', 'super_admin'];
if (!isset($_SESSION['tipo_usuario']) || !in_array($_SESSION['tipo_usuario'], $allowed_types)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado - Sistema Académico</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .error-container { max-width: 600px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .error-icon { text-align: center; color: #dc3545; font-size: 4rem; margin-bottom: 20px; }
            h1 { color: #dc3545; text-align: center; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: all 0.3s; }
            .btn-primary { background: #007bff; color: white; }
            .btn-success { background: #28a745; color: white; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 10px rgba(0,0,0,0.2); }
            .user-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-ban"></i>
            </div>
            <h1><?php echo !isset($_SESSION['tipo_usuario']) ? 'Error de Permisos' : 'Acceso Denegado'; ?></h1>
            
            <?php if (isset($_SESSION['tipo_usuario'])): ?>
                <div class="user-info">
                    <strong>Usuario:</strong> <?php echo htmlspecialchars(($_SESSION['nombres'] ?? 'N/A') . ' ' . ($_SESSION['apellidos'] ?? '')); ?><br>
                    <strong>Tipo:</strong> <?php echo htmlspecialchars($_SESSION['tipo_usuario']); ?>
                </div>
                <p>Su tipo de usuario no tiene permisos para acceder a esta página.</p>
                <p><strong>Permisos requeridos:</strong> Docente o Super Administrador</p>
            <?php else: ?>
                <p><strong>No se pudo determinar su tipo de usuario.</strong></p>
                <p>Esto puede deberse a un problema en la configuración de la sesión.</p>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="../dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Dashboard
                </a>
                <a href="?test=1" class="btn btn-success">
                    <i class="fas fa-flask"></i> Modo Testing
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Función para obtener cursos del docente
function getCoursesByTeacher($pdo, $teacher_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre, codigo, periodo_lectivo, periodo_academico, estado 
            FROM unidades_didacticas 
            WHERE docente_id = ? AND estado = 'activo'
            ORDER BY periodo_lectivo DESC, nombre ASC
        ");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener curso por ID
function getCourseById($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ud.*, pe.nombre as programa_nombre, 
                   CONCAT(u.nombres, ' ', u.apellidos) as docente_nombre
            FROM unidades_didacticas ud
            JOIN programas_estudio pe ON ud.programa_id = pe.id
            JOIN usuarios u ON ud.docente_id = u.id
            WHERE ud.id = ?
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener estudiantes matriculados
function getEnrolledStudents($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.dni, u.nombres, u.apellidos, 
                   CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                   m.fecha_matricula, m.estado as estado_matricula
            FROM matriculas m
            JOIN usuarios u ON m.estudiante_id = u.id
            WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener sesiones del curso
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
        return [];
    }
}

// Función para obtener sesión por ID
function getSessionById($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, unidad_didactica_id, numero_sesion, titulo, fecha, descripcion, estado
            FROM sesiones
            WHERE id = ?
        ");
        $stmt->execute([$session_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener indicadores de logro
function getLearningIndicators($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, numero_indicador, nombre, descripcion, peso
            FROM indicadores_logro
            WHERE unidad_didactica_id = ?
            ORDER BY numero_indicador ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener un indicador de logro por ID
function getLearningIndicatorById($pdo, $indicator_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, unidad_didactica_id, numero_indicador, nombre, descripcion, peso
            FROM indicadores_logro
            WHERE id = ?
        ");
        $stmt->execute([$indicator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener indicadores de evaluación por sesión
function getEvaluationIndicators($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ie.id, ie.nombre, ie.descripcion, ie.peso,
                   il.nombre as indicador_logro_nombre, il.numero_indicador,
                   ie.indicador_logro_id
            FROM indicadores_evaluacion ie
            JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
            WHERE ie.sesion_id = ?
            ORDER BY il.numero_indicador ASC
        ");
        $stmt->execute([$session_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener un indicador de evaluación por ID
function getEvaluationIndicatorById($pdo, $indicator_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ie.id, ie.sesion_id, ie.indicador_logro_id, ie.nombre, ie.descripcion, ie.peso,
                   il.nombre as indicador_logro_nombre, il.numero_indicador,
                   s.titulo as sesion_titulo, s.numero_sesion
            FROM indicadores_evaluacion ie
            JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
            JOIN sesiones s ON ie.sesion_id = s.id
            WHERE ie.id = ?
        ");
        $stmt->execute([$indicator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener calificaciones por indicador
function getGradesByIndicator($pdo, $indicator_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT estudiante_id, calificacion
            FROM evaluaciones_sesion
            WHERE indicador_evaluacion_id = ?
        ");
        $stmt->execute([$indicator_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para verificar si un indicador de logro tiene evaluaciones registradas
function hasEvaluationRecords($pdo, $learning_indicator_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(es.id) as total
            FROM evaluaciones_sesion es
            JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
            WHERE ie.indicador_logro_id = ?
        ");
        $stmt->execute([$learning_indicator_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    } catch (PDOException $e) {
        return true; // Por seguridad, asumimos que tiene registros
    }
}

// Función para verificar si un indicador de evaluación tiene calificaciones registradas
function hasGradeRecords($pdo, $evaluation_indicator_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(id) as total
            FROM evaluaciones_sesion
            WHERE indicador_evaluacion_id = ?
        ");
        $stmt->execute([$evaluation_indicator_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    } catch (PDOException $e) {
        return true; // Por seguridad, asumimos que tiene registros
    }
}

// Función para crear indicador de logro
function createLearningIndicator($pdo, $course_id, $numero, $nombre, $descripcion, $peso) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO indicadores_logro (unidad_didactica_id, numero_indicador, nombre, descripcion, peso)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$course_id, $numero, $nombre, $descripcion, $peso]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para actualizar indicador de logro
function updateLearningIndicator($pdo, $indicator_id, $numero, $nombre, $descripcion, $peso) {
    try {
        $stmt = $pdo->prepare("
            UPDATE indicadores_logro 
            SET numero_indicador = ?, nombre = ?, descripcion = ?, peso = ?
            WHERE id = ?
        ");
        return $stmt->execute([$numero, $nombre, $descripcion, $peso, $indicator_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para eliminar indicador de logro
function deleteLearningIndicator($pdo, $indicator_id) {
    try {
        // Primero eliminar los indicadores de evaluación relacionados
        $stmt = $pdo->prepare("DELETE FROM indicadores_evaluacion WHERE indicador_logro_id = ?");
        $stmt->execute([$indicator_id]);
        
        // Luego eliminar el indicador de logro
        $stmt = $pdo->prepare("DELETE FROM indicadores_logro WHERE id = ?");
        return $stmt->execute([$indicator_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para crear indicador de evaluación
function createEvaluationIndicator($pdo, $session_id, $learning_indicator_id, $nombre, $descripcion, $peso) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO indicadores_evaluacion (sesion_id, indicador_logro_id, nombre, descripcion, peso)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$session_id, $learning_indicator_id, $nombre, $descripcion, $peso]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para actualizar indicador de evaluación
function updateEvaluationIndicator($pdo, $indicator_id, $learning_indicator_id, $nombre, $descripcion, $peso) {
    try {
        $stmt = $pdo->prepare("
            UPDATE indicadores_evaluacion 
            SET indicador_logro_id = ?, nombre = ?, descripcion = ?, peso = ?
            WHERE id = ?
        ");
        return $stmt->execute([$learning_indicator_id, $nombre, $descripcion, $peso, $indicator_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para eliminar indicador de evaluación
function deleteEvaluationIndicator($pdo, $indicator_id) {
    try {
        // Primero eliminar las evaluaciones relacionadas
        $stmt = $pdo->prepare("DELETE FROM evaluaciones_sesion WHERE indicador_evaluacion_id = ?");
        $stmt->execute([$indicator_id]);
        
        // Luego eliminar el indicador de evaluación
        $stmt = $pdo->prepare("DELETE FROM indicadores_evaluacion WHERE id = ?");
        return $stmt->execute([$indicator_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para registrar evaluación
function recordEvaluation($pdo, $indicator_id, $student_id, $grade) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO evaluaciones_sesion (indicador_evaluacion_id, estudiante_id, calificacion)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            calificacion = VALUES(calificacion),
            fecha_evaluacion = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$indicator_id, $student_id, $grade]);
    } catch (PDOException $e) {
        return false;
    }
}

// Obtener cursos del docente
$myCourses = getCoursesByTeacher($pdo, $user_id);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Debug: mostrar la acción recibida
        error_log("Acción recibida: " . $_POST['action']);
        error_log("POST data: " . print_r($_POST, true));
        
        switch ($_POST['action']) {
            case 'create_learning_indicator':
                $unidad_didactica_id = intval($_POST['unidad_didactica_id']);
                $numero_indicador = intval($_POST['numero_indicador']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $peso = floatval($_POST['peso']);
                
                if ($nombre && $numero_indicador > 0 && $peso >= 0 && $peso <= 100) {
                    if (createLearningIndicator($pdo, $unidad_didactica_id, $numero_indicador, $nombre, $descripcion, $peso)) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de logro creado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al crear el indicador de logro. Verifique que el número de indicador no se repita.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Datos inválidos. Verifique todos los campos.</div>';
                }
                break;
                
            case 'update_learning_indicator':
                $indicator_id = intval($_POST['indicator_id']);
                $numero_indicador = intval($_POST['numero_indicador']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $peso = floatval($_POST['peso']);
                
                error_log("Actualizando indicador de logro ID: " . $indicator_id);
                error_log("Datos: numero=$numero_indicador, nombre=$nombre, peso=$peso");
                
                if ($indicator_id && $nombre && $numero_indicador > 0 && $peso >= 0 && $peso <= 100) {
                    if (updateLearningIndicator($pdo, $indicator_id, $numero_indicador, $nombre, $descripcion, $peso)) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de logro actualizado exitosamente.</div>';
                        error_log("Indicador actualizado exitosamente");
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al actualizar el indicador de logro.</div>';
                        error_log("Error al actualizar indicador");
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Datos inválidos. Verifique todos los campos.</div>';
                    error_log("Datos inválidos para actualización");
                }
                break;
                
            case 'delete_learning_indicator':
                $indicator_id = intval($_POST['indicator_id']);
                error_log("Intentando eliminar indicador de logro ID: " . $indicator_id);
                
                if ($indicator_id) {
                    if (hasEvaluationRecords($pdo, $indicator_id)) {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> No se puede eliminar el indicador porque tiene evaluaciones registradas.</div>';
                        error_log("No se puede eliminar - tiene evaluaciones registradas");
                    } else {
                        if (deleteLearningIndicator($pdo, $indicator_id)) {
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de logro eliminado exitosamente.</div>';
                            error_log("Indicador eliminado exitosamente");
                        } else {
                            $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al eliminar el indicador de logro.</div>';
                            error_log("Error al eliminar indicador");
                        }
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ID de indicador inválido.</div>';
                    error_log("ID de indicador inválido");
                }
                break;
                
            case 'create_evaluation_indicator':
                $sesion_id = intval($_POST['sesion_id']);
                $indicador_logro_id = intval($_POST['indicador_logro_id']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $peso = floatval($_POST['peso']);
                
                if ($nombre && $sesion_id > 0 && $indicador_logro_id > 0 && $peso >= 0 && $peso <= 100) {
                    if (createEvaluationIndicator($pdo, $sesion_id, $indicador_logro_id, $nombre, $descripcion, $peso)) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de evaluación creado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al crear el indicador de evaluación.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Datos inválidos. Verifique todos los campos.</div>';
                }
                break;
                
            case 'update_evaluation_indicator':
                $indicator_id = intval($_POST['indicator_id']);
                $indicador_logro_id = intval($_POST['indicador_logro_id']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $peso = floatval($_POST['peso']);
                
                error_log("Actualizando indicador de evaluación ID: " . $indicator_id);
                error_log("Datos: indicador_logro_id=$indicador_logro_id, nombre=$nombre, peso=$peso");
                
                if ($indicator_id && $nombre && $indicador_logro_id > 0 && $peso >= 0 && $peso <= 100) {
                    if (updateEvaluationIndicator($pdo, $indicator_id, $indicador_logro_id, $nombre, $descripcion, $peso)) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de evaluación actualizado exitosamente.</div>';
                        error_log("Indicador de evaluación actualizado exitosamente");
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al actualizar el indicador de evaluación.</div>';
                        error_log("Error al actualizar indicador de evaluación");
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Datos inválidos. Verifique todos los campos.</div>';
                    error_log("Datos inválidos para actualización de evaluación");
                }
                break;
                
            case 'delete_evaluation_indicator':
                $indicator_id = intval($_POST['indicator_id']);
                error_log("Intentando eliminar indicador de evaluación ID: " . $indicator_id);
                
                if ($indicator_id) {
                    if (hasGradeRecords($pdo, $indicator_id)) {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> No se puede eliminar el indicador porque tiene calificaciones registradas.</div>';
                        error_log("No se puede eliminar - tiene calificaciones registradas");
                    } else {
                        if (deleteEvaluationIndicator($pdo, $indicator_id)) {
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Indicador de evaluación eliminado exitosamente.</div>';
                            error_log("Indicador de evaluación eliminado exitosamente");
                        } else {
                            $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al eliminar el indicador de evaluación.</div>';
                            error_log("Error al eliminar indicador de evaluación");
                        }
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ID de indicador inválido.</div>';
                    error_log("ID de indicador de evaluación inválido");
                }
                break;
                
            case 'record_evaluations':
                $calificaciones = $_POST['calificaciones'] ?? [];
                $success = true;
                $evaluationsRecorded = 0;
                
                foreach ($calificaciones as $indicador_id => $estudiantes) {
                    foreach ($estudiantes as $estudiante_id => $calificacion) {
                        if (!empty($calificacion) && is_numeric($calificacion)) {
                            $calificacion = floatval($calificacion);
                            if ($calificacion >= 0 && $calificacion <= 20) {
                                if (recordEvaluation($pdo, intval($indicador_id), intval($estudiante_id), $calificacion)) {
                                    $evaluationsRecorded++;
                                } else {
                                    $success = false;
                                }
                            }
                        }
                    }
                }
                
                if ($success && $evaluationsRecorded > 0) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Se registraron ' . $evaluationsRecorded . ' evaluaciones exitosamente.</div>';
                } elseif ($evaluationsRecorded > 0) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Se registraron ' . $evaluationsRecorded . ' evaluaciones, pero hubo algunos errores.</div>';
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> No se registraron evaluaciones. Verifique los datos ingresados.</div>';
                }
                break;
        }
    }
}

// Variables para mostrar contenido
$selectedCourse = null;
$sessions = [];
$learningIndicators = [];
$selectedSession = null;
$evaluationIndicators = [];
$enrolledStudents = [];
$selectedGradesSession = null;
$gradesEvaluationIndicators = [];
$existingGrades = [];

// Cargar datos si se seleccionó un curso
if (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    $selectedCourse = getCourseById($pdo, $course_id);
    
    if ($selectedCourse) {
        $sessions = getSessionsByCourse($pdo, $course_id);
        $learningIndicators = getLearningIndicators($pdo, $course_id);
        $enrolledStudents = getEnrolledStudents($pdo, $course_id);
        
        if (isset($_GET['session_id']) && is_numeric($_GET['session_id'])) {
            $session_id = intval($_GET['session_id']);
            $evaluationIndicators = getEvaluationIndicators($pdo, $session_id);
        }
        
        // Para el registro de calificaciones
        if (isset($_GET['grades_session_id']) && is_numeric($_GET['grades_session_id'])) {
            $grades_session_id = intval($_GET['grades_session_id']);
            $selectedGradesSession = getSessionById($pdo, $grades_session_id);
            $gradesEvaluationIndicators = getEvaluationIndicators($pdo, $grades_session_id);
            
            // Obtener calificaciones existentes
            if (!empty($gradesEvaluationIndicators)) {
                foreach ($gradesEvaluationIndicators as $indicator) {
                    $existingGrades[$indicator['id']] = getGradesByIndicator($pdo, $indicator['id']);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Evaluaciones - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0f206eff;
            --primary-dark: #0c1dbbff;
            --secondary-color: #0790ffff;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-hover: 0 4px 20px rgba(0,0,0,0.15);
            --border-radius: 10px;
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1200px;
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
        }
        
        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
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
            margin-top: 25px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card h4 {
            color: #495057;
            margin-bottom: 15px;
            margin-top: 20px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            height: 100px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .evaluation-table th,
        .evaluation-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
            word-wrap: break-word;
        }
        
        .evaluation-table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 0.9rem;
        }
        
        .evaluation-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .evaluation-table tr:hover {
            background-color: #e3f2fd;
        }
        
        .evaluation-table .student-name {
            text-align: left;
            font-weight: 500;
            width: 200px;
            min-width: 200px;
        }
        
        .evaluation-table .indicator-header {
            min-width: 120px;
            max-width: 150px;
            font-size: 12px;
        }
        
        .grade-input {
            width: 70px;
            padding: 8px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .grade-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .grade-input.has-value {
            background-color: #e8f5e8;
            border-color: var(--success-color);
            color: var(--success-color);
            font-weight: 600;
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
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
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
        
        .indicator-list {
            list-style: none;
            padding: 0;
        }
        
        .indicator-item {
            padding: 20px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: var(--transition);
            position: relative;
        }
        
        .indicator-item:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
            transform: translateX(5px);
        }
        
        .indicator-number {
            font-weight: bold;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .indicator-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 5px;
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
            font-size: 0.95rem;
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
            transform: translateY(-2px);
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
        
        .grades-session-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border: 2px solid #2196f3;
        }
        
        .grades-session-info h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            font-weight: 600;
        }
        
        .no-indicators-message {
            text-align: center;
            padding: 50px;
            color: #666;
            background: linear-gradient(135deg, #f9f9f9 0%, #ffffff 100%);
            border-radius: var(--border-radius);
            border: 2px dashed var(--border-color);
        }
        
        .no-indicators-message i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: var(--border-radius);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border: none;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { 
                opacity: 0; 
                transform: translateY(-50px) scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close:hover {
            color: var(--danger-color);
            transform: scale(1.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
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
            
            .header .user-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .evaluation-table {
                font-size: 12px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                flex: none;
                text-align: left;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .grade-input {
                width: 50px;
                padding: 6px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 20px;
                width: 95%;
            }
            
            .indicator-actions {
                position: static;
                margin-top: 15px;
                justify-content: flex-end;
            }
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: #212529;
        }
        
        .badge-info {
            background-color: var(--info-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-chart-line"></i> Gestión de Evaluaciones</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(($_SESSION['nombres'] ?? 'Usuario') . ' ' . ($_SESSION['apellidos'] ?? 'Temporal')); ?></span>
                <a href="../dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <?php echo $message; ?>
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
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="course_id"><i class="fas fa-graduation-cap"></i> Unidad Didáctica:</label>
                        <select name="course_id" id="course_id" onchange="this.form.submit()" required>
                            <option value="">Seleccione una unidad didáctica...</option>
                            <?php foreach ($myCourses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombre']) . ' - ' . $c['periodo_lectivo'] . ' (' . $c['periodo_academico'] . ')'; ?>
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
                    <h4><?php echo count($sessions); ?></h4>
                    <p><i class="fas fa-calendar-alt"></i> Sesiones</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo count($learningIndicators); ?></h4>
                    <p><i class="fas fa-target"></i> Indicadores de Logro</p>
                </div>
                <div class="stat-card">
                    <h4><?php echo count($enrolledStudents); ?></h4>
                    <p><i class="fas fa-users"></i> Estudiantes</p>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($selectedCourse['nombre']); ?></h2>
                <p><strong><i class="fas fa-code"></i> Código:</strong> <?php echo htmlspecialchars($selectedCourse['codigo']); ?> | 
                   <strong><i class="fas fa-calendar"></i> Período:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo']); ?> | 
                   <strong><i class="fas fa-layer-group"></i> Período Académico:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_academico']); ?></p>
                
                <div class="tabs">
                    <button class="tab <?php echo !isset($_GET['grades_session_id']) && !isset($_GET['session_id']) ? 'active' : ''; ?>" onclick="showTab('indicators')">
                        <i class="fas fa-target"></i> Indicadores de Logro
                    </button>
                    <button class="tab <?php echo isset($_GET['session_id']) ? 'active' : ''; ?>" onclick="showTab('sessions')">
                        <i class="fas fa-tasks"></i> Evaluaciones por Sesión
                    </button>
                    <button class="tab <?php echo isset($_GET['grades_session_id']) ? 'active' : ''; ?>" onclick="showTab('grades')">
                        <i class="fas fa-chart-bar"></i> Registrar Calificaciones
                    </button>
                </div>
                
                <!-- Tab: Indicadores de Logro -->
                <div id="indicators" class="tab-content <?php echo !isset($_GET['grades_session_id']) && !isset($_GET['session_id']) ? 'active' : ''; ?>">
                    <h3><i class="fas fa-plus-circle"></i> Crear Indicador de Logro</h3>
                    <form method="POST" id="learning-indicator-form">
                        <input type="hidden" name="action" value="create_learning_indicator">
                        <input type="hidden" name="unidad_didactica_id" value="<?php echo $selectedCourse['id']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="numero_indicador"><i class="fas fa-sort-numeric-up"></i> Número de Indicador:</label>
                                <input type="number" name="numero_indicador" id="numero_indicador" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="peso"><i class="fas fa-percentage"></i> Peso (%):</label>
                                <input type="number" name="peso" id="peso" min="0" max="100" step="0.01" value="100" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre"><i class="fas fa-tag"></i> Nombre del Indicador:</label>
                            <input type="text" name="nombre" id="nombre" required maxlength="200" placeholder="Nombre descriptivo del indicador">
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion"><i class="fas fa-align-left"></i> Descripción:</label>
                            <textarea name="descripcion" id="descripcion" placeholder="Descripción detallada del indicador (opcional)"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Crear Indicador de Logro
                        </button>
                    </form>
                    
                    <h3><i class="fas fa-list"></i> Indicadores de Logro Existentes</h3>
                    <?php if (empty($learningIndicators)): ?>
                        <div class="no-indicators-message">
                            <i class="fas fa-target"></i>
                            <h4>No hay indicadores de logro</h4>
                            <p>Crear el primer indicador de logro para esta unidad didáctica.</p>
                        </div>
                    <?php else: ?>
                        <ul class="indicator-list">
                            <?php foreach ($learningIndicators as $indicator): ?>
                                <li class="indicator-item">
                                    <div class="indicator-actions">
                                        <button class="btn btn-warning btn-sm" onclick="editLearningIndicator(<?php echo $indicator['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteLearningIndicator(<?php echo $indicator['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <span class="indicator-number">Indicador <?php echo $indicator['numero_indicador']; ?>:</span>
                                    <strong><?php echo htmlspecialchars($indicator['nombre']); ?></strong>
                                    <?php if ($indicator['descripcion']): ?>
                                        <br><small><i class="fas fa-info-circle"></i> <strong>Descripción:</strong> <?php echo htmlspecialchars($indicator['descripcion']); ?></small>
                                    <?php endif; ?>
                                    <br><small><i class="fas fa-weight"></i> <strong>Peso:</strong> <span class="badge badge-info"><?php echo $indicator['peso']; ?>%</span></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- Tab: Evaluaciones por Sesión -->
                <div id="sessions" class="tab-content <?php echo isset($_GET['session_id']) ? 'active' : ''; ?>">
                    <h3><i class="fas fa-cog"></i> Configurar Evaluaciones por Sesión</h3>
                    
                    <?php if (empty($sessions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay sesiones creadas para esta unidad didáctica.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="session_select"><i class="fas fa-calendar-check"></i> Seleccionar Sesión:</label>
                            <select id="session_select" onchange="selectSession(this.value)">
                                <option value="">Seleccione una sesión...</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['session_id']) && $_GET['session_id'] == $s['id']) ? 'selected' : ''; ?>>
                                        Sesión <?php echo $s['numero_sesion']; ?> - <?php echo htmlspecialchars($s['titulo']); ?> (<?php echo date('d/m/Y', strtotime($s['fecha'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (isset($_GET['session_id'])): ?>
                            <?php if (empty($learningIndicators)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Debe crear primero los indicadores de logro en la pestaña correspondiente antes de configurar evaluaciones por sesión.
                                </div>
                            <?php else: ?>
                                <h4><i class="fas fa-plus"></i> Crear Indicador de Evaluación para la Sesión</h4>
                                <form method="POST" id="evaluation-indicator-form">
                                    <input type="hidden" name="action" value="create_evaluation_indicator">
                                    <input type="hidden" name="sesion_id" value="<?php echo $_GET['session_id']; ?>">
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="indicador_logro_id"><i class="fas fa-target"></i> Indicador de Logro:</label>
                                            <select name="indicador_logro_id" id="indicador_logro_id" required>
                                                <option value="">Seleccione un indicador...</option>
                                                <?php foreach ($learningIndicators as $indicator): ?>
                                                    <option value="<?php echo $indicator['id']; ?>">
                                                        Indicador <?php echo $indicator['numero_indicador']; ?> - <?php echo htmlspecialchars($indicator['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="peso_eval"><i class="fas fa-percentage"></i> Peso (%):</label>
                                            <input type="number" name="peso" id="peso_eval" min="0" max="100" step="0.01" value="100" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nombre_eval"><i class="fas fa-clipboard-check"></i> Nombre de la Evaluación:</label>
                                        <input type="text" name="nombre" id="nombre_eval" required maxlength="200" placeholder="Ej: Examen parcial, Práctica de laboratorio, etc.">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="descripcion_eval"><i class="fas fa-align-left"></i> Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_eval" placeholder="Descripción de la evaluación (opcional)"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Crear Indicador de Evaluación
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <h4><i class="fas fa-list-check"></i> Indicadores de Evaluación de la Sesión</h4>
                            <?php if (empty($evaluationIndicators)): ?>
                                <div class="no-indicators-message">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No hay indicadores de evaluación</h4>
                                    <p>Crear el primer indicador de evaluación para esta sesión.</p>
                                </div>
                            <?php else: ?>
                                <ul class="indicator-list">
                                    <?php foreach ($evaluationIndicators as $evalIndicator): ?>
                                        <li class="indicator-item">
                                            <div class="indicator-actions">
                                                <button class="btn btn-warning btn-sm" onclick="editEvaluationIndicator(<?php echo $evalIndicator['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteEvaluationIndicator(<?php echo $evalIndicator['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <strong><?php echo htmlspecialchars($evalIndicator['nombre']); ?></strong>
                                            <br><small><i class="fas fa-link"></i> <strong>Basado en:</strong> Indicador <?php echo $evalIndicator['numero_indicador']; ?> - <?php echo htmlspecialchars($evalIndicator['indicador_logro_nombre']); ?></small>
                                            <?php if ($evalIndicator['descripcion']): ?>
                                                <br><small><i class="fas fa-info-circle"></i> <strong>Descripción:</strong> <?php echo htmlspecialchars($evalIndicator['descripcion']); ?></small>
                                            <?php endif; ?>
                                            <br><small><i class="fas fa-weight"></i> <strong>Peso:</strong> <span class="badge badge-info"><?php echo $evalIndicator['peso']; ?>%</span></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Tab: Registrar Calificaciones -->
                <div id="grades" class="tab-content <?php echo isset($_GET['grades_session_id']) ? 'active' : ''; ?>">
                    <h3><i class="fas fa-chart-line"></i> Registrar Calificaciones</h3>
                    
                    <?php if (empty($sessions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay sesiones creadas para registrar calificaciones.
                        </div>
                    <?php elseif (empty($enrolledStudents)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay estudiantes matriculados en esta unidad didáctica.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="session_select_grades"><i class="fas fa-calendar-alt"></i> Seleccionar Sesión:</label>
                            <select id="session_select_grades" onchange="selectSessionForGrades(this.value)">
                                <option value="">Seleccione una sesión...</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['grades_session_id']) && $_GET['grades_session_id'] == $s['id']) ? 'selected' : ''; ?>>
                                        Sesión <?php echo $s['numero_sesion']; ?> - <?php echo htmlspecialchars($s['titulo']); ?> (<?php echo date('d/m/Y', strtotime($s['fecha'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($selectedGradesSession && !empty($gradesEvaluationIndicators)): ?>
                            <div class="grades-session-info">
                                <h4><i class="fas fa-calendar-day"></i> Sesión <?php echo $selectedGradesSession['numero_sesion']; ?>: <?php echo htmlspecialchars($selectedGradesSession['titulo']); ?></h4>
                                <p><strong><i class="fas fa-calendar"></i> Fecha:</strong> <?php echo date('d/m/Y', strtotime($selectedGradesSession['fecha'])); ?></p>
                                <?php if ($selectedGradesSession['descripcion']): ?>
                                    <p><strong><i class="fas fa-info-circle"></i> Descripción:</strong> <?php echo htmlspecialchars($selectedGradesSession['descripcion']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" id="grades-form">
                                <input type="hidden" name="action" value="record_evaluations">
                                
                                <div class="table-container">
                                    <table class="evaluation-table">
                                        <thead>
                                            <tr>
                                                <th class="student-name"><i class="fas fa-user-graduate"></i> Estudiante</th>
                                                <?php foreach ($gradesEvaluationIndicators as $indicator): ?>
                                                    <th class="indicator-header">
                                                        <i class="fas fa-clipboard-check"></i><br>
                                                        <?php echo htmlspecialchars($indicator['nombre']); ?>
                                                        <br><small class="badge badge-info"><?php echo $indicator['peso']; ?>%</small>
                                                        <br><small class="badge badge-success">IL<?php echo $indicator['numero_indicador']; ?></small>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrolledStudents as $student): ?>
                                                <tr>
                                                    <td class="student-name">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($student['nombre_completo']); ?>
                                                        <br><small><i class="fas fa-id-card"></i> DNI: <?php echo $student['dni']; ?></small>
                                                    </td>
                                                    <?php foreach ($gradesEvaluationIndicators as $indicator): ?>
                                                        <?php 
                                                        $existingGrade = '';
                                                        if (isset($existingGrades[$indicator['id']])) {
                                                            foreach ($existingGrades[$indicator['id']] as $grade) {
                                                                if ($grade['estudiante_id'] == $student['id']) {
                                                                    $existingGrade = $grade['calificacion'];
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                        <td>
                                                            <input type="number" 
                                                                   name="calificaciones[<?php echo $indicator['id']; ?>][<?php echo $student['id']; ?>]" 
                                                                   class="grade-input <?php echo $existingGrade ? 'has-value' : ''; ?>" 
                                                                   min="0" 
                                                                   max="20" 
                                                                   step="0.01" 
                                                                   value="<?php echo $existingGrade; ?>"
                                                                   placeholder="0-20"
                                                                   title="Calificación de 0 a 20">
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div style="margin-top: 25px; text-align: center;">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Guardar Calificaciones
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearAllGrades()">
                                        <i class="fas fa-eraser"></i> Limpiar Todo
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="validateAllGrades()">
                                        <i class="fas fa-check-circle"></i> Validar Datos
                                    </button>
                                </div>
                                
                                <div class="alert alert-info" style="margin-top: 25px;">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Instrucciones:</strong>
                                        <ul style="margin: 10px 0; padding-left: 20px;">
                                            <li>Las calificaciones deben estar entre 0 y 20 (escala vigesimal).</li>
                                            <li>Use decimales con hasta 2 lugares si es necesario (ejemplo: 15.75).</li>
                                            <li>Los campos vacíos no se guardarán.</li>
                                            <li>Puede modificar calificaciones existentes (se actualizarán automáticamente).</li>
                                            <li>IL = Indicador de Logro asociado a cada evaluación.</li>
                                        </ul>
                                    </div>
                                </div>
                            </form>
                            
                        <?php elseif ($selectedGradesSession && empty($gradesEvaluationIndicators)): ?>
                            <div class="no-indicators-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h4>No hay indicadores de evaluación para esta sesión</h4>
                                <p>Debe crear indicadores de evaluación en la pestaña "Evaluaciones por Sesión" antes de poder registrar calificaciones.</p>
                                <button class="btn btn-primary" onclick="showTab('sessions')">
                                    <i class="fas fa-arrow-right"></i> Ir a Evaluaciones por Sesión
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para editar indicador de logro -->
    <div id="editLearningModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Indicador de Logro</h3>
                <span class="close" onclick="closeModal('editLearningModal')">&times;</span>
            </div>
            <form id="editLearningForm" method="POST">
                <input type="hidden" name="action" value="update_learning_indicator">
                <input type="hidden" name="indicator_id" id="edit_learning_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_numero_indicador"><i class="fas fa-sort-numeric-up"></i> Número de Indicador:</label>
                        <input type="number" name="numero_indicador" id="edit_numero_indicador" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_peso"><i class="fas fa-percentage"></i> Peso (%):</label>
                        <input type="number" name="peso" id="edit_peso" min="0" max="100" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_nombre"><i class="fas fa-tag"></i> Nombre del Indicador:</label>
                    <input type="text" name="nombre" id="edit_nombre" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label for="edit_descripcion"><i class="fas fa-align-left"></i> Descripción:</label>
                    <textarea name="descripcion" id="edit_descripcion"></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editLearningModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para editar indicador de evaluación -->
    <div id="editEvaluationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Indicador de Evaluación</h3>
                <span class="close" onclick="closeModal('editEvaluationModal')">&times;</span>
            </div>
            <form id="editEvaluationForm" method="POST">
                <input type="hidden" name="action" value="update_evaluation_indicator">
                <input type="hidden" name="indicator_id" id="edit_evaluation_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_indicador_logro_id"><i class="fas fa-target"></i> Indicador de Logro:</label>
                        <select name="indicador_logro_id" id="edit_indicador_logro_id" required>
                            <?php foreach ($learningIndicators as $indicator): ?>
                                <option value="<?php echo $indicator['id']; ?>">
                                    Indicador <?php echo $indicator['numero_indicador']; ?> - <?php echo htmlspecialchars($indicator['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_peso_eval"><i class="fas fa-percentage"></i> Peso (%):</label>
                        <input type="number" name="peso" id="edit_peso_eval" min="0" max="100" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_nombre_eval"><i class="fas fa-clipboard-check"></i> Nombre de la Evaluación:</label>
                    <input type="text" name="nombre" id="edit_nombre_eval" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label for="edit_descripcion_eval"><i class="fas fa-align-left"></i> Descripción:</label>
                    <textarea name="descripcion" id="edit_descripcion_eval"></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editEvaluationModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info">
            <i class="fas fa-bug"></i>
            <strong>Modo Debug Activado:</strong> Revise la consola del navegador (F12) para ver los logs de debugging.
            Los logs del servidor se encuentran en el archivo error.log de PHP.
        </div>
    <?php endif; ?>
    
    <script>
        // Variables globales para almacenar datos de indicadores
        const learningIndicators = <?php echo json_encode($learningIndicators); ?>;
        const evaluationIndicators = <?php echo json_encode($evaluationIndicators); ?>;
        
        // Debug: mostrar datos cargados
        console.log('Learning indicators loaded:', learningIndicators);
        console.log('Evaluation indicators loaded:', evaluationIndicators);
        
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            
            // Find and activate the clicked tab
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(tab => {
                if (tab.textContent.trim().includes('Indicadores de Logro') && tabName === 'indicators') {
                    tab.classList.add('active');
                } else if (tab.textContent.trim().includes('Evaluaciones por Sesión') && tabName === 'sessions') {
                    tab.classList.add('active');
                } else if (tab.textContent.trim().includes('Registrar Calificaciones') && tabName === 'grades') {
                    tab.classList.add('active');
                }
            });
            
            // Clean URL parameters when switching tabs
            const url = new URL(window.location);
            if (tabName !== 'grades') {
                url.searchParams.delete('grades_session_id');
            }
            if (tabName !== 'sessions') {
                url.searchParams.delete('session_id');
            }
            
            // Update URL without page reload
            window.history.replaceState({}, '', url);
        }
        
        function selectSession(sessionId) {
            if (sessionId) {
                const url = new URL(window.location);
                url.searchParams.set('session_id', sessionId);
                url.searchParams.delete('grades_session_id');
                window.location = url;
            }
        }
        
        function selectSessionForGrades(sessionId) {
            if (sessionId) {
                const url = new URL(window.location);
                url.searchParams.set('grades_session_id', sessionId);
                url.searchParams.delete('session_id');
                window.location = url;
            }
        }
        
        function clearAllGrades() {
            if (confirm('¿Está seguro de que quiere limpiar todas las calificaciones del formulario?')) {
                const gradeInputs = document.querySelectorAll('.grade-input');
                gradeInputs.forEach(input => {
                    input.value = '';
                    input.classList.remove('has-value');
                    input.style.borderColor = '';
                    input.style.backgroundColor = '';
                });
                showSuccessMessage('Todas las calificaciones han sido limpiadas.');
            }
        }
        
        function validateAllGrades() {
            const gradeInputs = document.querySelectorAll('.grade-input');
            let validCount = 0;
            let invalidCount = 0;
            let emptyCount = 0;
            
            gradeInputs.forEach(input => {
                const value = input.value.trim();
                if (value === '') {
                    emptyCount++;
                } else {
                    const numValue = parseFloat(value);
                    if (isNaN(numValue) || numValue < 0 || numValue > 20) {
                        invalidCount++;
                        input.style.borderColor = '#dc3545';
                        input.style.backgroundColor = '#f8d7da';
                    } else {
                        validCount++;
                        input.style.borderColor = '#28a745';
                        input.style.backgroundColor = '#d4edda';
                    }
                }
            });
            
            let message = `Validación completada:\n`;
            message += `✓ Calificaciones válidas: ${validCount}\n`;
            message += `✗ Calificaciones inválidas: ${invalidCount}\n`;
            message += `○ Campos vacíos: ${emptyCount}`;
            
            alert(message);
        }
        
        // Funciones para modales
        function showModal(modalId) {
            console.log('Mostrando modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                console.log('Modal mostrado exitosamente');
            } else {
                console.error('Modal no encontrado:', modalId);
                alert('Error: No se pudo abrir el formulario de edición');
            }
        }
        
        function closeModal(modalId) {
            console.log('Cerrando modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Funciones para editar indicadores de logro
        function editLearningIndicator(indicatorId) {
            console.log('Editing learning indicator:', indicatorId);
            console.log('Available indicators:', learningIndicators);
            
            const indicator = learningIndicators.find(i => i.id == indicatorId);
            if (indicator) {
                console.log('Found indicator:', indicator);
                document.getElementById('edit_learning_id').value = indicator.id;
                document.getElementById('edit_numero_indicador').value = indicator.numero_indicador;
                document.getElementById('edit_nombre').value = indicator.nombre;
                document.getElementById('edit_descripcion').value = indicator.descripcion || '';
                document.getElementById('edit_peso').value = indicator.peso;
                showModal('editLearningModal');
            } else {
                console.error('Indicator not found:', indicatorId);
                alert('No se encontró el indicador de logro');
            }
        }
        
        function deleteLearningIndicator(indicatorId) {
            console.log('Deleting learning indicator:', indicatorId);
            if (confirm('¿Está seguro de que quiere eliminar este indicador de logro?\n\nEsta acción también eliminará todos los indicadores de evaluación relacionados.')) {
                // Crear y enviar formulario
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_learning_indicator';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'indicator_id';
                idInput.value = indicatorId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                
                console.log('Submitting delete form:', form);
                form.submit();
            }
        }
        
        // Funciones para editar indicadores de evaluación
        function editEvaluationIndicator(indicatorId) {
            console.log('Editing evaluation indicator:', indicatorId);
            console.log('Available evaluation indicators:', evaluationIndicators);
            
            const indicator = evaluationIndicators.find(i => i.id == indicatorId);
            if (indicator) {
                console.log('Found evaluation indicator:', indicator);
                document.getElementById('edit_evaluation_id').value = indicator.id;
                document.getElementById('edit_indicador_logro_id').value = indicator.indicador_logro_id;
                document.getElementById('edit_nombre_eval').value = indicator.nombre;
                document.getElementById('edit_descripcion_eval').value = indicator.descripcion || '';
                document.getElementById('edit_peso_eval').value = indicator.peso;
                showModal('editEvaluationModal');
            } else {
                console.error('Evaluation indicator not found:', indicatorId);
                alert('No se encontró el indicador de evaluación');
            }
        }
        
        function deleteEvaluationIndicator(indicatorId) {
            console.log('Deleting evaluation indicator:', indicatorId);
            if (confirm('¿Está seguro de que quiere eliminar este indicador de evaluación?\n\nEsta acción también eliminará todas las calificaciones registradas para este indicador.')) {
                // Crear y enviar formulario
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_evaluation_indicator';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'indicator_id';
                idInput.value = indicatorId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                
                console.log('Submitting delete evaluation form:', form);
                form.submit();
            }
        }
        
        // Validar calificaciones en tiempo real
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('grade-input')) {
                const value = parseFloat(e.target.value);
                
                if (e.target.value !== '' && (isNaN(value) || value < 0 || value > 20)) {
                    e.target.style.borderColor = '#dc3545';
                    e.target.style.backgroundColor = '#f8d7da';
                    e.target.title = 'La calificación debe estar entre 0 y 20';
                } else {
                    e.target.style.borderColor = '';
                    e.target.style.backgroundColor = '';
                    e.target.title = 'Calificación de 0 a 20';
                    
                    if (e.target.value !== '') {
                        e.target.classList.add('has-value');
                    } else {
                        e.target.classList.remove('has-value');
                    }
                }
            }
        });
        
        // Cerrar modal al hacer clic fuera de él
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
        
        // Inicialización cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            // Validación del formulario de calificaciones antes de enviar
            const gradesForm = document.getElementById('grades-form');
            if (gradesForm) {
                gradesForm.addEventListener('submit', function(e) {
                    const gradeInputs = document.querySelectorAll('.grade-input');
                    let hasValidData = false;
                    let hasInvalidData = false;
                    
                    gradeInputs.forEach(input => {
                        if (input.value !== '') {
                            hasValidData = true;
                            const value = parseFloat(input.value);
                            if (isNaN(value) || value < 0 || value > 20) {
                                hasInvalidData = true;
                            }
                        }
                    });
                    
                    if (!hasValidData) {
                        alert('Debe ingresar al menos una calificación antes de guardar.');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (hasInvalidData) {
                        alert('Hay calificaciones inválidas. Las calificaciones deben estar entre 0 y 20.');
                        e.preventDefault();
                        return false;
                    }
                    
                    // Mostrar indicador de carga
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<div class="loading"></div> Guardando...';
                    submitBtn.disabled = true;
                    
                    return confirm('¿Está seguro de que quiere guardar estas calificaciones?');
                });
            }
            
            // Agregar funcionalidad de click a los tabs
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(tab => {
                tab.addEventListener('click', function() {
                    if (this.textContent.trim().includes('Indicadores de Logro')) {
                        showTab('indicators');
                    } else if (this.textContent.trim().includes('Evaluaciones por Sesión')) {
                        showTab('sessions');
                    } else if (this.textContent.trim().includes('Registrar Calificaciones')) {
                        showTab('grades');
                    }
                });
            });
            
            // Mejorar UX de los selects
            const selects = document.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    if (this.value) {
                        this.style.borderColor = '#28a745';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            });
            
            // Auto-focus en formularios
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab) {
                const firstInput = activeTab.querySelector('input[type="number"], input[type="text"], select');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
        
        // Función para mostrar mensajes de éxito temporales
        function showSuccessMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success';
            alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '100px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.style.animation = 'slideIn 0.3s ease-out';
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => alertDiv.remove(), 300);
            }, 3000);
        }
        
        // Añadir animaciones CSS para los mensajes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Shortcuts de teclado
        document.addEventListener('keydown', function(e) {
            // Esc para cerrar modales
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    openModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
            
            // Ctrl+S para guardar (solo en el tab de calificaciones)
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab && activeTab.id === 'grades') {
                    const submitBtn = activeTab.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.click();
                    }
                }
            }
        });
    </script>
</body>
</html>