<?php
// ===== ARCHIVO 1: generate_pdf.php (NUEVO ARCHIVO) =====
<?php
// Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    die('Error: Sesión no encontrada');
}

// Configuración de base de datos (usar las mismas credenciales del archivo principal)
$host = 'localhost';
$dbname = 'iespaltohuallaga_regauxiliar_bd';
$username = 'iespaltohuallaga_user_regaux';
$password = ')wBRCeID[ldb%b^K';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificar parámetros
if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    die('Error: ID de curso inválido');
}

$course_id = intval($_GET['course_id']);
$session_id = isset($_GET['session_id']) && is_numeric($_GET['session_id']) ? intval($_GET['session_id']) : null;

// Función para obtener datos del curso
function getCourseData($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ud.*, pe.nombre as programa_nombre, 
                   CONCAT(u.nombres, ' ', u.apellidos) as docente_nombre
            FROM unidades_didacticas ud
            JOIN programas_estudio pe ON ud.programa_id = pe.id
            JOIN usuarios u ON ud.docente_id = u.id
            WHERE ud.id = ?
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener estudiantes matriculados
function getEnrolledStudents($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.dni, u.nombres, u.apellidos, 
                   CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo,
                   m.fecha_matricula, m.estado as estado_matricula
            FROM matriculas m
            JOIN usuarios u ON m.estudiante_id = u.id
            WHERE m.unidad_didactica_id = ? AND m.estado = 'activo'
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener indicadores de evaluación por sesión o general
function getEvaluationIndicators($pdo, $course_id, $session_id = null) {
    try {
        if ($session_id) {
            // Indicadores de una sesión específica
            $stmt = $pdo->prepare("
                SELECT ie.id, ie.nombre, ie.descripcion, ie.peso,
                       il.nombre as indicador_logro_nombre, il.numero_indicador,
                       ie.indicador_logro_id, s.titulo as sesion_titulo, s.numero_sesion
                FROM indicadores_evaluacion ie
                JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
                JOIN sesiones s ON ie.sesion_id = s.id
                WHERE ie.sesion_id = ?
                ORDER BY il.numero_indicador ASC
            ");
            $stmt->execute([$session_id]);
        } else {
            // Todos los indicadores del curso
            $stmt = $pdo->prepare("
                SELECT ie.id, ie.nombre, ie.descripcion, ie.peso,
                       il.nombre as indicador_logro_nombre, il.numero_indicador,
                       ie.indicador_logro_id, s.titulo as sesion_titulo, s.numero_sesion
                FROM indicadores_evaluacion ie
                JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
                JOIN sesiones s ON ie.sesion_id = s.id
                WHERE il.unidad_didactica_id = ?
                ORDER BY s.numero_sesion ASC, il.numero_indicador ASC
            ");
            $stmt->execute([$course_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener calificaciones
function getGradesData($pdo, $indicators, $students) {
    $grades = [];
    $averages = [];
    
    foreach ($students as $student) {
        $grades[$student['id']] = [];
        $totalPoints = 0;
        $totalWeight = 0;
        
        foreach ($indicators as $indicator) {
            try {
                $stmt = $pdo->prepare("
                    SELECT calificacion
                    FROM evaluaciones_sesion
                    WHERE indicador_evaluacion_id = ? AND estudiante_id = ?
                ");
                $stmt->execute([$indicator['id'], $student['id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $grade = floatval($result['calificacion']);
                    $grades[$student['id']][$indicator['id']] = $grade;
                    $totalPoints += $grade * $indicator['peso'];
                    $totalWeight += $indicator['peso'];
                } else {
                    $grades[$student['id']][$indicator['id']] = null;
                }
            } catch (PDOException $e) {
                $grades[$student['id']][$indicator['id']] = null;
            }
        }
        
        $averages[$student['id']] = $totalWeight > 0 ? round($totalPoints / $totalWeight, 2) : 0;
    }
    
    return ['grades' => $grades, 'averages' => $averages];
}

// Obtener datos
$course = getCourseData($pdo, $course_id);
if (!$course) {
    die('Error: Curso no encontrado');
}

$students = getEnrolledStudents($pdo, $course_id);
$indicators = getEvaluationIndicators($pdo, $course_id, $session_id);
$gradesData = getGradesData($pdo, $indicators, $students);

// Configurar headers para PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="ficha_evaluacion_' . $course['codigo'] . '.pdf"');

// Generar HTML para convertir a PDF
$html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #0f206e;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18px;
            color: #0f206e;
            margin: 0 0 5px 0;
            font-weight: bold;
        }
        
        .header h2 {
            font-size: 14px;
            color: #666;
            margin: 0;
            font-weight: normal;
        }
        
        .course-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0f206e;
        }
        
        .course-info h3 {
            margin: 0 0 8px 0;
            color: #0f206e;
            font-size: 14px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .info-item {
            font-size: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .table-container {
            margin-top: 15px;
        }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 20px;
        }
        
        .evaluation-table th {
            background: #0f206e;
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
            word-wrap: break-word;
        }
        
        .evaluation-table td {
            padding: 4px;
            text-align: center;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .evaluation-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .student-name {
            text-align: left !important;
            font-weight: bold;
            padding-left: 8px !important;
            width: 120px;
            min-width: 120px;
        }
        
        .student-dni {
            font-size: 8px;
            color: #666;
            font-weight: normal;
        }
        
        .indicator-header {
            width: 40px;
            min-width: 40px;
            font-size: 8px;
            padding: 4px 2px !important;
        }
        
        .average-column {
            background: #e8f5e8 !important;
            font-weight: bold;
            color: #0f206e;
            width: 35px;
        }
        
        .grade-cell {
            font-weight: bold;
        }
        
        .grade-excellent { color: #28a745; }
        .grade-good { color: #17a2b8; }
        .grade-regular { color: #ffc107; }
        .grade-poor { color: #dc3545; }
        
        .footer {
            margin-top: 25px;
            border-top: 2px solid #eee;
            padding-top: 15px;
            font-size: 9px;
            color: #666;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 25px;
        }
        
        .signature-box {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 30px;
        }
        
        .legend {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 8px;
        }
        
        .legend h4 {
            margin: 0 0 5px 0;
            font-size: 10px;
            color: #0f206e;
        }
        
        .legend-item {
            display: inline-block;
            margin-right: 15px;
        }
        
        .break-before {
            page-break-before: always;
        }
        
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>';

$html .= '<div class="header">
    <h1>FICHA DE EVALUACIÓN ACADÉMICA</h1>
    <h2>Instituto de Educación Superior Tecnológico Público "Palto Huallaga"</h2>
</div>';

$html .= '<div class="course-info">
    <h3>' . htmlspecialchars($course['nombre']) . '</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Código:</span> ' . htmlspecialchars($course['codigo']) . '
        </div>
        <div class="info-item">
            <span class="info-label">Programa:</span> ' . htmlspecialchars($course['programa_nombre']) . '
        </div>
        <div class="info-item">
            <span class="info-label">Docente:</span> ' . htmlspecialchars($course['docente_nombre']) . '
        </div>
        <div class="info-item">
            <span class="info-label">Período:</span> ' . htmlspecialchars($course['periodo_lectivo']) . ' - ' . htmlspecialchars($course['periodo_academico']) . '
        </div>
    </div>
    <div class="info-item">
        <span class="info-label">Fecha de generación:</span> ' . date('d/m/Y H:i') . '
    </div>
</div>';

if (empty($indicators)) {
    $html .= '<div style="text-align: center; padding: 50px; color: #666;">
        <h3>No hay indicadores de evaluación configurados</h3>
        <p>Configure primero los indicadores de evaluación para generar la ficha.</p>
    </div>';
} elseif (empty($students)) {
    $html .= '<div style="text-align: center; padding: 50px; color: #666;">
        <h3>No hay estudiantes matriculados</h3>
        <p>No se encontraron estudiantes matriculados en esta unidad didáctica.</p>
    </div>';
} else {
    $html .= '<div class="table-container">
        <table class="evaluation-table">
            <thead>
                <tr>
                    <th class="student-name">ESTUDIANTE</th>';
    
    // Headers de indicadores
    foreach ($indicators as $indicator) {
        $html .= '<th class="indicator-header">
            IE' . $indicator['numero_indicador'] . '<br>
            <span style="font-size: 7px;">Ses. ' . $indicator['numero_sesion'] . '</span><br>
            <span style="font-size: 7px;">(' . $indicator['peso'] . '%)</span>
        </th>';
    }
    
    $html .= '<th class="average-column">PROM.</th>
                </tr>
            </thead>
            <tbody>';
    
    // Filas de estudiantes
    foreach ($students as $student) {
        $html .= '<tr>
            <td class="student-name">
                ' . htmlspecialchars($student['nombre_completo']) . '<br>
                <span class="student-dni">DNI: ' . htmlspecialchars($student['dni']) . '</span>
            </td>';
        
        // Calificaciones por indicador
        foreach ($indicators as $indicator) {
            $grade = $gradesData['grades'][$student['id']][$indicator['id']];
            $gradeClass = '';
            $gradeText = '-';
            
            if ($grade !== null) {
                $gradeText = number_format($grade, 1);
                if ($grade >= 18) $gradeClass = 'grade-excellent';
                elseif ($grade >= 14) $gradeClass = 'grade-good';
                elseif ($grade >= 11) $gradeClass = 'grade-regular';
                else $gradeClass = 'grade-poor';
            }
            
            $html .= '<td class="grade-cell ' . $gradeClass . '">' . $gradeText . '</td>';
        }
        
        // Promedio
        $average = $gradesData['averages'][$student['id']];
        $avgClass = '';
        if ($average >= 18) $avgClass = 'grade-excellent';
        elseif ($average >= 14) $avgClass = 'grade-good';
        elseif ($average >= 11) $avgClass = 'grade-regular';
        else $avgClass = 'grade-poor';
        
        $html .= '<td class="average-column grade-cell ' . $avgClass . '">' . 
                 number_format($average, 1) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    // Leyenda
    $html .= '<div class="legend">
        <h4>LEYENDA</h4>
        <div>
            <span class="legend-item"><strong>IE:</strong> Indicador de Evaluación</span>
            <span class="legend-item"><strong>Ses.:</strong> Número de Sesión</span>
            <span class="legend-item"><strong>PROM.:</strong> Promedio Ponderado</span>
        </div>
        <div style="margin-top: 5px;">
            <span class="legend-item" style="color: #28a745;"><strong>18-20:</strong> Excelente</span>
            <span class="legend-item" style="color: #17a2b8;"><strong>14-17:</strong> Bueno</span>
            <span class="legend-item" style="color: #ffc107;"><strong>11-13:</strong> Regular</span>
            <span class="legend-item" style="color: #dc3545;"><strong>0-10:</strong> Deficiente</span>
        </div>
    </div>';
}

$html .= '<div class="footer">
    <div class="signature-section">
        <div>
            <div class="signature-box">
                <strong>' . htmlspecialchars($course['docente_nombre']) . '</strong><br>
                Docente
            </div>
        </div>
        <div>
            <div class="signature-box">
                <strong>Coordinador Académico</strong><br>
                Programa de Estudios
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; font-size: 8px; color: #999;">
        Generado por el Sistema Académico - Instituto "Palto Huallaga" - ' . date('d/m/Y H:i:s') . '
    </div>
</div>';

$html .= '</body></html>';

// Para generar PDF usando biblioteca como DomPDF, mPDF o similar
// Aquí mostramos el HTML por ahora - puedes integrar una biblioteca PDF

// Si quieres usar DomPDF (recomendado):
// require_once 'vendor/autoload.php';
// use Dompdf\Dompdf;
// $dompdf = new Dompdf();
// $dompdf->loadHtml($html);
// $dompdf->setPaper('A4', 'portrait');
// $dompdf->render();
// $dompdf->stream('ficha_evaluacion.pdf');

// Por ahora, mostrar HTML para testing
echo $html;
?>

// ===== MODIFICACIONES PARA EL ARCHIVO PRINCIPAL =====
// Agregar estas funciones y modificaciones a tu archivo principal:

// FUNCIÓN ADICIONAL PARA OBTENER SESIÓN POR ID (agregar si no existe)
function getSessionById($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, unidad_didactica_id, numero_sesion, titulo, fecha, descripcion, estado
            FROM sesiones
            WHERE id = ?
        ");
        $stmt->execute([$session_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// BOTÓN PARA AGREGAR EN LA SECCIÓN DE CALIFICACIONES (después de la línea 1015 aprox):
// Buscar donde dice: <h3><i class="fas fa-chart-line"></i> Registrar Calificaciones</h3>
// Y agregar después de esa línea:

/*
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div></div>
    <div>
        <a href="generate_pdf.php?course_id=<?php echo $selectedCourse['id']; ?>" 
           class="btn btn-success" target="_blank" title="Descargar ficha de evaluación completa">
            <i class="fas fa-file-pdf"></i> Ficha Completa PDF
        </a>
        <?php if (isset($_GET['grades_session_id'])): ?>
            <a href="generate_pdf.php?course_id=<?php echo $selectedCourse['id']; ?>&session_id=<?php echo $_GET['grades_session_id']; ?>" 
               class="btn btn-info" target="_blank" title="Descargar solo esta sesión">
                <i class="fas fa-file-pdf"></i> Solo esta Sesión
            </a>
        <?php endif; ?>
    </div>
</div>
*/
?>