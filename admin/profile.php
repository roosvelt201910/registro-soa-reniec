<?php
// Configuración de errores para debugging
date_default_timezone_set('America/Lima'); // Para hora de Perú
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// MODO DE TESTING RÁPIDO - REMOVER EN PRODUCCIÓN
if (isset($_GET['test']) && $_GET['test'] == '1') {
    $_SESSION['user_id'] = 1;
    $_SESSION['tipo_usuario'] = 'super_admin';
    $_SESSION['nombres'] = 'Administrador';
    $_SESSION['apellidos'] = 'Principal';
    $_SESSION['dni'] = '12345678';
    $_SESSION['email'] = 'admin@instituto.edu.pe';
}

// Verificación básica de sesión
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Error</title></head>
    <body>
        <h2>Error de Sesión</h2>
        <p><a href="?test=1">Usar Modo Testing</a> | <a href="../login.php">Iniciar Sesión</a></p>
    </body></html>
    <?php
    exit();
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'iespaltohuallaga_regauxiliar_bd';
$username = 'iespaltohuallaga_user_regaux';
$password = ')wBRCeID[ldb%b^K';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$message = '';

// Función para completar datos de usuario
function completeUserData($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
            $_SESSION['nombres'] = $user['nombres'];
            $_SESSION['apellidos'] = $user['apellidos'];
            $_SESSION['dni'] = $user['dni'];
            $_SESSION['email'] = $user['email'];
            return $user;
        }
    } catch (PDOException $e) {
        return null;
    }
    return null;
}

// Obtener datos del usuario
$userData = completeUserData($pdo, $user_id);

if (!$userData) {
    $userData = array(
        'id' => $user_id,
        'dni' => $_SESSION['dni'] ?? '12345678',
        'nombres' => $_SESSION['nombres'] ?? 'Usuario',
        'apellidos' => $_SESSION['apellidos'] ?? 'Temporal',
        'email' => $_SESSION['email'] ?? 'usuario@instituto.edu.pe',
        'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'super_admin',
        'estado' => 'activo',
        'fecha_creacion' => date('Y-m-d H:i:s')
    );
}

