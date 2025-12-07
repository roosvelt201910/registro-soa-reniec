<?php
require_once '../config/database.php';

// Verificar sesión y tipo de usuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'update_profile') {
            $email = sanitizeInput($_POST['email']);
            $telefono = sanitizeInput($_POST['telefono'] ?? '');
            
            try {
                // Verificar si el email ya existe para otro usuario
                $sql_check = "SELECT id FROM usuarios WHERE email = :email AND id != :student_id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':email', $email);
                $stmt_check->bindParam(':student_id', $student_id);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    $message = 'El correo electrónico ya está registrado por otro usuario.';
                    $message_type = 'error';
                } else {
                    // Actualizar perfil (añadiendo campo telefono si existe en la BD)
                    $sql_update = "UPDATE usuarios SET email = :email WHERE id = :student_id";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bindParam(':email', $email);
                    $stmt_update->bindParam(':student_id', $student_id);
                    
                    if ($stmt_update->execute()) {
                        $message = 'Perfil actualizado exitosamente.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error al actualizar el perfil.';
                        $message_type = 'error';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        } elseif ($_POST['action'] == 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $message = 'Las contraseñas nuevas no coinciden.';
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'La contraseña debe tener al menos 6 caracteres.';
                $message_type = 'error';
            } else {
                try {
                    // Verificar contraseña actual
                    $sql_verify = "SELECT password FROM usuarios WHERE id = :student_id";
                    $stmt_verify = $conn->prepare($sql_verify);
                    $stmt_verify->bindParam(':student_id', $student_id);
                    $stmt_verify->execute();
                    $user = $stmt_verify->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($current_password, $user['password'])) {
                        // Actualizar contraseña
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql_update_pass = "UPDATE usuarios SET password = :password WHERE id = :student_id";
                        $stmt_update_pass = $conn->prepare($sql_update_pass);
                        $stmt_update_pass->bindParam(':password', $hashed_password);
                        $stmt_update_pass->bindParam(':student_id', $student_id);
                        
                        if ($stmt_update_pass->execute()) {
                            $message = 'Contraseña actualizada exitosamente.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error al actualizar la contraseña.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'La contraseña actual es incorrecta.';
                        $message_type = 'error';
                    }
                } catch (PDOException $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

try {
    // Obtener información del estudiante
    $sql_student = "
        SELECT 
            u.dni,
            u.nombres,
            u.apellidos,
            u.email,
            u.fecha_creacion,
            u.estado
        FROM usuarios u
        WHERE u.id = :student_id
    ";
    
    $stmt_student = $conn->prepare($sql_student);
    $stmt_student->bindParam(':student_id', $student_id);
    $stmt_student->execute();
    $student_info = $stmt_student->fetch(PDO::FETCH_ASSOC);
    
    // Obtener cursos matriculados
    $sql_courses = "
        SELECT 
            ud.nombre as curso_nombre,
            ud.codigo,
            ud.periodo_lectivo,
            ud.periodo_academico,
            pe.nombre as programa_nombre,
            m.fecha_matricula
        FROM matriculas m
        INNER JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
        INNER JOIN programas_estudio pe ON ud.programa_id = pe.id
        WHERE m.estudiante_id = :student_id
        AND m.estado = 'activo'
        ORDER BY m.fecha_matricula DESC
    ";
    
    $stmt_courses = $conn->prepare($sql_courses);
    $stmt_courses->bindParam(':student_id', $student_id);
    $stmt_courses->execute();
    $enrolled_courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas generales
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT m.unidad_didactica_id) as total_cursos,
            COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_completadas,
            COUNT(DISTINCT CASE WHEN a.estado = 'presente' THEN a.id END) as total_asistencias,
            COUNT(DISTINCT CASE WHEN a.estado = 'falta' THEN a.id END) as total_faltas
        FROM matriculas m
        LEFT JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
        LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
        LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = m.estudiante_id
        WHERE m.estudiante_id = :student_id
        AND m.estado = 'activo'
    ";
    
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bindParam(':student_id', $student_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Calcular promedio general
    $sql_average = "
        SELECT AVG(promedio_indicador) as promedio_general
        FROM (
            SELECT AVG(es.calificacion) as promedio_indicador
            FROM evaluaciones_sesion es
            INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
            INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
            INNER JOIN unidades_didacticas ud ON il.unidad_didactica_id = ud.id
            INNER JOIN matriculas m ON m.unidad_didactica_id = ud.id
            WHERE m.estudiante_id = :student_id
            AND m.estado = 'activo'
            GROUP BY il.id
        ) as promedios
    ";
    
    $stmt_average = $conn->prepare($sql_average);
    $stmt_average->bindParam(':student_id', $student_id);
    $stmt_average->execute();
    $average_result = $stmt_average->fetch(PDO::FETCH_ASSOC);
    $promedio_general = $average_result['promedio_general'] ?? 0;
    
    // Calcular porcentaje de asistencia
    $attendance_percentage = 0;
    if (($stats['total_asistencias'] + $stats['total_faltas']) > 0) {
        $attendance_percentage = ($stats['total_asistencias'] / ($stats['total_asistencias'] + $stats['total_faltas'])) * 100;
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - IESPH Alto Huallaga</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --primary-light: #818CF8;
            --secondary: #06B6D4;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #1F2937;
            --gray: #6B7280;
            --light: #F9FAFB;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%);
            z-index: -1;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title h1 {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .header-title i {
            font-size: 1.5rem;
            color: white;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .breadcrumb a:hover {
            opacity: 0.8;
        }
        
        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 150px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            z-index: 0;
        }
        
        .profile-content {
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar-section {
            display: flex;
            align-items: end;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            border: 5px solid white;
            box-shadow: var(--shadow-lg);
        }
        
        .profile-info {
            flex: 1;
            padding-bottom: 1rem;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .profile-role {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.375rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 12px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light);
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--light);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        
        .form-input:disabled {
            background: var(--light);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-group-inline {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background: var(--dark);
            transform: translateY(-2px);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .courses-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .course-item {
            padding: 1rem;
            background: var(--light);
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .course-item:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(5px);
        }
        
        .course-info {
            flex: 1;
        }
        
        .course-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .course-meta {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .course-date {
            font-size: 0.75rem;
            color: var(--gray);
            text-align: right;
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--light);
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .activity-content {
            padding: 1rem;
            background: var(--light);
            border-radius: 10px;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .activity-description {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .strength-weak {
            color: var(--danger);
        }
        
        .strength-medium {
            color: var(--warning);
        }
        
        .strength-strong {
            color: var(--success);
        }
        
        .show-password-toggle {
            position: relative;
        }
        
        .show-password-toggle .toggle-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .show-password-toggle .toggle-btn:hover {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-group-inline {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-graduation-cap"></i>
                <h1>IESPH Alto Huallaga</h1>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="header-btn">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="../logout.php" class="header-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <nav class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <span>Mi Perfil</span>
        </nav>
        
        <!-- Mensaje de feedback -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Header del perfil -->
        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name">
                            <?php echo htmlspecialchars($student_info['apellidos'] . ', ' . $student_info['nombres']); ?>
                        </h2>
                        <span class="profile-role">
                            <i class="fas fa-user-graduate"></i> Estudiante
                        </span>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_cursos']; ?></div>
                        <div class="stat-label">Cursos Activos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: <?php echo $promedio_general >= 13 ? 'var(--success)' : ($promedio_general >= 10 ? 'var(--warning)' : 'var(--danger)'); ?>">
                            <?php echo number_format($promedio_general, 1); ?>
                        </div>
                        <div class="stat-label">Promedio General</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: <?php echo $attendance_percentage >= 70 ? 'var(--success)' : 'var(--danger)'; ?>">
                            <?php echo number_format($attendance_percentage, 0); ?>%
                        </div>
                        <div class="stat-label">Asistencia</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['sesiones_completadas']; ?></div>
                        <div class="stat-label">Sesiones Completadas</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Información Personal -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3 class="card-title">Información Personal</h3>
                </div>
                
                <form method="POST" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group-inline">
                        <div class="form-group">
                            <label class="form-label">DNI</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($student_info['dni']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <input type="text" class="form-input" value="<?php echo ucfirst($student_info['estado']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group-inline">
                        <div class="form-group">
                            <label class="form-label">Nombres</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($student_info['nombres']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Apellidos</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($student_info['apellidos']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Correo Electrónico</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($student_info['email']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="telefono">Teléfono (Opcional)</label>
                        <input type="tel" 
                               id="telefono" 
                               name="telefono" 
                               class="form-input" 
                               placeholder="999 999 999">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Registro</label>
                        <input type="text" 
                               class="form-input" 
                               value="<?php echo date('d/m/Y H:i', strtotime($student_info['fecha_creacion'])); ?>" 
                               disabled>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Cambios
                    </button>
                </form>
            </div>
            
            <!-- Cambiar Contraseña -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3 class="card-title">Seguridad</h3>
                </div>
                
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_password">Contraseña Actual</label>
                        <div class="show-password-toggle">
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-input" 
                                   required>
                            <button type="button" class="toggle-btn" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password">Nueva Contraseña</label>
                        <div class="show-password-toggle">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-input" 
                                   required
                                   onkeyup="checkPasswordStrength(this.value)">
                            <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nueva Contraseña</label>
                        <div class="show-password-toggle">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-input" 
                                   required>
                            <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Cambiar Contraseña
                    </button>
                </form>
            </div>
            
            <!-- Cursos Matriculados -->
            <div class="card full-width">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="card-title">Cursos Matriculados</h3>
                </div>
                
                <?php if (!empty($enrolled_courses)): ?>
                    <div class="courses-list">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="course-item">
                                <div class="course-info">
                                    <div class="course-name">
                                        <i class="fas fa-graduation-cap" style="color: var(--primary); margin-right: 0.5rem;"></i>
                                        <?php echo htmlspecialchars($course['curso_nombre']); ?>
                                    </div>
                                    <div class="course-meta">
                                        <span style="margin-right: 1rem;">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['codigo']); ?>
                                        </span>
                                        <span style="margin-right: 1rem;">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($course['programa_nombre']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($course['periodo_lectivo'] . ' - ' . $course['periodo_academico']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="course-date">
                                    Matriculado el<br>
                                    <?php echo date('d/m/Y', strtotime($course['fecha_matricula'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray); padding: 2rem;">
                        No tienes cursos matriculados actualmente.
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Actividad Reciente -->
            <div class="card full-width">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="card-title">Actividad Reciente</h3>
                </div>
                
                <div class="activity-timeline">
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title">Inicio de sesión</div>
                            <div class="activity-description">Has accedido al sistema</div>
                            <div class="activity-time">Hoy, <?php echo date('H:i'); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($stats['sesiones_completadas'] > 0): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title">Sesiones Completadas</div>
                            <div class="activity-description">Has completado <?php echo $stats['sesiones_completadas']; ?> sesiones</div>
                            <div class="activity-time">Este periodo</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['total_asistencias'] > 0): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title">Registro de Asistencia</div>
                            <div class="activity-description"><?php echo $stats['total_asistencias']; ?> asistencias registradas</div>
                            <div class="activity-time">Total acumulado</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Función para mostrar/ocultar contraseña
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Función para verificar la fortaleza de la contraseña
        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('password-strength');
            let strength = 0;
            let message = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                case 2:
                    message = '<span class="strength-weak"><i class="fas fa-exclamation-triangle"></i> Contraseña débil</span>';
                    break;
                case 3:
                case 4:
                    message = '<span class="strength-medium"><i class="fas fa-shield-alt"></i> Contraseña media</span>';
                    break;
                case 5:
                    message = '<span class="strength-strong"><i class="fas fa-check-circle"></i> Contraseña fuerte</span>';
                    break;
            }
            
            strengthElement.innerHTML = message;
        }
        
        // Validación del formulario de contraseña
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas nuevas no coinciden');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
        });
        
        // Ocultar mensajes de alerta después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>