<?php

session_start();
date_default_timezone_set('America/Lima'); // Para hora de Perú
class Database {
    private $host = "localhost";
    private $db_name = "michelle_arqos";
    private $username = "michelle_arqos";  // Cambiar según tu configuración
    private $password = "$[sTJWL]CEkSIHMs";      // Cambiar según tu configuración
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Funciones auxiliares
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function calculateCondition($promedio) {
    if ($promedio < 9.5) {
        return "DESAPROBADO";
    } elseif ($promedio <= 12) {
        return "EN PROCESO";
    } elseif ($promedio < 18) {
        return "APROBADO";
    } else {
        return "EXCELENTE";
    }
}

function calculateAttendanceCondition($porcentaje) {
    if ($porcentaje >= 70) {
        return "APROBADO";
    } else {
        return "DPI"; // Desaprobado por Inasistencia
    }
}

// Verificar si el usuario tiene permisos
function hasPermission($required_permission) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    $user_type = $_SESSION['user_type'];
    
    switch ($required_permission) {
        case 'super_admin':
            return $user_type === 'super_admin';
        case 'docente':
            return in_array($user_type, ['super_admin', 'docente']);
        case 'estudiante':
            return in_array($user_type, ['super_admin', 'docente', 'estudiante']);
        default:
            return false;
    }
}

// Requerir login
function requireLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
}

// Requerir permisos específicos
function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>