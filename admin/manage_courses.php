<?php
// Incluir archivo de configuración
require_once '../config/database.php';

// Verificar permisos
requirePermission('super_admin');

// Inicializar base de datos
$database = new Database();
$conn = $database->getConnection();

$message = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create_course') {
            $nombre = sanitizeInput($_POST['nombre']);
            $codigo = sanitizeInput($_POST['codigo']);
            $programa_id = (int)$_POST['programa_id'];
            $periodo_lectivo = sanitizeInput($_POST['periodo_lectivo']);
            $periodo_academico = sanitizeInput($_POST['periodo_academico']);
            $docente_id = (int)$_POST['docente_id'];
            
            if (empty($nombre) || empty($codigo) || !$programa_id || empty($periodo_lectivo) || empty($periodo_academico) || !$docente_id) {
                $message = '<div class="alert alert-error">Todos los campos son obligatorios.</div>';
            } else {
                try {
                    // Verificar si el código ya existe
                    $check_query = "SELECT id FROM unidades_didacticas WHERE codigo = :codigo";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bindParam(':codigo', $codigo);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = '<div class="alert alert-error">Error: El código del curso ya existe.</div>';
                    } else {
                        // Insertar el nuevo curso
                        $query = "INSERT INTO unidades_didacticas 
                                  (nombre, codigo, programa_id, periodo_lectivo, periodo_academico, docente_id, estado) 
                                  VALUES 
                                  (:nombre, :codigo, :programa_id, :periodo_lectivo, :periodo_academico, :docente_id, 'activo')";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':nombre', $nombre);
                        $stmt->bindParam(':codigo', $codigo);
                        $stmt->bindParam(':programa_id', $programa_id);
                        $stmt->bindParam(':periodo_lectivo', $periodo_lectivo);
                        $stmt->bindParam(':periodo_academico', $periodo_academico);
                        $stmt->bindParam(':docente_id', $docente_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success">Curso creado exitosamente.</div>';
                            // Limpiar el formulario
                            $_POST = array();
                        } else {
                            $message = '<div class="alert alert-error">Error al crear el curso.</div>';
                        }
                    }
                } catch(PDOException $e) {
                    $message = '<div class="alert alert-error">Error: ' . $e->getMessage() . '</div>';
                }
            }
        } elseif ($_POST['action'] == 'edit_course') {
            $course_id = (int)$_POST['course_id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $codigo = sanitizeInput($_POST['codigo']);
            $programa_id = (int)$_POST['programa_id'];
            $periodo_lectivo = sanitizeInput($_POST['periodo_lectivo']);
            $periodo_academico = sanitizeInput($_POST['periodo_academico']);
            $docente_id = (int)$_POST['docente_id'];
            $estado = sanitizeInput($_POST['estado']);
            
            if (empty($nombre) || empty($codigo) || !$programa_id || empty($periodo_lectivo) || empty($periodo_academico) || !$docente_id) {
                $message = '<div class="alert alert-error">Todos los campos son obligatorios.</div>';
            } else {
                try {
                    // Verificar si el código ya existe en otro curso
                    $check_query = "SELECT id FROM unidades_didacticas WHERE codigo = :codigo AND id != :course_id";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bindParam(':codigo', $codigo);
                    $check_stmt->bindParam(':course_id', $course_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = '<div class="alert alert-error">Error: El código del curso ya existe en otro curso.</div>';
                    } else {
                        // Actualizar el curso
                        $query = "UPDATE unidades_didacticas 
                                  SET nombre = :nombre, 
                                      codigo = :codigo, 
                                      programa_id = :programa_id, 
                                      periodo_lectivo = :periodo_lectivo, 
                                      periodo_academico = :periodo_academico, 
                                      docente_id = :docente_id,
                                      estado = :estado
                                  WHERE id = :course_id";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':nombre', $nombre);
                        $stmt->bindParam(':codigo', $codigo);
                        $stmt->bindParam(':programa_id', $programa_id);
                        $stmt->bindParam(':periodo_lectivo', $periodo_lectivo);
                        $stmt->bindParam(':periodo_academico', $periodo_academico);
                        $stmt->bindParam(':docente_id', $docente_id);
                        $stmt->bindParam(':estado', $estado);
                        $stmt->bindParam(':course_id', $course_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success">Curso actualizado exitosamente.</div>';
                            // Redirigir para evitar reenvío del formulario
                            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=list&updated=1");
                            exit;
                        } else {
                            $message = '<div class="alert alert-error">Error al actualizar el curso.</div>';
                        }
                    }
                } catch(PDOException $e) {
                    $message = '<div class="alert alert-error">Error: ' . $e->getMessage() . '</div>';
                }
            }
        } elseif ($_POST['action'] == 'enroll_student') {
            $estudiante_id = (int)$_POST['estudiante_id'];
            $unidad_didactica_id = (int)$_POST['unidad_didactica_id'];
            
            if ($estudiante_id && $unidad_didactica_id) {
                try {
                    // Verificar si ya está matriculado
                    $check_query = "SELECT id FROM matriculas 
                                   WHERE estudiante_id = :estudiante_id 
                                   AND unidad_didactica_id = :unidad_didactica_id";
                    
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bindParam(':estudiante_id', $estudiante_id);
                    $check_stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = '<div class="alert alert-error">El estudiante ya está matriculado en este curso.</div>';
                    } else {
                        // Matricular al estudiante
                        $query = "INSERT INTO matriculas 
                                  (estudiante_id, unidad_didactica_id, estado) 
                                  VALUES 
                                  (:estudiante_id, :unidad_didactica_id, 'activo')";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':estudiante_id', $estudiante_id);
                        $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success">Estudiante matriculado exitosamente.</div>';
                        } else {
                            $message = '<div class="alert alert-error">Error al matricular estudiante.</div>';
                        }
                    }
                } catch(PDOException $e) {
                    $message = '<div class="alert alert-error">Error: ' . $e->getMessage() . '</div>';
                }
            } else {
                $message = '<div class="alert alert-error">Debe seleccionar un estudiante.</div>';
            }
        }
    }
}

