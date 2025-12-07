<?php
require_once 'config/database.php';
requireLogin();

$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Obtener datos básicos
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener cursos del usuario
    $myCourses = [];
    if ($user_type == 'docente') {
        $query = "SELECT ud.*, p.nombre as programa_nombre FROM unidades_didacticas ud 
                 JOIN programas_estudio p ON ud.programa_id = p.id 
                 WHERE ud.docente_id = :docente_id AND ud.estado = 'activo'
                 ORDER BY ud.periodo_lectivo DESC, ud.nombre";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':docente_id', $user_id);
        $stmt->execute();
        $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_type == 'estudiante') {
        $query = "SELECT ud.*, p.nombre as programa_nombre, CONCAT(u.nombres, ' ', u.apellidos) as docente_nombre
                 FROM matriculas m
                 JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
                 JOIN programas_estudio p ON ud.programa_id = p.id
                 JOIN usuarios u ON ud.docente_id = u.id
                 WHERE m.estudiante_id = :estudiante_id AND m.estado = 'activo'
                 ORDER BY ud.periodo_lectivo DESC, ud.nombre";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':estudiante_id', $user_id);
        $stmt->execute();
        $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener estadísticas adicionales según el tipo de usuario
    $stats = [];
    if ($user_type == 'super_admin') {
        // Estadísticas para super admin
        $stats['total_usuarios'] = $conn->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'")->fetchColumn();
        $stats['total_cursos'] = $conn->query("SELECT COUNT(*) FROM unidades_didacticas WHERE estado = 'activo'")->fetchColumn();
        $stats['total_docentes'] = $conn->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'docente' AND estado = 'activo'")->fetchColumn();
        $stats['total_estudiantes'] = $conn->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'estudiante' AND estado = 'activo'")->fetchColumn();
        $stats['total_programas'] = $conn->query("SELECT COUNT(*) FROM programas_estudio WHERE estado = 'activo'")->fetchColumn();
        
        // Actividad reciente
        $stmt = $conn->query("SELECT u.nombres, u.apellidos, u.tipo_usuario, u.fecha_creacion 
                             FROM usuarios u WHERE u.estado = 'activo' 
                             ORDER BY u.fecha_creacion DESC LIMIT 5");
        $stats['usuarios_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_type == 'docente') {
        // Estadísticas para docente
        $stats['mis_cursos'] = count($myCourses);
        
        // Total de estudiantes en mis cursos
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT m.estudiante_id) as total FROM matriculas m 
                               JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id 
                               WHERE ud.docente_id = ? AND m.estado = 'activo'");
        $stmt->execute([$user_id]);
        $stats['total_estudiantes'] = $stmt->fetchColumn();
        
        // Sesiones programadas esta semana
        $stmt = $conn->prepare("SELECT COUNT(*) FROM sesiones s 
                               JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                               WHERE ud.docente_id = ? AND s.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                               AND s.estado = 'programada'");
        $stmt->execute([$user_id]);
        $stats['sesiones_semana'] = $stmt->fetchColumn();
        
        // Evaluaciones pendientes
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ie.id) FROM indicadores_evaluacion ie 
                               JOIN sesiones s ON ie.sesion_id = s.id 
                               JOIN unidades_didacticas ud ON s.unidad_didactica_id = ud.id 
                               WHERE ud.docente_id = ? AND ie.id NOT IN (SELECT DISTINCT indicador_evaluacion_id FROM evaluaciones_sesion)");
        $stmt->execute([$user_id]);
        $stats['evaluaciones_pendientes'] = $stmt->fetchColumn();
        
    } elseif ($user_type == 'estudiante') {
        // Estadísticas para estudiante
        $stats['mis_cursos'] = count($myCourses);
        
        // Promedio general
        if (!empty($myCourses)) {
            $course_ids = array_column($myCourses, 'id');
            $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
            $stmt = $conn->prepare("SELECT AVG(es.calificacion) as promedio FROM evaluaciones_sesion es 
                                   JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id 
                                   JOIN sesiones s ON ie.sesion_id = s.id 
                                   WHERE s.unidad_didactica_id IN ($placeholders) AND es.estudiante_id = ?");
            $stmt->execute(array_merge($course_ids, [$user_id]));
            $stats['promedio_general'] = round($stmt->fetchColumn(), 2) ?: 0;
        } else {
            $stats['promedio_general'] = 0;
        }
        
        // Asistencias del mes
        if (!empty($myCourses)) {
            $course_ids = array_column($myCourses, 'id');
            $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
            $stmt = $conn->prepare("SELECT COUNT(*) FROM asistencias a 
                                   JOIN sesiones s ON a.sesion_id = s.id 
                                   WHERE s.unidad_didactica_id IN ($placeholders) AND a.estudiante_id = ? 
                                   AND a.estado = 'presente' AND MONTH(s.fecha) = MONTH(CURDATE())");
            $stmt->execute(array_merge($course_ids, [$user_id]));
            $stats['asistencias_mes'] = $stmt->fetchColumn();
        } else {
            $stats['asistencias_mes'] = 0;
        }
    }
    
} catch(PDOException $e) {
    $myCourses = [];
    $stats = [];
}

// Función para obtener saludo según la hora
function getSaludo() {
    $hora = date('H');
    if ($hora >= 5 && $hora < 12) return 'Buenos días';
    elseif ($hora >= 12 && $hora < 19) return 'Buenas tardes';
    elseif ($hora >= 19 && $hora < 24) return 'Buenas noches';
    else return 'Buenas madrugadas'; // Para horas entre 0 y 5 am
}

// Función para obtener el color del badge según el tipo de usuario
function getUserBadgeColor($type) {
    switch($type) {
        case 'super_admin': return '#e74c3c';
        case 'docente': return '#3498db';
        case 'estudiante': return '#2ecc71';
        default: return '#95a5a6';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Académico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d2d7dff;
            --primary-dark: #0c0587ff;
            --primary-light: #a5b4fc;
            --secondary-color: #054969ff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --darker-color: #111827;
            --light-color: #f9fafb;
            --lighter-color: #f3f4f6;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
            color: var(--dark-color);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Animaciones clave */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Header mejorado */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
            animation: fadeIn 0.5s ease-out;
            background-size: 200% 200%;
            animation: gradientBG 8s ease infinite;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
        }
        
        .logo:hover {
            transform: translateX(-3px);
        }
        
        .logo i {
            font-size: 2rem;
            color: rgba(255,255,255,0.9);
            transition: var(--transition);
        }
        
        .logo:hover i {
            transform: rotate(-10deg) scale(1.1);
        }
        
        .logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(to right, white, #e0e7ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .user-profile:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: var(--transition);
        }
        
        .user-profile:hover .user-avatar {
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }
        
        .user-details h4 {
            font-size: 0.9rem;
            margin-bottom: 2px;
            font-weight: 500;
        }
        
        .user-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Sección de bienvenida mejorada */
        .welcome-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            background: linear-gradient(135deg, white 0%, #f8fafc 100%);
            border: 1px solid rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
            overflow: hidden;
            position: relative;
            transition: var(--transition);
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
        }
        
        .welcome-section:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .welcome-text h2 {
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
        }
        
        .welcome-text p {
            color: #64748b;
            font-size: 1.1rem;
            max-width: 600px;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: var(--transition);
            font-weight: 500;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-slow);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .quick-action-btn:hover::before {
            left: 100%;
        }
        
        /* Menú de navegación mejorado */
        .navigation-menu {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
            transition: var(--transition);
        }
        
        .navigation-menu:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .menu-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .menu-header h3 {
            color: var(--dark-color);
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .menu-header i {
            color: var(--primary-color);
            transition: var(--transition);
        }
        
        .menu-header:hover i {
            transform: rotate(15deg);
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transform-origin: left;
            transition: var(--transition);
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .menu-item:hover::before {
            transform: scaleX(1);
        }
        
        .menu-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .menu-item:hover .menu-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: var(--shadow-md);
        }
        
        .menu-content {
            flex: 1;
            position: relative;
        }
        
        .menu-content h4 {
            color: var(--dark-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: var(--transition);
        }
        
        .menu-item:hover .menu-content h4 {
            color: var(--primary-dark);
        }
        
        .menu-content p {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 50px;
            min-width: 20px;
            text-align: center;
            animation: pulse 1.5s infinite;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }
        
        /* Estadísticas mejoradas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:hover::before {
            height: 6px;
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
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .stat-description {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        /* Grid principal mejorado */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            height: fit-content;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .card:hover {
            transform: translateY(-3px);
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
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            transition: var(--transition);
        }
        
        .card:hover .card-title i {
            transform: rotate(10deg) scale(1.1);
            color: var(--primary-color);
        }
        
        .course-list {
            list-style: none;
        }
        
        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .course-item:last-child {
            border-bottom: none;
        }
        
        .course-item:hover {
            background: var(--lighter-color);
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .course-info h4 {
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .course-item:hover .course-info h4 {
            color: var(--primary-dark);
        }
        
        .course-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .course-meta i {
            margin-right: 4px;
            color: var(--primary-color);
        }
        
        .course-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        /* Actividad reciente mejorada */
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .activity-item:hover {
            background: var(--lighter-color);
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
            transition: var(--transition);
        }
        
        .activity-item:hover .activity-avatar {
            transform: scale(1.1);
            box-shadow: var(--shadow-sm);
        }
        
        .activity-content h5 {
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            transition: var(--transition);
        }
        
        .activity-item:hover .activity-content h5 {
            color: var(--primary-dark);
        }
        
        .activity-content p {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        /* Panel de notificaciones mejorado */
        .notification-panel {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            animation: pulse 3s infinite;
            background-size: 200% 200%;
            animation: gradientBG 8s ease infinite;
        }
        
        .notification-panel:hover {
            animation: none;
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .notification-header i {
            font-size: 1.5rem;
            animation: float 3s ease-in-out infinite;
        }
        
        .notification-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .notification-panel p {
            position: relative;
            z-index: 1;
        }
        
        /* Estado vacío mejorado */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
            transition: var(--transition);
        }
        
        .empty-state:hover {
            transform: translateY(-3px);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            transition: var(--transition);
        }
        
        .empty-state:hover i {
            opacity: 0.8;
            transform: scale(1.1);
        }
        
        .empty-state h4 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        /* Barra de progreso mejorada */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: progressShine 2s infinite;
        }
        
        @keyframes progressShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Responsive mejorado */
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            
            .menu-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            
            .welcome-content {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .quick-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }
            
            .user-profile {
                justify-content: center;
                width: 100%;
            }
            
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-item {
                flex-direction: column;
                text-align: center;
                padding: 2rem 1rem;
            }
            
            .menu-icon {
                margin-bottom: 1rem;
            }
            
            .course-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .course-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-action-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <h1>Registro Auxiliar Docente</h1>
            </div>
            <div class="user-info">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user_name); ?></h4>
                        <span class="user-badge" style="background-color: <?php echo getUserBadgeColor($user_type); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $user_type)); ?>
                        </span>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Sección de bienvenida -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2><?php echo getSaludo(); ?>, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h2>
                    <p>Bienvenido de nuevo a tu panel de control académico</p>
                </div>
                <div class="quick-actions">
                    <?php if ($user_type == 'docente'): ?>
                        <a href="teacher/evaluations.php" class="quick-action-btn">
                            <i class="fas fa-clipboard-list"></i>
                            Evaluaciones
                        </a>
                        <a href="teacher/attendance.php" class="quick-action-btn">
                            <i class="fas fa-user-check"></i>
                            Asistencia
                        </a>
                    <?php elseif ($user_type == 'estudiante'): ?>
                        <a href="student/grades.php" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            Mis Notas
                        </a>
                        <a href="student/attendance.php" class="quick-action-btn">
                            <i class="fas fa-calendar-check"></i>
                            Mi Asistencia
                        </a>
                    <?php else: ?>
                        <a href="admin/manage_users.php" class="quick-action-btn">
                            <i class="fas fa-users-cog"></i>
                            Gestionar Usuarios
                        </a>
                        <a href="admin/reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            Reportes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Menú de navegación principal -->
        <div class="navigation-menu">
            <div class="menu-header">
                <h3>
                    <i class="fas fa-compass"></i>
                    Navegación Principal
                </h3>
            </div>
            
            <?php if ($user_type == 'super_admin'): ?>
                <!-- MENÚ SUPER ADMIN -->
                <div class="menu-grid">
                    <a href="admin/manage_users.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Gestionar Usuarios</h4>
                            <p>Administrar docentes y estudiantes</p>
                        </div>
                    </a>
                    
                    <a href="admin/manage_courses.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Gestionar Cursos</h4>
                            <p>Administrar unidades didácticas</p>
                        </div>
                    </a>
                    
                    <a href="admin/manage_programs.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Programas de Estudio</h4>
                            <p>Gestionar carreras y programas</p>
                        </div>
                    </a>
                    
                    <a href="admin/reports.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Reportes Generales</h4>
                            <p>Estadísticas y análisis</p>
                        </div>
                    </a>
                    
                    <a href="admin/system_settings.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #805ad5, #6b46c1);">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Configuración</h4>
                            <p>Ajustes del sistema</p>
                        </div>
                    </a>
                    
                    <a href="admin/backup.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Respaldos</h4>
                            <p>Gestión de copias de seguridad</p>
                        </div>
                    </a>

                      <a href="admin/profile.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Perfil</h4>
                            <p>Administrar Perfil de Usuario</p>
                        </div>
                    </a>



                </div>
                
            <?php elseif ($user_type == 'docente'): ?>
                <!-- MENÚ DOCENTE -->
                <div class="menu-grid">
                    <a href="teacher/my_courses.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mis unidades Didacticas</h4>
                            <p>Ver y gestionar mis unidades didácticas</p>
                        </div>
                    </a>
                    
                    <a href="teacher/attendance.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Registro de Asistencia</h4>
                            <p>Controlar asistencia de estudiantes</p>
                        </div>
                    </a>
                    
                    <a href="teacher/evaluations.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Evaluaciones</h4>
                            <p>Gestionar indicadores y calificaciones</p>
                            <?php if (isset($stats['evaluaciones_pendientes']) && $stats['evaluaciones_pendientes'] > 0): ?>
                                <span class="notification-badge"><?php echo $stats['evaluaciones_pendientes']; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    
                    <a href="teacher/reports.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Reportes y Consolidados</h4>
                            <p>Informes de rendimiento y asistencia</p>
                        </div>
                    </a>
                    
                    <a href="teacher/manage_students.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #805ad5, #6b46c1);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Gestionar Estudiantes</h4>
                            <p>Matricular y administrar estudiantes</p>
                        </div>
                    </a>
                    
                    <a href="teacher/sessions.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Programar Sesiones</h4>
                            <p>Crear y gestionar sesiones de clase</p>
                        </div>
                    </a>
                </div>
                 <a href="teacher/profile.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mi Perfil</h4>
                            <p>Actualizar información personal</p>
                        </div>
                    </a>


            <?php else: // estudiante ?>
                <!-- MENÚ ESTUDIANTE -->
                <div class="menu-grid">
                    <a href="student/my_courses.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mis Cursos</h4>
                            <p>Ver mis cursos matriculados</p>
                        </div>
                    </a>
                    
                    <a href="student/grades.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mis Notas</h4>
                            <p>Consultar calificaciones y promedio</p>
                        </div>
                    </a>
                    
                    <a href="student/attendance.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mi Asistencia</h4>
                            <p>Revisar historial de asistencias</p>
                        </div>
                    </a>
                    
                    <a href="student/reports.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mis Reportes</h4>
                            <p>Informes de rendimiento académico</p>
                        </div>
                    </a>
                    
                    <a href="student/schedule.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #805ad5, #6b46c1);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mi Horario</h4>
                            <p>Ver horario de clases</p>
                        </div>
                    </a>
                    
                    <a href="student/profile.php" class="menu-item">
                        <div class="menu-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="menu-content">
                            <h4>Mi Perfil</h4>
                            <p>Actualizar información personal</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <?php if ($user_type == 'super_admin'): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Usuarios</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_usuarios'] ?? 0; ?></div>
                    <div class="stat-description">Usuarios activos en el sistema</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Cursos Activos</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_cursos'] ?? 0; ?></div>
                    <div class="stat-description">Unidades didácticas disponibles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Docentes</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_docentes'] ?? 0; ?></div>
                    <div class="stat-description">Profesores registrados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Estudiantes</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_estudiantes'] ?? 0; ?></div>
                    <div class="stat-description">Estudiantes matriculados</div>
                </div>
                
            <?php elseif ($user_type == 'docente'): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Mis Cursos</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['mis_cursos'] ?? 0; ?></div>
                    <div class="stat-description">Cursos asignados este período</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Estudiantes</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_estudiantes'] ?? 0; ?></div>
                    <div class="stat-description">Total en mis cursos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Sesiones Esta Semana</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['sesiones_semana'] ?? 0; ?></div>
                    <div class="stat-description">Clases programadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Evaluaciones Pendientes</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['evaluaciones_pendientes'] ?? 0; ?></div>
                    <div class="stat-description">Por calificar</div>
                </div>
                
            <?php else: // estudiante ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Mis Cursos</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['mis_cursos'] ?? 0; ?></div>
                    <div class="stat-description">Cursos matriculados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Promedio General</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['promedio_general'] ?? '0.0'; ?></div>
                    <div class="stat-description">Calificación promedio</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($stats['promedio_general'] ?? 0) * 5, 100); ?>%"></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Asistencias Este Mes</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $stats['asistencias_mes'] ?? 0; ?></div>
                    <div class="stat-description">Clases asistidas</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grid principal -->
        <div class="main-grid">
            <!-- Lista de cursos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-book-open"></i>
                        <?php echo $user_type == 'docente' ? 'Mis Cursos' : ($user_type == 'estudiante' ? 'Mis Cursos' : 'Cursos Recientes'); ?>
                    </h3>
                </div>
                
                <?php if (empty($myCourses) && $user_type != 'super_admin'): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h4>No hay cursos disponibles</h4>
                        <p>No tienes cursos asignados en este momento.</p>
                    </div>
                <?php else: ?>
                    <ul class="course-list">
                        <?php 
                        $displayCourses = $user_type == 'super_admin' ? [] : $myCourses;
                        if ($user_type == 'super_admin') {
                            try {
                                $query = "SELECT ud.*, p.nombre as programa_nombre, CONCAT(u.nombres, ' ', u.apellidos) as docente_nombre 
                                         FROM unidades_didacticas ud 
                                         JOIN programas_estudio p ON ud.programa_id = p.id
                                         JOIN usuarios u ON ud.docente_id = u.id 
                                         WHERE ud.estado = 'activo' 
                                         ORDER BY ud.id DESC LIMIT 6";
                                $stmt = $conn->prepare($query);
                                $stmt->execute();
                                $displayCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch(Exception $e) {
                                $displayCourses = [];
                            }
                        }
                        
                        foreach ($displayCourses as $course): ?>
                            <li class="course-item">
                                <div class="course-info">
                                    <h4><?php echo htmlspecialchars($course['nombre']); ?></h4>
                                    <div class="course-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($course['periodo_lectivo']); ?></span>
                                        <?php if (isset($course['programa_nombre'])): ?>
                                            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['programa_nombre']); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($course['docente_nombre'])): ?>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($course['docente_nombre']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="course-actions">
                                    <?php if ($user_type == 'docente'): ?>
                                        <a href="teacher/evaluations.php?course_id=<?php echo $course['id']; ?>" class="btn-small btn-primary">
                                            <i class="fas fa-clipboard-list"></i> Evaluar
                                        </a>
                                        <a href="teacher/attendance.php?course_id=<?php echo $course['id']; ?>" class="btn-small btn-outline">
                                            <i class="fas fa-user-check"></i> Asistencia
                                        </a>
                                    <?php elseif ($user_type == 'estudiante'): ?>
                                        <a href="student/grades.php?course_id=<?php echo $course['id']; ?>" class="btn-small btn-primary">
                                            <i class="fas fa-chart-line"></i> Ver Notas
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Panel lateral -->
            <div>
                <!-- Notificaciones -->
                <?php if ($user_type == 'docente' && isset($stats['evaluaciones_pendientes']) && $stats['evaluaciones_pendientes'] > 0): ?>
                    <div class="notification-panel">
                        <div class="notification-header">
                            <i class="fas fa-bell"></i>
                            <h3>Recordatorio</h3>
                        </div>
                        <p>Tienes <strong><?php echo $stats['evaluaciones_pendientes']; ?></strong> evaluaciones pendientes por calificar.</p>
                        <a href="teacher/evaluations.php" class="quick-action-btn" style="background: rgba(255,255,255,0.2); margin-top: 1rem;">
                            <i class="fas fa-arrow-right"></i>
                            Ver evaluaciones
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Actividad reciente -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i>
                            Actividad Reciente
                        </h3>
                    </div>
                    
                    <?php if ($user_type == 'super_admin' && !empty($stats['usuarios_recientes'])): ?>
                        <div>
                            <?php foreach ($stats['usuarios_recientes'] as $usuario): ?>
                                <div class="activity-item">
                                    <div class="activity-avatar">
                                        <?php echo strtoupper(substr($usuario['nombres'], 0, 1)); ?>
                                    </div>
                                    <div class="activity-content">
                                        <h5><?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?></h5>
                                        <p>Se registró como <?php echo ucfirst($usuario['tipo_usuario']); ?></p>
                                        <small><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4>Sin actividad reciente</h4>
                            <p>No hay actividad para mostrar.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                
                
                
                
            </div>
        </div>
    </div>

    <script>
        // Animaciones y efectos
        document.addEventListener('DOMContentLoaded', function() {
            // Animación de los números en las estadísticas
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent) || parseFloat(stat.textContent) || 0;
                let currentValue = 0;
                const increment = finalValue / 50;
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        stat.textContent = finalValue % 1 === 0 ? finalValue : finalValue.toFixed(1);
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(currentValue);
                    }
                }, 20);
            });
            
            // Efecto de hover en las tarjetas
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Efectos en los elementos del menú
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.menu-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1.1) rotate(5deg)';
                    }
                });
                
                item.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.menu-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1) rotate(0deg)';
                    }
                });
                
                // Agregar ripple effect al hacer clic
                item.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Agregar tooltips a los botones
            const buttons = document.querySelectorAll('.quick-action-btn, .btn-small');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Animación de entrada para las estadísticas
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            const animatedElements = document.querySelectorAll('.stat-card, .menu-item');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(el);
            });
            
            // Efecto de typing en el saludo
            const welcomeText = document.querySelector('.welcome-text h2');
            if (welcomeText) {
                const text = welcomeText.textContent;
                welcomeText.textContent = '';
                let i = 0;
                
                const typeWriter = setInterval(() => {
                    welcomeText.textContent += text.charAt(i);
                    i++;
                    if (i >= text.length) {
                        clearInterval(typeWriter);
                    }
                }, 100);
            }
        });
        
        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: white;
                border-left: 4px solid var(--${type}-color);
                border-radius: 8px;
                box-shadow: var(--shadow-lg);
                z-index: 1000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-info-circle" style="color: var(--${type}-color); margin-right: 8px;"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="
                    background: none;
                    border: none;
                    margin-left: 12px;
                    cursor: pointer;
                    color: #64748b;
                ">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }
        
        // Detectar modo oscuro del sistema
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-mode');
        }
    </script>
    
    <style>
        /* Efecto ripple */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Smooth transitions para iconos */
        .menu-icon {
            transition: transform 0.3s ease;
        }
        
        /* Mejoras de accesibilidad */
        .menu-item:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Modo oscuro (opcional) */
        .dark-mode {
            --light-color: #1a202c;
            --dark-color: #f7fafc;
        }
    </style>





