<?php
date_default_timezone_set('America/Lima'); // Para hora de Perú
// Configuraciones del sistema
define('SYSTEM_NAME', 'Sistema Académico');
define('INSTITUTE_NAME', 'Instituto de Educación Superior Público "Alto Huallaga"');
define('INSTITUTE_LOCATION', 'Tocache');
define('SYSTEM_VERSION', '1.0');

// Configuraciones de evaluación
define('MIN_GRADE', 0);
define('MAX_GRADE', 20);
define('MIN_ATTENDANCE_PERCENTAGE', 70);

// Escalas de calificación
define('GRADE_EXCELLENT_MIN', 18);
define('GRADE_APPROVED_MIN', 12.5);
define('GRADE_PROCESS_MIN', 9.5);

// Estados del sistema
define('STATUS_ACTIVE', 'activo');
define('STATUS_INACTIVE', 'inactivo');

// Tipos de usuario
define('USER_SUPER_ADMIN', 'super_admin');
define('USER_TEACHER', 'docente');
define('USER_STUDENT', 'estudiante');

// Estados de asistencia
define('ATTENDANCE_PRESENT', 'presente');
define('ATTENDANCE_ABSENT', 'falta');
define('ATTENDANCE_PERMISSION', 'permiso');

// Estados de sesión
define('SESSION_SCHEDULED', 'programada');
define('SESSION_COMPLETED', 'realizada');
define('SESSION_CANCELLED', 'cancelada');

// Condiciones académicas
define('CONDITION_EXCELLENT', 'EXCELENTE');
define('CONDITION_APPROVED', 'APROBADO');
define('CONDITION_PROCESS', 'EN PROCESO');
define('CONDITION_FAILED', 'DESAPROBADO');
define('CONDITION_DPI', 'DPI'); // Desaprobado por Inasistencia

// Configuraciones de paginación
define('RECORDS_PER_PAGE', 50);

// Configuraciones de archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Funciones de utilidad adicionales
function formatGrade($grade) {
    if ($grade === null || $grade == 0) {
        return '-';
    }
    return number_format($grade, 1);
}

function getGradeColor($grade) {
    if ($grade >= GRADE_EXCELLENT_MIN) return '#28a745'; // Verde
    if ($grade >= GRADE_APPROVED_MIN) return '#007bff';  // Azul
    if ($grade >= GRADE_PROCESS_MIN) return '#ffc107';   // Amarillo
    return '#dc3545'; // Rojo
}

function formatPercentage($percentage) {
    return number_format($percentage, 1) . '%';
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    return date($format, strtotime($datetime));
}

function generateCourseCode($program_code, $sequence = 1) {
    return strtoupper($program_code) . str_pad($sequence, 3, '0', STR_PAD_LEFT);
}

function validateDNI($dni) {
    return preg_match('/^[0-9]{8}$/', $dni);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateGrade($grade) {
    return is_numeric($grade) && $grade >= MIN_GRADE && $grade <= MAX_GRADE;
}

function getAttendanceIcon($status) {
    switch ($status) {
        case ATTENDANCE_PRESENT:
            return '✅';
        case ATTENDANCE_ABSENT:
            return '❌';
        case ATTENDANCE_PERMISSION:
            return '⚠️';
        default:
            return '❓';
    }
}

function getConditionBadgeClass($condition) {
    switch ($condition) {
        case CONDITION_EXCELLENT:
            return 'badge-excellent';
        case CONDITION_APPROVED:
            return 'badge-approved';
        case CONDITION_PROCESS:
            return 'badge-process';
        case CONDITION_FAILED:
        case CONDITION_DPI:
            return 'badge-failed';
        default:
            return 'badge-default';
    }
}

// Configuraciones de zona horaria
date_default_timezone_set('America/Lima');

// Configuración de errores (para desarrollo)
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuraciones de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Función para generar tokens CSRF (para futuras mejoras de seguridad)
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para logging (para futuras mejoras)
function logActivity($user_id, $action, $details = '') {
    // Implementar logging en base de datos o archivo
    // Por ahora solo placeholder
    $log_entry = date('Y-m-d H:i:s') . " - User $user_id: $action - $details\n";
    // file_put_contents('logs/activity.log', $log_entry, FILE_APPEND | LOCK_EX);
}

// Configuraciones de email (para futuras notificaciones)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'sistema@instituto.edu.pe');
define('FROM_NAME', INSTITUTE_NAME);

// Configuraciones de backup automático
define('BACKUP_ENABLED', false);
define('BACKUP_FREQUENCY', '1 day');
define('BACKUP_RETENTION', '30 days');

// Configuraciones de notificaciones
define('NOTIFICATIONS_ENABLED', true);
define('EMAIL_NOTIFICATIONS', false);
define('SMS_NOTIFICATIONS', false);

// Mensajes del sistema
define('MSG_SUCCESS_CREATED', 'Registro creado exitosamente.');
define('MSG_SUCCESS_UPDATED', 'Registro actualizado exitosamente.');
define('MSG_SUCCESS_DELETED', 'Registro eliminado exitosamente.');
define('MSG_ERROR_GENERIC', 'Ha ocurrido un error. Por favor, inténtelo nuevamente.');
define('MSG_ERROR_PERMISSION', 'No tiene permisos para realizar esta acción.');
define('MSG_ERROR_NOT_FOUND', 'El registro solicitado no fue encontrado.');
define('MSG_ERROR_DUPLICATE', 'Ya existe un registro con estos datos.');
define('MSG_ERROR_INVALID_DATA', 'Los datos proporcionados no son válidos.');

// Configuración de mantenimiento
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'El sistema está en mantenimiento. Por favor, inténtelo más tarde.');

// Función para verificar modo mantenimiento
function checkMaintenanceMode() {
    if (MAINTENANCE_MODE && !isset($_SESSION['is_admin'])) {
        die('<h1>Sistema en Mantenimiento</h1><p>' . MAINTENANCE_MESSAGE . '</p>');
    }
}
?>