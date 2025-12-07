<?php
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
            
            if ($_POST['action'] == 'create_program') {
                $nombre = sanitizeInput($_POST['nombre']);
                $codigo = sanitizeInput($_POST['codigo']);
                $descripcion = sanitizeInput($_POST['descripcion']);
                $duracion_semestres = (int)$_POST['duracion_semestres'];
                $modalidad = sanitizeInput($_POST['modalidad']);
                $estado = sanitizeInput($_POST['estado']);
                
                if (empty($nombre) || empty($codigo)) {
                    $message = '<div class="alert alert-error">El nombre y código son obligatorios.</div>';
                } else {
                    $query = "INSERT INTO programas_estudio (nombre, codigo, descripcion, duracion_semestres, modalidad, estado) 
                             VALUES (:nombre, :codigo, :descripcion, :duracion_semestres, :modalidad, :estado)";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':codigo', $codigo);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':duracion_semestres', $duracion_semestres);
                    $stmt->bindParam(':modalidad', $modalidad);
                    $stmt->bindParam(':estado', $estado);
                    
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Programa de estudio creado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error">Error al crear el programa. El código puede estar duplicado.</div>';
                    }
                }
                
            } elseif ($_POST['action'] == 'update_program') {
                $id = (int)$_POST['program_id'];
                $nombre = sanitizeInput($_POST['nombre']);
                $codigo = sanitizeInput($_POST['codigo']);
                $descripcion = sanitizeInput($_POST['descripcion']);
                $duracion_semestres = (int)$_POST['duracion_semestres'];
                $modalidad = sanitizeInput($_POST['modalidad']);
                $estado = sanitizeInput($_POST['estado']);
                
                if (empty($nombre) || empty($codigo)) {
                    $message = '<div class="alert alert-error">El nombre y código son obligatorios.</div>';
                } else {
                    $query = "UPDATE programas_estudio SET nombre = :nombre, codigo = :codigo, descripcion = :descripcion, 
                             duracion_semestres = :duracion_semestres, modalidad = :modalidad, estado = :estado 
                             WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':nombre', $nombre);
                    $stmt->bindParam(':codigo', $codigo);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':duracion_semestres', $duracion_semestres);
                    $stmt->bindParam(':modalidad', $modalidad);
                    $stmt->bindParam(':estado', $estado);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Programa actualizado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error">Error al actualizar el programa.</div>';
                    }
                }
                
            } elseif ($_POST['action'] == 'toggle_status') {
                $id = (int)$_POST['program_id'];
                $new_status = sanitizeInput($_POST['new_status']);
                
                $query = "UPDATE programas_estudio SET estado = :estado WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':estado', $new_status);
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $status_text = $new_status == 'activo' ? 'activado' : 'desactivado';
                    $message = '<div class="alert alert-success">Programa ' . $status_text . ' exitosamente.</div>';
                } else {
                    $message = '<div class="alert alert-error">Error al cambiar el estado del programa.</div>';
                }
                
            } elseif ($_POST['action'] == 'delete_program') {
                $id = (int)$_POST['program_id'];
                
                // Verificar si tiene cursos asociados
                $query = "SELECT COUNT(*) FROM unidades_didacticas WHERE programa_id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $course_count = $stmt->fetchColumn();
                
                if ($course_count > 0) {
                    $message = '<div class="alert alert-error">No se puede eliminar el programa porque tiene ' . $course_count . ' curso(s) asociado(s). Primero elimine o reasigne los cursos.</div>';
                } else {
                    $query = "DELETE FROM programas_estudio WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Programa eliminado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error">Error al eliminar el programa.</div>';
                    }
                }
            }
            
        } catch(PDOException $e) {
            $message = '<div class="alert alert-error">Error de base de datos: ' . $e->getMessage() . '</div>';
        }
    }
}

// Agregar columnas faltantes si no existen
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar y agregar columnas
    $columns_to_add = [
        'descripcion' => 'ALTER TABLE programas_estudio ADD COLUMN descripcion TEXT',
        'duracion_semestres' => 'ALTER TABLE programas_estudio ADD COLUMN duracion_semestres INT DEFAULT 6',
        'modalidad' => 'ALTER TABLE programas_estudio ADD COLUMN modalidad ENUM("presencial", "virtual", "semipresencial") DEFAULT "presencial"'
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        try {
            $conn->exec($sql);
        } catch(PDOException $e) {
            // Columna ya existe, continúar
        }
    }
} catch(PDOException $e) {
    // Error al agregar columnas
}

