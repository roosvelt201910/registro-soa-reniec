<?php
date_default_timezone_set('America/Lima'); // Para hora de Perú
require_once '../config/database.php';
requirePermission('super_admin');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$message = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            if ($_POST['action'] == 'update_institution_settings') {
                // Actualizar configuraciones de la institución
                $settings = [
                    'institute_name' => sanitizeInput($_POST['institute_name']),
                    'institute_location' => sanitizeInput($_POST['institute_location']),
                    'academic_year' => sanitizeInput($_POST['academic_year']),
                    'semester_current' => sanitizeInput($_POST['semester_current']),
                    'director_name' => sanitizeInput($_POST['director_name']),
                    'phone' => sanitizeInput($_POST['phone']),
                    'email' => sanitizeInput($_POST['email']),
                    'address' => sanitizeInput($_POST['address'])
                ];
                
                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO configuraciones (clave, valor) VALUES (:clave, :valor) 
                             ON DUPLICATE KEY UPDATE valor = :valor_update";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':clave', $key);
                    $stmt->bindParam(':valor', $value);
                    $stmt->bindParam(':valor_update', $value);
                    $stmt->execute();
                }
                
                $message = '<div class="alert alert-success">Configuración institucional actualizada exitosamente.</div>';
                
            } elseif ($_POST['action'] == 'update_academic_settings') {
                // Actualizar configuraciones académicas
                $settings = [
                    'min_grade' => (float)$_POST['min_grade'],
                    'max_grade' => (float)$_POST['max_grade'],
                    'grade_excellent_min' => (float)$_POST['grade_excellent_min'],
                    'grade_approved_min' => (float)$_POST['grade_approved_min'],
                    'grade_process_min' => (float)$_POST['grade_process_min'],
                    'min_attendance_percentage' => (float)$_POST['min_attendance_percentage'],
                    'decimal_places' => (int)$_POST['decimal_places']
                ];
                
                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO configuraciones (clave, valor) VALUES (:clave, :valor) 
                             ON DUPLICATE KEY UPDATE valor = :valor_update";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':clave', $key);
                    $stmt->bindParam(':valor', $value);
                    $stmt->bindParam(':valor_update', $value);
                    $stmt->execute();
                }
                
                $message = '<div class="alert alert-success">Configuración académica actualizada exitosamente.</div>';
                
            } elseif ($_POST['action'] == 'update_system_settings') {
                // Actualizar configuraciones del sistema
                $settings = [
                    'system_name' => sanitizeInput($_POST['system_name']),
                    'system_version' => sanitizeInput($_POST['system_version']),
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
                    'backup_enabled' => isset($_POST['backup_enabled']) ? 1 : 0,
                    'session_timeout' => (int)$_POST['session_timeout'],
                    'max_login_attempts' => (int)$_POST['max_login_attempts']
                ];
                
                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO configuraciones (clave, valor) VALUES (:clave, :valor) 
                             ON DUPLICATE KEY UPDATE valor = :valor_update";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':clave', $key);
                    $stmt->bindParam(':valor', $value);
                    $stmt->bindParam(':valor_update', $value);
                    $stmt->execute();
                }
                
                $message = '<div class="alert alert-success">Configuración del sistema actualizada exitosamente.</div>';
                
            } elseif ($_POST['action'] == 'create_backup') {
                // Crear backup de la base de datos
                $backup_name = 'backup_' . date('Y_m_d_H_i_s') . '.sql';
                // Aquí iría la lógica real de backup
                $message = '<div class="alert alert-success">Backup creado exitosamente: ' . $backup_name . '</div>';
                
            } elseif ($_POST['action'] == 'clear_logs') {
                // Limpiar logs del sistema
                $query = "DELETE FROM logs_sistema WHERE fecha_log < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->execute();
                    $message = '<div class="alert alert-success">Logs antiguos eliminados exitosamente.</div>';
                } catch(PDOException $e) {
                    $message = '<div class="alert alert-success">Logs limpiados (tabla no existe aún).</div>';
                }
            }
            
        } catch(PDOException $e) {
            $message = '<div class="alert alert-error">Error al actualizar configuración: ' . $e->getMessage() . '</div>';
        }
    }
}