// Funciones de estadísticas del sistema
function getSystemStats($pdo) {
    try {
        $stats = array();
        
        // Total de usuarios
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'");
        $stats['total_usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Usuarios por tipo
        $stmt = $pdo->query("SELECT tipo_usuario, COUNT(*) as total FROM usuarios WHERE estado = 'activo' GROUP BY tipo_usuario");
        $userTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($userTypes as $type) {
            $stats['usuarios_' . $type['tipo_usuario']] = $type['total'];
        }
        
        // Total de cursos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM unidades_didacticas WHERE estado = 'activo'");
        $stats['total_cursos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de programas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM programas_estudio WHERE estado = 'activo'");
        $stats['total_programas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de matrículas activas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM matriculas WHERE estado = 'activo'");
        $stats['total_matriculas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de sesiones realizadas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesiones WHERE estado = 'realizada'");
        $stats['total_sesiones'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de evaluaciones registradas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluaciones_sesion");
        $stats['total_evaluaciones'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $stats;
    } catch (PDOException $e) {
        return array(
            'total_usuarios' => 0,
            'usuarios_super_admin' => 0,
            'usuarios_docente' => 0,
            'usuarios_estudiante' => 0,
            'total_cursos' => 0,
            'total_programas' => 0,
            'total_matriculas' => 0,
            'total_sesiones' => 0,
            'total_evaluaciones' => 0
        );
    }
}

// Función para actualizar perfil
function updateProfile($pdo, $user_id, $nombres, $apellidos, $email, $dni) {
    try {
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET nombres = ?, apellidos = ?, email = ?, dni = ?
            WHERE id = ?
        ");
        return $stmt->execute([$nombres, $apellidos, $email, $dni, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Función para cambiar contraseña
function changePassword($pdo, $user_id, $current_password, $new_password) {
    try {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Verificar contraseña actual (asumiendo que está hasheada)
        if (!password_verify($current_password, $user['password'])) {
            return false;
        }
        
        // Actualizar contraseña
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed_password, $user_id]);
        
    } catch (PDOException $e) {
        return false;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $nombres = trim($_POST['nombres']);
                $apellidos = trim($_POST['apellidos']);
                $email = trim($_POST['email']);
                $dni = trim($_POST['dni']);
                
                if ($nombres && $apellidos && $email && $dni) {
                    if (updateProfile($pdo, $user_id, $nombres, $apellidos, $email, $dni)) {
                        $_SESSION['nombres'] = $nombres;
                        $_SESSION['apellidos'] = $apellidos;
                        $_SESSION['email'] = $email;
                        $_SESSION['dni'] = $dni;
                        
                        $userData['nombres'] = $nombres;
                        $userData['apellidos'] = $apellidos;
                        $userData['email'] = $email;
                        $userData['dni'] = $dni;
                        
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Perfil actualizado exitosamente.</div>';
                    } else {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al actualizar el perfil.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Todos los campos son obligatorios.</div>';
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($current_password && $new_password && $confirm_password) {
                    if ($new_password !== $confirm_password) {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Las contraseñas nuevas no coinciden.</div>';
                    } elseif (strlen($new_password) < 6) {
                        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> La contraseña debe tener al menos 6 caracteres.</div>';
                    } else {
                        if (changePassword($pdo, $user_id, $current_password, $new_password)) {
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Contraseña cambiada exitosamente.</div>';
                        } else {
                            $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error al cambiar la contraseña. Verifique su contraseña actual.</div>';
                        }
                    }
                } else {
                    $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Todos los campos de contraseña son obligatorios.</div>';
                }
                break;
        }
    }
}

// Obtener estadísticas del sistema
$systemStats = getSystemStats($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --shadow-hover: 0 4px 25px rgba(0,0,0,0.15);
            --border-radius: 12px;
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
            min-height: 100vh;
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
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
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
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .profile-avatar {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .avatar-circle:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-hover);
        }
        
        .profile-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .profile-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .profile-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .profile-info-item i {
            width: 20px;
            color: var(--primary-color);
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, white, #f8f9fa);
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-weight: 500;
            font-size: 0.9rem;
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
        
        .password-strength {
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .password-weak {
            background: #f8d7da;
            color: #721c24;
        }
        
        .password-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .password-strong {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--border-color);
        }
        
        .activity-content {
            background: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
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
            
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($userData['nombres'] . ' ' . $userData['apellidos']); ?></span>
                <a href="../dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['test']) && $_GET['test'] == '1'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-flask"></i>
                <strong>MODO TESTING ACTIVADO:</strong> Sesión temporal creada.
                <a href="?" style="color: #856404; text-decoration: underline; margin-left: 10px;">Quitar modo testing</a>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Información del Perfil -->
            <div class="card">
                <h2><i class="fas fa-id-card"></i> Información Personal</h2>
                
                <div class="profile-avatar">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($userData['nombres'], 0, 1) . substr($userData['apellidos'], 0, 1)); ?>
                    </div>
                    <h3><?php echo htmlspecialchars($userData['nombres'] . ' ' . $userData['apellidos']); ?></h3>
                    <span class="badge badge-<?php echo $userData['tipo_usuario'] == 'super_admin' ? 'primary' : 'secondary'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $userData['tipo_usuario'])); ?>
                    </span>
                </div>
                
                <div class="profile-info">
                    <div class="profile-info-item">
                        <i class="fas fa-id-badge"></i>
                        <strong>DNI:</strong> <?php echo htmlspecialchars($userData['dni']); ?>
                    </div>
                    <div class="profile-info-item">
                        <i class="fas fa-envelope"></i>
                        <strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?>
                    </div>
                    <div class="profile-info-item">
                        <i class="fas fa-user-tag"></i>
                        <strong>Tipo:</strong> <?php echo ucfirst(str_replace('_', ' ', $userData['tipo_usuario'])); ?>
                    </div>
                    <div class="profile-info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($userData['fecha_creacion'])); ?>
                    </div>
                    <div class="profile-info-item">
                        <i class="fas fa-circle" style="color: <?php echo $userData['estado'] == 'activo' ? '#28a745' : '#dc3545'; ?>"></i>
                        <strong>Estado:</strong> <?php echo ucfirst($userData['estado']); ?>
                    </div>
                </div>
                
                <h3><i class="fas fa-clock"></i> Actividad Reciente</h3>
                <div class="activity-timeline">
                    <div class="activity-item">
                        <div class="activity-content">
                            <strong>Sesión iniciada</strong><br>
                            <small class="text-muted">Hoy, <?php echo date('H:i'); ?></small>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-content">
                            <strong>Perfil actualizado</strong><br>
                            <small class="text-muted">Última actualización disponible</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuraciones -->
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="showTab('profile')">
                        <i class="fas fa-user-edit"></i> Editar Perfil
                    </button>
                    <button class="tab" onclick="showTab('password')">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                    <button class="tab" onclick="showTab('settings')">
                        <i class="fas fa-cog"></i> Configuraciones
                    </button>
                </div>
                
                <!-- Tab: Editar Perfil -->
                <div id="profile" class="tab-content active">
                    <h3><i class="fas fa-user-edit"></i> Editar Información Personal</h3>
                    
                    <form method="POST" id="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombres"><i class="fas fa-user"></i> Nombres:</label>
                                <input type="text" name="nombres" id="nombres" 
                                       value="<?php echo htmlspecialchars($userData['nombres']); ?>" required maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellidos"><i class="fas fa-user"></i> Apellidos:</label>
                                <input type="text" name="apellidos" id="apellidos" 
                                       value="<?php echo htmlspecialchars($userData['apellidos']); ?>" required maxlength="100">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="dni"><i class="fas fa-id-card"></i> DNI:</label>
                                <input type="text" name="dni" id="dni" 
                                       value="<?php echo htmlspecialchars($userData['dni']); ?>" required maxlength="8" 
                                       pattern="[0-9]{8}" title="DNI debe tener 8 dígitos">
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                                <input type="email" name="email" id="email" 
                                       value="<?php echo htmlspecialchars($userData['email']); ?>" required maxlength="100">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Actualizar Perfil
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Restablecer
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Tab: Cambiar Contraseña -->
                <div id="password" class="tab-content">
                    <h3><i class="fas fa-key"></i> Cambiar Contraseña</h3>
                    
                    <form method="POST" id="password-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-lock"></i> Contraseña Actual:</label>
                            <input type="password" name="current_password" id="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> Nueva Contraseña:</label>
                            <input type="password" name="new_password" id="new_password" required minlength="6" 
                                   onkeyup="checkPasswordStrength(this.value)">
                            <div id="password-strength" class="password-strength" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirmar Nueva Contraseña:</label>
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Requisitos de contraseña:</strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li>Mínimo 6 caracteres</li>
                                    <li>Se recomienda usar letras, números y símbolos</li>
                                    <li>Evite contraseñas obvias como fechas o nombres</li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Tab: Configuraciones -->
                <div id="settings" class="tab-content">
                    <h3><i class="fas fa-cog"></i> Configuraciones del Sistema</h3>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Información del Sistema:</strong> Las configuraciones globales solo pueden ser modificadas por super administradores.
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-bell"></i> Notificaciones:</label>
                        <div style="margin-top: 10px;">
                            <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <input type="checkbox" checked disabled> Notificaciones de sistema
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <input type="checkbox" checked disabled> Alertas de seguridad
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" disabled> Reportes automáticos
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-palette"></i> Tema del Sistema:</label>
                        <select disabled>
                            <option>Claro (Por defecto)</option>
                            <option>Oscuro</option>
                            <option>Automático</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-language"></i> Idioma:</label>
                        <select disabled>
                            <option>Español (Por defecto)</option>
                            <option>English</option>
                            <option>Português</option>
                        </select>
                    </div>
                    
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-save"></i> Guardar Configuraciones
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas del Sistema -->
        <div class="card">
            <h2><i class="fas fa-chart-bar"></i> Estadísticas del Sistema</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number"><?php echo $systemStats['total_usuarios']; ?></div>
                    <div class="label">Total Usuarios</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="number"><?php echo $systemStats['usuarios_estudiante'] ?? 0; ?></div>
                    <div class="label">Estudiantes</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="number"><?php echo $systemStats['usuarios_docente'] ?? 0; ?></div>
                    <div class="label">Docentes</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-book"></i></div>
                    <div class="number"><?php echo $systemStats['total_cursos']; ?></div>
                    <div class="label">Unidades Didácticas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="number"><?php echo $systemStats['total_programas']; ?></div>
                    <div class="label">Programas de Estudio</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                    <div class="number"><?php echo $systemStats['total_matriculas']; ?></div>
                    <div class="label">Matrículas Activas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="number"><?php echo $systemStats['total_sesiones']; ?></div>
                    <div class="label">Sesiones Realizadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="number"><?php echo $systemStats['total_evaluaciones']; ?></div>
                    <div class="label">Evaluaciones Registradas</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Ocultar todos los contenidos de tabs
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Quitar clase activa de todos los tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el tab clickeado
            const clickedTab = event.target.closest('.tab');
            if (clickedTab) {
                clickedTab.classList.add('active');
            }
        }
        
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let strength = 0;
            let feedback = [];
            
            // Verificar longitud
            if (password.length >= 8) strength++;
            else feedback.push('al menos 8 caracteres');
            
            // Verificar letras minúsculas
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('letras minúsculas');
            
            // Verificar letras mayúsculas
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('letras mayúsculas');
            
            // Verificar números
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('números');
            
            // Verificar símbolos
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('símbolos especiales');
            
            let strengthText = '';
            let strengthClass = '';
            
            if (strength < 2) {
                strengthText = 'Débil';
                strengthClass = 'password-weak';
            } else if (strength < 4) {
                strengthText = 'Media';
                strengthClass = 'password-medium';
            } else {
                strengthText = 'Fuerte';
                strengthClass = 'password-strong';
            }
            
            strengthDiv.className = 'password-strength ' + strengthClass;
            strengthDiv.innerHTML = `Fortaleza: ${strengthText}` + 
                (feedback.length > 0 ? ` - Falta: ${feedback.join(', ')}` : '');
        }
        
        // Validaciones de formularios
        document.addEventListener('DOMContentLoaded', function() {
            // Validación del formulario de perfil
            const profileForm = document.getElementById('profile-form');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const nombres = document.getElementById('nombres').value.trim();
                    const apellidos = document.getElementById('apellidos').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const dni = document.getElementById('dni').value.trim();
                    
                    if (!nombres || !apellidos || !email || !dni) {
                        alert('Todos los campos son obligatorios');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (!/^[0-9]{8}$/.test(dni)) {
                        alert('El DNI debe tener exactamente 8 dígitos');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        alert('Ingrese un email válido');
                        e.preventDefault();
                        return false;
                    }
                    
                    return confirm('¿Está seguro de que quiere actualizar su perfil?');
                });
            }
            
            // Validación del formulario de contraseña
            const passwordForm = document.getElementById('password-form');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (!currentPassword || !newPassword || !confirmPassword) {
                        alert('Todos los campos son obligatorios');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        alert('La nueva contraseña debe tener al menos 6 caracteres');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        alert('Las contraseñas nuevas no coinciden');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (currentPassword === newPassword) {
                        alert('La nueva contraseña debe ser diferente a la actual');
                        e.preventDefault();
                        return false;
                    }
                    
                    return confirm('¿Está seguro de que quiere cambiar su contraseña?');
                });
            }
            
            // Agregar event listeners a los tabs
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(tab => {
                tab.addEventListener('click', function() {
                    if (this.textContent.includes('Editar Perfil')) {
                        showTab('profile');
                    } else if (this.textContent.includes('Cambiar Contraseña')) {
                        showTab('password');
                    } else if (this.textContent.includes('Configuraciones')) {
                        showTab('settings');
                    }
                });
            });
            
            // Animaciones de entrada
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
            
            // Animaciones para las estadísticas
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, 100);
                }, 1000 + (index * 100));
            });
        });
    </script>
</body>
</html>