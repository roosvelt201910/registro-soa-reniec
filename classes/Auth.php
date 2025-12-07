<?php
require_once '../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function login($dni, $password) {
        try {
            $query = "SELECT id, dni, nombres, apellidos, tipo_usuario, estado FROM usuarios WHERE dni = :dni AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Para este ejemplo, usamos password simple, en producción usar password_verify()
                if ($password === 'password123') { // Cambiar por validación real
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_dni'] = $user['dni'];
                    $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
                    $_SESSION['user_type'] = $user['tipo_usuario'];
                    $_SESSION['logged_in'] = true;
                    
                    return true;
                }
            }
            return false;
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function hasPermission($required_permission) {
        if (!$this->isLoggedIn()) {
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
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ../login.php');
            exit();
        }
    }
    
    public function requirePermission($permission) {
        $this->requireLogin();
        if (!$this->hasPermission($permission)) {
            header('Location: ../unauthorized.php');
            exit();
        }
    }

    // Métodos adicionales para compatibilidad
    public function getCurrentUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    public function getCurrentUserDni() {
        return $this->isLoggedIn() ? $_SESSION['user_dni'] : null;
    }
    
    public function getCurrentUserName() {
        return $this->isLoggedIn() ? $_SESSION['user_name'] : null;
    }
    
    public function getCurrentUserType() {
        return $this->isLoggedIn() ? $_SESSION['user_type'] : null;
    }
}

class User {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function createUser($dni, $nombres, $apellidos, $email, $tipo_usuario) {
        try {
            $query = "INSERT INTO usuarios (dni, nombres, apellidos, email, password, tipo_usuario) VALUES (:dni, :nombres, :apellidos, :email, :password, :tipo_usuario)";
            $stmt = $this->conn->prepare($query);
            
            $password = password_hash('password123', PASSWORD_DEFAULT); // Cambiar por sistema de password real
            
            $stmt->bindParam(':dni', $dni);
            $stmt->bindParam(':nombres', $nombres);
            $stmt->bindParam(':apellidos', $apellidos);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':tipo_usuario', $tipo_usuario);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function getStudentByDni($dni) {
        try {
            $query = "SELECT * FROM usuarios WHERE dni = :dni AND tipo_usuario = 'estudiante' AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function getAllStudents() {
        try {
            $query = "SELECT id, dni, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE tipo_usuario = 'estudiante' AND estado = 'activo' ORDER BY apellidos, nombres";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
    
    public function getAllTeachers() {
        try {
            $query = "SELECT id, dni, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE tipo_usuario = 'docente' AND estado = 'activo' ORDER BY apellidos, nombres";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }

    // Métodos adicionales necesarios para el sistema de asistencias
    public function getUserById($user_id) {
        try {
            $query = "SELECT id, dni, nombres, apellidos, email, tipo_usuario, estado FROM usuarios WHERE id = :user_id AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Error in getUserById: " . $exception->getMessage());
            return false;
        }
    }

    public function verifyLogin($dni, $password) {
        try {
            $query = "SELECT id, dni, nombres, apellidos, email, password, tipo_usuario FROM usuarios WHERE dni = :dni AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Para compatibilidad con tu sistema actual
                if ($password === 'password123') {
                    unset($user['password']); // No retornar la contraseña
                    return $user;
                }
                
                // También verificar con password_verify para futuras implementaciones
                if (password_verify($password, $user['password'])) {
                    unset($user['password']); // No retornar la contraseña
                    return $user;
                }
            }
            
            return false;
        } catch(PDOException $exception) {
            error_log("Error in verifyLogin: " . $exception->getMessage());
            return false;
        }
    }
}
?>