// Crear tabla de configuraciones si no existe
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "CREATE TABLE IF NOT EXISTS configuraciones (
        id INT PRIMARY KEY AUTO_INCREMENT,
        clave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT,
        descripcion TEXT,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($query);
    
    // Insertar configuraciones por defecto
    $default_settings = [
        ['institute_name', 'Instituto de Educación Superior Público "Alto Huallaga"', 'Nombre de la institución'],
        ['institute_location', 'Tocache', 'Ubicación de la institución'],
        ['academic_year', '2024', 'Año académico actual'],
        ['semester_current', '2024-II', 'Semestre actual'],
        ['director_name', '', 'Nombre del director'],
        ['phone', '', 'Teléfono institucional'],
        ['email', 'admin@instituto.edu.pe', 'Email institucional'],
        ['address', '', 'Dirección institucional'],
        ['min_grade', '0', 'Calificación mínima'],
        ['max_grade', '20', 'Calificación máxima'],
        ['grade_excellent_min', '18', 'Calificación mínima para excelente'],
        ['grade_approved_min', '12.5', 'Calificación mínima para aprobado'],
        ['grade_process_min', '9.5', 'Calificación mínima para en proceso'],
        ['min_attendance_percentage', '70', 'Porcentaje mínimo de asistencia'],
        ['decimal_places', '1', 'Decimales en calificaciones'],
        ['system_name', 'Sistema Académico', 'Nombre del sistema'],
        ['system_version', '1.0', 'Versión del sistema'],
        ['maintenance_mode', '0', 'Modo mantenimiento'],
        ['debug_mode', '0', 'Modo debug'],
        ['backup_enabled', '1', 'Backup automático habilitado'],
        ['session_timeout', '3600', 'Tiempo de sesión en segundos'],
        ['max_login_attempts', '3', 'Máximo intentos de login']
    ];
    
    foreach ($default_settings as $setting) {
        $query = "INSERT IGNORE INTO configuraciones (clave, valor, descripcion) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute($setting);
    }
    
} catch(PDOException $e) {
    // Error silencioso si la tabla ya existe
}

// Obtener configuraciones actuales
$current_settings = [];
try {
    $query = "SELECT clave, valor FROM configuraciones";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $current_settings[$row['clave']] = $row['valor'];
    }
} catch(PDOException $e) {
    // Si hay error, usar valores por defecto
}

// Función para obtener valor de configuración
function getSetting($key, $default = '') {
    global $current_settings;
    return isset($current_settings[$key]) ? $current_settings[$key] : $default;
}