// Obtener datos de programas con estadísticas
try {
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    
    $query = "SELECT 
                p.*,
                COUNT(DISTINCT ud.id) as total_cursos,
                COUNT(DISTINCT CASE WHEN ud.estado = 'activo' THEN ud.id END) as cursos_activos,
                COUNT(DISTINCT m.id) as total_matriculas,
                COUNT(DISTINCT CASE WHEN m.estado = 'activo' THEN m.id END) as matriculas_activas
             FROM programas_estudio p
             LEFT JOIN unidades_didacticas ud ON p.id = ud.programa_id
             LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id
             WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (p.nombre LIKE :search OR p.codigo LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($filter_status)) {
        $query .= " AND p.estado = :status";
        $params[':status'] = $filter_status;
    }
    
    $query .= " GROUP BY p.id, p.nombre, p.codigo, p.descripcion, p.duracion_semestres, p.modalidad, p.estado
               ORDER BY p.nombre";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales
    $query = "SELECT 
                COUNT(*) as total_programas,
                COUNT(CASE WHEN estado = 'activo' THEN 1 END) as programas_activos,
                COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as programas_inactivos
             FROM programas_estudio";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $programs = [];
    $general_stats = ['total_programas' => 0, 'programas_activos' => 0, 'programas_inactivos' => 0];
    $message = '<div class="alert alert-error">Error al cargar programas: ' . $e->getMessage() . '</div>';
}