// Mensaje de actualización exitosa
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = '<div class="alert alert-success">Curso actualizado exitosamente.</div>';
}

// Obtener todos los cursos
try {
    $query = "SELECT 
                ud.*, 
                pe.nombre as programa_nombre,
                CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre
              FROM unidades_didacticas ud
              LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
              LEFT JOIN usuarios u ON ud.docente_id = u.id
              ORDER BY ud.id DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allCourses = [];
}

// Obtener todos los docentes
try {
    $query = "SELECT 
                id,
                dni,
                CONCAT(apellidos, ', ', nombres) as nombre_completo,
                email,
                estado
              FROM usuarios
              WHERE tipo_usuario = 'docente'
              AND estado = 'activo'
              ORDER BY apellidos, nombres";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $teachers = [];
}

// Obtener todos los estudiantes
try {
    $query = "SELECT 
                id,
                dni,
                CONCAT(apellidos, ', ', nombres) as nombre_completo,
                email,
                estado
              FROM usuarios
              WHERE tipo_usuario = 'estudiante'
              AND estado = 'activo'
              ORDER BY apellidos, nombres";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $students = [];
}

// Obtener programas de estudio
try {
    $query = "SELECT * FROM programas_estudio WHERE estado = 'activo' ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $programs = [];
}

// Manejo de curso seleccionado para ver estudiantes
$selectedCourse = null;
$enrolledStudents = [];

if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
    $course_id = (int)$_GET['course_id'];
    
    try {
        // Obtener información del curso
        $query = "SELECT 
                    ud.*, 
                    pe.nombre as programa_nombre,
                    CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre
                  FROM unidades_didacticas ud
                  LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
                  LEFT JOIN usuarios u ON ud.docente_id = u.id
                  WHERE ud.id = :course_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener estudiantes matriculados
        if ($selectedCourse) {
            $query = "SELECT 
                        u.dni,
                        CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                        m.estado as estado_matricula,
                        m.fecha_matricula
                      FROM matriculas m
                      JOIN usuarios u ON m.estudiante_id = u.id
                      WHERE m.unidad_didactica_id = :course_id
                      AND u.tipo_usuario = 'estudiante'
                      ORDER BY u.apellidos, u.nombres";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        // Error al obtener el curso
    }
}

