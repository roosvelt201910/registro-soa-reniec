<?php
require_once '../config/database.php';
requirePermission('docente');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$mensaje = '';
$tipo_mensaje = '';

// ==================== PROCESAR ASISTENCIA ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_asistencia'])) {
    $sesion_id = $_POST['sesion_id'];
    $fecha = $_POST['fecha_sesion'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();
        
        // Actualizar la sesión como realizada
        $stmt = $conn->prepare("UPDATE sesiones SET estado = 'realizada', fecha = ? WHERE id = ?");
        $stmt->execute([$fecha, $sesion_id]);
        
        // Guardar asistencia de cada estudiante
        if (isset($_POST['asistencia'])) {
            foreach ($_POST['asistencia'] as $estudiante_id => $estado) {
                $observaciones = isset($_POST['observaciones'][$estudiante_id]) ? $_POST['observaciones'][$estudiante_id] : '';
                
                // Verificar si ya existe un registro
                $check = $conn->prepare("SELECT id FROM asistencias WHERE sesion_id = ? AND estudiante_id = ?");
                $check->execute([$sesion_id, $estudiante_id]);
                
                if ($check->rowCount() > 0) {
                    // Actualizar registro existente
                    $stmt = $conn->prepare("UPDATE asistencias SET estado = ?, observaciones = ?, fecha_registro = NOW() WHERE sesion_id = ? AND estudiante_id = ?");
                    $stmt->execute([$estado, $observaciones, $sesion_id, $estudiante_id]);
                } else {
                    // Insertar nuevo registro
                    $stmt = $conn->prepare("INSERT INTO asistencias (sesion_id, estudiante_id, estado, observaciones) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$sesion_id, $estudiante_id, $estado, $observaciones]);
                }
            }
        }
        
        $conn->commit();
        $mensaje = "Asistencia guardada correctamente";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al guardar la asistencia: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ==================== CREAR NUEVA SESIÓN ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_sesion'])) {
    $unidad_didactica_id = $_POST['unidad_didactica_id'];
    $numero_sesion = $_POST['numero_sesion'];
    $titulo = $_POST['titulo'];
    $fecha = $_POST['fecha'];
    $descripcion = $_POST['descripcion'] ?? '';
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Verificar que el número de sesión no exista
        $check = $conn->prepare("SELECT id FROM sesiones WHERE unidad_didactica_id = ? AND numero_sesion = ?");
        $check->execute([$unidad_didactica_id, $numero_sesion]);
        
        if ($check->rowCount() > 0) {
            $mensaje = "Ya existe una sesión con ese número";
            $tipo_mensaje = "error";
        } else {
            // Insertar nueva sesión
            $stmt = $conn->prepare("INSERT INTO sesiones (unidad_didactica_id, numero_sesion, titulo, fecha, descripcion, estado) VALUES (?, ?, ?, ?, ?, 'programada')");
            
            if ($stmt->execute([$unidad_didactica_id, $numero_sesion, $titulo, $fecha, $descripcion])) {
                $nueva_sesion_id = $conn->lastInsertId();
                $mensaje = "Sesión creada exitosamente";
                $tipo_mensaje = "success";
                header("Location: attendance.php?unidad_id=" . $unidad_didactica_id . "&sesion_id=" . $nueva_sesion_id);
                exit();
            }
        }
    } catch(PDOException $e) {
        $mensaje = "Error al crear la sesión: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ==================== OBTENER DATOS ====================
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener unidades didácticas del docente
    $query_unidades = "SELECT ud.*, pe.nombre as programa_nombre, 
                       (SELECT COUNT(DISTINCT m.estudiante_id) FROM matriculas m WHERE m.unidad_didactica_id = ud.id AND m.estado = 'activo') as total_estudiantes
                       FROM unidades_didacticas ud
                       LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
                       WHERE ud.docente_id = ? AND ud.estado = 'activo'
                       ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $conn->prepare($query_unidades);
    $stmt->execute([$user_id]);
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $unidades = [];
    $mensaje = "Error al cargar datos: " . $e->getMessage();
    $tipo_mensaje = "error";
}

$unidad_seleccionada = isset($_GET['unidad_id']) ? intval($_GET['unidad_id']) : null;
$sesion_seleccionada = isset($_GET['sesion_id']) ? $_GET['sesion_id'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Asistencia - <?php echo htmlspecialchars($user_name); ?></title>
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
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
            background: #5a6268;
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #667eea;
            padding: 10px 20px;
            font-size: 14px;
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .attendance-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .attendance-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .attendance-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .attendance-option label {
            cursor: pointer;
            margin: 0;
            font-weight: normal;
        }
        
        .attendance-option.presente label { color: #28a745; }
        .attendance-option.falta label { color: #dc3545; }
        .attendance-option.tarde label { color: #ffc107; }
        .attendance-option.permiso label { color: #17a2b8; }
        
        .session-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .session-info h3 {
            color: #495057;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .obs-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .attendance-options {
                flex-direction: column;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th,
            .table td {
                padding: 8px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Sistema de Asistencia</h1>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span>Bienvenido, <?php echo htmlspecialchars($user_name); ?></span>
                <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Selección de Unidad Didáctica -->
        <div class="card">
            <h2>Seleccionar Unidad Didáctica</h2>
            <div class="form-group">
                <select onchange="window.location.href='attendance.php?unidad_id=' + this.value">
                    <option value="">-- Seleccione una unidad didáctica --</option>
                    <?php foreach ($unidades as $unidad): ?>
                        <option value="<?php echo $unidad['id']; ?>" <?php echo ($unidad_seleccionada == $unidad['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unidad['nombre']); ?> 
                            | <?php echo $unidad['periodo_academico']; ?> 
                            | <?php echo $unidad['periodo_lectivo']; ?>
                            (<?php echo $unidad['total_estudiantes']; ?> estudiantes)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if ($unidad_seleccionada): ?>
            <?php
            try {
                // Obtener información de la unidad
                $query_info = "SELECT ud.*, 
                              (SELECT COUNT(*) FROM sesiones WHERE unidad_didactica_id = ud.id) as total_sesiones,
                              (SELECT COUNT(*) FROM sesiones WHERE unidad_didactica_id = ud.id AND estado = 'realizada') as sesiones_realizadas,
                              (SELECT COUNT(DISTINCT m.estudiante_id) FROM matriculas m WHERE m.unidad_didactica_id = ud.id AND m.estado = 'activo') as total_estudiantes
                              FROM unidades_didacticas ud
                              WHERE ud.id = ?";
                $stmt = $conn->prepare($query_info);
                $stmt->execute([$unidad_seleccionada]);
                $info_unidad = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Obtener sesiones
                $query_sesiones = "SELECT s.*, 
                                  (SELECT COUNT(*) FROM asistencias WHERE sesion_id = s.id) as asistencias_registradas
                                  FROM sesiones s
                                  WHERE s.unidad_didactica_id = ? 
                                  ORDER BY s.numero_sesion";
                $stmt = $conn->prepare($query_sesiones);
                $stmt->execute([$unidad_seleccionada]);
                $sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                $info_unidad = null;
                $sesiones = [];
            }
            ?>
            
            <?php if ($info_unidad): ?>
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $info_unidad['total_sesiones']; ?></div>
                        <div class="stat-label">Total Sesiones</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $info_unidad['sesiones_realizadas']; ?></div>
                        <div class="stat-label">Sesiones Realizadas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $progreso = $info_unidad['total_sesiones'] > 0 
                                ? round(($info_unidad['sesiones_realizadas'] / $info_unidad['total_sesiones']) * 100) 
                                : 0;
                            echo $progreso . '%';
                            ?>
                        </div>
                        <div class="stat-label">Progreso</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $info_unidad['total_estudiantes']; ?></div>
                        <div class="stat-label">Estudiantes</div>
                    </div>
                </div>
                
                <!-- Selección de Sesión -->
                <div class="card">
                    <h2>Seleccionar Sesión de Clase</h2>
                    <div class="form-group">
                        <select onchange="window.location.href='attendance.php?unidad_id=<?php echo $unidad_seleccionada; ?>&sesion_id=' + this.value">
                            <option value="">-- Seleccione una sesión --</option>
                            <option value="nueva">Crear nueva sesión</option>
                            <?php foreach ($sesiones as $sesion): ?>
                                <option value="<?php echo $sesion['id']; ?>" <?php echo ($sesion_seleccionada == $sesion['id']) ? 'selected' : ''; ?>>
                                    Sesión <?php echo $sesion['numero_sesion']; ?>: <?php echo htmlspecialchars($sesion['titulo']); ?> 
                                    | <?php echo date('d/m/Y', strtotime($sesion['fecha'])); ?>
                                    | <?php echo ucfirst($sesion['estado']); ?>
                                    <?php if ($sesion['asistencias_registradas'] > 0): ?>
                                        ✓
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <?php if ($sesion_seleccionada == 'nueva'): ?>
                    <!-- Crear Nueva Sesión -->
                    <div class="card">
                        <h2>Crear Nueva Sesión</h2>
                        <form method="POST">
                            <input type="hidden" name="crear_sesion" value="1">
                            <input type="hidden" name="unidad_didactica_id" value="<?php echo $unidad_seleccionada; ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Número de Sesión *</label>
                                    <input type="number" name="numero_sesion" required min="1">
                                </div>
                                <div class="form-group">
                                    <label>Fecha *</label>
                                    <input type="date" name="fecha" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Título de la Sesión *</label>
                                <input type="text" name="titulo" required placeholder="Ej: Introducción a la farmacología">
                            </div>
                            
                            <div class="form-group">
                                <label>Descripción (Opcional)</label>
                                <textarea name="descripcion" placeholder="Descripción de la sesión..." style="min-height: 60px; resize: vertical;"></textarea>
                            </div>
                            
                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Crear Sesión</button>
                                <a href="attendance.php?unidad_id=<?php echo $unidad_seleccionada; ?>" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                    
                <?php elseif ($sesion_seleccionada && is_numeric($sesion_seleccionada)): ?>
                    <?php
                    try {
                        // Obtener información de la sesión
                        $query_info_sesion = "SELECT * FROM sesiones WHERE id = ?";
                        $stmt = $conn->prepare($query_info_sesion);
                        $stmt->execute([$sesion_seleccionada]);
                        $info_sesion = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Obtener estudiantes con su asistencia
                        $query_estudiantes = "SELECT u.id, u.dni, u.nombres, u.apellidos,
                                             a.estado as asistencia_estado, a.observaciones
                                             FROM matriculas m
                                             JOIN usuarios u ON m.estudiante_id = u.id
                                             LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.sesion_id = ?
                                             WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
                                             ORDER BY u.apellidos, u.nombres";
                        $stmt = $conn->prepare($query_estudiantes);
                        $stmt->execute([$sesion_seleccionada, $unidad_seleccionada]);
                        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {
                        $info_sesion = null;
                        $estudiantes = [];
                    }
                    ?>
                    
                    <?php if ($info_sesion): ?>
                        <!-- Información de la Sesión -->
                        <div class="session-info">
                            <h3>Información de la Sesión</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Sesión N°</div>
                                    <div class="info-value"><?php echo $info_sesion['numero_sesion']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Título</div>
                                    <div class="info-value"><?php echo htmlspecialchars($info_sesion['titulo']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Fecha</div>
                                    <div class="info-value"><?php echo date('d/m/Y', strtotime($info_sesion['fecha'])); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Estado</div>
                                    <div class="info-value">
                                        <span class="badge badge-<?php echo $info_sesion['estado'] == 'realizada' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($info_sesion['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php if ($info_sesion['descripcion']): ?>
                                <div style="margin-top: 15px;">
                                    <strong>Descripción:</strong> <?php echo htmlspecialchars($info_sesion['descripcion']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulario de Asistencia -->
                        <div class="card">
                            <h2>Registro de Asistencia</h2>
                            <form method="POST">
                                <input type="hidden" name="sesion_id" value="<?php echo $sesion_seleccionada; ?>">
                                <input type="hidden" name="guardar_asistencia" value="1">
                                
                                <div class="form-group">
                                    <label>Fecha de la Sesión:</label>
                                    <input type="date" name="fecha_sesion" value="<?php echo $info_sesion['fecha']; ?>" required style="max-width: 200px;">
                                </div>
                                
                                <?php if (count($estudiantes) > 0): ?>
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="12%">DNI</th>
                                                <th width="28%">Apellidos y Nombres</th>
                                                <th width="35%">Asistencia</th>
                                                <th width="20%">Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $contador = 1;
                                            foreach ($estudiantes as $estudiante): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $contador++; ?></td>
                                                    <td><?php echo htmlspecialchars($estudiante['dni']); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($estudiante['apellidos']); ?></strong>,
                                                        <?php echo htmlspecialchars($estudiante['nombres']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="attendance-options">
                                                            <div class="attendance-option presente">
                                                                <input type="radio" 
                                                                       id="presente_<?php echo $estudiante['id']; ?>" 
                                                                       name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                                       value="presente" 
                                                                       <?php echo ($estudiante['asistencia_estado'] == 'presente') ? 'checked' : ''; ?>>
                                                                <label for="presente_<?php echo $estudiante['id']; ?>">Presente</label>
                                                            </div>
                                                            <div class="attendance-option falta">
                                                                <input type="radio" 
                                                                       id="falta_<?php echo $estudiante['id']; ?>" 
                                                                       name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                                       value="falta" 
                                                                       <?php echo ($estudiante['asistencia_estado'] == 'falta' || !$estudiante['asistencia_estado']) ? 'checked' : ''; ?>>
                                                                <label for="falta_<?php echo $estudiante['id']; ?>">Falta</label>
                                                            </div>
                                                            <div class="attendance-option tarde">
                                                                <input type="radio" 
                                                                       id="tarde_<?php echo $estudiante['id']; ?>" 
                                                                       name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                                       value="tarde"
                                                                       <?php echo ($estudiante['asistencia_estado'] == 'tarde') ? 'checked' : ''; ?>>
                                                                <label for="tarde_<?php echo $estudiante['id']; ?>">Tarde</label>
                                                            </div>
                                                            <div class="attendance-option permiso">
                                                                <input type="radio" 
                                                                       id="permiso_<?php echo $estudiante['id']; ?>" 
                                                                       name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                                       value="permiso" 
                                                                       <?php echo ($estudiante['asistencia_estado'] == 'permiso') ? 'checked' : ''; ?>>
                                                                <label for="permiso_<?php echo $estudiante['id']; ?>">Permiso</label>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" 
                                                               class="obs-input"
                                                               name="observaciones[<?php echo $estudiante['id']; ?>]" 
                                                               placeholder="Observaciones..."
                                                               value="<?php echo htmlspecialchars($estudiante['observaciones'] ?? ''); ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <div class="actions">
                                        <button type="submit" class="btn btn-primary">Guardar Asistencia</button>
                                        <button type="button" class="btn btn-success" onclick="marcarTodos('presente')">Todos Presente</button>
                                        <button type="button" class="btn btn-danger" onclick="marcarTodos('falta')">Todos Falta</button>
                                        <button type="button" class="btn btn-warning" onclick="marcarTodos('tarde')">Todos Tarde</button>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <p>No hay estudiantes matriculados en esta unidad didáctica.</p>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function marcarTodos(estado) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${estado}"]`);
            radios.forEach(radio => {
                radio.checked = true;
            });
        }
        
        // Auto-ocultar alertas después de 5 segundos
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideIn 0.5s ease reverse';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>