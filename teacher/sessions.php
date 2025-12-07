<?php
// Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación básica
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'iespaltohuallaga_regauxiliar_bd';
$username = 'iespaltohuallaga_user_regaux'; // Ajustar según tu configuración
$password = ')wBRCeID[ldb%b^K';     // Ajustar según tu configuración

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';

$message = '';

// Función para sanitizar entrada
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función para validar fecha
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Función para generar número de sesión automático
function getNextSessionNumber($pdo, $course_id) {
    try {
        $query = "SELECT MAX(numero_sesion) as max_num FROM sesiones WHERE unidad_didactica_id = :course_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_num'] ?? 0) + 1;
    } catch (PDOException $e) {
        return 1;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action == 'create_session') {
                $unidad_didactica_id = (int)$_POST['unidad_didactica_id'];
                $titulo = sanitizeInput($_POST['titulo']);
                $fecha = $_POST['fecha'];
                $descripcion = sanitizeInput($_POST['descripcion']);
                $numero_sesion = isset($_POST['numero_sesion']) && !empty($_POST['numero_sesion']) ? 
                    (int)$_POST['numero_sesion'] : getNextSessionNumber($pdo, $unidad_didactica_id);
                
                // Validaciones
                if (empty($titulo)) {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> El título es obligatorio.</div>';
                } elseif (!validateDate($fecha)) {
                    $message = '<div class="alert alert-error"><i class="fas fa-calendar-times"></i> Fecha inválida.</div>';
                } else {
                    // Verificar que el curso pertenece al docente
                    $query = "SELECT id FROM unidades_didacticas WHERE id = :id AND docente_id = :docente_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':id', $unidad_didactica_id);
                    $stmt->bindParam(':docente_id', $user_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Verificar que no exista ya una sesión con el mismo número
                        $query = "SELECT id FROM sesiones WHERE unidad_didactica_id = :course_id AND numero_sesion = :numero";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindParam(':course_id', $unidad_didactica_id);
                        $stmt->bindParam(':numero', $numero_sesion);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Ya existe una sesión con el número ' . $numero_sesion . '.</div>';
                        } else {
                            // Crear sesión
                            $query = "INSERT INTO sesiones (unidad_didactica_id, numero_sesion, titulo, fecha, descripcion, estado) 
                                     VALUES (:unidad_didactica_id, :numero_sesion, :titulo, :fecha, :descripcion, 'programada')";
                            $stmt = $pdo->prepare($query);
                            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                            $stmt->bindParam(':numero_sesion', $numero_sesion);
                            $stmt->bindParam(':titulo', $titulo);
                            $stmt->bindParam(':fecha', $fecha);
                            $stmt->bindParam(':descripcion', $descripcion);
                            
                            if ($stmt->execute()) {
                                $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Sesión <strong>' . $numero_sesion . ': ' . htmlspecialchars($titulo) . '</strong> creada exitosamente.</div>';
                            } else {
                                $message = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> Error al crear la sesión.</div>';
                            }
                        }
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-lock"></i> No tiene permisos para crear sesiones en este curso.</div>';
                    }
                }
            } elseif ($action == 'update_session') {
                $session_id = (int)$_POST['session_id'];
                $titulo = sanitizeInput($_POST['titulo']);
                $fecha = $_POST['fecha'];
                $descripcion = sanitizeInput($_POST['descripcion']);
                $estado = $_POST['estado'];
                
                // Validaciones
                if (empty($titulo)) {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> El título es obligatorio.</div>';
                } elseif (!validateDate($fecha)) {
                    $message = '<div class="alert alert-error"><i class="fas fa-calendar-times"></i> Fecha inválida.</div>';
                } elseif (!in_array($estado, ['programada', 'realizada', 'cancelada'])) {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Estado inválido.</div>';
                } else {
                    // Verificar permisos
                    $query = "SELECT s.id FROM sesiones s 
                             JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                             WHERE s.id = :session_id AND ud.docente_id = :docente_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':session_id', $session_id);
                    $stmt->bindParam(':docente_id', $user_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Actualizar sesión
                        $query = "UPDATE sesiones SET titulo = :titulo, fecha = :fecha, descripcion = :descripcion, estado = :estado 
                                 WHERE id = :id";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindParam(':titulo', $titulo);
                        $stmt->bindParam(':fecha', $fecha);
                        $stmt->bindParam(':descripcion', $descripcion);
                        $stmt->bindParam(':estado', $estado);
                        $stmt->bindParam(':id', $session_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Sesión actualizada exitosamente.</div>';
                        } else {
                            $message = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> Error al actualizar la sesión.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-lock"></i> No tiene permisos para modificar esta sesión.</div>';
                    }
                }
            } elseif ($action == 'delete_session') {
                $session_id = (int)$_POST['session_id'];
                
                // Verificar permisos
                $query = "SELECT s.id, s.numero_sesion, s.titulo FROM sesiones s 
                         JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                         WHERE s.id = :session_id AND ud.docente_id = :docente_id";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':session_id', $session_id);
                $stmt->bindParam(':docente_id', $user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Verificar si tiene asistencias registradas
                    $query = "SELECT COUNT(*) as count FROM asistencias WHERE sesion_id = :session_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':session_id', $session_id);
                    $stmt->execute();
                    $attendance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($attendance_count > 0) {
                        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No se puede eliminar la sesión porque tiene asistencias registradas. Puede cancelarla en su lugar.</div>';
                    } else {
                        // Eliminar sesión
                        $query = "DELETE FROM sesiones WHERE id = :id";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindParam(':id', $session_id);
                        
                        if ($stmt->execute()) {
                            $message = '<div class="alert alert-success"><i class="fas fa-trash"></i> Sesión <strong>' . $session_info['numero_sesion'] . ': ' . htmlspecialchars($session_info['titulo']) . '</strong> eliminada exitosamente.</div>';
                        } else {
                            $message = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> Error al eliminar la sesión.</div>';
                        }
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-lock"></i> No tiene permisos para eliminar esta sesión.</div>';
                }
            } elseif ($action == 'bulk_update_status') {
                $selected_sessions = $_POST['selected_sessions'] ?? [];
                $new_status = $_POST['new_status'];
                
                if (empty($selected_sessions)) {
                    $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Debe seleccionar al menos una sesión.</div>';
                } elseif (!in_array($new_status, ['programada', 'realizada', 'cancelada'])) {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Estado inválido.</div>';
                } else {
                    $success_count = 0;
                    $error_count = 0;
                    
                    foreach ($selected_sessions as $session_id) {
                        $session_id = (int)$session_id;
                        
                        // Verificar permisos
                        $query = "SELECT s.id FROM sesiones s 
                                 JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                                 WHERE s.id = :session_id AND ud.docente_id = :docente_id";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindParam(':session_id', $session_id);
                        $stmt->bindParam(':docente_id', $user_id);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            $query = "UPDATE sesiones SET estado = :estado WHERE id = :id";
                            $stmt = $pdo->prepare($query);
                            $stmt->bindParam(':estado', $new_status);
                            $stmt->bindParam(':id', $session_id);
                            
                            if ($stmt->execute()) {
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        } else {
                            $error_count++;
                        }
                    }
                    
                    if ($success_count > 0 && $error_count == 0) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Estado actualizado exitosamente en ' . $success_count . ' sesión(es).</div>';
                    } elseif ($success_count > 0 && $error_count > 0) {
                        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Estado actualizado en ' . $success_count . ' sesión(es). ' . $error_count . ' error(es).</div>';
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> No se pudo actualizar el estado de ninguna sesión.</div>';
                    }
                }
            }
        }
    } catch(PDOException $e) {
        $message = '<div class="alert alert-error"><i class="fas fa-database"></i> Error de base de datos: ' . $e->getMessage() . '</div>';
    }
}