// Manejo de curso a editar
$editCourse = null;
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    
    try {
        $query = "SELECT * FROM unidades_didacticas WHERE id = :edit_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':edit_id', $edit_id);
        $stmt->execute();
        $editCourse = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Error al obtener el curso para editar
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cursos - Sistema Académico</title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .required {
            color: #dc3545;
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
        
        .btn-warning {
            background: #ffc107;
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
            background: #545b62;
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .btn-back:hover {
            background: #667eea;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .courses-table th,
        .courses-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .courses-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .courses-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-activo {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactivo {
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .table-container {
            overflow-x: auto;
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
            transition: all 0.3s;
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
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .edit-form-container {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .edit-form-container h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Gestión de Cursos</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($allCourses); ?></div>
                <div class="stat-label">Total Cursos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teachers); ?></div>
                <div class="stat-label">Docentes Disponibles</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo count($students); ?></div>
                <div class="stat-label">Estudiantes Totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo count($programs); ?></div>
                <div class="stat-label">Programas de Estudio</div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="showTab('create', this)">Crear Curso</button>
                <button class="tab" onclick="showTab('list', this)">Lista de Cursos</button>
                <button class="tab" onclick="showTab('enroll', this)">Matricular Estudiantes</button>
            </div>
            
            <!-- Tab: Crear Curso -->
            <div id="create" class="tab-content active">
                <h2><?php echo $editCourse ? 'Editar Curso' : 'Crear Nuevo Curso'; ?></h2>
                
                <!-- Formulario de edición -->
                <?php if ($editCourse): ?>
                <div class="edit-form-container">
                    <h3>Editando: <?php echo htmlspecialchars($editCourse['nombre']); ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit_course">
                        <input type="hidden" name="course_id" value="<?php echo $editCourse['id']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="edit_codigo">Código del Curso <span class="required">*</span></label>
                                <input type="text" 
                                       id="edit_codigo" 
                                       name="codigo" 
                                       placeholder="Ej: NCCIF001"
                                       value="<?php echo htmlspecialchars($editCourse['codigo']); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_programa_id">Programa de Estudio <span class="required">*</span></label>
                                <select name="programa_id" id="edit_programa_id" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['id']; ?>" 
                                                <?php echo ($editCourse['programa_id'] == $program['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($program['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_nombre">Nombre del Curso <span class="required">*</span></label>
                            <input type="text" 
                                   id="edit_nombre" 
                                   name="nombre" 
                                   placeholder="Ej: Normas de Control de calidad en la Industria Farmacéutica"
                                   value="<?php echo htmlspecialchars($editCourse['nombre']); ?>"
                                   required>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="edit_periodo_lectivo">Periodo Lectivo <span class="required">*</span></label>
                                <input type="text" 
                                       id="edit_periodo_lectivo" 
                                       name="periodo_lectivo" 
                                       placeholder="Ej: 2024-II"
                                       value="<?php echo htmlspecialchars($editCourse['periodo_lectivo']); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_periodo_academico">Periodo Académico <span class="required">*</span></label>
                                <input type="text" 
                                       id="edit_periodo_academico" 
                                       name="periodo_academico" 
                                       placeholder="Ej: VI-A"
                                       value="<?php echo htmlspecialchars($editCourse['periodo_academico']); ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="edit_docente_id">Docente Asignado <span class="required">*</span></label>
                                <select name="docente_id" id="edit_docente_id" required>
                                    <option value="">Seleccione un docente...</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>"
                                                <?php echo ($editCourse['docente_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['nombre_completo']); ?> (DNI: <?php echo $teacher['dni']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_estado">Estado <span class="required">*</span></label>
                                <select name="estado" id="edit_estado" required>
                                    <option value="activo" <?php echo ($editCourse['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo ($editCourse['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Actualizar Curso</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Formulario de creación -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_course">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="codigo">Código del Curso <span class="required">*</span></label>
                            <input type="text" 
                                   id="codigo" 
                                   name="codigo" 
                                   placeholder="Ej: NCCIF001"
                                   value="<?php echo isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="programa_id">Programa de Estudio <span class="required">*</span></label>
                            <select name="programa_id" id="programa_id" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo $program['id']; ?>" 
                                            <?php echo (isset($_POST['programa_id']) && $_POST['programa_id'] == $program['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($program['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre del Curso <span class="required">*</span></label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               placeholder="Ej: Normas de Control de calidad en la Industria Farmacéutica"
                               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="periodo_lectivo">Periodo Lectivo <span class="required">*</span></label>
                            <input type="text" 
                                   id="periodo_lectivo" 
                                   name="periodo_lectivo" 
                                   placeholder="Ej: 2024-II"
                                   value="<?php echo isset($_POST['periodo_lectivo']) ? htmlspecialchars($_POST['periodo_lectivo']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="periodo_academico">Periodo Académico <span class="required">*</span></label>
                            <input type="text" 
                                   id="periodo_academico" 
                                   name="periodo_academico" 
                                   placeholder="Ej: VI-A"
                                   value="<?php echo isset($_POST['periodo_academico']) ? htmlspecialchars($_POST['periodo_academico']) : ''; ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="docente_id">Docente Asignado <span class="required">*</span></label>
                        <select name="docente_id" id="docente_id" required>
                            <option value="">Seleccione un docente...</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"
                                        <?php echo (isset($_POST['docente_id']) && $_POST['docente_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['nombre_completo']); ?> (DNI: <?php echo $teacher['dni']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Crear Curso</button>
                </form>
            </div>
            
            <!-- Tab: Lista de Cursos -->
            <div id="list" class="tab-content">
                <h2>Lista de Cursos Registrados</h2>
                
                <?php if (empty($allCourses)): ?>
                    <p>No hay cursos registrados en el sistema.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="courses-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre del Curso</th>
                                    <th>Programa</th>
                                    <th>Periodo</th>
                                    <th>Docente</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allCourses as $curso): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($curso['codigo']); ?></td>
                                        <td><?php echo htmlspecialchars($curso['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($curso['programa_nombre'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($curso['periodo_lectivo']); ?><br>
                                            <small><?php echo htmlspecialchars($curso['periodo_academico']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($curso['docente_nombre'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $curso['estado']; ?>">
                                                <?php echo ucfirst($curso['estado']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="?edit_id=<?php echo $curso['id']; ?>&tab=create" 
                                               class="btn btn-warning btn-sm">
                                                Editar
                                            </a>
                                            <a href="?course_id=<?php echo $curso['id']; ?>&tab=enroll" 
                                               class="btn btn-success btn-sm">
                                                Ver Estudiantes
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Matricular Estudiantes -->
            <div id="enroll" class="tab-content">
                <h2>Matricular Estudiantes</h2>
                
                <div class="form-group">
                    <label for="course_select">Seleccionar Curso:</label>
                    <select id="course_select" onchange="selectCourse(this.value)">
                        <option value="">Seleccione un curso...</option>
                        <?php foreach ($allCourses as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>" 
                                    <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $curso['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['codigo'] . ' - ' . $curso['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selectedCourse): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #667eea;">
                        <h4>Curso Seleccionado: <?php echo htmlspecialchars($selectedCourse['nombre']); ?></h4>
                        <p><strong>Código:</strong> <?php echo htmlspecialchars($selectedCourse['codigo']); ?></p>
                        <p><strong>Periodo:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo'] . ' - ' . $selectedCourse['periodo_academico']); ?></p>
                    </div>
                    
                    <!-- Formulario para matricular -->
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="enroll_student">
                        <input type="hidden" name="unidad_didactica_id" value="<?php echo $selectedCourse['id']; ?>">
                        
                        <div class="form-group">
                            <label for="estudiante_id">Estudiante a Matricular:</label>
                            <select name="estudiante_id" id="estudiante_id" required>
                                <option value="">Seleccione un estudiante...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['nombre_completo']); ?> (DNI: <?php echo $student['dni']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Matricular Estudiante</button>
                    </form>
                    
                    <!-- Lista de estudiantes matriculados -->
                    <h4 style="margin-top: 30px;">Estudiantes Matriculados (<?php echo count($enrolledStudents); ?>)</h4>
                    <?php if (empty($enrolledStudents)): ?>
                        <p>No hay estudiantes matriculados en este curso.</p>
                    <?php else: ?>
                        <table class="courses-table">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Nombre Completo</th>
                                    <th>Estado</th>
                                    <th>Fecha Matrícula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['dni']); ?></td>
                                        <td><?php echo htmlspecialchars($student['nombre_completo']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $student['estado_matricula']; ?>">
                                                <?php echo ucfirst($student['estado_matricula']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($student['fecha_matricula'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName, tabElement) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            tabElement.classList.add('active');
        }
        
        function selectCourse(courseId) {
            if (courseId) {
                const url = new URL(window.location);
                url.searchParams.set('course_id', courseId);
                url.searchParams.set('tab', 'enroll');
                window.location = url;
            }
        }
        
        // Auto-capitalize course code
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Auto-capitalize edit course code if exists
        const editCodigo = document.getElementById('edit_codigo');
        if (editCodigo) {
            editCodigo.addEventListener('input', function(e) {
                e.target.value = e.target.value.toUpperCase();
            });
        }
        
        // Check URL parameters to show appropriate tab
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            const editId = urlParams.get('edit_id');
            
            if (tab === 'enroll') {
                const enrollTab = document.querySelector('.tab:nth-child(3)');
                if (enrollTab) {
                    showTab('enroll', enrollTab);
                }
            } else if (tab === 'list') {
                const listTab = document.querySelector('.tab:nth-child(2)');
                if (listTab) {
                    showTab('list', listTab);
                }
            } else if (editId || tab === 'create') {
                const createTab = document.querySelector('.tab:nth-child(1)');
                if (createTab) {
                    showTab('create', createTab);
                }
            }
        });
    </script>
</body>
</html>