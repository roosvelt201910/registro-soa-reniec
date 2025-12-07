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
        
        /* Layout principal */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
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
            font-size: 1.8rem;
            color: rgba(255,255,255,0.9);
            transition: var(--transition);
        }
        
        .logo:hover i {
            transform: rotate(-10deg) scale(1.1);
        }
        
        .logo h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
        }
        
        .sidebar-nav {
            padding: 0 1rem;
        }
        
        .nav-section {
            margin-bottom: 1.5rem;
        }
        
        .nav-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.75rem;
            padding: 0 1rem;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            margin-bottom: 0.25rem;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Contenido principal */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 0;
            transition: var(--transition);
        }
        
        /* Header */
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 90;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            border-radius: 50px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .user-profile:hover {
            background: var(--lighter-color);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: var(--transition);
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
            color: white;
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .btn-logout:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        /* Contenedor principal */
        .container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Sección de bienvenida */
        .welcome-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.5);
            overflow: hidden;
            position: relative;
            transition: var(--transition);
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
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        /* Estadísticas */
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
        
        /* Grid principal */
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
        
        /* Actividad reciente */
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
        
        /* Panel de notificaciones */
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
        }
        
        .notification-panel:hover {
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
        }
        
        .notification-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .notification-panel p {
            position: relative;
            z-index: 1;
        }
        
        /* Estado vacío */
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
        
        /* Barra de progreso */
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
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar .logo h1,
            .sidebar .nav-title,
            .sidebar .nav-item span {
                display: none;
            }
            
            .sidebar .nav-item {
                justify-content: center;
                padding: 0.75rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .header {
                padding: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1>Registro Auxiliar Docente</h1>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <?php if ($user_type == 'super_admin'): ?>
                    <div class="nav-section">
                        <div class="nav-title">Administración</div>
                        <a href="admin/manage_users.php" class="nav-item active">
                            <i class="fas fa-users-cog"></i>
                            <span>Gestionar Usuarios</span>
                        </a>
                        <a href="admin/manage_courses.php" class="nav-item">
                            <i class="fas fa-book-open"></i>
                            <span>Gestionar Cursos</span>
                        </a>
                        <a href="admin/manage_programs.php" class="nav-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Programas de Estudio</span>
                        </a>
                        <a href="admin/reports.php" class="nav-item">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reportes Generales</span>
                        </a>
                    </div>
                    
                    <div class="nav-section">
                        <div class="nav-title">Sistema</div>
                        <a href="admin/system_settings.php" class="nav-item">
                            <i class="fas fa-cogs"></i>
                            <span>Configuración</span>
                        </a>
                        <a href="admin/backup.php" class="nav-item">
                            <i class="fas fa-database"></i>
                            <span>Respaldos</span>
                        </a>
                        <a href="admin/profile.php" class="nav-item">
                            <i class="fas fa-user"></i>
                            <span>Perfil</span>
                        </a>
                    </div>
                    
                <?php elseif ($user_type == 'docente'): ?>
                    <div class="nav-section">
                        <div class="nav-title">Docencia</div>
                        <a href="teacher/my_courses.php" class="nav-item active">
                            <i class="fas fa-book-open"></i>
                            <span>Mis Unidades Didácticas</span>
                        </a>
                        <a href="teacher/attendance.php" class="nav-item">
                            <i class="fas fa-user-check"></i>
                            <span>Registro de Asistencia</span>
                        </a>
                        <a href="teacher/evaluations.php" class="nav-item">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Evaluaciones</span>
                        </a>
                        <a href="teacher/reports.php" class="nav-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Reportes y Consolidados</span>
                        </a>
                    </div>
                    
                    <div class="nav-section">
                        <div class="nav-title">Gestión</div>
                        <a href="teacher/manage_students.php" class="nav-item">
                            <i class="fas fa-users"></i>
                            <span>Gestionar Estudiantes</span>
                        </a>
                        <a href="teacher/sessions.php" class="nav-item">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Programar Sesiones</span>
                        </a>
                        <a href="teacher/profile.php" class="nav-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Mi Perfil</span>
                        </a>
                    </div>
                    
                <?php else: // estudiante ?>
                    <div class="nav-section">
                        <div class="nav-title">Académico</div>
                        <a href="student/my_courses.php" class="nav-item active">
                            <i class="fas fa-book"></i>
                            <span>Mis uniadades Didácticas</span>
                        </a>
                        <a href="student/grades.php" class="nav-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Mis Notas</span>
                        </a>
                        <a href="student/attendance.php" class="nav-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>Mi Asistencia</span>
                        </a>
                        <a href="student/reports.php" class="nav-item">
                            <i class="fas fa-file-alt"></i>
                            <span>Mis Reportes</span>
                        </a>
                    </div>
                    
                    <div class="nav-section">
                        <div class="nav-title">Personal</div>
                        <a href="student/schedule.php" class="nav-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Mi Horario</span>
                        </a>
                        <a href="student/profile.php" class="nav-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Mi Perfil</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contenido principal -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <h2>Dashboard</h2>
                </div>
                <div class="header-right">
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
            
            <!-- Contenido -->
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
                                <span class="stat-title">Mis Unidades de Didácticas</span>
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
                            <div class="stat-description">Total en mis Unidades de Didácticas</div>
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
                                <span class="stat-title">Mis Unidades de Didácticas</span>
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?php echo $stats['mis_cursos'] ?? 0; ?></div>
                            <div class="stat-description">Unidades de Didácticas matriculados</div>
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
                                <?php echo $user_type == 'docente' ? 'Mis unidades Didacticas' : ($user_type == 'estudiante' ? 'Mis Cursos' : 'Cursos Recientes'); ?>
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
            const menuItems = document.querySelectorAll('.nav-item');
            menuItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active')) {
                        this.style.transform = 'translateX(0)';
                    }
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
            
            const animatedElements = document.querySelectorAll('.stat-card');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(el);
            });
        });
    </script>
</body>
</html>