// Obtener programa específico para edición
$edit_program = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $query = "SELECT * FROM programas_estudio WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $edit_id);
        $stmt->execute();
        $edit_program = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $edit_program = null;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Programas de Estudio - Sistema Académico</title>
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
        
        .search-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .search-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .search-group input,
        .search-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 10px;
            margin-bottom: 5px;
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
        
        .programs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .programs-table th,
        .programs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .programs-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }
        
        .programs-table tr:hover {
            background-color: #f8f9fa;
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
        
        .modalidad-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        
        .modalidad-presencial {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .modalidad-virtual {
            background: #d4edda;
            color: #155724;
        }
        
        .modalidad-semipresencial {
            background: #fff3cd;
            color: #856404;
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
        
        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .program-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .program-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            border-bottom: 2px solid transparent;
            font-size: 14px;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-filters {
                flex-direction: column;
            }
            
            .search-group {
                min-width: auto;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .programs-table {
                font-size: 12px;
            }
            
            .programs-table th,
            .programs-table td {
                padding: 8px 4px;
            }
            
            .program-stats {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📚 Gestión de Programas de Estudio</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $general_stats['total_programas']; ?></div>
                <div class="stat-label">Total Programas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $general_stats['programas_activos']; ?></div>
                <div class="stat-label">Programas Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $general_stats['programas_inactivos']; ?></div>
                <div class="stat-label">Programas Inactivos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($programs, 'cursos_activos')); ?></div>
                <div class="stat-label">Cursos Activos</div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="showTab('list')">📋 Lista de Programas</button>
                <button class="tab" onclick="showTab('create')">➕ <?php echo $edit_program ? 'Editar' : 'Crear'; ?> Programa</button>
            </div>
            
            <!-- Tab: Lista de Programas -->
            <div id="list" class="tab-content active">
                <h2>📋 Programas de Estudio Registrados</h2>
                
                <!-- Filtros de Búsqueda -->
                <form method="GET" class="search-filters">
                    <div class="search-group">
                        <label for="search">Buscar:</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nombre o código del programa">
                    </div>
                    
                    <div class="search-group">
                        <label for="status">Estado:</label>
                        <select id="status" name="status">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $filter_status == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $filter_status == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="search-group">
                        <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                        <a href="?" class="btn btn-secondary">🔄 Limpiar</a>
                    </div>
                </form>
                
                <?php if (empty($programs)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3>No hay programas de estudio registrados</h3>
                        <p>Crea el primer programa de estudio para comenzar.</p>
                        <button onclick="showTab('create')" class="btn btn-success">Crear Primer Programa</button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="programs-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre del Programa</th>
                                    <th>Duración</th>
                                    <th>Modalidad</th>
                                    <th>Estado</th>
                                    <th>Estadísticas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programs as $program): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($program['codigo']); ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($program['nombre']); ?></strong>
                                                <?php if (!empty($program['descripcion'])): ?>
                                                    <br><small style="color: #666;"><?php echo htmlspecialchars(substr($program['descripcion'], 0, 100)); ?><?php echo strlen($program['descripcion']) > 100 ? '...' : ''; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $program['duracion_semestres'] ?: 'N/A'; ?> semestres
                                        </td>
                                        <td>
                                            <span class="modalidad-badge modalidad-<?php echo $program['modalidad'] ?: 'presencial'; ?>">
                                                <?php echo ucfirst($program['modalidad'] ?: 'Presencial'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $program['estado']; ?>">
                                                <?php echo ucfirst($program['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="program-stats">
                                                <div class="program-stat">
                                                    📚 <strong><?php echo $program['cursos_activos']; ?></strong> / <?php echo $program['total_cursos']; ?> cursos
                                                </div>
                                                <div class="program-stat">
                                                    👥 <strong><?php echo $program['matriculas_activas']; ?></strong> estudiantes
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="?edit=<?php echo $program['id']; ?>&tab=create" class="btn btn-primary btn-small">
                                                ✏️ Editar
                                            </a>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $program['estado'] == 'activo' ? 'inactivo' : 'activo'; ?>">
                                                <button type="submit" class="btn <?php echo $program['estado'] == 'activo' ? 'btn-warning' : 'btn-success'; ?> btn-small">
                                                    <?php echo $program['estado'] == 'activo' ? '⏸️ Desactivar' : '▶️ Activar'; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if ($program['total_cursos'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar este programa? Esta acción no se puede deshacer.')">
                                                    <input type="hidden" name="action" value="delete_program">
                                                    <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        🗑️ Eliminar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-small" disabled title="No se puede eliminar un programa con cursos asociados">
                                                    🔒 Eliminar
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Crear/Editar Programa -->
            <div id="create" class="tab-content">
                <h2><?php echo $edit_program ? '✏️ Editar' : '➕ Crear'; ?> Programa de Estudio</h2>
                
                <?php if ($edit_program): ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                        <strong>Editando:</strong> <?php echo htmlspecialchars($edit_program['nombre']); ?>
                        <a href="?" class="btn btn-secondary" style="float: right; margin-top: -5px;">Cancelar Edición</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_program ? 'update_program' : 'create_program'; ?>">
                    <?php if ($edit_program): ?>
                        <input type="hidden" name="program_id" value="<?php echo $edit_program['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="codigo">Código del Programa <span class="required">*</span></label>
                            <input type="text" 
                                   id="codigo" 
                                   name="codigo" 
                                   value="<?php echo $edit_program ? htmlspecialchars($edit_program['codigo']) : ''; ?>"
                                   placeholder="Ej: FARM001, ENFE002"
                                   style="text-transform: uppercase;"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duracion_semestres">Duración (Semestres)</label>
                            <select id="duracion_semestres" name="duracion_semestres">
                                <option value="6" <?php echo ($edit_program && $edit_program['duracion_semestres'] == 6) || (!$edit_program) ? 'selected' : ''; ?>>6 semestres</option>
                                <option value="4" <?php echo ($edit_program && $edit_program['duracion_semestres'] == 4) ? 'selected' : ''; ?>>4 semestres</option>
                                <option value="8" <?php echo ($edit_program && $edit_program['duracion_semestres'] == 8) ? 'selected' : ''; ?>>8 semestres</option>
                                <option value="10" <?php echo ($edit_program && $edit_program['duracion_semestres'] == 10) ? 'selected' : ''; ?>>10 semestres</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="modalidad">Modalidad</label>
                            <select id="modalidad" name="modalidad">
                                <option value="presencial" <?php echo (!$edit_program || $edit_program['modalidad'] == 'presencial') ? 'selected' : ''; ?>>Presencial</option>
                                <option value="virtual" <?php echo ($edit_program && $edit_program['modalidad'] == 'virtual') ? 'selected' : ''; ?>>Virtual</option>
                                <option value="semipresencial" <?php echo ($edit_program && $edit_program['modalidad'] == 'semipresencial') ? 'selected' : ''; ?>>Semipresencial</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="activo" <?php echo (!$edit_program || $edit_program['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo ($edit_program && $edit_program['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre del Programa <span class="required">*</span></label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               value="<?php echo $edit_program ? htmlspecialchars($edit_program['nombre']) : ''; ?>"
                               placeholder="Ej: Técnica en Farmacia, Técnica en Enfermería"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción del Programa</label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  placeholder="Descripción detallada del programa de estudio, objetivos, perfil profesional, etc."><?php echo $edit_program ? htmlspecialchars($edit_program['descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 12px 30px;">
                            <?php echo $edit_program ? '💾 Actualizar Programa' : '➕ Crear Programa'; ?>
                        </button>
                        
                        <?php if ($edit_program): ?>
                            <a href="?" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
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
            
            // Update tab title if editing
            if (tabName === 'create') {
                const isEditing = <?php echo $edit_program ? 'true' : 'false'; ?>;
                const tab = document.querySelector('.tab:nth-child(2)');
                tab.textContent = isEditing ? '✏️ Editar Programa' : '➕ Crear Programa';
            }
        }
        
        // Auto-capitalize program code
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo').value.trim();
            const nombre = document.getElementById('nombre').value.trim();
            
            if (!codigo || !nombre) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios (código y nombre).');
                return false;
            }
            
            if (codigo.length < 3) {
                e.preventDefault();
                alert('El código debe tener al menos 3 caracteres.');
                return false;
            }
        });
        
        // Check URL parameters to show appropriate tab
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            const edit = urlParams.get('edit');
            
            if (tab === 'create' || edit) {
                showTab('create');
                // Update tab button
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelector('.tab:nth-child(2)').classList.add('active');
            }
        });
        
        // Auto-submit search form on input change (with delay)
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length === 0 || this.value.length >= 3) {
                    this.form.submit();
                }
            }, 500);
        });
        
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>