<?php
require_once '../config/database.php';

class Attendance {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function recordAttendance($sesion_id, $estudiante_id, $estado, $observaciones = '') {
        try {
            $query = "INSERT INTO asistencias (sesion_id, estudiante_id, estado, observaciones) 
                     VALUES (:sesion_id, :estudiante_id, :estado, :observaciones)
                     ON DUPLICATE KEY UPDATE estado = :estado_update, observaciones = :observaciones_update";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':sesion_id', $sesion_id);
            $stmt->bindParam(':estudiante_id', $estudiante_id);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':estado_update', $estado);
            $stmt->bindParam(':observaciones_update', $observaciones);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function getAttendanceByCourse($unidad_didactica_id) {
        try {
            $query = "SELECT 
                        s.id as sesion_id, s.numero_sesion, s.titulo, s.fecha,
                        u.id as estudiante_id, u.dni, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                        a.estado, a.observaciones
                     FROM sesiones s
                     CROSS JOIN matriculas m
                     JOIN usuarios u ON m.estudiante_id = u.id
                     LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
                     WHERE s.unidad_didactica_id = :unidad_didactica_id 
                     AND m.unidad_didactica_id = :unidad_didactica_id_2 
                     AND m.estado = 'activo'
                     ORDER BY u.apellidos, u.nombres, s.numero_sesion";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->bindParam(':unidad_didactica_id_2', $unidad_didactica_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
    
    public function getAttendanceSummary($unidad_didactica_id) {
        try {
            $query = "SELECT 
                        u.id as estudiante_id, u.dni, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                        COUNT(s.id) as total_sesiones_programadas,
                        COUNT(a.id) as total_sesiones_registradas,
                        SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as asistencias,
                        SUM(CASE WHEN a.estado = 'falta' THEN 1 ELSE 0 END) as faltas,
                        SUM(CASE WHEN a.estado = 'permiso' THEN 1 ELSE 0 END) as permisos,
                        CASE 
                            WHEN COUNT(a.id) > 0 THEN
                                ROUND((SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2)
                            ELSE 0
                        END as porcentaje_asistencia
                     FROM matriculas m
                     JOIN usuarios u ON m.estudiante_id = u.id
                     JOIN unidades_didacticas ud ON m.unidad_didactica_id = ud.id
                     LEFT JOIN sesiones s ON s.unidad_didactica_id = ud.id
                     LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
                     WHERE m.unidad_didactica_id = :unidad_didactica_id AND m.estado = 'activo'
                     GROUP BY u.id, u.dni, u.apellidos, u.nombres
                     ORDER BY u.apellidos, u.nombres";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
}

class Evaluation {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function createLearningIndicator($unidad_didactica_id, $numero_indicador, $nombre, $descripcion = '', $peso = 100.00) {
        try {
            $query = "INSERT INTO indicadores_logro (unidad_didactica_id, numero_indicador, nombre, descripcion, peso) VALUES (:unidad_didactica_id, :numero_indicador, :nombre, :descripcion, :peso)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->bindParam(':numero_indicador', $numero_indicador);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':peso', $peso);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function createEvaluationIndicator($sesion_id, $indicador_logro_id, $nombre, $descripcion = '', $peso = 100.00) {
        try {
            $query = "INSERT INTO indicadores_evaluacion (sesion_id, indicador_logro_id, nombre, descripcion, peso) VALUES (:sesion_id, :indicador_logro_id, :nombre, :descripcion, :peso)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':sesion_id', $sesion_id);
            $stmt->bindParam(':indicador_logro_id', $indicador_logro_id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':peso', $peso);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function recordEvaluation($indicador_evaluacion_id, $estudiante_id, $calificacion) {
        try {
            $query = "INSERT INTO evaluaciones_sesion (indicador_evaluacion_id, estudiante_id, calificacion) 
                     VALUES (:indicador_evaluacion_id, :estudiante_id, :calificacion)
                     ON DUPLICATE KEY UPDATE calificacion = :calificacion_update";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':indicador_evaluacion_id', $indicador_evaluacion_id);
            $stmt->bindParam(':estudiante_id', $estudiante_id);
            $stmt->bindParam(':calificacion', $calificacion);
            $stmt->bindParam(':calificacion_update', $calificacion);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            return false;
        }
    }
    
    public function getLearningIndicators($unidad_didactica_id) {
        try {
            $query = "SELECT * FROM indicadores_logro WHERE unidad_didactica_id = :unidad_didactica_id ORDER BY numero_indicador";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
    
    public function getEvaluationIndicators($sesion_id) {
        try {
            $query = "SELECT ie.*, il.nombre as indicador_logro_nombre 
                     FROM indicadores_evaluacion ie
                     JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
                     WHERE ie.sesion_id = :sesion_id 
                     ORDER BY il.numero_indicador";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sesion_id', $sesion_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
    
    public function getStudentGrades($unidad_didactica_id, $estudiante_id) {
        try {
            $query = "SELECT 
                        il.numero_indicador, il.nombre as indicador_logro,
                        s.numero_sesion, s.titulo as sesion_titulo,
                        ie.nombre as indicador_evaluacion,
                        es.calificacion
                     FROM indicadores_logro il
                     JOIN indicadores_evaluacion ie ON il.id = ie.indicador_logro_id
                     JOIN sesiones s ON ie.sesion_id = s.id
                     LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id AND es.estudiante_id = :estudiante_id
                     WHERE il.unidad_didactica_id = :unidad_didactica_id
                     ORDER BY il.numero_indicador, s.numero_sesion";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->bindParam(':estudiante_id', $estudiante_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
    
    public function getAuxiliaryEvaluationReport($unidad_didactica_id) {
        try {
            $query = "SELECT 
                        u.id as estudiante_id, u.dni, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                        il.numero_indicador, il.nombre as indicador_logro,
                        AVG(es.calificacion) as promedio_indicador
                     FROM matriculas m
                     JOIN usuarios u ON m.estudiante_id = u.id
                     JOIN indicadores_logro il ON il.unidad_didactica_id = m.unidad_didactica_id
                     LEFT JOIN indicadores_evaluacion ie ON il.id = ie.indicador_logro_id
                     LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id AND es.estudiante_id = u.id
                     WHERE m.unidad_didactica_id = :unidad_didactica_id AND m.estado = 'activo'
                     GROUP BY u.id, il.id
                     ORDER BY u.apellidos, u.nombres, il.numero_indicador";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            return [];
        }
    }
}
?>