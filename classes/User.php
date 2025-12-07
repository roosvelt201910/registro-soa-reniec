<?php
// classes/User.php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Obtiene un estudiante por su DNI
     */
    public function getStudentByDni($dni) {
        try {
            $query = "
                SELECT id, dni, nombres, apellidos, email, tipo_usuario, estado
                FROM usuarios 
                WHERE dni = :dni AND tipo_usuario = 'estudiante' AND estado = 'activo'
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getStudentByDni: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene un usuario por ID
     */
    public function getUserById($user_id) {
        try {
            $query = "
                SELECT id, dni, nombres, apellidos, email, tipo_usuario, estado
                FROM usuarios 
                WHERE id = :user_id AND estado = 'activo'
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getUserById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene todos los estudiantes
     */
    public function getAllStudents() {
        try {
            $query = "
                SELECT id, dni, nombres, apellidos, email, estado
                FROM usuarios 
                WHERE tipo_usuario = 'estudiante'
                ORDER BY apellidos, nombres
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getAllStudents: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todos los docentes
     */
    public function getAllTeachers() {
        try {
            $query = "
                SELECT id, dni, nombres, apellidos, email, estado
                FROM usuarios 
                WHERE tipo_usuario = 'docente'
                ORDER BY apellidos, nombres
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getAllTeachers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica las credenciales de login
     */
    public function verifyLogin($dni, $password) {
        try {
            $query = "
                SELECT id, dni, nombres, apellidos, email, password, tipo_usuario
                FROM usuarios 
                WHERE dni = :dni AND estado = 'activo'
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // No retornar la contraseña
                unset($user['password']);
                return $user;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error in verifyLogin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crea un nuevo usuario
     */
    public function createUser($data) {
        try {
            $query = "
                INSERT INTO usuarios (dni, nombres, apellidos, email, password, tipo_usuario)
                VALUES (:dni, :nombres, :apellidos, :email, :password, :tipo_usuario)
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $data['dni'], PDO::PARAM_STR);
            $stmt->bindParam(':nombres', $data['nombres'], PDO::PARAM_STR);
            $stmt->bindParam(':apellidos', $data['apellidos'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':password', password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->bindParam(':tipo_usuario', $data['tipo_usuario'], PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error in createUser: " . $e->getMessage());
            return false;
        }
    }
}
?>