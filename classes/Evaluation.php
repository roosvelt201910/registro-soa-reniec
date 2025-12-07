<?php
// classes/Evaluation.php
require_once __DIR__ . '/../config/database.php';

class Evaluation {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Obtiene las calificaciones de un estudiante en un curso
     */
    public function getStudentGrades($student_id, $course_id) {
        try {
            $query = "
                SELECT 
                    il.id as indicador_logro_id,
                    il.numero_indicador,
                    il.nombre as indicador_nombre,
                    il.descripcion as indicador_descripcion,
                    il.peso as indicador_peso,
                    ie.id as indicador_evaluacion_id,
                    ie.nombre as evaluacion_nombre,
                    ie.descripcion as evaluacion_descripcion,
                    ie.peso as evaluacion_peso,
                    s.numero_sesion,
                    s.titulo as sesion_titulo,
                    s.fecha as sesion_fecha,
                    es.calificacion,
                    es.fecha_evaluacion
                FROM indicadores_logro il
                LEFT JOIN indicadores_evaluacion ie ON il.id = ie.indicador_logro_id
                LEFT JOIN sesiones s ON ie.sesion_id = s.id
                LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id AND es.estudiante_id = :student_id
                WHERE il.unidad_didactica_id = :course_id
                ORDER BY il.numero_indicador, s.numero_sesion, ie.id
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getStudentGrades: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcula el promedio de un estudiante en un curso
     */
    public function calculateCourseAverage($student_id, $course_id) {
        try {
            // Obtener todas las calificaciones del estudiante en el curso
            $grades = $this->getStudentGrades($student_id, $course_id);
            
            if (empty($grades)) {
                return [
                    'promedio' => 0,
                    'total_evaluaciones' => 0,
                    'evaluaciones_completadas' => 0,
                    'condicion' => 'SIN EVALUAR'
                ];
            }
            
            // Agrupar por indicador de logro para calcular promedio ponderado
            $indicadores = [];
            $total_evaluaciones = 0;
            $evaluaciones_completadas = 0;
            
            foreach ($grades as $grade) {
                $ind_id = $grade['indicador_logro_id'];
                
                if (!isset($indicadores[$ind_id])) {
                    $indicadores[$ind_id] = [
                        'nombre' => $grade['indicador_nombre'],
                        'peso' => $grade['indicador_peso'],
                        'evaluaciones' => [],
                        'promedio_indicador' => 0
                    ];
                }
                
                if ($grade['indicador_evaluacion_id']) {
                    $total_evaluaciones++;
                    
                    if ($grade['calificacion'] !== null) {
                        $indicadores[$ind_id]['evaluaciones'][] = [
                            'calificacion' => $grade['calificacion'],
                            'peso' => $grade['evaluacion_peso']
                        ];
                        $evaluaciones_completadas++;
                    }
                }
            }
            
            // Calcular promedio por indicador y promedio general
            $suma_ponderada = 0;
            $suma_pesos = 0;
            $tiene_calificaciones = false;
            
            foreach ($indicadores as $ind_id => $indicador) {
                if (!empty($indicador['evaluaciones'])) {
                    // Promedio ponderado dentro del indicador
                    $suma_eval = 0;
                    $suma_pesos_eval = 0;
                    
                    foreach ($indicador['evaluaciones'] as $eval) {
                        $suma_eval += $eval['calificacion'] * $eval['peso'];
                        $suma_pesos_eval += $eval['peso'];
                    }
                    
                    if ($suma_pesos_eval > 0) {
                        $promedio_indicador = $suma_eval / $suma_pesos_eval;
                        $indicadores[$ind_id]['promedio_indicador'] = $promedio_indicador;
                        
                        // Contribuir al promedio general
                        $suma_ponderada += $promedio_indicador * $indicador['peso'];
                        $suma_pesos += $indicador['peso'];
                        $tiene_calificaciones = true;
                    }
                }
            }
            
            $promedio_final = 0;
            if ($suma_pesos > 0 && $tiene_calificaciones) {
                $promedio_final = $suma_ponderada / $suma_pesos;
            }
            
            return [
                'promedio' => round($promedio_final, 2),
                'total_evaluaciones' => $total_evaluaciones,
                'evaluaciones_completadas' => $evaluaciones_completadas,
                'condicion' => calculateCondition($promedio_final),
                'indicadores' => $indicadores
            ];
            
        } catch (Exception $e) {
            error_log("Error in calculateCourseAverage: " . $e->getMessage());
            return [
                'promedio' => 0,
                'total_evaluaciones' => 0,
                'evaluaciones_completadas' => 0,
                'condicion' => 'ERROR'
            ];
        }
    }
    
    /**
     * Obtiene el resumen académico completo de un estudiante
     */
    public function getStudentAcademicSummary($student_id) {
        try {
            // Obtener cursos del estudiante
            $course = new Course();
            $courses = $course->getStudentCourses($student_id);
            
            $summary = [
                'cursos' => [],
                'promedio_general' => 0,
                'cursos_aprobados' => 0,
                'cursos_desaprobados' => 0,
                'cursos_en_proceso' => 0,
                'total_cursos' => count($courses)
            ];
            
            $suma_promedios = 0;
            $cursos_con_nota = 0;
            
            foreach ($courses as $curso) {
                $course_avg = $this->calculateCourseAverage($student_id, $curso['id']);
                
                $course_data = [
                    'curso' => $curso,
                    'promedio' => $course_avg['promedio'],
                    'condicion' => $course_avg['condicion'],
                    'evaluaciones_completadas' => $course_avg['evaluaciones_completadas'],
                    'total_evaluaciones' => $course_avg['total_evaluaciones']
                ];
                
                $summary['cursos'][] = $course_data;
                
                if ($course_avg['promedio'] > 0) {
                    $suma_promedios += $course_avg['promedio'];
                    $cursos_con_nota++;
                    
                    // Contar por condición
                    if ($course_avg['promedio'] >= 13) {
                        $summary['cursos_aprobados']++;
                    } elseif ($course_avg['promedio'] >= 9.5) {
                        $summary['cursos_en_proceso']++;
                    } else {
                        $summary['cursos_desaprobados']++;
                    }
                }
            }
            
            // Promedio general
            if ($cursos_con_nota > 0) {
                $summary['promedio_general'] = round($suma_promedios / $cursos_con_nota, 2);
            }
            
            return $summary;
            
        } catch (Exception $e) {
            error_log("Error in getStudentAcademicSummary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene el historial de calificaciones detallado
     */
    public function getDetailedGradesHistory($student_id, $course_id) {
        try {
            $query = "
                SELECT 
                    il.numero_indicador,
                    il.nombre as indicador_nombre,
                    il.peso as indicador_peso,
                    ie.nombre as evaluacion_nombre,
                    ie.peso as evaluacion_peso,
                    s.numero_sesion,
                    s.titulo as sesion_titulo,
                    s.fecha as sesion_fecha,
                    es.calificacion,
                    es.fecha_evaluacion,
                    CASE 
                        WHEN es.calificacion IS NULL THEN 'Pendiente'
                        WHEN es.calificacion < 9.5 THEN 'Desaprobado'
                        WHEN es.calificacion < 13 THEN 'En Proceso'
                        WHEN es.calificacion < 18 THEN 'Aprobado'
                        ELSE 'Excelente'
                    END as estado_evaluacion
                FROM indicadores_logro il
                JOIN indicadores_evaluacion ie ON il.id = ie.indicador_logro_id
                JOIN sesiones s ON ie.sesion_id = s.id
                LEFT JOIN evaluaciones_sesion es ON ie.id = es.indicador_evaluacion_id AND es.estudiante_id = :student_id
                WHERE il.unidad_didactica_id = :course_id
                ORDER BY il.numero_indicador, s.numero_sesion, ie.id
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getDetailedGradesHistory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registra una calificación
     */
    public function recordGrade($indicador_evaluacion_id, $student_id, $calificacion) {
        try {
            // Validar que la calificación esté en el rango correcto
            if ($calificacion < 0 || $calificacion > 20) {
                return false;
            }
            
            $query = "
                INSERT INTO evaluaciones_sesion (indicador_evaluacion_id, estudiante_id, calificacion)
                VALUES (:indicador_evaluacion_id, :student_id, :calificacion)
                ON DUPLICATE KEY UPDATE 
                calificacion = :calificacion2,
                fecha_evaluacion = CURRENT_TIMESTAMP
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':indicador_evaluacion_id', $indicador_evaluacion_id, PDO::PARAM_INT);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':calificacion', $calificacion, PDO::PARAM_STR);
            $stmt->bindParam(':calificacion2', $calificacion, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error in recordGrade: " . $e->getMessage());
            return false;
        }
    }
}
?>