// Obtener datos
$myCourses = [];
$selectedCourse = null;
$sessions = [];
$courseStats = [];

try {
    // Obtener cursos del docente
    $query = "SELECT ud.*, p.nombre as programa_nombre FROM unidades_didacticas ud 
             JOIN programas_estudio p ON ud.programa_id = p.id 
             WHERE ud.docente_id = :docente_id AND ud.estado = 'activo'
             ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':docente_id', $user_id);
    $stmt->execute();
    $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (isset($_GET['course_id'])) {
        $course_id = (int)$_GET['course_id'];
        
        // Verificar que el curso pertenece al docente
        $query = "SELECT ud.*, p.nombre as programa_nombre FROM unidades_didacticas ud 
                 JOIN programas_estudio p ON ud.programa_id = p.id 
                 WHERE ud.id = :id AND ud.docente_id = :docente_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $course_id);
        $stmt->bindParam(':docente_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Filtros
            $date_filter = $_GET['date'] ?? '';
            $status_filter = $_GET['status'] ?? '';
            $search = $_GET['search'] ?? '';
            
            // Construir query con filtros
            $whereClause = "WHERE s.unidad_didactica_id = :course_id";
            $params = [':course_id' => $course_id];
            
            if (!empty($date_filter)) {
                $whereClause .= " AND DATE(s.fecha) = :date";
                $params[':date'] = $date_filter;
            }
            
            if (!empty($status_filter)) {
                $whereClause .= " AND s.estado = :status";
                $params[':status'] = $status_filter;
            }
            
            if (!empty($search)) {
                $whereClause .= " AND (s.titulo LIKE :search OR s.descripcion LIKE :search OR s.numero_sesion LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            // Obtener sesiones con filtros
            $query = "SELECT s.*, 
                            COUNT(a.id) as total_asistencias,
                            COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) as asistencias_presentes,
                            COUNT(CASE WHEN a.estado = 'falta' THEN 1 END) as faltas,
                            COUNT(CASE WHEN a.estado = 'permiso' THEN 1 END) as permisos
                     FROM sesiones s
                     LEFT JOIN asistencias a ON s.id = a.sesion_id
                     $whereClause
                     GROUP BY s.id
                     ORDER BY s.numero_sesion ASC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular estadísticas del curso
            $query = "SELECT 
                        COUNT(*) as total_sesiones,
                        COUNT(CASE WHEN estado = 'programada' THEN 1 END) as sesiones_programadas,
                        COUNT(CASE WHEN estado = 'realizada' THEN 1 END) as sesiones_realizadas,
                        COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) as sesiones_canceladas
                      FROM sesiones 
                      WHERE unidad_didactica_id = :course_id";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $courseStats = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $message = '<div class="alert alert-error"><i class="fas fa-database"></i> Error al cargar datos: ' . $e->getMessage() . '</div>';
}