<!-- Agrega esto justo antes del cierre del body (antes de </body>) -->
<script src="https://cdn.jsdelivr.net/npm/shepherd.js@8.3.1/dist/js/shepherd.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@8.3.1/dist/css/shepherd.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si es la primera vez que el usuario ve el dashboard
    const tourShown = localStorage.getItem('dashboardTourShown');
    
    if (!tourShown) {
        // Inicializar el tour
        const tour = new Shepherd.Tour({
            defaultStepOptions: {
                classes: 'shadow-md bg-white dark:bg-gray-800',
                scrollTo: { behavior: 'smooth', block: 'center' }
            },
            useModalOverlay: true
        });

        // Paso 1: Bienvenida
        tour.addStep({
            id: 'welcome',
            text: `<h3 class="text-lg font-bold mb-2">¡Bienvenido al Sistema Académico!</h3>
                  <p>Este tour te guiará por las principales funciones del dashboard. ¿Listo para comenzar?, si no entiendes ni michi escribeme al 950980740</p>`,
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Comenzar',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.welcome-section',
                on: 'bottom'
            }
        });

        // Paso 2: Navegación principal
        tour.addStep({
            id: 'navigation',
            text: `<h3 class="text-lg font-bold mb-2">Menú Principal</h3>
                  <p>Desde aquí puedes acceder a todas las funciones del sistema según tu rol (${'<?php echo $user_type; ?>'}).</p>
                  <p>Las opciones están organizadas por categorías para facilitar su uso.</p>`,
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.navigation-menu',
                on: 'bottom'
            }
        });

        // Paso 3: Estadísticas
        tour.addStep({
            id: 'stats',
            text: `<h3 class="text-lg font-bold mb-2">Tus Estadísticas</h3>
                  <p>Aquí encontrarás información clave sobre tu actividad en el sistema.</p>
                  <p>Los datos se actualizan automáticamente y son específicos para tu rol.</p>`,
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.stats-grid',
                on: 'bottom'
            }
        });

        // Paso 4: Cursos
        tour.addStep({
            id: 'courses',
            text: `<h3 class="text-lg font-bold mb-2">Tus Cursos</h3>
                  <p>Esta sección muestra ${'<?php echo $user_type == "docente" ? "los cursos que impartes" : ($user_type == "estudiante" ? "tus cursos matriculados" : "los cursos recientes del sistema"); ?>'}.</p>
                  <p>Desde aquí puedes acceder rápidamente a las funciones principales de cada curso.</p>`,
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.main-grid .card',
                on: 'bottom'
            }
        });

        // Paso 5: Actividad reciente
        tour.addStep({
            id: 'activity',
            text: `<h3 class="text-lg font-bold mb-2">Actividad Reciente</h3>
                  <p>Este panel muestra las últimas actividades relevantes en el sistema.</p>
                  <p>${'<?php echo $user_type == "super_admin" ? "Puedes ver los usuarios recientemente registrados." : "Aquí verás notificaciones importantes sobre tus cursos."; ?>'}</p>`,
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.main-grid > div:last-child .card',
                on: 'bottom'
            }
        });

        // Paso final
        tour.addStep({
            id: 'complete',
            text: `<h3 class="text-lg font-bold mb-2">¡Tour completado!</h3>
                  <p>Ahora estás listo para usar el sistema académico.</p>
                  <p>Puedes volver a ver este tour en cualquier momento haciendo clic en el botón de ayuda.</p>`,
            buttons: [
                {
                    text: 'Cerrar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ],
            attachTo: {
                element: '.header',
                on: 'bottom'
            }
        });

        // Iniciar el tour
        tour.start();
        
        // Marcar como visto
        localStorage.setItem('dashboardTourShown', 'true');
    }

    // Agregar botón para reiniciar el tour
    const helpButton = document.createElement('a');
    helpButton.innerHTML = '<i class="fas fa-question-circle"></i> Ayuda';
    helpButton.className = 'btn-logout';
    helpButton.style.marginLeft = '10px';
    helpButton.style.cursor = 'pointer';
    helpButton.onclick = function() {
        localStorage.removeItem('dashboardTourShown');
        location.reload();
    };
    
    document.querySelector('.user-info').appendChild(helpButton);
});




