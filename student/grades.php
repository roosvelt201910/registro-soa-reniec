<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/Course.php';
require_once '../classes/Attendance.php';

// Permitir acceso tanto a estudiantes logueados como consulta por DNI
$auth = new Auth();
$course = new Course();
$evaluation = new Evaluation();
$attendance = new Attendance();

$student_data = null;
$student_courses = [];
$student_grades = [];
$attendance_summary = [];
$search_dni = '';

// Si es estudiante logueado, usar su ID
if ($auth->isLoggedIn() && $_SESSION['user_type'] == 'estudiante') {
    $user = new User();
    $student_data = $user->getStudentByDni($_SESSION['user_dni']);
    $student_courses = $course->getStudentCourses($_SESSION['user_id']);
} elseif (isset($_POST['dni']) || isset($_GET['dni'])) {
    // Consulta por DNI sin login
    $search_dni = $_POST['dni'] ?? $_GET['dni'];
    $search_dni = sanitizeInput($search_dni);
    
    if (strlen($search_dni) == 8 && is_numeric($search_dni)) {
        $user = new User();
        $student_data = $user->getStudentByDni($search_dni);
        
        if ($student_data) {
            $student_courses = $course->getStudentCourses($student_data['id']);
        }
    }
}

// Obtener notas y asistencias si hay datos del estudiante
if ($student_data && !empty($student_courses)) {
    foreach ($student_courses as $curso) {
        $student_grades[$curso['id']] = $evaluation->getStudentGrades($curso['id'], $student_data['id']);
        $attendance_summary[$curso['id']] = $attendance->getAttendanceSummary($curso['id']);
        
        // Filtrar solo el estudiante actual en el resumen de asistencia
        $attendance_summary[$curso['id']] = array_filter($attendance_summary[$curso['id']], function($item) use ($student_data) {
            return $item['estudiante_id'] == $student_data['id'];
        });
        $attendance_summary[$curso['id']] = reset($attendance_summary[$curso['id']]) ?: null;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Notas - IESPH Alto Huallaga</title>
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
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            z-index: -1;
        }
        
        .animated-bg {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.4;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            background-size: 200% 200%;
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -100% 0; }
            100% { background-position: 100% 0; }
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideDown 0.6s ease-out;
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
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }
        
        .header .institute-name {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .header .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-lg);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            animation: fadeInUp 0.8s ease-out;
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
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .search-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.6s ease-out;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .search-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .search-card h2 {
            color: var(--dark);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .search-card h2 i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-group {
            flex: 1;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light);
        }
        
        .form-group i {
            position: absolute;
            left: 1rem;
            top: 2.75rem;
            color: var(--gray);
            transition: color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        
        .form-group input:focus + i {
            color: var(--primary);
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            align-self: flex-end;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .student-info {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            animation: slideInRight 0.6s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .student-info::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.05) 0%, transparent 70%);
        }
        
        .student-info h2 {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }
        
        .student-info h2 i {
            color: var(--primary);
        }
        
        .student-details {
            background: linear-gradient(135deg, var(--light), white);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            position: relative;
            z-index: 1;
        }
        
        .student-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .detail-item i {
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .detail-item strong {
            color: var(--gray);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-item span {
            color: var(--dark);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .course-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--primary));
            background-size: 200% 100%;
            animation: gradientMove 3s ease infinite;
        }
        
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .course-header {
            border-bottom: 2px solid var(--light);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .course-header h3 {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .course-header h3 i {
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .course-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 12px;
        }
        
        .course-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-info-item i {
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .course-info-item strong {
            color: var(--gray);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .course-info-item span {
            color: var(--dark);
            font-weight: 500;
        }
        
        .section-title {
            color: var(--dark);
            font-size: 1.25rem;
            font-weight: 700;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .grades-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 2rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .grades-table thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .grades-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .grades-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--light);
        }
        
        .grades-table tbody tr:hover {
            background: var(--light);
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
        }
        
        .grades-table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .indicator-badge {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .session-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .session-number {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .session-title {
            color: var(--gray);
            font-size: 0.75rem;
        }
        
        .grade-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.125rem;
            display: inline-block;
            min-width: 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .grade-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        .grade-excellent {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }
        
        .grade-good {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: white;
        }
        
        .grade-process {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }
        
        .grade-failed {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }
        
        .condition-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .condition-excellent {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .condition-good {
            background: rgba(59, 130, 246, 0.1);
            color: #2563EB;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .condition-process {
            background: rgba(245, 158, 11, 0.1);
            color: #D97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .condition-failed {
            background: rgba(239, 68, 68, 0.1);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, white, var(--light));
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(79, 70, 229, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }
        
        .no-data i {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
        }
        
        .no-data h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .loader {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading .loader {
            display: block;
        }
        
        .loading .btn-text {
            display: none;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.75rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .course-info {
                grid-template-columns: 1fr;
            }
            
            .grades-table {
                font-size: 0.875rem;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
        }
        
        @media print {
            body {
                background: white;
            }
            
            .btn-back,
            .search-card {
                display: none;
            }
            
            .course-card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    
    <div class="header">
        <div class="logo">
            <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 1.75rem;"></i>
        </div>
        <h1>Sistema de Consulta de Notas</h1>
        <p class="institute-name">Instituto de Educación Superior Tecnológico Público "Alto Huallaga" - Tocache</p>
    </div>
    
    <div class="container">
        <?php if ($auth->isLoggedIn()): ?>
            <a href="../dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        <?php endif; ?>
        
        <!-- Formulario de búsqueda -->
        <?php if (!$auth->isLoggedIn() || $_SESSION['user_type'] != 'estudiante'): ?>
            <div class="search-card">
                <h2><i class="fas fa-search"></i> Consultar Notas por DNI</h2>
                <form method="POST" class="search-form" id="searchForm">
                    <div class="form-group">
                        <label for="dni">Número de DNI</label>
                        <input type="text" 
                               id="dni" 
                               name="dni" 
                               maxlength="8" 
                               pattern="[0-9]{8}" 
                               value="<?php echo htmlspecialchars($search_dni); ?>"
                               placeholder="12345678"
                               required>
                        <i class="fas fa-id-card"></i>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-text">
                            <i class="fas fa-search"></i>
                            CONSULTAR
                        </span>
                        <div class="loader"></div>
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($search_dni && !$student_data): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>No se encontraron resultados</strong><br>
                    No existe ningún estudiante registrado con el DNI <?php echo htmlspecialchars($search_dni); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($student_data): ?>
            <!-- Información del estudiante -->
            <div class="student-info">
                <h2><i class="fas fa-user-graduate"></i> Información del Estudiante</h2>
                <div class="student-details">
                    <div class="student-details-grid">
                        <div class="detail-item">
                            <i class="fas fa-user"></i>
                            <div>
                                <strong>Nombre Completo</strong><br>
                                <span><?php echo htmlspecialchars($student_data['apellidos'] . ', ' . $student_data['nombres']); ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-id-card"></i>
                            <div>
                                <strong>DNI</strong><br>
                                <span><?php echo htmlspecialchars($student_data['dni']); ?></span>
                            </div>
                        </div>
                        <?php if ($student_data['email']): ?>
                        <div class="detail-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>Correo Electrónico</strong><br>
                                <span><?php echo htmlspecialchars($student_data['email']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($student_courses)): ?>
                <div class="no-data">
                    <i class="fas fa-book-open"></i>
                    <h3>Sin cursos matriculados</h3>
                    <p>El estudiante no está matriculado en ninguna unidad didáctica actualmente.</p>
                </div>
            <?php else: ?>
                <!-- Cursos y notas -->
                <?php foreach ($student_courses as $curso): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><i class="fas fa-book"></i> <?php echo htmlspecialchars($curso['nombre']); ?></h3>
                            <div class="course-info">
                                <div class="course-info-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <div>
                                        <strong>Programa de Estudio</strong><br>
                                        <span><?php echo htmlspecialchars($curso['programa_nombre']); ?></span>
                                    </div>
                                </div>
                                <div class="course-info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <strong>Periodo</strong><br>
                                        <span><?php echo htmlspecialchars($curso['periodo_lectivo'] . ' - ' . $curso['periodo_academico']); ?></span>
                                    </div>
                                </div>
                                <div class="course-info-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Docente</strong><br>
                                        <span><?php echo htmlspecialchars($curso['docente_nombre']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($student_grades[$curso['id']])): ?>
                            <div class="no-data">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No hay evaluaciones registradas para esta unidad didáctica.</p>
                            </div>
                        <?php else: ?>
                            <!-- Tabla de notas -->
                            <h4 class="section-title">
                                <i class="fas fa-chart-line"></i>
                                Evaluaciones por Indicador de Logro por sesion de clases
                            </h4>
                            <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-bullseye"></i> Indicador de Logro de la Capacidad</th>
                                        <th><i class="fas fa-chalkboard"></i> Sesión</th>
                                        <th><i class="fas fa-tasks"></i> Evaluación</th>
                                        <th><i class="fas fa-star"></i> Calificación</th>
                                        <th><i class="fas fa-award"></i> Condición</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_indicator = '';
                                    $indicator_grades = [];
                                    
                                    foreach ($student_grades[$curso['id']] as $grade):
                                        $calificacion = $grade['calificacion'] ?? 0;
                                        $condicion = calculateCondition($calificacion);
                                        
                                        // Agrupar por indicador para calcular promedios
                                        if (!isset($indicator_grades[$grade['numero_indicador']])) {
                                            $indicator_grades[$grade['numero_indicador']] = [];
                                        }
                                        if ($calificacion > 0) {
                                            $indicator_grades[$grade['numero_indicador']][] = $calificacion;
                                        }
                                        
                                        $grade_class = '';
                                        $condition_class = '';
                                        if ($calificacion >= 18) {
                                            $grade_class = 'grade-excellent';
                                            $condition_class = 'condition-excellent';
                                        } elseif ($calificacion >= 13) {
                                            $grade_class = 'grade-good';
                                            $condition_class = 'condition-good';
                                        } elseif ($calificacion >= 10) {
                                            $grade_class = 'grade-process';
                                            $condition_class = 'condition-process';
                                        } else {
                                            $grade_class = 'grade-failed';
                                            $condition_class = 'condition-failed';
                                        }
                                    ?>
                                        <tr>
                                            <?php if ($current_indicator != $grade['numero_indicador']): ?>
                                                <?php $current_indicator = $grade['numero_indicador']; ?>
                                                <td>
                                                    <span class="indicator-badge">
                                                        Indicador <?php echo $grade['numero_indicador']; ?>
                                                    </span>
                                                    <br>
                                                    <small style="color: var(--gray); display: block; margin-top: 0.5rem;">
                                                        <?php echo htmlspecialchars($grade['indicador_logro']); ?>
                                                    </small>
                                                </td>
                                            <?php else: ?>
                                                <td></td>
                                            <?php endif; ?>
                                            
                                            <td>
                                                <div class="session-info">
                                                    <span class="session-number">
                                                        Sesión <?php echo $grade['numero_sesion']; ?>
                                                    </span>
                                                    <span class="session-title">
                                                        <?php echo htmlspecialchars($grade['sesion_titulo']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <td><?php echo htmlspecialchars($grade['indicador_evaluacion']); ?></td>
                                            
                                            <td>
                                                <?php if ($calificacion > 0): ?>
                                                    <span class="grade-badge <?php echo $grade_class; ?>">
                                                        <?php echo number_format($calificacion, 1); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php if ($calificacion > 0): ?>
                                                    <span class="condition-badge <?php echo $condition_class; ?>">
                                                        <?php echo $condicion; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Resumen de estadísticas -->
                            <div class="summary-stats">
                                <?php
                                $total_grades = 0;
                                $sum_grades = 0;
                                $promedios_indicadores = [];
                                
                                foreach ($indicator_grades as $indicator_num => $grades) {
                                    if (!empty($grades)) {
                                        $promedio_indicador = array_sum($grades) / count($grades);
                                        $promedios_indicadores[] = $promedio_indicador;
                                        $total_grades += count($grades);
                                        $sum_grades += array_sum($grades);
                                    }
                                }
                                
                                $promedio_general = !empty($promedios_indicadores) ? array_sum($promedios_indicadores) / count($promedios_indicadores) : 0;
                                $condicion_general = calculateCondition($promedio_general);
                                ?>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($promedio_general, 1); ?></div>
                                    <div class="stat-label">Promedio General</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="stat-number"><?php echo $condicion_general; ?></div>
                                    <div class="stat-label">Condición Académica</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="stat-number"><?php echo count($promedios_indicadores); ?></div>
                                    <div class="stat-label">Indicadores Evaluados</div>
                                </div>
                                
                                <?php if ($attendance_summary[$curso['id']]): ?>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="stat-number"><?php echo number_format($attendance_summary[$curso['id']]['porcentaje_asistencia'], 1); ?>%</div>
                                        <div class="stat-label">Asistencia</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Validación del DNI en tiempo real
        document.getElementById('dni')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.substring(0, 8);
            }
            e.target.value = value;
        });
        
        // Animación de carga en el formulario
        document.getElementById('searchForm')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-primary');
            btn.classList.add('loading');
        });
        
        // Animación de aparición de elementos
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.course-card, .stat-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
            observer.observe(el);
        });
    </script>
</body>
</html>