// Obtener sesión para editar
$editSession = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    
    try {
        $query = "SELECT s.* FROM sesiones s 
                 JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                 WHERE s.id = :id AND ud.docente_id = :docente_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $edit_id);
        $stmt->bindParam(':docente_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $editSession = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        // Error al obtener sesión para editar
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Sesiones - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #022e61ff;
            --primary-dark: #0f1ea7ff;
            --secondary-color: #1411edff;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --info-color: #4299e1;
            --dark-color: #2d3748;
            --light-color: #f7fafc;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark-color);
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .header-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-title i {
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
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
            min-height: 80px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
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
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-info {
            background: var(--info-color);
            color: white;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-description {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .search-filter-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 2px solid var(--border-color);
        }
        
        .search-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .sessions-table th,
        .sessions-table td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sessions-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-weight: 600;
            color: var(--dark-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .sessions-table tr:hover {
            background-color: #f8fafc;
        }
        
        .sessions-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-programada {
            background: #cce7ff;
            color: #004085;
        }
        
        .status-realizada {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .session-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .course-info {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }
        
        .course-info h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .course-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .course-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .bulk-actions {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 2px solid var(--border-color);
        }
        
        .bulk-actions select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }
        
        .checkbox-custom {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 8px;
            font-size: 12px;
        }
        
        .attendance-item {
            text-align: center;
            padding: 4px;
            border-radius: 4px;
        }
        
        .attendance-presentes {
            background: #d4edda;
            color: #155724;
        }
        
        .attendance-faltas {
            background: #f8d7da;
            color: #721c24;
        }
        
        .attendance-permisos {
            background: #fff3cd;
            color: #856404;
        }
        
        @media (max-width: 1024px) {
            .search-filter-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .course-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .sessions-table {
                font-size: 12px;
            }
            
            .sessions-table th,
            .sessions-table td {
                padding: 8px 6px;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-calendar-plus"></i>
                <h1>Gestión de Sesiones</h1>
            </div>
            <a href="../dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>
        
        <!-- Selección de Curso -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-book-open"></i>
                    Seleccionar Unidad Didáctica
                </h2>
            </div>
            
            <?php if (empty($myCourses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <h3>No tiene unidades didácticas asignadas</h3>
                    <p>Contacte al administrador para que le asigne cursos.</p>
                </div>
            <?php else: ?>
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="course_id">Unidad Didáctica:</label>
                        <select name="course_id" id="course_id" onchange="this.form.submit()" required>
                            <option value="">Seleccione una unidad didáctica...</option>
                            <?php foreach ($myCourses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['nombre']) . ' - ' . $course['periodo_lectivo'] . ' (' . $course['periodo_academico'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($selectedCourse): ?>
            <!-- Información del Curso -->
            <div class="course-info">
                <h3><?php echo htmlspecialchars($selectedCourse['nombre']); ?></h3>
                <div class="course-details">
                    <div class="course-detail-item">
                        <i class="fas fa-graduation-cap"></i>
                        <span><strong>Programa:</strong> <?php echo htmlspecialchars($selectedCourse['programa_nombre']); ?></span>
                    </div>
                    <div class="course-detail-item">
                        <i class="fas fa-calendar"></i>
                        <span><strong>Período:</strong> <?php echo htmlspecialchars($selectedCourse['periodo_lectivo']); ?></span>
                    </div>
                    <div class="course-detail-item">
                        <i class="fas fa-user-tie"></i>
                        <span><strong>Docente:</strong> <?php echo htmlspecialchars($user_name); ?></span>
                    </div>
                    <div class="course-detail-item">
                        <i class="fas fa-code"></i>
                        <span><strong>Código:</strong> <?php echo htmlspecialchars($selectedCourse['codigo']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Sesiones</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $courseStats['total_sesiones'] ?? 0; ?></div>
                    <div class="stat-description">Sesiones creadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Programadas</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $courseStats['sesiones_programadas'] ?? 0; ?></div>
                    <div class="stat-description">Pendientes de realizar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Realizadas</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $courseStats['sesiones_realizadas'] ?? 0; ?></div>
                    <div class="stat-description">Sesiones completadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Canceladas</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $courseStats['sesiones_canceladas'] ?? 0; ?></div>
                    <div class="stat-description">Sesiones canceladas</div>
                </div>
            </div>
            
            <!-- Crear Nueva Sesión -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-plus-circle"></i>
                        <?php echo $editSession ? 'Editar Sesión' : 'Crear Nueva Sesión'; ?>
                    </h2>
                    <?php if ($editSession): ?>
                        <a href="?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-info">
                            <i class="fas fa-plus"></i>
                            Nueva Sesión
                        </a>
                    <?php endif; ?>
                </div>
                
                <form method="POST" id="sessionForm">
                    <input type="hidden" name="action" value="<?php echo $editSession ? 'update_session' : 'create_session'; ?>">
                    <input type="hidden" name="unidad_didactica_id" value="<?php echo $selectedCourse['id']; ?>">
                    <?php if ($editSession): ?>
                        <input type="hidden" name="session_id" value="<?php echo $editSession['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="numero_sesion">Número de Sesión:</label>
                            <input type="number" 
                                   id="numero_sesion" 
                                   name="numero_sesion" 
                                   min="1" 
                                   value="<?php echo $editSession ? $editSession['numero_sesion'] : ''; ?>"
                                   placeholder="<?php echo $editSession ? '' : 'Auto-asignado si se deja vacío'; ?>"
                                   <?php echo $editSession ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha">Fecha:</label>
                            <input type="date" 
                                   id="fecha" 
                                   name="fecha" 
                                   value="<?php echo $editSession ? $editSession['fecha'] : date('Y-m-d'); ?>"
                                   required>
                        </div>
                        
                        <?php if ($editSession): ?>
                            <div class="form-group">
                                <label for="estado">Estado:</label>
                                <select name="estado" id="estado" required>
                                    <option value="programada" <?php echo $editSession['estado'] == 'programada' ? 'selected' : ''; ?>>Programada</option>
                                    <option value="realizada" <?php echo $editSession['estado'] == 'realizada' ? 'selected' : ''; ?>>Realizada</option>
                                    <option value="cancelada" <?php echo $editSession['estado'] == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="titulo">Título de la Sesión:</label>
                        <input type="text" 
                               id="titulo" 
                               name="titulo" 
                               value="<?php echo $editSession ? htmlspecialchars($editSession['titulo']) : ''; ?>"
                               placeholder="Ej: Introducción a la Bioseguridad"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción (Opcional):</label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  placeholder="Descripción detallada de los temas a tratar..."><?php echo $editSession ? htmlspecialchars($editSession['descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            <?php echo $editSession ? 'Actualizar Sesión' : 'Crear Sesión'; ?>
                        </button>
                        
                        <?php if ($editSession): ?>
                            <a href="?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-info">
                                <i class="fas fa-times"></i>
                                Cancelar Edición
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Búsqueda y Filtros -->
            <div class="search-filter-section">
                <form method="GET" id="filterForm">
                    <input type="hidden" name="course_id" value="<?php echo $selectedCourse['id']; ?>">
                    <div class="search-filter-grid">
                        <div class="form-group">
                            <label for="search">Buscar sesión:</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Buscar por título, descripción o número..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date">Fecha:</label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Estado:</label>
                            <select name="status" id="status">
                                <option value="">Todos los estados</option>
                                <option value="programada" <?php echo ($_GET['status'] ?? '') == 'programada' ? 'selected' : ''; ?>>Programadas</option>
                                <option value="realizada" <?php echo ($_GET['status'] ?? '') == 'realizada' ? 'selected' : ''; ?>>Realizadas</option>
                                <option value="cancelada" <?php echo ($_GET['status'] ?? '') == 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Buscar
                            </button>
                            <a href="?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-info">
                                <i class="fas fa-undo"></i>
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Sesiones -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-list"></i>
                        Sesiones del Curso (<?php echo count($sessions); ?>)
                    </h2>
                    <div>
                        <button class="btn btn-info" onclick="exportToCSV()">
                            <i class="fas fa-download"></i>
                            Exportar CSV
                        </button>
                    </div>
                </div>
                
                <?php if (empty($sessions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No hay sesiones programadas</h3>
                        <p>Comience creando su primera sesión de clase.</p>
                    </div>
                <?php else: ?>
                    <!-- Acciones en lote -->
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="action" value="bulk_update_status">
                        <div class="bulk-actions">
                            <label>
                                <input type="checkbox" id="selectAll" class="checkbox-custom">
                                Seleccionar todos
                            </label>
                            
                            <select name="new_status" id="newStatus">
                                <option value="">Cambiar estado a...</option>
                                <option value="programada">Programada</option>
                                <option value="realizada">Realizada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                            
                            <button type="submit" class="btn btn-warning" onclick="return confirmBulkAction()">
                                <i class="fas fa-tasks"></i>
                                Aplicar cambios
                            </button>
                        </div>
                        
                        <div class="table-container">
                            <table class="sessions-table" id="sessionsTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAllHeader" class="checkbox-custom"></th>
                                        <th>Sesión</th>
                                        <th>Título</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Asistencias</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_sessions[]" value="<?php echo $session['id']; ?>" class="checkbox-custom session-checkbox">
                                            </td>
                                            <td>
                                                <span class="session-number"><?php echo $session['numero_sesion']; ?></span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($session['titulo']); ?></strong></td>
                                            <td><?php echo date('d/m/Y', strtotime($session['fecha'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $session['estado']; ?>">
                                                    <?php echo ucfirst($session['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($session['total_asistencias'] > 0): ?>
                                                    <div class="attendance-summary">
                                                        <div class="attendance-item attendance-presentes">
                                                            P: <?php echo $session['asistencias_presentes']; ?>
                                                        </div>
                                                        <div class="attendance-item attendance-faltas">
                                                            F: <?php echo $session['faltas']; ?>
                                                        </div>
                                                        <div class="attendance-item attendance-permisos">
                                                            Pe: <?php echo $session['permisos']; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #666;">Sin registro</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($session['descripcion']): ?>
                                                    <span title="<?php echo htmlspecialchars($session['descripcion']); ?>">
                                                        <?php echo strlen($session['descripcion']) > 50 ? 
                                                            htmlspecialchars(substr($session['descripcion'], 0, 50)) . '...' : 
                                                            htmlspecialchars($session['descripcion']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #666;">Sin descripción</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="attendance.php?session_id=<?php echo $session['id']; ?>&course_id=<?php echo $selectedCourse['id']; ?>" 
                                                   class="btn btn-primary btn-small" title="Registrar asistencia">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                                
                                                <a href="?course_id=<?php echo $selectedCourse['id']; ?>&edit_id=<?php echo $session['id']; ?>" 
                                                   class="btn btn-info btn-small" title="Editar sesión">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('¿Está seguro de eliminar la Sesión <?php echo $session['numero_sesion']; ?>: <?php echo htmlspecialchars($session['titulo']); ?>?')">
                                                    <input type="hidden" name="action" value="delete_session">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small" title="Eliminar sesión">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Acciones Rápidas -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Acciones Rápidas
                    </h2>
                </div>
                
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; padding: 1rem 0;">
                    <a href="attendance.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-clipboard-check"></i>
                        Registrar Asistencia
                    </a>
                    
                    <a href="evaluations.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-success">
                        <i class="fas fa-pen-alt"></i>
                        Gestionar Evaluaciones
                    </a>
                    
                    <a href="manage_students.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-info">
                        <i class="fas fa-users"></i>
                        Gestionar Estudiantes
                    </a>
                    
                    <a href="reports.php?course_id=<?php echo $selectedCourse['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-chart-line"></i>
                        Ver Reportes
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Validación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha');
            if (fechaInput) {
                fechaInput.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        this.style.borderColor = 'var(--warning-color)';
                        this.title = 'Fecha anterior a hoy';
                    } else {
                        this.style.borderColor = 'var(--border-color)';
                        this.title = '';
                    }
                });
            }
            
            // Auto-completar número de sesión
            const numeroSesionInput = document.getElementById('numero_sesion');
            if (numeroSesionInput && !numeroSesionInput.value && !numeroSesionInput.readOnly) {
                const nextNumber = <?php echo $selectedCourse ? getNextSessionNumber($pdo, $selectedCourse['id']) : 1; ?>;
                numeroSesionInput.placeholder = `Sugerido: ${nextNumber}`;
            }
        });
        
        // Seleccionar todos los checkboxes
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectAllHeaderCheckbox = document.getElementById('selectAllHeader');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.session-checkbox');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        }
        
        if (selectAllHeaderCheckbox) {
            selectAllHeaderCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.session-checkbox');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                if (selectAllCheckbox) selectAllCheckbox.checked = this.checked;
            });
        }
        
        // Actualizar el estado del checkbox "seleccionar todos"
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('session-checkbox')) {
                const allCheckboxes = document.querySelectorAll('.session-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.session-checkbox:checked');
                
                if (selectAllCheckbox && selectAllHeaderCheckbox) {
                    if (checkedCheckboxes.length === allCheckboxes.length) {
                        selectAllCheckbox.checked = true;
                        selectAllHeaderCheckbox.checked = true;
                    } else {
                        selectAllCheckbox.checked = false;
                        selectAllHeaderCheckbox.checked = false;
                    }
                }
            }
        });
        
        // Confirmación para acciones en lote
        function confirmBulkAction() {
            const selectedSessions = document.querySelectorAll('.session-checkbox:checked');
            const newStatusSelect = document.getElementById('newStatus');
            const newStatus = newStatusSelect ? newStatusSelect.value : '';
            
            if (selectedSessions.length === 0) {
                alert('Debe seleccionar al menos una sesión.');
                return false;
            }
            
            if (!newStatus) {
                alert('Debe seleccionar un nuevo estado.');
                return false;
            }
            
            return confirm(`¿Está seguro de cambiar el estado de ${selectedSessions.length} sesión(es) a "${newStatus}"?`);
        }
        
        // Exportar a CSV
        function exportToCSV() {
            const table = document.getElementById('sessionsTable');
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            
            let csv = [];
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                
                let csvRow = [];
                for (let j = 1; j < cols.length - 1; j++) { // Excluir checkbox y acciones
                    let cellText = cols[j].textContent.trim();
                    cellText = cellText.replace(/"/g, '""');
                    csvRow.push('"' + cellText + '"');
                }
                csv.push(csvRow.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'sesiones_<?php echo $selectedCourse['codigo'] ?? 'curso'; ?>_<?php echo date('Y-m-d'); ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Búsqueda en tiempo real
        let searchTimeout;
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const filterForm = document.getElementById('filterForm');
                    if (filterForm) filterForm.submit();
                }, 500);
            });
        }
        
        // Validación del formulario
        const sessionForm = document.getElementById('sessionForm');
        if (sessionForm) {
            sessionForm.addEventListener('submit', function(e) {
                const titulo = document.getElementById('titulo').value;
                const fecha = document.getElementById('fecha').value;
                
                if (!titulo.trim()) {
                    e.preventDefault();
                    alert('El título de la sesión es obligatorio.');
                    document.getElementById('titulo').focus();
                    return false;
                }
                
                if (!fecha) {
                    e.preventDefault();
                    alert('La fecha es obligatoria.');
                    document.getElementById('fecha').focus();
                    return false;
                }
                
                const confirmText = <?php echo $editSession ? '"¿Está seguro de actualizar esta sesión?"' : '"¿Está seguro de crear esta sesión?"'; ?>;
                return confirm(confirmText);
            });
        }
    </script>
</body>
</html>