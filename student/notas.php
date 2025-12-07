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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #0891b2;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --dark: #111827;
            --gray-900: #111827;
            --gray-800: #1f2937;
            --gray-700: #374151;
            --gray-600: #4b5563;
            --gray-500: #6b7280;
            --gray-400: #9ca3af;
            --gray-300: #d1d5db;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --gray-50: #f9fafb;
            --white: #ffffff;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
            --indigo-50: #eef2ff;
            --indigo-100: #e0e7ff;
            
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            --border-radius-sm: 6px;
            --border-radius: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--gray-900);
            line-height: 1.6;
            font-size: 14px;
            min-height: 100vh;
            font-weight: 400;
            letter-spacing: -0.01em;
        }
        
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 2rem 0;
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .logo-container {
            flex-shrink: 0;
        }
        
        .logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 28px;
            box-shadow: var(--shadow-md);
        }
        
        .header-text h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            letter-spacing: -0.025em;
        }
        
        .header-text .institute-name {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
            line-height: 1.4;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--white);
            color: var(--gray-700);
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            transition: all var(--transition-fast);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xs);
        }
        
        .btn-back:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            transform: translateX(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .search-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        
        .search-card h2 {
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .search-card h2 i {
            color: var(--primary);
            font-size: 1.125rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
        }
        
        .form-group {
            flex: 1;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
            background: var(--white);
            color: var(--gray-900);
            font-weight: 400;
        }
        
        .form-group i {
            position: absolute;
            left: 1rem;
            top: 2.375rem;
            color: var(--gray-400);
            transition: color var(--transition-fast);
            font-size: 0.875rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-group input:focus + i {
            color: var(--primary);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            align-self: flex-end;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border: 1px solid var(--primary);
            box-shadow: var(--shadow-xs);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: var(--shadow-xs);
        }
        
        .student-info {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        
        .student-info h2 {
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .student-info h2 i {
            color: var(--primary);
            font-size: 1.125rem;
        }
        
        .student-details {
            background: var(--blue-50);
            padding: 1.5rem;
            border-radius: var(--border-radius-md);
            border: 1px solid var(--blue-100);
        }
        
        .student-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .detail-item i {
            color: var(--primary);
            width: 20px;
            text-align: center;
            margin-top: 0.125rem;
            font-size: 0.875rem;
        }
        
        .detail-item div strong {
            color: var(--gray-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .detail-item div span {
            color: var(--gray-900);
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.4;
        }
        
        .course-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .course-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .course-header {
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .course-header h3 {
            color: var(--gray-900);
            font-size: 1.375rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            line-height: 1.3;
        }
        
        .course-header h3 i {
            color: var(--primary);
            font-size: 1.125rem;
        }
        
        .course-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            padding: 1.25rem;
            background: var(--indigo-50);
            border-radius: var(--border-radius);
            border: 1px solid var(--indigo-100);
        }
        
        .course-info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .course-info-item i {
            color: var(--primary);
            width: 18px;
            text-align: center;
            margin-top: 0.125rem;
            font-size: 0.875rem;
        }
        
        .course-info-item div strong {
            color: var(--gray-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .course-info-item div span {
            color: var(--gray-900);
            font-weight: 500;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        .section-title {
            color: var(--gray-900);
            font-size: 1.125rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 2rem;
            border-radius: var(--border-radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
            border: 1px solid var(--gray-200);
            background: var(--white);
        }
        
        .grades-table thead {
            background: var(--gray-50);
        }
        
        .grades-table th {
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .grades-table tbody tr {
            transition: all var(--transition-fast);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .grades-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .grades-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .grades-table td {
            padding: 1rem 0.75rem;
            vertical-align: top;
            font-size: 0.875rem;
        }
        
        .indicator-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.375rem 0.75rem;
            border-radius: var(--border-radius-sm);
            display: inline-block;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .session-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .session-number {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .session-title {
            color: var(--gray-600);
            font-size: 0.75rem;
            line-height: 1.3;
        }
        
        .grade-badge {
            padding: 0.5rem 0.75rem;
            border-radius: var(--border-radius-sm);
            font-weight: 700;
            font-size: 0.875rem;
            display: inline-block;
            min-width: 50px;
            text-align: center;
            color: var(--white);
        }
        
        .grade-excellent {
            background: var(--success);
        }
        
        .grade-good {
            background: var(--primary);
        }
        
        .grade-process {
            background: var(--warning);
        }
        
        .grade-failed {
            background: var(--danger);
        }
        
        .condition-badge {
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
        }
        
        .condition-excellent {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        
        .condition-good {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }
        
        .condition-process {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning);
            border: 1px solid rgba(217, 119, 6, 0.2);
        }
        
        .condition-failed {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius-md);
            text-align: center;
            border: 1px solid var(--gray-200);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.125rem;
        }
        
        .stat-number {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
            background: var(--gray-50);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--gray-200);
        }
        
        .no-data i {
            font-size: 3rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }
        
        .no-data h3 {
            font-size: 1.125rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .no-data p {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.875rem;
        }
        
        .alert i {
            font-size: 1.125rem;
            margin-top: 0.125rem;
        }
        
        .alert-error {
            background: rgba(220, 38, 38, 0.05);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        
        .alert strong {
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
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
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .header-text h1 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 1.5rem 1rem;
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
            
            .student-details-grid {
                grid-template-columns: 1fr;
            }
            
            .grades-table {
                font-size: 0.8rem;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-card {
                padding: 1.25rem;
            }
        }
        
        @media (max-width: 480px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            body {
                background: var(--white);
                font-size: 12px;
            }
            
            .btn-back,
            .search-card {
                display: none;
            }
            
            .course-card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid var(--gray-300);
                margin-bottom: 1rem;
            }
            
            .header {
                box-shadow: none;
                border-bottom: 2px solid var(--gray-900);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
            <div class="header-text">
                <h1>Sistema de Consulta de Notas</h1>
                <p class="institute-name">Instituto de Educación Superior Tecnológico Público "Alto Huallaga" - Tocache</p>
            </div>
        </div>
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
                               placeholder="Aquí ingresa tú Nº DNI"
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
                                <strong>Nombre Completo</strong>
                                <span><?php echo htmlspecialchars($student_data['apellidos'] . ', ' . $student_data['nombres']); ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-id-card"></i>
                            <div>
                                <strong>DNI</strong>
                                <span><?php echo htmlspecialchars($student_data['dni']); ?></span>
                            </div>
                        </div>
                        <?php if ($student_data['email']): ?>
                        <div class="detail-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>Correo Electrónico</strong>
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
                                        <strong>Programa de Estudio</strong>
                                        <span><?php echo htmlspecialchars($curso['programa_nombre']); ?></span>
                                    </div>
                                </div>
                                <div class="course-info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <strong>Periodo</strong>
                                        <span><?php echo htmlspecialchars($curso['periodo_lectivo'] . ' - ' . $curso['periodo_academico']); ?></span>
                                    </div>
                                </div>
                                <div class="course-info-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Docente</strong>
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
                            <!-- Promedios por sesión -->
                            <h4 class="section-title">
                                <i class="fas fa-chart-line"></i>
                                Promedios por Sesión de Clases
                            </h4>
                            
                            <?php
                            // Agrupar evaluaciones por sesión para calcular promedios
                            $session_averages = [];
                            $indicator_grades = [];
                            
                            foreach ($student_grades[$curso['id']] as $grade) {
                                $calificacion = $grade['calificacion'] ?? 0;
                                $session_key = $grade['numero_sesion'];
                                
                                if ($calificacion > 0) {
                                    if (!isset($session_averages[$session_key])) {
                                        $session_averages[$session_key] = [
                                            'numero_sesion' => $grade['numero_sesion'],
                                            'sesion_titulo' => $grade['sesion_titulo'],
                                            'grades' => [],
                                            'indicadores' => []
                                        ];
                                    }
                                    $session_averages[$session_key]['grades'][] = $calificacion;
                                    $session_averages[$session_key]['indicadores'][] = $grade['numero_indicador'];
                                }
                                
                                // Agrupar por indicador para estadísticas generales
                                if (!isset($indicator_grades[$grade['numero_indicador']])) {
                                    $indicator_grades[$grade['numero_indicador']] = [];
                                }
                                if ($calificacion > 0) {
                                    $indicator_grades[$grade['numero_indicador']][] = $calificacion;
                                }
                            }
                            
                            // Función para determinar la clase CSS según la calificación
                            function getGradeClass($promedio) {
                                if ($promedio >= 18) return 'grade-excellent';
                                if ($promedio >= 13) return 'grade-good';
                                if ($promedio >= 10) return 'grade-process';
                                return 'grade-failed';
                            }
                            
                            function getConditionClass($promedio) {
                                if ($promedio >= 18) return 'condition-excellent';
                                if ($promedio >= 13) return 'condition-good';
                                if ($promedio >= 10) return 'condition-process';
                                return 'condition-failed';
                            }
                            ?>
                            
                            <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-chalkboard"></i> Sesión de Clase</th>
                                        <th><i class="fas fa-bullseye"></i> Indicadores de Logro de la Capacidad</th>
                                        <th><i class="fas fa-calculator"></i> Número de indicadores de evaluación </th>
                                        <th><i class="fas fa-star"></i> Promedio de Sesión</th>
                                        <th><i class="fas fa-award"></i> Condición</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($session_averages as $session): ?>
                                        <?php 
                                        $promedio_sesion = array_sum($session['grades']) / count($session['grades']);
                                        $indicadores_unicos = array_unique($session['indicadores']);
                                        $condicion = calculateCondition($promedio_sesion);
                                        $grade_class = getGradeClass($promedio_sesion);
                                        $condition_class = getConditionClass($promedio_sesion);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="session-info">
                                                    <span class="session-number">
                                                        Sesión <?php echo $session['numero_sesion']; ?>
                                                    </span>
                                                    <span class="session-title">
                                                        <?php echo htmlspecialchars($session['sesion_titulo']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <?php foreach ($indicadores_unicos as $indicador): ?>
                                                    <span class="indicator-badge" style="margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block;">
                                                        Ind. <?php echo $indicador; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                            
                                            <td>
                                                <span style="font-weight: 600; color: var(--gray-700);">
                                                    <?php echo count($session['grades']); ?> Indicadores promediados
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <span class="grade-badge <?php echo $grade_class; ?>">
                                                    <?php echo number_format($promedio_sesion, 1); ?>
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <span class="condition-badge <?php echo $condition_class; ?>">
                                                    <?php echo $condicion; ?>
                                                </span>
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
                                    <div class="stat-label">Indicadores de logro de la capacidad Evaluados</div>
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
        
        // Intersection Observer para animaciones suaves
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
        
        // Aplicar animaciones a elementos
        document.querySelectorAll('.course-card, .stat-card, .student-info').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
            observer.observe(el);
        });
        
        // Scroll suave para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Mejorar la accesibilidad del teclado
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    </script>
</body>
</html>