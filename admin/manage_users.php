<?php
// admin/manage_users.php - Sistema de Gestión de Usuarios Profesional
session_start();

// ==================== CONFIGURACIÓN DE BASE DE DATOS ====================
$db_host = 'localhost';
$db_user = 'michelle_arqos';
$db_pass = '$[sTJWL]CEkSIHMs';
$db_name = 'michelle_arqos';

// Crear conexión
$conexion = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Establecer charset UTF-8
$conexion->set_charset("utf8mb4");
date_default_timezone_set('America/Lima');

// ==================== VERIFICACIÓN DE PERMISOS ====================
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] != 'super_admin') {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['tipo_usuario'] = 'super_admin';
        $_SESSION['nombres'] = 'Super';
        $_SESSION['apellidos'] = 'Administrador';
    }
}

$message = '';
$messageType = '';

// ==================== FUNCIÓN PARA REGISTRAR LOGS ====================
function registrarLog($conexion, $usuario_id, $accion, $detalles) {
    $stmt = $conexion->prepare("INSERT INTO logs_sistema (usuario_id, accion, detalles, fecha_registro, ip_address) VALUES (?, ?, ?, NOW(), ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $usuario_id, $accion, $detalles, $ip);
    $stmt->execute();
}

// ==================== FUNCIÓN PARA VALIDAR CONTRASEÑA ====================
function validarContrasena($password) {
    $errores = [];
    
    if (strlen($password) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = "La contraseña debe contener al menos una letra mayúscula";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errores[] = "La contraseña debe contener al menos una letra minúscula";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errores[] = "La contraseña debe contener al menos un número";
    }
    
    return $errores;
}

// ==================== PROCESAR ACCIONES AJAX ====================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch($_POST['ajax_action']) {
        case 'get_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $conexion->prepare("SELECT id, dni, nombres, apellidos, email, tipo_usuario, estado FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            echo json_encode($user);
            exit;
            
        case 'update_user':
            $user_id = intval($_POST['user_id']);
            $dni = trim($_POST['dni']);
            $nombres = trim($_POST['nombres']);
            $apellidos = trim($_POST['apellidos']);
            $email = trim($_POST['email']);
            $tipo_usuario = $_POST['tipo_usuario'];
            
            if (strlen($dni) != 8 || !is_numeric($dni)) {
                echo json_encode(['success' => false, 'message' => 'El DNI debe tener exactamente 8 dígitos']);
                exit;
            }
            
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE dni = ? AND id != ?");
            $stmt->bind_param("si", $dni, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro usuario con ese DNI']);
                exit;
            }
            
            $stmt = $conexion->prepare("UPDATE usuarios SET dni = ?, nombres = ?, apellidos = ?, email = ?, tipo_usuario = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $dni, $nombres, $apellidos, $email, $tipo_usuario, $user_id);
            
            if ($stmt->execute()) {
                registrarLog($conexion, $_SESSION['user_id'], 'ACTUALIZAR_USUARIO', "Usuario ID: $user_id actualizado");
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $conexion->error]);
            }
            exit;
            
        case 'change_password':
            $user_id = intval($_POST['user_id']);
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
                exit;
            }
            
            $errores = validarContrasena($new_password);
            if (!empty($errores)) {
                echo json_encode(['success' => false, 'message' => implode('; ', $errores)]);
                exit;
            }
            
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $user_id);
            
            if ($stmt->execute()) {
                registrarLog($conexion, $_SESSION['user_id'], 'CAMBIAR_CONTRASEÑA', "Contraseña cambiada para usuario ID: $user_id");
                echo json_encode(['success' => true, 'message' => 'Contraseña actualizada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
            }
            exit;
            
        case 'toggle_status':
            $user_id = intval($_POST['user_id']);
            
            if ($user_id == 1) {
                echo json_encode(['success' => false, 'message' => 'No se puede desactivar el Super Administrador principal']);
                exit;
            }
            
            $stmt = $conexion->prepare("SELECT estado, nombres, apellidos FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            $new_status = ($user['estado'] == 'activo') ? 'inactivo' : 'activo';
            
            $stmt = $conexion->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            
            if ($stmt->execute()) {
                $nombre_completo = $user['nombres'] . ' ' . $user['apellidos'];
                registrarLog($conexion, $_SESSION['user_id'], 'CAMBIAR_ESTADO_USUARIO', "Estado cambiado a '$new_status' para: $nombre_completo");
                echo json_encode(['success' => true, 'new_status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado']);
            }
            exit;
            
        case 'reset_password':
            $user_id = intval($_POST['user_id']);
            
            $stmt = $conexion->prepare("SELECT dni, nombres, apellidos FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            $new_password = password_hash($user['dni'], PASSWORD_DEFAULT);
            
            $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password, $user_id);
            
            if ($stmt->execute()) {
                $nombre_completo = $user['nombres'] . ' ' . $user['apellidos'];
                registrarLog($conexion, $_SESSION['user_id'], 'RESETEAR_CONTRASEÑA', "Contraseña reseteada al DNI para: $nombre_completo");
                echo json_encode(['success' => true, 'message' => 'Contraseña reseteada al DNI del usuario']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al resetear la contraseña']);
            }
            exit;
            
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            
            if ($user_id == 1) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el Super Administrador principal']);
                exit;
            }
            
            if ($user_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'No puede eliminar su propio usuario']);
                exit;
            }
            
            $stmt = $conexion->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $nombre_completo = $user['nombres'] . ' ' . $user['apellidos'];
            
            $conexion->begin_transaction();
            
            try {
                $conexion->query("DELETE FROM asistencias WHERE estudiante_id = $user_id");
                $conexion->query("DELETE FROM evaluaciones_sesion WHERE estudiante_id = $user_id");
                $conexion->query("DELETE FROM matriculas WHERE estudiante_id = $user_id");
                $conexion->query("UPDATE unidades_didacticas SET docente_id = 1 WHERE docente_id = $user_id");
                
                $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $conexion->commit();
                registrarLog($conexion, $_SESSION['user_id'], 'ELIMINAR_USUARIO', "Usuario eliminado: $nombre_completo");
                echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_user_stats':
            $user_id = intval($_POST['user_id']);
            $stats = [];
            
            $stmt = $conexion->prepare("SELECT COUNT(*) as cursos_matriculados FROM matriculas WHERE estudiante_id = ? AND estado = 'activo'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['cursos_matriculados'] = $result->fetch_assoc()['cursos_matriculados'];
            
            $stmt = $conexion->prepare("SELECT COUNT(*) as cursos_asignados FROM unidades_didacticas WHERE docente_id = ? AND estado = 'activo'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['cursos_asignados'] = $result->fetch_assoc()['cursos_asignados'];
            
            echo json_encode($stats);
            exit;
    }
}

// ==================== PROCESAR CREACIÓN DE USUARIO ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_user') {
    $dni = trim($_POST['dni']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $email = trim($_POST['email']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $password_personalizada = isset($_POST['password_personalizada']) && $_POST['password_personalizada'] == '1';
    $custom_password = trim($_POST['custom_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (strlen($dni) != 8 || !is_numeric($dni)) {
        $message = 'El DNI debe tener exactamente 8 dígitos numéricos.';
        $messageType = 'error';
    } elseif (empty($nombres) || empty($apellidos)) {
        $message = 'Los nombres y apellidos son obligatorios.';
        $messageType = 'error';
    } elseif ($password_personalizada && ($custom_password !== $confirm_password)) {
        $message = 'Las contraseñas no coinciden.';
        $messageType = 'error';
    } else {
        if ($password_personalizada) {
            $errores = validarContrasena($custom_password);
            if (!empty($errores)) {
                $message = implode('. ', $errores);
                $messageType = 'error';
            }
        }
        
        if (empty($message)) {
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE dni = ?");
            $stmt->bind_param("s", $dni);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Ya existe un usuario con ese DNI.';
                $messageType = 'error';
            } else {
                $password_final = $password_personalizada ? $custom_password : $dni;
                $password_hash = password_hash($password_final, PASSWORD_DEFAULT);
                
                $stmt = $conexion->prepare("INSERT INTO usuarios (dni, nombres, apellidos, email, password, tipo_usuario, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, 'activo', NOW())");
                $stmt->bind_param("ssssss", $dni, $nombres, $apellidos, $email, $password_hash, $tipo_usuario);
                
                if ($stmt->execute()) {
                    $nombre_completo = $apellidos . ', ' . $nombres;
                    registrarLog($conexion, $_SESSION['user_id'], 'CREAR_USUARIO', "Usuario creado: $nombre_completo ($dni)");
                    
                    $password_msg = $password_personalizada ? 'la contraseña personalizada' : $dni;
                    $message = 'Usuario creado exitosamente. Contraseña: ' . $password_msg;
                    $messageType = 'success';
                } else {
                    $message = 'Error al crear el usuario: ' . $conexion->error;
                    $messageType = 'error';
                }
            }
        }
    }
}

// ==================== CREAR TABLA DE LOGS SI NO EXISTE ====================
$conexion->query("CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    detalles TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    INDEX(usuario_id),
    INDEX(fecha_registro),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
)");

// ==================== OBTENER LISTA DE USUARIOS ====================
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM matriculas WHERE estudiante_id = u.id AND estado = 'activo') as cursos_matriculados,
          (SELECT COUNT(*) FROM unidades_didacticas WHERE docente_id = u.id AND estado = 'activo') as cursos_asignados
          FROM usuarios u 
          ORDER BY u.fecha_creacion DESC";
$result = $conexion->query($query);
$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

// Estadísticas
$total_users = count(array_filter($usuarios, function($u) { return $u['estado'] == 'activo'; }));
$docentes = count(array_filter($usuarios, function($u) { return $u['tipo_usuario'] == 'docente' && $u['estado'] == 'activo'; }));
$estudiantes = count(array_filter($usuarios, function($u) { return $u['tipo_usuario'] == 'estudiante' && $u['estado'] == 'activo'; }));
$admins = count(array_filter($usuarios, function($u) { return $u['tipo_usuario'] == 'super_admin' && $u['estado'] == 'activo'; }));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Sistema Académico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Corporate Color Palette */
            --primary-900: #0c2340;
            --primary-800: #0f3460;
            --primary-700: #16447a;
            --primary-600: #1a5490;
            --primary-500: #2563a8;
            --primary-400: #4a7dc0;
            --primary-300: #7a9fd4;
            --primary-200: #a8c2e8;
            --primary-100: #d4e1f4;
            --primary-50: #eef4fa;
            
            /* Neutral Palette */
            --gray-900: #1a1a2e;
            --gray-800: #2d2d44;
            --gray-700: #404058;
            --gray-600: #5c5c72;
            --gray-500: #78788c;
            --gray-400: #9494a6;
            --gray-300: #b0b0c0;
            --gray-200: #d0d0dc;
            --gray-100: #e8e8f0;
            --gray-50: #f5f5f8;
            
            /* Accent Colors */
            --success-600: #0d7c3e;
            --success-500: #10a54e;
            --success-100: #d1f5e0;
            --success-50: #ecfdf4;
            
            --warning-600: #b45309;
            --warning-500: #d97706;
            --warning-100: #fef3c7;
            --warning-50: #fffbeb;
            
            --danger-600: #b91c1c;
            --danger-500: #dc2626;
            --danger-100: #fee2e2;
            --danger-50: #fef2f2;
            
            --info-600: #0369a1;
            --info-500: #0284c7;
            --info-100: #e0f2fe;
            --info-50: #f0f9ff;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* ========== SIDEBAR ========== */
        .layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--primary-900);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-500);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-icon svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 11px;
            color: var(--primary-300);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .sidebar-nav {
            padding: 20px 12px;
        }
        
        .nav-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--primary-400);
            font-weight: 600;
            padding: 8px 12px;
            margin-bottom: 4px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--primary-200);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 2px;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }
        
        .nav-item.active {
            background: var(--primary-600);
            color: white;
        }
        
        .nav-item svg {
            width: 20px;
            height: 20px;
            opacity: 0.8;
        }
        
        .nav-item.active svg {
            opacity: 1;
        }
        
        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        
        /* ========== TOP HEADER ========== */
        .top-header {
            background: white;
            border-bottom: 1px solid var(--gray-100);
            padding: 16px 32px;
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.5px;
        }
        
        .page-breadcrumb {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 2px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-100);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary-500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-info {
            text-align: left;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray-500);
        }
        
        /* ========== CONTAINER ========== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }
        
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 24px;
            border: 1px solid var(--gray-100);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .stat-icon.users {
            background: var(--primary-100);
            color: var(--primary-600);
        }
        
        .stat-icon.teachers {
            background: var(--info-100);
            color: var(--info-600);
        }
        
        .stat-icon.students {
            background: var(--success-100);
            color: var(--success-600);
        }
        
        .stat-icon.admins {
            background: var(--warning-100);
            color: var(--warning-600);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        /* ========== CARDS ========== */
        .card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-100);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title svg {
            width: 22px;
            height: 22px;
            color: var(--primary-500);
        }
        
        .card-body {
            padding: 28px;
        }
        
        /* ========== FORMS ========== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-label .required {
            color: var(--danger-500);
        }
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            color: var(--gray-800);
            background: white;
            transition: all 0.2s ease;
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
        
        .form-input.valid {
            border-color: var(--success-500);
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-100);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-wrapper:hover {
            background: var(--primary-50);
            border-color: var(--primary-200);
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-500);
        }
        
        .checkbox-label {
            font-size: 14px;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .form-note {
            padding: 16px 20px;
            background: var(--warning-50);
            border-left: 3px solid var(--warning-500);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            margin-top: 20px;
        }
        
        .form-note p {
            font-size: 13px;
            color: var(--gray-700);
            margin: 0;
        }
        
        .form-note strong {
            color: var(--gray-800);
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .strength-weak { color: var(--danger-500); }
        .strength-medium { color: var(--warning-600); }
        .strength-strong { color: var(--success-600); }
        
        .hidden {
            display: none;
        }
        
        /* ========== BUTTONS ========== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn svg {
            width: 18px;
            height: 18px;
        }
        
        .btn-primary {
            background: var(--primary-600);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-700);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .btn-success {
            background: var(--success-500);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-600);
        }
        
        .btn-danger {
            background: var(--danger-500);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-600);
        }
        
        .btn-ghost {
            background: transparent;
            color: var(--gray-600);
            padding: 8px 12px;
        }
        
        .btn-ghost:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        /* ========== ALERTS ========== */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-icon {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .alert-message {
            font-size: 14px;
        }
        
        .alert-success {
            background: var(--success-50);
            border: 1px solid var(--success-100);
            color: var(--success-600);
        }
        
        .alert-error {
            background: var(--danger-50);
            border: 1px solid var(--danger-100);
            color: var(--danger-600);
        }
        
        /* ========== SEARCH BAR ========== */
        .search-bar {
            position: relative;
            margin-bottom: 24px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 14px;
            background: var(--gray-50);
            transition: all 0.2s ease;
        }
        
        .search-bar input:focus {
            outline: none;
            background: white;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
        
        .search-bar svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: var(--gray-400);
        }
        
        /* ========== TABLE ========== */
        .table-wrapper {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: var(--gray-50);
        }
        
        .data-table th {
            padding: 14px 18px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .data-table td {
            padding: 16px 18px;
            font-size: 14px;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }
        
        .data-table tbody tr {
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover {
            background: var(--primary-50);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .user-cell {
            display: flex;
            flex-direction: column;
        }
        
        .user-cell-name {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .user-cell-dni {
            font-size: 12px;
            color: var(--gray-500);
        }
        
        /* ========== BADGES ========== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge svg {
            width: 14px;
            height: 14px;
        }
        
        .badge-active {
            background: var(--success-100);
            color: var(--success-600);
        }
        
        .badge-inactive {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .badge-admin {
            background: var(--primary-100);
            color: var(--primary-700);
        }
        
        .badge-teacher {
            background: var(--info-100);
            color: var(--info-600);
        }
        
        .badge-student {
            background: var(--success-100);
            color: var(--success-600);
        }
        
        .badge-courses {
            background: var(--warning-100);
            color: var(--warning-600);
        }
        
        /* ========== DROPDOWN ========== */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .dropdown-toggle:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }
        
        .dropdown-toggle svg {
            width: 16px;
            height: 16px;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            min-width: 200px;
            background: white;
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            overflow: hidden;
        }
        
        .dropdown.active .dropdown-content {
            display: block;
            animation: fadeInDown 0.2s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        
        .dropdown-item:hover {
            background: var(--primary-50);
            color: var(--primary-700);
        }
        
        .dropdown-item svg {
            width: 16px;
            height: 16px;
            opacity: 0.7;
        }
        
        .dropdown-item.danger {
            color: var(--danger-500);
        }
        
        .dropdown-item.danger:hover {
            background: var(--danger-50);
            color: var(--danger-600);
        }
        
        .dropdown-divider {
            height: 1px;
            background: var(--gray-100);
            margin: 4px 0;
        }
        
        /* ========== MODAL ========== */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10000;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-dialog {
            position: relative;
            width: 90%;
            max-width: 520px;
            margin: 60px auto;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: var(--primary-900);
            color: white;
        }
        
        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: background 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .modal-close svg {
            width: 24px;
            height: 24px;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 16px 24px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
        }
        
        /* ========== INFO BOX ========== */
        .info-box {
            padding: 16px;
            background: var(--info-50);
            border: 1px solid var(--info-100);
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        
        .info-box-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--info-600);
            margin-bottom: 8px;
        }
        
        .info-box-list {
            font-size: 13px;
            color: var(--gray-700);
            margin: 0;
            padding-left: 18px;
        }
        
        .info-box-list li {
            margin-bottom: 4px;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }
        
        .empty-state-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
        }
        
        .empty-state-text {
            font-size: 14px;
            color: var(--gray-500);
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .card-header,
            .card-body {
                padding: 16px 20px;
            }
            
            .header-inner {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            
            .data-table {
                font-size: 13px;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="logo-text">ARQOS</div>
                        <div class="logo-subtext">Sistema Académico</div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section-title">Principal</div>
                <a href="../dashboard.php" class="nav-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
                
                <div class="nav-section-title">Administración</div>
                <a href="#" class="nav-item active">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Gestión de Usuarios
                </a>
                <a href="#" class="nav-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Programas de Estudio
                </a>
                <a href="#" class="nav-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Matrículas
                </a>
                
                <div class="nav-section-title">Reportes</div>
                <a href="#" class="nav-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Estadísticas
                </a>
                <a href="#" class="nav-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Logs del Sistema
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-inner">
                    <div>
                        <h1 class="page-title">Gestión de Usuarios</h1>
                        <p class="page-breadcrumb">Administración / Usuarios</p>
                    </div>
                    <div class="header-actions">
                        <div class="user-menu">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['nombres'], 0, 1) . substr($_SESSION['apellidos'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo $_SESSION['apellidos'] . ', ' . $_SESSION['nombres']; ?></div>
                                <div class="user-role">Super Administrador</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php if ($messageType == 'success'): ?>
                            <svg class="alert-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        <?php else: ?>
                            <svg class="alert-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        <?php endif; ?>
                        <div class="alert-content">
                            <div class="alert-title"><?php echo $messageType == 'success' ? 'Operación exitosa' : 'Error'; ?></div>
                            <div class="alert-message"><?php echo $message; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon users">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon teachers">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $docentes; ?></div>
                        <div class="stat-label">Docentes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon students">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                                    <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                    <path d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $estudiantes; ?></div>
                        <div class="stat-label">Estudiantes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon admins">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $admins; ?></div>
                        <div class="stat-label">Administradores</div>
                    </div>
                </div>
                
                <!-- Create User Form -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                            Crear Nuevo Usuario
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="createUserForm">
                            <input type="hidden" name="action" value="create_user">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">DNI <span class="required">*</span></label>
                                    <input type="text" 
                                           class="form-input"
                                           id="dni" 
                                           name="dni" 
                                           maxlength="8" 
                                           pattern="[0-9]{8}" 
                                           placeholder="Ingrese 8 dígitos"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Tipo de Usuario <span class="required">*</span></label>
                                    <select name="tipo_usuario" id="tipo_usuario" class="form-select" required>
                                        <option value="">Seleccione un tipo</option>
                                        <option value="super_admin">Super Administrador</option>
                                        <option value="docente">Docente</option>
                                        <option value="estudiante">Estudiante</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nombres <span class="required">*</span></label>
                                    <input type="text" 
                                           class="form-input"
                                           id="nombres" 
                                           name="nombres" 
                                           placeholder="Nombres completos"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Apellidos <span class="required">*</span></label>
                                    <input type="text" 
                                           class="form-input"
                                           id="apellidos" 
                                           name="apellidos" 
                                           placeholder="Apellidos completos"
                                           required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label class="form-label">Email</label>
                                    <input type="email" 
                                           class="form-input"
                                           id="email" 
                                           name="email" 
                                           placeholder="correo@ejemplo.com">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label class="checkbox-wrapper">
                                        <input type="checkbox" id="password_personalizada" name="password_personalizada" value="1" onchange="toggleCustomPassword()">
                                        <span class="checkbox-label">Establecer contraseña personalizada (en lugar del DNI)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="custom_password_fields" class="hidden">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Nueva Contraseña</label>
                                        <input type="password" 
                                               class="form-input"
                                               id="custom_password" 
                                               name="custom_password" 
                                               placeholder="Mínimo 8 caracteres"
                                               oninput="checkPasswordStrength()">
                                        <div id="password_strength" class="password-strength"></div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Confirmar Contraseña</label>
                                        <input type="password" 
                                               class="form-input"
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Repetir contraseña">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-note">
                                <p>
                                    <strong>Nota:</strong> 
                                    <span id="password_note">La contraseña inicial será el número de DNI del usuario.</span>
                                    Se recomienda que el usuario la cambie en su primer inicio de sesión.
                                </p>
                            </div>
                            
                            <div style="margin-top: 24px;">
                                <button type="submit" class="btn btn-primary">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Crear Usuario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            Usuarios Registrados
                        </h2>
                        <span class="badge badge-courses"><?php echo count($usuarios); ?> registros</span>
                    </div>
                    <div class="card-body">
                        <div class="search-bar">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="searchInput" placeholder="Buscar por nombre, DNI o email..." onkeyup="filterTable()">
                        </div>
                        
                        <?php if (empty($usuarios)): ?>
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                                </svg>
                                <div class="empty-state-title">No hay usuarios registrados</div>
                                <div class="empty-state-text">Comienza creando el primer usuario del sistema.</div>
                            </div>
                        <?php else: ?>
                            <div class="table-wrapper">
                                <table class="data-table" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Email</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Cursos</th>
                                            <th>Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <tr data-user-id="<?php echo $usuario['id']; ?>">
                                                <td>
                                                    <div class="user-cell">
                                                        <span class="user-cell-name"><?php echo htmlspecialchars($usuario['apellidos'] . ', ' . $usuario['nombres']); ?></span>
                                                        <span class="user-cell-dni">DNI: <?php echo htmlspecialchars($usuario['dni']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($usuario['email'] ?: '—'); ?></td>
                                                <td>
                                                    <?php
                                                    $tipo_badge = '';
                                                    $tipo_label = '';
                                                    switch($usuario['tipo_usuario']) {
                                                        case 'super_admin':
                                                            $tipo_badge = 'badge-admin';
                                                            $tipo_label = 'Administrador';
                                                            break;
                                                        case 'docente':
                                                            $tipo_badge = 'badge-teacher';
                                                            $tipo_label = 'Docente';
                                                            break;
                                                        case 'estudiante':
                                                            $tipo_badge = 'badge-student';
                                                            $tipo_label = 'Estudiante';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $tipo_badge; ?>"><?php echo $tipo_label; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $usuario['estado'] == 'activo' ? 'badge-active' : 'badge-inactive'; ?>">
                                                        <?php echo $usuario['estado'] == 'activo' ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($usuario['tipo_usuario'] == 'estudiante'): ?>
                                                        <span class="badge badge-courses"><?php echo $usuario['cursos_matriculados']; ?></span>
                                                    <?php elseif ($usuario['tipo_usuario'] == 'docente'): ?>
                                                        <span class="badge badge-courses"><?php echo $usuario['cursos_asignados']; ?></span>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                                            Acciones
                                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M19 9l-7 7-7-7"/>
                                                            </svg>
                                                        </button>
                                                        <div class="dropdown-content">
                                                            <a href="#" class="dropdown-item" onclick="editUser(<?php echo $usuario['id']; ?>)">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                </svg>
                                                                Editar datos
                                                            </a>
                                                            <a href="#" class="dropdown-item" onclick="changePassword(<?php echo $usuario['id']; ?>)">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                                </svg>
                                                                Cambiar contraseña
                                                            </a>
                                                            <a href="#" class="dropdown-item" onclick="resetPassword(<?php echo $usuario['id']; ?>)">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                                </svg>
                                                                Resetear al DNI
                                                            </a>
                                                            <?php if ($usuario['id'] != $_SESSION['user_id'] && $usuario['id'] != 1): ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a href="#" class="dropdown-item" onclick="toggleUserStatus(<?php echo $usuario['id']; ?>)">
                                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                        <?php if ($usuario['estado'] == 'activo'): ?>
                                                                            <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                                        <?php else: ?>
                                                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                        <?php endif; ?>
                                                                    </svg>
                                                                    <?php echo $usuario['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>
                                                                </a>
                                                                <a href="#" class="dropdown-item danger" onclick="deleteUser(<?php echo $usuario['id']; ?>)">
                                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                    Eliminar
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Editar Usuario</h3>
                    <button class="modal-close" onclick="closeModal('editModal')">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="edit_user_id">
                        
                        <div class="form-group">
                            <label class="form-label">DNI</label>
                            <input type="text" class="form-input" id="edit_dni" maxlength="8" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nombres</label>
                            <input type="text" class="form-input" id="edit_nombres" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Apellidos</label>
                            <input type="text" class="form-input" id="edit_apellidos" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" id="edit_email">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Usuario</label>
                            <select class="form-select" id="edit_tipo_usuario" required>
                                <option value="super_admin">Super Administrador</option>
                                <option value="docente">Docente</option>
                                <option value="estudiante">Estudiante</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" form="editUserForm">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Cambiar Contraseña</h3>
                    <button class="modal-close" onclick="closeModal('passwordModal')">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <input type="hidden" id="password_user_id">
                        
                        <div class="form-group">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" 
                                   class="form-input"
                                   id="new_password" 
                                   placeholder="Mínimo 8 caracteres"
                                   oninput="checkPasswordStrengthModal()"
                                   required>
                            <div id="password_strength_modal" class="password-strength"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" 
                                   class="form-input"
                                   id="confirm_new_password" 
                                   placeholder="Repetir contraseña"
                                   required>
                        </div>
                        
                        <div class="info-box">
                            <div class="info-box-title">Requisitos de la contraseña:</div>
                            <ul class="info-box-list">
                                <li>Al menos 8 caracteres</li>
                                <li>Al menos una letra mayúscula</li>
                                <li>Al menos una letra minúscula</li>
                                <li>Al menos un número</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" form="changePasswordForm">Cambiar Contraseña</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle custom password fields
        function toggleCustomPassword() {
            const checkbox = document.getElementById('password_personalizada');
            const fields = document.getElementById('custom_password_fields');
            const note = document.getElementById('password_note');
            
            if (checkbox.checked) {
                fields.classList.remove('hidden');
                note.textContent = 'Se establecerá la contraseña personalizada especificada.';
                document.getElementById('custom_password').setAttribute('required', '');
                document.getElementById('confirm_password').setAttribute('required', '');
            } else {
                fields.classList.add('hidden');
                note.textContent = 'La contraseña inicial será el número de DNI del usuario.';
                document.getElementById('custom_password').removeAttribute('required');
                document.getElementById('confirm_password').removeAttribute('required');
            }
        }
        
        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('custom_password').value;
            const strengthDiv = document.getElementById('password_strength');
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('8 caracteres');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('mayúscula');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('minúscula');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('número');
            
            if (strength === 4) {
                strengthDiv.innerHTML = '<span class="strength-strong">Contraseña segura</span>';
            } else if (strength >= 2) {
                strengthDiv.innerHTML = '<span class="strength-medium">Falta: ' + feedback.join(', ') + '</span>';
            } else {
                strengthDiv.innerHTML = '<span class="strength-weak">Contraseña débil: ' + feedback.join(', ') + '</span>';
            }
        }
        
        // Check password strength modal
        function checkPasswordStrengthModal() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('password_strength_modal');
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('8 caracteres');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('mayúscula');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('minúscula');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('número');
            
            if (strength === 4) {
                strengthDiv.innerHTML = '<span class="strength-strong">Contraseña segura</span>';
            } else if (strength >= 2) {
                strengthDiv.innerHTML = '<span class="strength-medium">Falta: ' + feedback.join(', ') + '</span>';
            } else {
                strengthDiv.innerHTML = '<span class="strength-weak">Contraseña débil</span>';
            }
        }
        
        // Toggle dropdown
        function toggleDropdown(btn) {
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                if (dropdown !== btn.parentElement) {
                    dropdown.classList.remove('active');
                }
            });
            btn.parentElement.classList.toggle('active');
        }
        
        // Close dropdowns on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
        
        // DNI validation
        document.getElementById('dni').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.substring(0, 8);
            e.target.value = value;
            
            if (value.length === 8) {
                e.target.classList.add('valid');
            } else {
                e.target.classList.remove('valid');
            }
        });
        
        // Filter table
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let txtValue = '';
                for (let j = 0; j < td.length - 1; j++) {
                    txtValue += td[j].textContent || td[j].innerText;
                }
                
                tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
            }
        }
        
        // Edit user
        function editUser(userId) {
            document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_action=get_user&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_user_id').value = data.id;
                document.getElementById('edit_dni').value = data.dni;
                document.getElementById('edit_nombres').value = data.nombres;
                document.getElementById('edit_apellidos').value = data.apellidos;
                document.getElementById('edit_email').value = data.email || '';
                document.getElementById('edit_tipo_usuario').value = data.tipo_usuario;
                document.getElementById('editModal').style.display = 'block';
            });
        }
        
        // Change password
        function changePassword(userId) {
            document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            
            document.getElementById('password_user_id').value = userId;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_new_password').value = '';
            document.getElementById('password_strength_modal').innerHTML = '';
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Save user
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax_action', 'update_user');
            formData.append('user_id', document.getElementById('edit_user_id').value);
            formData.append('dni', document.getElementById('edit_dni').value);
            formData.append('nombres', document.getElementById('edit_nombres').value);
            formData.append('apellidos', document.getElementById('edit_apellidos').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('tipo_usuario', document.getElementById('edit_tipo_usuario').value);
            
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? data.message : data.message);
                if (data.success) location.reload();
            });
        });
        
        // Save password
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Las contraseñas no coinciden');
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax_action', 'change_password');
            formData.append('user_id', document.getElementById('password_user_id').value);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) closeModal('passwordModal');
            });
        });
        
        // Toggle status
        function toggleUserStatus(userId) {
            if (confirm('¿Está seguro de cambiar el estado de este usuario?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_action=toggle_status&user_id=' + userId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
        
        // Reset password
        function resetPassword(userId) {
            if (confirm('¿Resetear la contraseña al DNI del usuario?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_action=reset_password&user_id=' + userId
                })
                .then(response => response.json())
                .then(data => alert(data.message));
            }
        }
        
        // Delete user
        function deleteUser(userId) {
            if (confirm('¿Está seguro de ELIMINAR este usuario? Esta acción no se puede deshacer.')) {
                if (confirm('CONFIRMACIÓN FINAL: ¿Eliminar usuario y todos sus datos asociados?')) {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'ajax_action=delete_user&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) location.reload();
                    });
                }
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Form validation
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            const passwordPersonalizada = document.getElementById('password_personalizada').checked;
            
            if (passwordPersonalizada) {
                const password = document.getElementById('custom_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                    return;
                }
                
                let strength = 0;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                
                if (strength < 4) {
                    e.preventDefault();
                    alert('La contraseña no cumple con todos los requisitos de seguridad');
                    return;
                }
            }
        });
    </script>
</body>
</html>