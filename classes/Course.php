<?php
// classes/Course.php
require_once __DIR__ . '/../config/database.php';

class Course {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Obtiene los cursos de un estudiante
     */
    public function getStudentCourses($student_id) {
        try {
            $query = "
                SELECT 
                    ud.id,
                    ud.nombre,
                    ud.codigo,
                    ud.periodo_lectivo,
                    ud.periodo_academico,
                    pe.nombre as programa_nombre,
                    CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre,
                    m.fecha_matricula,
                    m.estado as estado_matricula
                FROM matriculas m
                JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
                JOIN programas_estudio pe ON ud.programa_id = pe.id
                JOIN usuarios u ON ud.docente_id = u.id
                WHERE m.estudiante_id = :student_id AND m.estado = 'activo' AND ud.estado = 'activo'
                ORDER BY ud.periodo_lectivo DESC, ud.nombre
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getStudentCourses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene los cursos de un docente
     */
    public function getTeacherCourses($teacher_id) {
        try {
            $query = "
                SELECT 
                    ud.id,
                    ud.nombre,
                    ud.codigo,
                    ud.periodo_lectivo,
                    ud.periodo_academico,
                    pe.nombre as programa_nombre,
                    COUNT(m.id) as total_estudiantes
                FROM unidades_didacticas ud
                JOIN programas_estudio pe ON ud.programa_id = pe.id
                LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
                WHERE ud.docente_id = :teacher_id AND ud.estado = 'activo'
                GROUP BY ud.id, ud.nombre, ud.codigo, ud.periodo_lectivo, ud.periodo_academico, pe.nombre
                ORDER BY ud.periodo_lectivo DESC, ud.nombre
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getTeacherCourses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todos los cursos
     */
    public function getAllCourses() {
        try {
            $query = "
                SELECT 
                    ud.id,
                    ud.nombre,
                    ud.codigo,
                    ud.periodo_lectivo,
                    ud.periodo_academico,
                    pe.nombre as programa_nombre,
                    CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre,
                    ud.estado,
                    COUNT(m.id) as total_estudiantes
                FROM unidades_didacticas ud
                JOIN programas_estudio pe ON ud.programa_id = pe.id
                JOIN usuarios u ON ud.docente_id = u.id
                LEFT JOIN matriculas m ON ud.id = m.unidad_didactica_id AND m.estado = 'activo'
                GROUP BY ud.id, ud.nombre, ud.codigo, ud.periodo_lectivo, ud.periodo_academico, pe.nombre, u.apellidos, u.nombres, ud.estado
                ORDER BY ud.periodo_lectivo DESC, ud.nombre
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getAllCourses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene un curso por ID
     */
    public function getCourseById($course_id) {
        try {
            $query = "
                SELECT 
                    ud.id,
                    ud.nombre,
                    ud.codigo,
                    ud.periodo_lectivo,
                    ud.periodo_academico,
                    ud.programa_id,
                    pe.nombre as programa_nombre,
                    ud.docente_id,
                    CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre,
                    ud.estado
                FROM unidades_didacticas ud
                JOIN programas_estudio pe ON ud.programa_id = pe.id
                JOIN usuarios u ON ud.docente_id = u.id
                WHERE ud.id = :course_id
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getCourseById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene los estudiantes matriculados en un curso
     */
    public function getCourseStudents($course_id) {
        try {
            $query = "
                SELECT 
                    u.id,
                    u.dni,
                    u.nombres,
                    u.apellidos,
                    CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                    u.email,
                    m.fecha_matricula,
                    m.estado as estado_matricula
                FROM matriculas m
                JOIN usuarios u ON m.estudiante_id = u.id
                WHERE m.unidad_didactica_id = :course_id AND u.tipo_usuario = 'estudiante'
                ORDER BY u.apellidos, u.nombres
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getCourseStudents: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Matricula un estudiante en un curso
     */
    public function enrollStudent($student_id, $course_id) {
        try {
            $query = "
                INSERT INTO matriculas (estudiante_id, unidad_didactica_id)
                VALUES (:student_id, :course_id)
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error in enrollStudent: " . $e->getMessage());
            return false;
        }
    }
}
?>