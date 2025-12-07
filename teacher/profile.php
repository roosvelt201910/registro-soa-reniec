<?php
require_once '../config/database.php';

// Verificar sesión y tipo de usuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'docente') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
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
            $bio = sanitizeInput($_POST['bio'] ?? '');
            $especialidad = sanitizeInput($_POST['especialidad'] ?? '');
            
            try {
                // Verificar si el email ya existe para otro usuario
                $sql_check = "SELECT id FROM usuarios WHERE email = :email AND id != :teacher_id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':email', $email);
                $stmt_check->bindParam(':teacher_id', $teacher_id);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    $message = 'El correo electrónico ya está registrado por otro usuario.';
                    $message_type = 'error';
                } else {
                    // Actualizar perfil
                    $sql_update = "UPDATE usuarios SET email = :email WHERE id = :teacher_id";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bindParam(':email', $email);
                    $stmt_update->bindParam(':teacher_id', $teacher_id);
                    
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
                    $sql_verify = "SELECT password FROM usuarios WHERE id = :teacher_id";
                    $stmt_verify = $conn->prepare($sql_verify);
                    $stmt_verify->bindParam(':teacher_id', $teacher_id);
                    $stmt_verify->execute();
                    $user = $stmt_verify->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        $password_valid = false;
                        
                        // Verificar con password_verify (contraseñas hasheadas)
                        if (password_verify($current_password, $user['password'])) {
                            $password_valid = true;
                        }
                        // Verificar directamente (contraseñas sin hash - temporal)
                        elseif ($current_password === $user['password']) {
                            $password_valid = true;
                        }
                        
                        if ($password_valid) {
                            // Actualizar contraseña
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $sql_update_pass = "UPDATE usuarios SET password = :password WHERE id = :teacher_id";
                            $stmt_update_pass = $conn->prepare($sql_update_pass);
                            $stmt_update_pass->bindParam(':password', $hashed_password);
                            $stmt_update_pass->bindParam(':teacher_id', $teacher_id);
                            
                            if ($stmt_update_pass->execute()) {
                                $message = 'Contraseña actualizada exitosamente y encriptada de forma segura.';
                                $message_type = 'success';
                            } else {
                                $message = 'Error al actualizar la contraseña.';
                                $message_type = 'error';
                            }
                        } else {
                            $message = 'La contraseña actual es incorrecta.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Error: Usuario no encontrado.';
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
    // Obtener información del docente
    $sql_teacher = "
        SELECT 
            u.dni,
            u.nombres,
            u.apellidos,
            u.email,
            u.fecha_creacion,
            u.estado
        FROM usuarios u
        WHERE u.id = :teacher_id
    ";
    
    $stmt_teacher = $conn->prepare($sql_teacher);
    $stmt_teacher->bindParam(':teacher_id', $teacher_id);
    $stmt_teacher->execute();
    $teacher_info = $stmt_teacher->fetch(PDO::FETCH_ASSOC);
    
    // Obtener cursos que imparte con estadísticas detalladas
    $sql_courses = "
        SELECT 
            ud.id,
            ud.nombre as curso_nombre,
            ud.codigo,
            ud.periodo_lectivo,
            ud.periodo_academico,
            pe.nombre as programa_nombre,
            COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
            AVG(es.calificacion) as promedio_curso,
            COUNT(DISTINCT CASE WHEN es.calificacion >= 13 THEN es.estudiante_id END) as estudiantes_aprobados,
            COUNT(DISTINCT s.id) as total_sesiones,
            COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_realizadas
        FROM unidades_didacticas ud
        INNER JOIN programas_estudio pe ON ud.programa_id = pe.id
        LEFT JOIN matriculas m ON m.unidad_didactica_id = ud.id AND m.estado = 'activo'
        LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
        LEFT JOIN indicadores_logro il ON il.unidad_didactica_id = ud.id
        LEFT JOIN indicadores_evaluacion ie ON ie.indicador_logro_id = il.id
        LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
        WHERE ud.docente_id = :teacher_id
        AND ud.estado = 'activo'
        GROUP BY ud.id
        ORDER BY ud.periodo_lectivo DESC, ud.nombre ASC
    ";
    
    $stmt_courses = $conn->prepare($sql_courses);
    $stmt_courses->bindParam(':teacher_id', $teacher_id);
    $stmt_courses->execute();
    $teaching_courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas generales del docente
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT ud.id) as total_cursos,
            COUNT(DISTINCT m.estudiante_id) as total_estudiantes,
            COUNT(DISTINCT s.id) as total_sesiones,
            COUNT(DISTINCT CASE WHEN s.estado = 'realizada' THEN s.id END) as sesiones_completadas,
            COUNT(DISTINCT es.id) as total_evaluaciones,
            COUNT(DISTINCT CASE WHEN es.calificacion >= 13 THEN es.id END) as evaluaciones_aprobadas
        FROM unidades_didacticas ud
        LEFT JOIN matriculas m ON m.unidad_didactica_id = ud.id AND m.estado = 'activo'
        LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
        LEFT JOIN indicadores_logro il ON il.unidad_didactica_id = ud.id
        LEFT JOIN indicadores_evaluacion ie ON ie.indicador_logro_id = il.id
        LEFT JOIN evaluaciones_sesion es ON es.indicador_evaluacion_id = ie.id
        WHERE ud.docente_id = :teacher_id
        AND ud.estado = 'activo'
    ";
    
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bindParam(':teacher_id', $teacher_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Obtener promedio de calificaciones de sus estudiantes
    $sql_average = "
        SELECT AVG(es.calificacion) as promedio_general
        FROM evaluaciones_sesion es
        INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
        INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
        INNER JOIN unidades_didacticas ud ON il.unidad_didactica_id = ud.id
        WHERE ud.docente_id = :teacher_id
    ";
    
    $stmt_average = $conn->prepare($sql_average);
    $stmt_average->bindParam(':teacher_id', $teacher_id);
    $stmt_average->execute();
    $average_result = $stmt_average->fetch(PDO::FETCH_ASSOC);
    $promedio_estudiantes = $average_result['promedio_general'] ?? 0;
    
    // Obtener actividad reciente con más detalles
    $sql_recent = "
        SELECT 
            'sesion' as tipo,
            s.titulo as descripcion,
            s.fecha as fecha_actividad,
            ud.nombre as curso_nombre,
            COUNT(DISTINCT a.estudiante_id) as participantes
        FROM sesiones s
        INNER JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id
        LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estado = 'presente'
        WHERE ud.docente_id = :teacher_id
        AND s.estado = 'realizada'
        GROUP BY s.id
        
        UNION ALL
        
        SELECT 
            'evaluacion' as tipo,
            CONCAT('Evaluación: ', il.nombre) as descripcion,
            es.fecha_evaluacion as fecha_actividad,
            ud.nombre as curso_nombre,
            COUNT(DISTINCT es.estudiante_id) as participantes
        FROM evaluaciones_sesion es
        INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
        INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
        INNER JOIN unidades_didacticas ud ON il.unidad_didactica_id = ud.id
        WHERE ud.docente_id = :teacher_id2
        GROUP BY ie.id, es.fecha_evaluacion
        
        ORDER BY fecha_actividad DESC
        LIMIT 10
    ";
    
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->bindParam(':teacher_id', $teacher_id);
    $stmt_recent->bindParam(':teacher_id2', $teacher_id);
    $stmt_recent->execute();
    $recent_activities = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener próximas sesiones
    $sql_upcoming = "
        SELECT 
            s.titulo,
            s.fecha,
            s.numero_sesion,
            ud.nombre as curso_nombre,
            ud.codigo
        FROM sesiones s
        INNER JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id
        WHERE ud.docente_id = :teacher_id
        AND s.estado = 'programada'
        AND s.fecha >= CURDATE()
        ORDER BY s.fecha ASC
        LIMIT 5
    ";
    
    $stmt_upcoming = $conn->prepare($sql_upcoming);
    $stmt_upcoming->bindParam(':teacher_id', $teacher_id);
    $stmt_upcoming->execute();
    $upcoming_sessions = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener top 5 estudiantes con mejor rendimiento
    $sql_top_students = "
        SELECT 
            u.nombres,
            u.apellidos,
            AVG(es.calificacion) as promedio,
            COUNT(DISTINCT ud.id) as cursos_tomados
        FROM usuarios u
        INNER JOIN evaluaciones_sesion es ON es.estudiante_id = u.id
        INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
        INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
        INNER JOIN unidades_didacticas ud ON il.unidad_didactica_id = ud.id
        WHERE ud.docente_id = :teacher_id
        GROUP BY u.id
        ORDER BY promedio DESC
        LIMIT 5
    ";
    
    $stmt_top = $conn->prepare($sql_top_students);
    $stmt_top->bindParam(':teacher_id', $teacher_id);
    $stmt_top->execute();
    $top_students = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular porcentaje de progreso
    $progress_percentage = $stats['total_sesiones'] > 0 ? 
        ($stats['sesiones_completadas'] / $stats['total_sesiones']) * 100 : 0;
    
    // Calcular tasa de aprobación
    $approval_rate = $stats['total_evaluaciones'] > 0 ?
        ($stats['evaluaciones_aprobadas'] / $stats['total_evaluaciones']) * 100 : 0;
    
    // Obtener distribución de calificaciones para gráfico
    $sql_grade_distribution = "
        SELECT 
            CASE 
                WHEN es.calificacion >= 18 THEN 'Excelente'
                WHEN es.calificacion >= 13 THEN 'Bueno'
                WHEN es.calificacion >= 10 THEN 'En Proceso'
                ELSE 'Desaprobado'
            END as categoria,
            COUNT(*) as cantidad
        FROM evaluaciones_sesion es
        INNER JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
        INNER JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
        INNER JOIN unidades_didacticas ud ON il.unidad_didactica_id = ud.id
        WHERE ud.docente_id = :teacher_id
        GROUP BY categoria
        ORDER BY 
            CASE categoria
                WHEN 'Excelente' THEN 1
                WHEN 'Bueno' THEN 2
                WHEN 'En Proceso' THEN 3
                ELSE 4
            END
    ";
    
    $stmt_distribution = $conn->prepare($sql_grade_distribution);
    $stmt_distribution->bindParam(':teacher_id', $teacher_id);
    $stmt_distribution->execute();
    $grade_distribution = $stmt_distribution->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Verificar si la contraseña necesita ser migrada
$password_needs_migration = false;
try {
    $sql_check_password = "SELECT password FROM usuarios WHERE id = :teacher_id";
    $stmt_check_password = $conn->prepare($sql_check_password);
    $stmt_check_password->bindParam(':teacher_id', $teacher_id);
    $stmt_check_password->execute();
    $password_info = $stmt_check_password->fetch(PDO::FETCH_ASSOC);
    
    if ($password_info) {
        $hash_info = password_get_info($password_info['password']);
        if ($hash_info['algoName'] === 'unknown') {
            $password_needs_migration = true;
        }
    }
} catch (Exception $e) {
    // Silenciar errores de verificación de password
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎓 Mi Perfil Docente Ultra - IESP "Alto Huallaga"</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #042e7dff;
            --primary-dark: #03477eff;
            --primary-light: #0213adff;
            --secondary: #06b6d4;
            --secondary-dark: #0891b2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #f3f4f6;
            --gray-lighter: #f9fafb;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #04275cff 0%, #09268dff 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
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
                radial-gradient(circle at 80% 20%, rgba(9, 54, 132, 0.31) 0%, transparent 50%);
            z-index: -2;
        }
        
        .animated-particles {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: float 25s infinite linear;
        }
        
        @keyframes float {
            from {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            to {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            animation: slideDown 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 2rem;
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
            font-size: 1.875rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .header-title i {
            font-size: 2rem;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .header-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: none;
        }
        
        .header-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .header-btn:hover::before {
            left: 100%;
        }
        
        .header-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
            animation: fadeInUp 1s cubic-bezier(0.23, 1, 0.32, 1);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .breadcrumb i {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .profile-header {
            background: white;
            border-radius: 24px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-2xl);
            position: relative;
            overflow: hidden;
            animation: scaleIn 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .profile-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E");
            z-index: 1;
        }
        
        .profile-content {
            position: relative;
            z-index: 2;
            padding: 2rem;
        }
        
        .profile-avatar-section {
            display: flex;
            align-items: end;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 160px;
            height: 160px;
            background: linear-gradient(135deg, white, var(--gray-lighter));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--primary);
            border: 6px solid white;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .profile-avatar::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: var(--transition);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05) rotate(5deg);
        }
        
        .profile-avatar:hover::before {
            animation: shine 0.8s ease-in-out;
        }
        
        @keyframes shine {
            from { transform: translateX(-100%) rotate(45deg); }
            to { transform: translateX(100%) rotate(45deg); }
        }
        
        .profile-info {
            flex: 1;
            padding-bottom: 1rem;
        }
        
        .profile-name {
            font-size: 2.75rem;
            font-weight: 900;
            color: white;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
            animation: slideInRight 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .profile-badges {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            animation: slideInRight 0.8s cubic-bezier(0.23, 1, 0.32, 1) 0.2s both;
        }
        
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .profile-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .profile-badge:hover::before {
            left: 100%;
        }
        
        .profile-badge:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            animation: slideInUp 0.8s cubic-bezier(0.23, 1, 0.32, 1) 0.4s both;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--gray-lighter), white);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover::before {
            transform: scaleX(1);
        }
        
        .stat-item:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            animation: countUp 1.5s ease-out;
        }
        
        @keyframes countUp {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            animation: fadeInUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s;
        }
        
        .card:hover::before {
            left: 100%;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
            position: relative;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            animation: expandLine 0.8s ease-out 0.5s both;
        }
        
        @keyframes expandLine {
            from { width: 0; }
            to { width: 80px; }
        }
        
        .card-header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .card-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: var(--transition);
        }
        
        .card-icon:hover {
            transform: scale(1.1) rotate(10deg);
        }
        
        .card-icon:hover::before {
            animation: iconShine 0.6s ease-in-out;
        }
        
        @keyframes iconShine {
            from { transform: translateX(-100%) rotate(45deg); }
            to { transform: translateX(100%) rotate(45deg); }
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark);
        }
        
        .card-action {
            background: var(--gray-light);
            color: var(--gray);
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.25rem;
        }
        
        .card-action:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .card-action.editing {
            background: var(--danger) !important;
            color: white !important;
            animation: pulse 2s infinite;
        }
        
        .form-input.editable {
            background: white !important;
            border-color: var(--primary) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2) !important;
        }
        
        .form-input:disabled {
            background: var(--gray-light);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        #profileForm.editing .form-input:not(:disabled) {
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from {
                box-shadow: 0 0 5px rgba(102, 126, 234, 0.2);
            }
            to {
                box-shadow: 0 0 20px rgba(102, 126, 234, 0.4);
            }
        }
        
        .editing-indicator {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .col-span-4 { grid-column: span 4; }
        .col-span-6 { grid-column: span 6; }
        .col-span-8 { grid-column: span 8; }
        .col-span-12 { grid-column: span 12; }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--gray-lighter);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .form-input:disabled {
            background: var(--gray-light);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group-inline {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background: var(--gray);
            color: white;
        }
        
        .alert {
            padding: 1.5rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
            animation: slideInAlert 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }
        
        @keyframes slideInAlert {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            animation: expandAlert 0.6s ease-out 0.3s both;
        }
        
        @keyframes expandAlert {
            from { height: 0; }
            to { height: 100%; }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-success::before {
            background: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert-error::before {
            background: var(--danger);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .alert-warning::before {
            background: var(--warning);
        }
        
        .alert i {
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }
        
        .course-card {
            padding: 2rem;
            background: linear-gradient(135deg, var(--gray-lighter), white);
            border-radius: 16px;
            border-left: 5px solid var(--primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, var(--primary-light), transparent);
            opacity: 0.1;
            transition: opacity 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-left-color: var(--secondary);
        }
        
        .course-card:hover::before {
            opacity: 0.2;
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .course-name {
            font-weight: 800;
            color: var(--dark);
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        .course-code {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .course-meta {
            font-size: 0.875rem;
            color: var(--gray);
            display: flex;
            gap: 1.5rem;
            margin: 1rem 0;
        }
        
        .course-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-meta-item i {
            width: 16px;
            color: var(--primary);
        }
        
        .course-stats-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }
        
        .course-stat-mini {
            text-align: center;
        }
        
        .course-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .course-stat-label {
            font-size: 0.625rem;
            color: var(--gray);
            text-transform: uppercase;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--gray-light);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            transition: width 1.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .chart-container {
            position: relative;
            height: 320px;
        }
        
        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 450px;
            overflow-y: auto;
        }
        
        .upcoming-item {
            padding: 1.25rem;
            background: var(--gray-lighter);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .upcoming-item:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(8px);
        }
        
        .upcoming-date {
            min-width: 70px;
            text-align: center;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            border-radius: 12px;
            color: white;
            box-shadow: 0 8px 16px rgba(6, 182, 212, 0.3);
        }
        
        .upcoming-date-day {
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
        }
        
        .upcoming-date-month {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        .upcoming-info {
            flex: 1;
        }
        
        .upcoming-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        .upcoming-course {
            font-size: 0.875rem;
            color: var(--secondary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .top-students-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .student-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: var(--gray-lighter);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .student-item:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(8px);
        }
        
        .student-rank {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        
        .student-rank.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            box-shadow: 0 8px 16px rgba(255, 215, 0, 0.4);
        }
        
        .student-rank.silver {
            background: linear-gradient(135deg, #C0C0C0, #808080);
            box-shadow: 0 8px 16px rgba(192, 192, 192, 0.4);
        }
        
        .student-rank.bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            box-shadow: 0 8px 16px rgba(205, 127, 50, 0.4);
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }
        
        .student-stats {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .student-grade {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
            max-height: 450px;
            overflow-y: auto;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
            animation: slideInLeft 0.5s ease;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 0.75rem;
            width: 14px;
            height: 14px;
            background: white;
            border: 3px solid var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .activity-item.sesion::before {
            border-color: var(--success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .activity-item.evaluacion::before {
            border-color: var(--warning);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
        }
        
        .activity-content {
            padding: 1.25rem;
            background: var(--gray-lighter);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .activity-content:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(5px);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }
        
        .activity-title {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .activity-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: white;
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--gray);
            box-shadow: var(--shadow-sm);
        }
        
        .activity-description {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        
        .activity-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .activity-course {
            color: var(--primary);
            font-weight: 600;
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
            border-radius: 6px;
            transition: var(--transition);
        }
        
        .show-password-toggle .toggle-btn:hover {
            color: var(--primary);
            background: var(--gray-light);
        }
        
        .password-strength {
            margin-top: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
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
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
        }
        
        .settings-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gray-light);
        }
        
        .settings-section h4 {
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .settings-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .settings-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            padding: 0.75rem;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .settings-option:hover {
            background: var(--gray-light);
        }
        
        .settings-option input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(6, 1fr);
            }
            
            .col-span-4,
            .col-span-6,
            .col-span-8 {
                grid-column: span 6;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-content {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .header-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .col-span-4,
            .col-span-6,
            .col-span-8,
            .col-span-12 {
                grid-column: span 1;
            }
            
            .profile-avatar-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-name {
                font-size: 2.25rem;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-group-inline {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-value {
                font-size: 2rem;
            }
        }
        
        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-light);
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary-dark));
        }
    </style>
</head>
<body>
    <!-- Partículas animadas mejoradas -->
    <div class="animated-particles">
        <?php for($i = 0; $i < 30; $i++): ?>
            <div class="particle" style="left: <?php echo rand(0, 100); ?>%; animation-delay: <?php echo rand(0, 25); ?>s; animation-duration: <?php echo rand(20, 30); ?>s;"></div>
        <?php endfor; ?>
    </div>
    
    <header class="header">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-graduation-cap"></i>
                <h1>IESTP "Alto Huallaga"</h1>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="header-btn">
                    <i class="fas fa-print"></i>
                    <span>Imprimir</span>
                </button>
                <button onclick="exportData()" class="header-btn">
                    <i class="fas fa-download"></i>
                    <span>Exportar</span>
                </button>
                <a href="../dashboard.php" class="header-btn">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="../logout.php" class="header-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <nav class="breadcrumb">
            <a href="../dashboard.php">
                <i class="fas fa-home"></i> Inicio
            </a>
            <i class="fas fa-chevron-right"></i>
            <span><i class="fas fa-user-circle"></i> Mi Perfil Docente Ultra</span>
        </nav>
        
        <!-- Advertencia de contraseña sin hash -->
        <?php if ($password_needs_migration): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>⚠️ Advertencia de Seguridad:</strong> Tu contraseña necesita ser actualizada para mayor seguridad. 
                    <a href="password_debug.php" style="color: var(--warning); text-decoration: underline; font-weight: bold;">
                        Haz clic aquí para solucionarlo
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Mensaje de feedback -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Header del perfil ultra mejorado -->
        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name">
                            <?php echo htmlspecialchars($teacher_info['apellidos'] . ', ' . $teacher_info['nombres']); ?>
                        </h2>
                        <div class="profile-badges">
                            <span class="profile-badge">
                                <i class="fas fa-chalkboard-teacher"></i> 
                                <span>Docente Profesional</span>
                            </span>
                            <span class="profile-badge">
                                <i class="fas fa-id-card"></i> 
                                <span>DNI: <?php echo htmlspecialchars($teacher_info['dni']); ?></span>
                            </span>
                            <span class="profile-badge">
                                <i class="fas fa-envelope"></i> 
                                <span><?php echo htmlspecialchars($teacher_info['email']); ?></span>
                            </span>
                            <span class="profile-badge">
                                <i class="fas fa-calendar-plus"></i> 
                                <span>Desde <?php echo date('Y', strtotime($teacher_info['fecha_creacion'])); ?></span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_cursos'] ?? 0; ?></div>
                        <div class="stat-label"><i class="fas fa-book"></i> Cursos Activos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_estudiantes'] ?? 0; ?></div>
                        <div class="stat-label"><i class="fas fa-user-graduate"></i> Estudiantes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['sesiones_completadas'] ?? 0; ?></div>
                        <div class="stat-label"><i class="fas fa-chalkboard"></i> Sesiones</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_evaluaciones'] ?? 0; ?></div>
                        <div class="stat-label"><i class="fas fa-clipboard-check"></i> Evaluaciones</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($promedio_estudiantes, 1); ?></div>
                        <div class="stat-label"><i class="fas fa-chart-line"></i> Promedio</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($progress_percentage, 0); ?>%</div>
                        <div class="stat-label"><i class="fas fa-tasks"></i> Progreso</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($approval_rate, 0); ?>%</div>
                        <div class="stat-label"><i class="fas fa-award"></i> Aprobación</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Información Personal -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3 class="card-title">Información Personal</h3>
                    </div>
                    <button class="card-action" onclick="toggleEdit('profile')">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
                
                <form method="POST" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group-inline">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-id-card"></i> Documento de Identidad
                            </label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($teacher_info['dni']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-toggle-on"></i> Estado de Cuenta
                            </label>
                            <input type="text" class="form-input" value="<?php echo ucfirst($teacher_info['estado']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group-inline">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Nombres
                            </label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($teacher_info['nombres']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user-tag"></i> Apellidos
                            </label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($teacher_info['apellidos']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">
                            <i class="fas fa-envelope"></i> Correo Electrónico
                        </label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($teacher_info['email']); ?>" 
                               data-original-value="<?php echo htmlspecialchars($teacher_info['email']); ?>"
                               disabled required>
                    </div>
                    
                    
                    
                    
                    
                    <button type="submit" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-save"></i>
                        <span>Guardar Cambios</span>
                    </button>
                </form>
            </div>
            
            <!-- Seguridad y Privacidad -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="card-title">Seguridad y Privacidad</h3>
                    </div>
                </div>
                
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_password">
                            <i class="fas fa-key"></i> Contraseña Actual
                        </label>
                        <div class="show-password-toggle">
                            <input type="password" id="current_password" name="current_password" class="form-input" required placeholder="Ingresa tu contraseña actual">
                            <button type="button" class="toggle-btn" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password">
                            <i class="fas fa-lock"></i> Nueva Contraseña
                        </label>
                        <div class="show-password-toggle">
                            <input type="password" id="new_password" name="new_password" class="form-input" required onkeyup="checkPasswordStrength(this.value)" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">
                            <i class="fas fa-lock-open"></i> Confirmar Nueva Contraseña
                        </label>
                        <div class="show-password-toggle">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required placeholder="Repite la nueva contraseña">
                            <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        <span>Cambiar Contraseña</span>
                    </button>
                </form>
                
                <div class="settings-section">
                    <h4><i class="fas fa-cog"></i> Configuraciones Adicionales</h4>
                    <div class="settings-options">
                        <label class="settings-option">
                            <input type="checkbox" checked>
                            <span><i class="fas fa-bell"></i> Recibir notificaciones por email</span>
                        </label>
                        <label class="settings-option">
                            <input type="checkbox" checked>
                            <span><i class="fas fa-eye"></i> Mostrar mi perfil a estudiantes</span>
                        </label>
                        <label class="settings-option">
                            <input type="checkbox">
                            <span><i class="fas fa-moon"></i> Modo oscuro (próximamente)</span>
                        </label>
                        <label class="settings-option">
                            <input type="checkbox" checked>
                            <span><i class="fas fa-chart-bar"></i> Permitir estadísticas</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Distribución de Calificaciones -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3 class="card-title">Distribución de Calificaciones</h3>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
            
            <!-- Próximas Sesiones -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="card-title">Próximas Sesiones de Clase</h3>
                    </div>
                    <?php if (!empty($upcoming_sessions)): ?>
                        <span class="notification-badge"><?php echo count($upcoming_sessions); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="upcoming-list">
                    <?php if (!empty($upcoming_sessions)): ?>
                        <?php foreach ($upcoming_sessions as $session): 
                            $date = new DateTime($session['fecha']);
                            $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
                        ?>
                            <div class="upcoming-item">
                                <div class="upcoming-date">
                                    <div class="upcoming-date-day"><?php echo $date->format('d'); ?></div>
                                    <div class="upcoming-date-month"><?php echo $months[$date->format('n') - 1]; ?></div>
                                </div>
                                <div class="upcoming-info">
                                    <div class="upcoming-title">
                                        <i class="fas fa-chalkboard"></i>
                                        Sesión <?php echo $session['numero_sesion']; ?>: <?php echo htmlspecialchars($session['titulo']); ?>
                                    </div>
                                    <div class="upcoming-course">
                                        <i class="fas fa-book"></i> 
                                        <span><?php echo htmlspecialchars($session['curso_nombre']); ?> (<?php echo htmlspecialchars($session['codigo']); ?>)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 2rem;">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            No hay sesiones programadas próximamente.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cursos que Imparte -->
            <div class="card col-span-12">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3 class="card-title">Mis Cursos Asignados (<?php echo count($teaching_courses); ?>)</h3>
                    </div>
                </div>
                
                <?php if (!empty($teaching_courses)): ?>
                    <div class="courses-grid">
                        <?php foreach ($teaching_courses as $course): 
                            $course_progress = $course['total_sesiones'] > 0 ? 
                                ($course['sesiones_realizadas'] / $course['total_sesiones']) * 100 : 0;
                        ?>
                            <div class="course-card">
                                <div class="course-header">
                                    <div>
                                        <div class="course-name">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?php echo htmlspecialchars($course['curso_nombre']); ?>
                                        </div>
                                        <span class="course-code">
                                            <i class="fas fa-code"></i>
                                            <?php echo htmlspecialchars($course['codigo']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="course-meta">
                                    <div class="course-meta-item">
                                        <i class="fas fa-university"></i>
                                        <span><?php echo htmlspecialchars($course['programa_nombre']); ?></span>
                                    </div>
                                    <div class="course-meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo htmlspecialchars($course['periodo_lectivo'] . ' - ' . $course['periodo_academico']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="course-stats-mini">
                                    <div class="course-stat-mini">
                                        <div class="course-stat-value"><?php echo $course['total_estudiantes']; ?></div>
                                        <div class="course-stat-label">Estudiantes</div>
                                    </div>
                                    <div class="course-stat-mini">
                                        <div class="course-stat-value" style="color: <?php echo ($course['promedio_curso'] ?? 0) >= 13 ? 'var(--success)' : 'var(--warning)'; ?>">
                                            <?php echo number_format($course['promedio_curso'] ?? 0, 1); ?>
                                        </div>
                                        <div class="course-stat-label">Promedio</div>
                                    </div>
                                    <div class="course-stat-mini">
                                        <div class="course-stat-value"><?php echo $course['estudiantes_aprobados'] ?? 0; ?></div>
                                        <div class="course-stat-label">Aprobados</div>
                                    </div>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $course_progress; ?>%"></div>
                                </div>
                                <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                                    <i class="fas fa-tasks"></i>
                                    <?php echo $course['sesiones_realizadas']; ?>/<?php echo $course['total_sesiones']; ?> sesiones completadas (<?php echo number_format($course_progress, 1); ?>%)
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray); padding: 3rem;">
                        <i class="fas fa-chalkboard-teacher" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                        No tienes cursos asignados actualmente.
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Top 5 Estudiantes -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3 class="card-title">Top Estudiantes Destacados</h3>
                    </div>
                </div>
                
                <div class="top-students-list">
                    <?php if (!empty($top_students)): ?>
                        <?php foreach ($top_students as $index => $student): ?>
                            <div class="student-item">
                                <div class="student-rank <?php echo $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')); ?>">
                                    <?php if ($index == 0): ?>
                                        <i class="fas fa-crown"></i>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="student-info">
                                    <div class="student-name">
                                        <?php echo htmlspecialchars($student['apellidos'] . ', ' . $student['nombres']); ?>
                                    </div>
                                    <div class="student-stats">
                                        <i class="fas fa-book"></i> <?php echo $student['cursos_tomados']; ?> cursos completados
                                    </div>
                                </div>
                                <div class="student-grade">
                                    <?php echo number_format($student['promedio'], 1); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 2rem;">
                            <i class="fas fa-user-graduate" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            No hay datos de estudiantes disponibles.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actividad Reciente -->
            <div class="card col-span-6">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="card-title">Actividad Reciente</h3>
                    </div>
                </div>
                
                <div class="activity-timeline">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item <?php echo $activity['tipo']; ?>">
                                <div class="activity-content">
                                    <div class="activity-header">
                                        <div class="activity-title">
                                            <?php if ($activity['tipo'] == 'sesion'): ?>
                                                <i class="fas fa-chalkboard"></i> Sesión Realizada
                                            <?php else: ?>
                                                <i class="fas fa-clipboard-check"></i> Evaluación
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-badge">
                                            <i class="fas fa-users"></i> <?php echo $activity['participantes'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="activity-description">
                                        <?php echo htmlspecialchars($activity['descripcion']); ?>
                                    </div>
                                    <div class="activity-footer">
                                        <div class="activity-course">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($activity['curso_nombre']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="fas fa-clock"></i>
                                            <?php 
                                            if ($activity['fecha_actividad']) {
                                                echo date('d/m/Y', strtotime($activity['fecha_actividad'])); 
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 2rem;">
                            <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            No hay actividad reciente registrada.
                        </p>
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
                alert('❌ Las contraseñas nuevas no coinciden');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('❌ La contraseña debe tener al menos 6 caracteres');
                return false;
            }
        });
        
        // Toggle edición de formulario
        function toggleEdit(form) {
            const formElement = document.getElementById(form + 'Form');
            const editButton = document.querySelector(`button[onclick="toggleEdit('${form}')"]`);
            const editIcon = editButton.querySelector('i');
            const submitButton = formElement.querySelector('button[type="submit"]');
            
            // Campos que se pueden editar
            const editableFields = ['email', 'telefono', 'especialidad', 'bio'];
            
            // Verificar el estado actual
            const isEditing = formElement.classList.contains('editing');
            
            if (!isEditing) {
                // Activar modo edición
                formElement.classList.add('editing');
                editIcon.className = 'fas fa-times';
                editButton.style.background = 'var(--danger)';
                editButton.style.color = 'white';
                editButton.title = 'Cancelar edición';
                submitButton.style.display = 'inline-flex';
                
                // Agregar indicador visual a la card
                const card = formElement.closest('.card');
                card.style.border = '2px solid var(--primary)';
                card.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
                
                // Agregar badge de edición
                let editingBadge = document.createElement('div');
                editingBadge.className = 'editing-indicator';
                editingBadge.innerHTML = '<i class="fas fa-edit" style="font-size: 10px;"></i>';
                card.style.position = 'relative';
                card.appendChild(editingBadge);
                
                // Habilitar campos editables
                editableFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        field.disabled = false;
                        field.style.background = 'white';
                        field.style.borderColor = 'var(--primary)';
                        field.style.transform = 'translateY(-2px)';
                        field.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.2)';
                    }
                });
                
                // Mostrar mensaje de edición
                showEditingMessage(true);
                
            } else {
                // Desactivar modo edición
                formElement.classList.remove('editing');
                editIcon.className = 'fas fa-edit';
                editButton.style.background = '';
                editButton.style.color = '';
                editButton.title = 'Editar información';
                submitButton.style.display = 'none';
                
                // Quitar indicador visual de la card
                const card = formElement.closest('.card');
                card.style.border = '';
                card.style.boxShadow = '';
                
                // Remover badge de edición
                const editingBadge = card.querySelector('.editing-indicator');
                if (editingBadge) {
                    editingBadge.remove();
                }
                
                // Deshabilitar campos editables y restaurar valores originales
                editableFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        // Restaurar valor original si existe
                        const originalValue = field.getAttribute('data-original-value');
                        if (originalValue !== null) {
                            field.value = originalValue;
                        }
                        
                        field.disabled = true;
                        field.style.background = 'var(--gray-lighter)';
                        field.style.borderColor = 'var(--gray-light)';
                        field.style.transform = '';
                        field.style.boxShadow = '';
                    }
                });
                
                // Ocultar mensaje de edición
                showEditingMessage(false);
            }
        }
        
        // Función para mostrar mensaje de edición
        function showEditingMessage(show) {
            let messageDiv = document.getElementById('editing-message');
            
            if (show && !messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.id = 'editing-message';
                messageDiv.className = 'alert alert-info';
                messageDiv.style.cssText = `
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    z-index: 10000;
                    min-width: 300px;
                    animation: slideInRight 0.5s ease;
                `;
                messageDiv.innerHTML = `
                    <i class="fas fa-edit"></i>
                    <span><strong>Modo Edición Activado:</strong> Puedes modificar tu información personal. Haz clic en "Guardar Cambios" para confirmar o en la "X" para cancelar.</span>
                `;
                document.body.appendChild(messageDiv);
                
                // Auto-ocultar después de 4 segundos
                setTimeout(() => {
                    if (messageDiv && messageDiv.parentNode) {
                        messageDiv.style.opacity = '0';
                        messageDiv.style.transform = 'translateX(100px)';
                        setTimeout(() => {
                            if (messageDiv.parentNode) {
                                messageDiv.parentNode.removeChild(messageDiv);
                            }
                        }, 500);
                    }
                }, 4000);
                
            } else if (!show && messageDiv) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateX(100px)';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 500);
            }
        }
        
        // Función para exportar datos
        function exportData() {
            const options = [
                'PDF - Informe completo del perfil',
                'Excel - Estadísticas de cursos',
                'CSV - Datos de estudiantes'
            ];
            
            const choice = prompt('¿Qué tipo de exportación deseas?\n\n1. ' + options[0] + '\n2. ' + options[1] + '\n3. ' + options[2] + '\n\nEscribe el número (1, 2 o 3):');
            
            switch(choice) {
                case '1':
                    alert('📄 Exportación a PDF iniciada. Se descargará automáticamente.');
                    break;
                case '2':
                    alert('📊 Exportación a Excel iniciada. Se descargará automáticamente.');
                    break;
                case '3':
                    alert('📋 Exportación a CSV iniciada. Se descargará automáticamente.');
                    break;
                default:
                    alert('Exportación cancelada.');
            }
        }
        
        // Gráfico de distribución de calificaciones mejorado
        const ctx = document.getElementById('gradeChart').getContext('2d');
        const gradeData = {
            labels: [
                <?php foreach ($grade_distribution as $grade): ?>
                    '<?php echo $grade['categoria']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($grade_distribution as $grade): ?>
                        <?php echo $grade['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 2,
                hoverOffset: 10
            }]
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: gradeData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12,
                                family: 'Inter'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += context.parsed + ' (' + percentage + '%)';
                                return label;
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
        
        // Animación de números contadores
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-value');
            counters.forEach(counter => {
                const target = parseFloat(counter.textContent);
                if (!isNaN(target)) {
                    let current = 0;
                    const increment = target / 60;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            counter.textContent = target % 1 === 0 ? target : target.toFixed(1);
                            clearInterval(timer);
                        } else {
                            counter.textContent = current % 1 === 0 ? Math.floor(current) : current.toFixed(1);
                        }
                    }, 20);
                }
            });
        }
        
        // Ejecutar animaciones al cargar
        window.addEventListener('load', () => {
            setTimeout(animateCounters, 500);
            
            // Guardar valores originales para la funcionalidad de edición
            const editableFields = ['email', 'telefono', 'especialidad', 'bio'];
            editableFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field && !field.getAttribute('data-original-value')) {
                    field.setAttribute('data-original-value', field.value);
                }
            });
        });
        
        // Ocultar mensajes de alerta después de 6 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-warning')) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(100px)';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 6000);
        
        // Animación de las barras de progreso
        document.addEventListener('DOMContentLoaded', function() {
            const progressFills = document.querySelectorAll('.progress-fill');
            progressFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 500);
            });
            
            // Animación escalonada de las tarjetas
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Observer para animaciones al hacer scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.course-card, .student-item, .upcoming-item, .activity-item').forEach(el => {
                observer.observe(el);
            });
        });
        
        // Validación en tiempo real de formularios
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--danger)';
                }
            });
            
            input.addEventListener('blur', function() {
                if (this.value && this.checkValidity()) {
                    this.style.borderColor = 'var(--success)';
                } else if (this.value) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = 'var(--gray-light)';
                }
            });
            
            // Feedback visual para campos editables
            input.addEventListener('focus', function() {
                if (!this.disabled) {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.3)';
                }
            });
            
            input.addEventListener('blur', function() {
                if (!this.disabled) {
                    // Solo mantener el transform si está en modo edición
                    const isInEditMode = this.closest('#profileForm').classList.contains('editing');
                    if (!isInEditMode) {
                        this.style.transform = '';
                        this.style.boxShadow = '';
                    }
                }
            });
        });
        
        // Efecto parallax suave para el header
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const header = document.querySelector('.profile-header');
            if (header) {
                header.style.transform = `translateY(${scrolled * 0.05}px)`;
            }
        });
        
        // Loading states para botones
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalContent = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Procesando...</span>';
                    submitBtn.disabled = true;
                    
                    // Si es el formulario de perfil en modo edición, mostrar mensaje especial
                    if (this.id === 'profileForm' && this.classList.contains('editing')) {
                        const editingMessage = document.getElementById('editing-message');
                        if (editingMessage) {
                            editingMessage.innerHTML = `
                                <i class="fas fa-spinner fa-spin"></i>
                                <span><strong>Guardando cambios...</strong> Por favor espera mientras actualizamos tu información.</span>
                            `;
                            editingMessage.style.background = 'rgba(245, 158, 11, 0.1)';
                            editingMessage.style.color = 'var(--warning)';
                        }
                    }
                    
                    // Restaurar después de 3 segundos
                    setTimeout(() => {
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
        });
        
        // Handler especial para el formulario de perfil
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            if (this.classList.contains('editing')) {
                // Actualizar valores originales después de guardar exitosamente
                setTimeout(() => {
                    const editableFields = ['email', 'telefono', 'especialidad', 'bio'];
                    editableFields.forEach(fieldName => {
                        const field = document.getElementById(fieldName);
                        if (field) {
                            field.setAttribute('data-original-value', field.value);
                        }
                    });
                    
                    // Salir del modo edición automáticamente después de guardar
                    setTimeout(() => {
                        toggleEdit('profile');
                    }, 1000);
                }, 2000);
            }
        });
    </script>
</body>
</html>