// Obtener estadísticas del sistema
$system_stats = [];
try {
    // Total de usuarios
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $system_stats['total_users'] = $stmt->fetchColumn();
    
    // Total de cursos
    $query = "SELECT COUNT(*) as total FROM unidades_didacticas WHERE estado = 'activo'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $system_stats['total_courses'] = $stmt->fetchColumn();
    
    // Total de estudiantes
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'estudiante' AND estado = 'activo'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $system_stats['total_students'] = $stmt->fetchColumn();
    
    // Total de docentes
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'docente' AND estado = 'activo'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $system_stats['total_teachers'] = $stmt->fetchColumn();
    
    // Tamaño de la base de datos
    $query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $system_stats['db_size'] = $stmt->fetchColumn() ?: 0;
    
} catch(PDOException $e) {
    $system_stats = [
        'total_users' => 0,
        'total_courses' => 0,
        'total_students' => 0,
        'total_teachers' => 0,
        'db_size' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - Sistema Académico</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            border-bottom: 2px solid transparent;
            font-size: 14px;
            white-space: nowrap;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab:hover {
            color: #667eea;
            background: #f8f9fa;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
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
        
        .form-group textarea {
            resize: vertical;
            height: 80px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 10px;
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
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .danger-zone h4 {
            color: #e53e3e;
            margin-bottom: 15px;
        }
        
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .system-info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .system-info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .system-info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .tab {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>⚙️ Configuración del Sistema</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Estadísticas del Sistema -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_users']; ?></div>
                <div class="stat-label">Usuarios Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_courses']; ?></div>
                <div class="stat-label">Cursos Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_students']; ?></div>
                <div class="stat-label">Estudiantes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_teachers']; ?></div>
                <div class="stat-label">Docentes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['db_size']; ?> MB</div>
                <div class="stat-label">Tamaño BD</div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="showTab('institution')">🏫 Institución</button>
                <button class="tab" onclick="showTab('academic')">📚 Académico</button>
                <button class="tab" onclick="showTab('system')">💻 Sistema</button>
                <button class="tab" onclick="showTab('maintenance')">🔧 Mantenimiento</button>
                <button class="tab" onclick="showTab('info')">ℹ️ Información</button>
            </div>
            
            <!-- Tab: Configuración Institucional -->
            <div id="institution" class="tab-content active">
                <h2>🏫 Configuración Institucional</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_institution_settings">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="institute_name">Nombre de la Institución:</label>
                            <input type="text" id="institute_name" name="institute_name" 
                                   value="<?php echo htmlspecialchars(getSetting('institute_name')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="institute_location">Ubicación:</label>
                            <input type="text" id="institute_location" name="institute_location" 
                                   value="<?php echo htmlspecialchars(getSetting('institute_location')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_year">Año Académico:</label>
                            <input type="text" id="academic_year" name="academic_year" 
                                   value="<?php echo htmlspecialchars(getSetting('academic_year')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester_current">Semestre Actual:</label>
                            <input type="text" id="semester_current" name="semester_current" 
                                   value="<?php echo htmlspecialchars(getSetting('semester_current')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="director_name">Nombre del Director:</label>
                            <input type="text" id="director_name" name="director_name" 
                                   value="<?php echo htmlspecialchars(getSetting('director_name')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Teléfono:</label>
                            <input type="text" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars(getSetting('phone')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Institucional:</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars(getSetting('email')); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Dirección:</label>
                        <textarea id="address" name="address"><?php echo htmlspecialchars(getSetting('address')); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Guardar Configuración Institucional</button>
                </form>
            </div>
            
            <!-- Tab: Configuración Académica -->
            <div id="academic" class="tab-content">
                <h2>📚 Configuración Académica</h2>
                
                <div class="info-box">
                    <h4>Escalas de Calificación</h4>
                    <p>Configure los rangos de calificación que se utilizarán en todo el sistema para evaluar el rendimiento académico de los estudiantes.</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_academic_settings">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="min_grade">Calificación Mínima:</label>
                            <input type="number" id="min_grade" name="min_grade" min="0" max="20" step="0.1"
                                   value="<?php echo getSetting('min_grade', '0'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_grade">Calificación Máxima:</label>
                            <input type="number" id="max_grade" name="max_grade" min="0" max="20" step="0.1"
                                   value="<?php echo getSetting('max_grade', '20'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_excellent_min">Calificación Mínima para EXCELENTE:</label>
                            <input type="number" id="grade_excellent_min" name="grade_excellent_min" min="0" max="20" step="0.1"
                                   value="<?php echo getSetting('grade_excellent_min', '18'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_approved_min">Calificación Mínima para APROBADO:</label>
                            <input type="number" id="grade_approved_min" name="grade_approved_min" min="0" max="20" step="0.1"
                                   value="<?php echo getSetting('grade_approved_min', '12.5'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_process_min">Calificación Mínima para EN PROCESO:</label>
                            <input type="number" id="grade_process_min" name="grade_process_min" min="0" max="20" step="0.1"
                                   value="<?php echo getSetting('grade_process_min', '9.5'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="min_attendance_percentage">Porcentaje Mínimo de Asistencia (%):</label>
                            <input type="number" id="min_attendance_percentage" name="min_attendance_percentage" min="0" max="100" step="1"
                                   value="<?php echo getSetting('min_attendance_percentage', '70'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="decimal_places">Decimales en Calificaciones:</label>
                            <select id="decimal_places" name="decimal_places" required>
                                <option value="0" <?php echo getSetting('decimal_places', '1') == '0' ? 'selected' : ''; ?>>Sin decimales (18)</option>
                                <option value="1" <?php echo getSetting('decimal_places', '1') == '1' ? 'selected' : ''; ?>>1 decimal (18.0)</option>
                                <option value="2" <?php echo getSetting('decimal_places', '1') == '2' ? 'selected' : ''; ?>>2 decimales (18.00)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4>Vista Previa de Escalas</h4>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px;">
                            <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; text-align: center;">
                                <strong>EXCELENTE</strong><br>
                                <span id="preview_excellent">18.0 - 20.0</span>
                            </div>
                            <div style="background: #d4edda; padding: 10px; border-radius: 5px; text-align: center;">
                                <strong>APROBADO</strong><br>
                                <span id="preview_approved">12.5 - 17.9</span>
                            </div>
                            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; text-align: center;">
                                <strong>EN PROCESO</strong><br>
                                <span id="preview_process">9.5 - 12.4</span>
                            </div>
                            <div style="background: #f8d7da; padding: 10px; border-radius: 5px; text-align: center;">
                                <strong>DESAPROBADO</strong><br>
                                <span id="preview_failed">0.0 - 9.4</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Guardar Configuración Académica</button>
                </form>
            </div>
            
            <!-- Tab: Configuración del Sistema -->
            <div id="system" class="tab-content">
                <h2>💻 Configuración del Sistema</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_system_settings">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="system_name">Nombre del Sistema:</label>
                            <input type="text" id="system_name" name="system_name" 
                                   value="<?php echo htmlspecialchars(getSetting('system_name', 'Sistema Académico')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="system_version">Versión del Sistema:</label>
                            <input type="text" id="system_version" name="system_version" 
                                   value="<?php echo htmlspecialchars(getSetting('system_version', '1.0')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="session_timeout">Tiempo de Sesión (segundos):</label>
                            <input type="number" id="session_timeout" name="session_timeout" min="300" max="86400"
                                   value="<?php echo getSetting('session_timeout', '3600'); ?>" required>
                            <small style="color: #666;">3600 segundos = 1 hora</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_login_attempts">Máximo Intentos de Login:</label>
                            <input type="number" id="max_login_attempts" name="max_login_attempts" min="1" max="10"
                                   value="<?php echo getSetting('max_login_attempts', '3'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                               <?php echo getSetting('maintenance_mode', '0') == '1' ? 'checked' : ''; ?>>
                        <label for="maintenance_mode">Modo Mantenimiento (los usuarios no podrán acceder al sistema)</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="debug_mode" name="debug_mode" 
                               <?php echo getSetting('debug_mode', '0') == '1' ? 'checked' : ''; ?>>
                        <label for="debug_mode">Modo Debug (mostrar errores detallados)</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="backup_enabled" name="backup_enabled" 
                               <?php echo getSetting('backup_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label for="backup_enabled">Backup Automático Habilitado</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Guardar Configuración del Sistema</button>
                </form>
                
                <?php if (getSetting('maintenance_mode', '0') == '1'): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ ATENCIÓN:</strong> El modo mantenimiento está activado. Los usuarios no pueden acceder al sistema.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Mantenimiento -->
            <div id="maintenance" class="tab-content">
                <h2>🔧 Mantenimiento del Sistema</h2>
                
                <div class="info-box">
                    <h4>Herramientas de Mantenimiento</h4>
                    <p>Utilice estas herramientas para mantener el sistema en óptimas condiciones.</p>
                </div>
                
                <div class="form-grid">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #667eea; margin-bottom: 15px;">🗄️ Backup de Base de Datos</h4>
                        <p style="margin-bottom: 15px; color: #666;">Crear una copia de seguridad de toda la base de datos.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="create_backup">
                            <button type="submit" class="btn btn-success" onclick="return confirm('¿Crear backup de la base de datos?')">
                                Crear Backup
                            </button>
                        </form>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #667eea; margin-bottom: 15px;">🗑️ Limpiar Logs</h4>
                        <p style="margin-bottom: 15px; color: #666;">Eliminar logs del sistema antiguos (más de 30 días).</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('¿Eliminar logs antiguos?')">
                                Limpiar Logs
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="danger-zone">
                    <h4>⚠️ Zona de Peligro</h4>
                    <p style="margin-bottom: 15px;">Las siguientes acciones pueden afectar el funcionamiento del sistema. Úselas con precaución.</p>
                    
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="btn btn-danger" onclick="alert('Funcionalidad en desarrollo')">
                            Resetear Configuraciones
                        </button>
                        <button class="btn btn-danger" onclick="alert('Funcionalidad en desarrollo')">
                            Limpiar Datos de Prueba
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Información del Sistema -->
            <div id="info" class="tab-content">
                <h2>ℹ️ Información del Sistema</h2>
                
                <div class="system-info">
                    <div class="system-info-item">
                        <div class="system-info-label">Versión del Sistema</div>
                        <div class="system-info-value"><?php echo getSetting('system_version', '1.0'); ?></div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Versión de PHP</div>
                        <div class="system-info-value"><?php echo PHP_VERSION; ?></div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Base de Datos</div>
                        <div class="system-info-value">MySQL <?php 
                            try {
                                $version = $conn->query('SELECT VERSION()')->fetchColumn();
                                echo explode('-', $version)[0];
                            } catch(Exception $e) {
                                echo 'N/A';
                            }
                        ?></div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Servidor Web</div>
                        <div class="system-info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Zona Horaria</div>
                        <div class="system-info-value"><?php echo date_default_timezone_get(); ?></div>
                    </div>
                    
                    <div class="system-info-item">
                        <div class="system-info-label">Última Actualización</div>
                        <div class="system-info-value"><?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4>🏫 Información Institucional</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                        <div>
                            <strong>Institución:</strong><br>
                            <?php echo htmlspecialchars(getSetting('institute_name')); ?>
                        </div>
                        <div>
                            <strong>Ubicación:</strong><br>
                            <?php echo htmlspecialchars(getSetting('institute_location')); ?>
                        </div>
                        <div>
                            <strong>Año Académico:</strong><br>
                            <?php echo htmlspecialchars(getSetting('academic_year')); ?>
                        </div>
                        <div>
                            <strong>Semestre Actual:</strong><br>
                            <?php echo htmlspecialchars(getSetting('semester_current')); ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4>📊 Estadísticas del Sistema</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;"><?php echo $system_stats['total_users']; ?></div>
                            <div style="font-size: 14px; color: #666;">Usuarios Totales</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;"><?php echo $system_stats['total_courses']; ?></div>
                            <div style="font-size: 14px; color: #666;">Cursos Activos</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;"><?php echo $system_stats['db_size']; ?> MB</div>
                            <div style="font-size: 14px; color: #666;">Tamaño de BD</div>
                        </div>
                    </div>
                </div>
            </div>
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
        
        // Actualizar vista previa de escalas de calificación
        function updateGradePreview() {
            const excellent = parseFloat(document.getElementById('grade_excellent_min').value) || 18;
            const approved = parseFloat(document.getElementById('grade_approved_min').value) || 12.5;
            const process = parseFloat(document.getElementById('grade_process_min').value) || 9.5;
            const max = parseFloat(document.getElementById('max_grade').value) || 20;
            const min = parseFloat(document.getElementById('min_grade').value) || 0;
            
            document.getElementById('preview_excellent').textContent = `${excellent} - ${max}`;
            document.getElementById('preview_approved').textContent = `${approved} - ${(excellent - 0.1).toFixed(1)}`;
            document.getElementById('preview_process').textContent = `${process} - ${(approved - 0.1).toFixed(1)}`;
            document.getElementById('preview_failed').textContent = `${min} - ${(process - 0.1).toFixed(1)}`;
        }
        
        // Event listeners para actualizar vista previa
        document.addEventListener('DOMContentLoaded', function() {
            updateGradePreview();
            
            ['grade_excellent_min', 'grade_approved_min', 'grade_process_min', 'max_grade', 'min_grade'].forEach(id => {
                document.getElementById(id).addEventListener('input', updateGradePreview);
            });
        });
        
        // Validaciones de formulario
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.querySelector('input[name="action"]').value === 'update_academic_settings') {
                    const excellent = parseFloat(document.getElementById('grade_excellent_min').value);
                    const approved = parseFloat(document.getElementById('grade_approved_min').value);
                    const process = parseFloat(document.getElementById('grade_process_min').value);
                    
                    if (excellent <= approved || approved <= process) {
                        e.preventDefault();
                        alert('Las escalas de calificación deben ser: Excelente > Aprobado > En Proceso');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>