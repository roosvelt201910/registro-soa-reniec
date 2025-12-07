<?php
session_start();
require_once('../config/conexion.php');

// Verificar que el usuario esté autenticado y sea docente
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] != 'docente') {
    header('Location: ../login.php');
    exit();
}

$docente_id = $_SESSION['usuario_id'];
$mensaje = '';
$tipo_mensaje = '';

// Procesar el formulario de asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_asistencia'])) {
    $sesion_id = $_POST['sesion_id'];
    $fecha = $_POST['fecha_sesion'];
    
    // Actualizar la sesión como realizada
    $stmt = $conexion->prepare("UPDATE sesiones SET estado = 'realizada', fecha = ? WHERE id = ?");
    $stmt->bind_param("si", $fecha, $sesion_id);
    $stmt->execute();
    
    // Guardar asistencia de cada estudiante
    if (isset($_POST['asistencia'])) {
        foreach ($_POST['asistencia'] as $estudiante_id => $estado) {
            $observaciones = isset($_POST['observaciones'][$estudiante_id]) ? $_POST['observaciones'][$estudiante_id] : '';
            
            // Verificar si ya existe un registro
            $check = $conexion->prepare("SELECT id FROM asistencias WHERE sesion_id = ? AND estudiante_id = ?");
            $check->bind_param("ii", $sesion_id, $estudiante_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Actualizar registro existente
                $stmt = $conexion->prepare("UPDATE asistencias SET estado = ?, observaciones = ? WHERE sesion_id = ? AND estudiante_id = ?");
                $stmt->bind_param("ssii", $estado, $observaciones, $sesion_id, $estudiante_id);
            } else {
                // Insertar nuevo registro
                $stmt = $conexion->prepare("INSERT INTO asistencias (sesion_id, estudiante_id, estado, observaciones) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $sesion_id, $estudiante_id, $estado, $observaciones);
            }
            $stmt->execute();
        }
        $mensaje = "Asistencia guardada correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener unidades didácticas del docente
$query_unidades = "SELECT id, nombre, codigo, periodo_lectivo, periodo_academico 
                   FROM unidades_didacticas 
                   WHERE docente_id = ? AND estado = 'activo'
                   ORDER BY periodo_lectivo DESC, nombre";
$stmt = $conexion->prepare($query_unidades);
$stmt->bind_param("i", $docente_id);
$stmt->execute();
$unidades = $stmt->get_result();

// Si se seleccionó una unidad didáctica
$unidad_seleccionada = isset($_GET['unidad_id']) ? $_GET['unidad_id'] : null;
$sesion_seleccionada = isset($_GET['sesion_id']) ? $_GET['sesion_id'] : null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencia - Sistema Académico</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            animation: slideIn 0.5s ease;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        select, input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        select:focus, input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .attendance-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .attendance-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .attendance-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 5px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .radio-option label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .radio-option.presente label {
            color: #28a745;
        }
        
        .radio-option.falta label {
            color: #dc3545;
        }
        
        .radio-option.tarde label {
            color: #ffc107;
        }
        
        .radio-option.permiso label {
            color: #17a2b8;
        }
        
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 40px;
        }
        
        .session-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .session-info h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        @media (max-width: 768px) {
            .radio-group {
                flex-direction: column;
                gap: 8px;
            }
            
            .attendance-table {
                font-size: 14px;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Registro de Asistencia</h1>
            <p>Control de asistencia de estudiantes</p>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert <?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <!-- Selección de Unidad Didáctica -->
            <div class="form-group">
                <label for="unidad_id">Seleccionar Unidad Didáctica:</label>
                <select id="unidad_id" onchange="window.location.href='asistencia.php?unidad_id=' + this.value">
                    <option value="">-- Seleccione una unidad didáctica --</option>
                    <?php 
                    $unidades->data_seek(0);
                    while ($unidad = $unidades->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $unidad['id']; ?>" <?php echo ($unidad_seleccionada == $unidad['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unidad['nombre'] . ' - ' . $unidad['periodo_lectivo'] . ' - ' . $unidad['periodo_academico']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if ($unidad_seleccionada): ?>
                <?php
                // Obtener sesiones de la unidad didáctica
                $query_sesiones = "SELECT id, numero_sesion, titulo, fecha, estado 
                                  FROM sesiones 
                                  WHERE unidad_didactica_id = ? 
                                  ORDER BY numero_sesion";
                $stmt = $conexion->prepare($query_sesiones);
                $stmt->bind_param("i", $unidad_seleccionada);
                $stmt->execute();
                $sesiones = $stmt->get_result();
                ?>
                
                <!-- Selección de Sesión -->
                <div class="form-group">
                    <label for="sesion_id">Seleccionar Sesión:</label>
                    <select id="sesion_id" onchange="window.location.href='asistencia.php?unidad_id=<?php echo $unidad_seleccionada; ?>&sesion_id=' + this.value">
                        <option value="">-- Seleccione una sesión --</option>
                        <option value="nueva">+ Crear nueva sesión</option>
                        <?php while ($sesion = $sesiones->fetch_assoc()): ?>
                            <option value="<?php echo $sesion['id']; ?>" <?php echo ($sesion_seleccionada == $sesion['id']) ? 'selected' : ''; ?>>
                                Sesión <?php echo $sesion['numero_sesion']; ?>: <?php echo htmlspecialchars($sesion['titulo']); ?> 
                                (<?php echo $sesion['fecha']; ?>) - <?php echo $sesion['estado']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <?php if ($sesion_seleccionada == 'nueva'): ?>
                    <!-- Formulario para crear nueva sesión -->
                    <div class="session-info">
                        <h3>Crear Nueva Sesión</h3>
                        <form method="POST" action="crear_sesion.php">
                            <input type="hidden" name="unidad_didactica_id" value="<?php echo $unidad_seleccionada; ?>">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label for="numero_sesion">Número de Sesión:</label>
                                    <input type="number" name="numero_sesion" required min="1" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                </div>
                                <div class="info-item">
                                    <label for="titulo">Título de la Sesión:</label>
                                    <input type="text" name="titulo" required style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                </div>
                                <div class="info-item">
                                    <label for="fecha">Fecha:</label>
                                    <input type="date" name="fecha" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="info-item">
                                    <label for="descripcion">Descripción:</label>
                                    <textarea name="descripcion" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Crear Sesión y Continuar</button>
                                <a href="asistencia.php?unidad_id=<?php echo $unidad_seleccionada; ?>" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($sesion_seleccionada && is_numeric($sesion_seleccionada)): ?>
                    <?php
                    // Obtener información de la sesión
                    $query_info_sesion = "SELECT s.*, ud.nombre as unidad_nombre 
                                         FROM sesiones s 
                                         JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                                         WHERE s.id = ?";
                    $stmt = $conexion->prepare($query_info_sesion);
                    $stmt->bind_param("i", $sesion_seleccionada);
                    $stmt->execute();
                    $info_sesion = $stmt->get_result()->fetch_assoc();
                    
                    // Obtener estudiantes matriculados
                    $query_estudiantes = "SELECT u.id, u.dni, u.nombres, u.apellidos,
                                         a.estado as asistencia_estado, a.observaciones
                                         FROM matriculas m
                                         JOIN usuarios u ON m.estudiante_id = u.id
                                         LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.sesion_id = ?
                                         WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
                                         ORDER BY u.apellidos, u.nombres";
                    $stmt = $conexion->prepare($query_estudiantes);
                    $stmt->bind_param("ii", $sesion_seleccionada, $unidad_seleccionada);
                    $stmt->execute();
                    $estudiantes = $stmt->get_result();
                    ?>
                    
                    <!-- Información de la Sesión -->
                    <div class="session-info">
                        <h3>Información de la Sesión</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Sesión N°</span>
                                <span class="info-value"><?php echo $info_sesion['numero_sesion']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Título</span>
                                <span class="info-value"><?php echo htmlspecialchars($info_sesion['titulo']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha</span>
                                <span class="info-value"><?php echo $info_sesion['fecha']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Estado</span>
                                <span class="info-value"><?php echo ucfirst($info_sesion['estado']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Formulario de Asistencia -->
                    <form method="POST" action="">
                        <input type="hidden" name="sesion_id" value="<?php echo $sesion_seleccionada; ?>">
                        <input type="hidden" name="guardar_asistencia" value="1">
                        
                        <div class="form-group">
                            <label for="fecha_sesion">Fecha de la Sesión:</label>
                            <input type="date" id="fecha_sesion" name="fecha_sesion" value="<?php echo $info_sesion['fecha']; ?>" required>
                        </div>
                        
                        <?php if ($estudiantes->num_rows > 0): ?>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">DNI</th>
                                        <th width="25%">Apellidos y Nombres</th>
                                        <th width="30%">Asistencia</th>
                                        <th width="25%">Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $contador = 1;
                                    while ($estudiante = $estudiantes->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $contador++; ?></td>
                                            <td><?php echo htmlspecialchars($estudiante['dni']); ?></td>
                                            <td><?php echo htmlspecialchars($estudiante['apellidos'] . ', ' . $estudiante['nombres']); ?></td>
                                            <td>
                                                <div class="radio-group">
                                                    <div class="radio-option presente">
                                                        <input type="radio" 
                                                               id="presente_<?php echo $estudiante['id']; ?>" 
                                                               name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                               value="presente" 
                                                               <?php echo ($estudiante['asistencia_estado'] == 'presente') ? 'checked' : ''; ?>>
                                                        <label for="presente_<?php echo $estudiante['id']; ?>">Presente</label>
                                                    </div>
                                                    <div class="radio-option falta">
                                                        <input type="radio" 
                                                               id="falta_<?php echo $estudiante['id']; ?>" 
                                                               name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                               value="falta" 
                                                               <?php echo ($estudiante['asistencia_estado'] == 'falta' || !$estudiante['asistencia_estado']) ? 'checked' : ''; ?>>
                                                        <label for="falta_<?php echo $estudiante['id']; ?>">Falta</label>
                                                    </div>
                                                    <div class="radio-option tarde">
                                                        <input type="radio" 
                                                               id="tarde_<?php echo $estudiante['id']; ?>" 
                                                               name="asistencia[<?php echo $estudiante['id']; ?>]" 
                                                               value="tarde">
                                                        <label for="tarde_<?php echo $estudiante['id']; ?>">Tarde</label>
                                                    </div>
                                                    <div class="radio-option permiso">
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
                                                <textarea name="observaciones[<?php echo $estudiante['id']; ?>]" 
                                                          placeholder="Observaciones..."><?php echo htmlspecialchars($estudiante['observaciones'] ?? ''); ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            
                            <div class="actions">
                                <button type="submit" class="btn btn-primary">💾 Guardar Asistencia</button>
                                <button type="button" class="btn btn-secondary" onclick="marcarTodos('presente')">✓ Todos Presente</button>
                                <button type="button" class="btn btn-secondary" onclick="marcarTodos('falta')">✗ Todos Falta</button>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <p>No hay estudiantes matriculados en esta unidad didáctica.</p>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function marcarTodos(estado) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${estado}"]`);
            radios.forEach(radio => {
                radio.checked = true;
            });
        }
        
        // Auto-ocultar mensajes después de 5 segundos
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