</script>
<script>
        // Animaciones mejoradas
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto de escritura en el saludo
            const welcomeText = document.querySelector('.welcome-text h2');
            if (welcomeText) {
                const text = welcomeText.textContent;
                welcomeText.textContent = '';
                let i = 0;
                
                const typeWriter = setInterval(() => {
                    welcomeText.textContent += text.charAt(i);
                    i++;
                    if (i >= text.length) {
                        clearInterval(typeWriter);
                        welcomeText.classList.add('animated-text');
                    }
                }, 100);
            }
            
            // Animación de números en estadísticas
            const animateValue = (element, start, end, duration) => {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const value = Math.floor(progress * (end - start) + start);
                    element.textContent = value;
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            };
            
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseFloat(stat.textContent) || 0;
                stat.textContent = '0';
                setTimeout(() => {
                    animateValue(stat, 0, finalValue, 1000);
                }, 300);
            });
            
            // Efecto hover 3D en tarjetas
            const cards = document.querySelectorAll('.stat-card, .card, .menu-item');
            cards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const angleY = (x - centerX) / 20;
                    const angleX = (centerY - y) / 20;
                    
                    card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-5px)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
                });
            });
            
            // Efecto de aparición escalonada
            const animatedElements = document.querySelectorAll('.stat-card, .menu-item, .card');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                });
                
                observer.observe(el);
            });
            
            // Efecto de onda al hacer clic
            document.addEventListener('click', function(e) {
                if (e.target.closest('.menu-item, .quick-action-btn, .btn-small')) {
                    const element = e.target.closest('.menu-item, .quick-action-btn, .btn-small');
                    const ripple = document.createElement('span');
                    const rect = element.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    element.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                }
            });
            
            // Tooltips personalizados
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            tooltipElements.forEach(el => {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = el.getAttribute('data-tooltip');
                document.body.appendChild(tooltip);
                
                el.addEventListener('mouseenter', (e) => {
                    const rect = el.getBoundingClientRect();
                    tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
                    tooltip.style.top = `${rect.top - tooltip.offsetHeight - 10}px`;
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateY(0)';
                });
                
                el.addEventListener('mouseleave', () => {
                    tooltip.style.opacity = '0';
                    tooltip.style.transform = 'translateY(10px)';
                });
            });
        });
        
        // Notificaciones mejoradas
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `custom-notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                </div>
                <div class="notification-content">
                    <p>${message}</p>
                </div>
                <button class="notification-close">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Animación de entrada
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Cierre al hacer clic
            notification.querySelector('.notification-close').addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
            
            // Cierre automático
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }
    </script>
    
    <style>
        /* Efecto ripple mejorado */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
            mix-blend-mode: overlay;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Texto animado */
        .animated-text {
            position: relative;
            display: inline-block;
        }
        
        .animated-text::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transform-origin: left;
            animation: underline 1.5s ease-in-out infinite;
        }
        
        @keyframes underline {
            0%, 100% { transform: scaleX(0); }
            50% { transform: scaleX(1); }
        }
        
        /* Tooltips personalizados */
        .custom-tooltip {
            position: fixed;
            background: var(--dark-color);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            pointer-events: none;
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px);
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }
        
        .custom-tooltip::before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px 5px 0;
            border-style: solid;
            border-color: var(--dark-color) transparent transparent;
        }
        
        /* Notificaciones personalizadas */
        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-left: 4px solid var(--primary-color);
            border-radius: 8px;
            box-shadow: var(--shadow-xl);
            display: flex;
            align-items: center;
            padding: 15px;
            max-width: 350px;
            transform: translateX(400px);
            transition: var(--transition);
            z-index: 1000;
        }
        
        .custom-notification.show {
            transform: translateX(0);
        }
        
        .custom-notification.notification-success {
            border-left-color: var(--success-color);
        }
        
        .custom-notification.notification-error {
            border-left-color: var(--danger-color);
        }
        
        .notification-icon {
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .notification-success .notification-icon {
            color: var(--success-color);
        }
        
        .notification-error .notification-icon {
            color: var(--danger-color);
        }
        
        .notification-content {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            margin-left: 10px;
            transition: var(--transition);
        }
        
        .notification-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }
        
        /* Modo oscuro */
        @media (prefers-color-scheme: dark) {
            :root {
                --dark-color: #f9fafb;
                --darker-color: #e5e7eb;
                --light-color: #1f2937;
                --lighter-color: #111827;
                --border-color: #374151;
            }
            
            body {
                background: linear-gradient(135deg, #111827 0%, #1e3a8a 100%);
            }
            
            .welcome-section, .navigation-menu, 
            .stat-card, .card, .menu-item {
                background: rgba(31, 41, 55, 0.8);
                border-color: rgba(255, 255, 255, 0.1);
                color: #f9fafb;
            }
            
            .menu-content h4, .card-title, 
            .activity-content h5, .course-info h4 {
                color: #f9fafb;
            }
            
            .menu-content p, .stat-description, 
            .activity-content p, .course-meta {
                color: #9ca3af;
            }
            
            .stat-number {
                color: white !important;
            }
            
            .empty-state {
                color: #9ca3af;
            }
            
            .progress-bar {
                background: rgba(17, 24, 39, 0.5);
            }
        }
    </style>



</body>
</html>