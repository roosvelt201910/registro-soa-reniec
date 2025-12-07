<?php
// Incluir archivo de configuración
require_once '../config/database.php';

// Verificar permisos
requirePermission('docente');

// Inicializar base de datos
$database = new Database();
$conn = $database->getConnection();

$message = '';

// Obtener parámetros de filtro
$curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Procesar formularios para crear indicadores y sesiones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create_indicator') {
            $unidad_didactica_id = (int)$_POST['unidad_didactica_id'];
            $numero_indicador = (int)$_POST['numero_indicador'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $peso = (float)$_POST['peso'];
            
            try {
                $query = "INSERT INTO indicadores_logro 
                          (unidad_didactica_id, numero_indicador, nombre, descripcion, peso) 
                          VALUES 
                          (:unidad_didactica_id, :numero_indicador, :nombre, :descripcion, :peso)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                $stmt->bindParam(':numero_indicador', $numero_indicador);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':peso', $peso);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Indicador de logro creado exitosamente.</div>';
                }
            } catch(PDOException $e) {
                $message = '<div class="alert alert-error">Error al crear indicador: ' . $e->getMessage() . '</div>';
            }
        } elseif ($_POST['action'] == 'create_session') {
            $unidad_didactica_id = (int)$_POST['unidad_didactica_id'];
            $numero_sesion = (int)$_POST['numero_sesion'];
            $titulo = sanitizeInput($_POST['titulo']);
            $fecha = sanitizeInput($_POST['fecha']);
            $indicador_logro_id = (int)$_POST['indicador_logro_id'];
            
            try {
                // Crear la sesión
                $query = "INSERT INTO sesiones 
                          (unidad_didactica_id, numero_sesion, titulo, fecha, estado) 
                          VALUES 
                          (:unidad_didactica_id, :numero_sesion, :titulo, :fecha, 'programada')";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':unidad_didactica_id', $unidad_didactica_id);
                $stmt->bindParam(':numero_sesion', $numero_sesion);
                $stmt->bindParam(':titulo', $titulo);
                $stmt->bindParam(':fecha', $fecha);
                
                if ($stmt->execute()) {
                    $sesion_id = $conn->lastInsertId();
                    
                    // Crear indicador de evaluación para la sesión
                    $query2 = "INSERT INTO indicadores_evaluacion 
                              (sesion_id, indicador_logro_id, nombre, descripcion, peso) 
                              VALUES 
                              (:sesion_id, :indicador_logro_id, :nombre, 'Evaluación de sesión', 100)";
                    
                    $stmt2 = $conn->prepare($query2);
                    $stmt2->bindParam(':sesion_id', $sesion_id);
                    $stmt2->bindParam(':indicador_logro_id', $indicador_logro_id);
                    $stmt2->bindParam(':nombre', $titulo);
                    $stmt2->execute();
                    
                    $message = '<div class="alert alert-success">Sesión creada exitosamente.</div>';
                }
            } catch(PDOException $e) {
                $message = '<div class="alert alert-error">Error al crear sesión: ' . $e->getMessage() . '</div>';
            }
        } elseif ($_POST['action'] == 'save_grades') {
            $evaluaciones = $_POST['evaluacion'] ?? [];
            $saved = 0;
            $errors = 0;
            
            foreach ($evaluaciones as $key => $calificacion) {
                list($indicador_evaluacion_id, $estudiante_id) = explode('_', $key);
                
                if ($calificacion !== '' && $calificacion >= 0 && $calificacion <= 20) {
                    try {
                        $query = "INSERT INTO evaluaciones_sesion 
                                  (indicador_evaluacion_id, estudiante_id, calificacion) 
                                  VALUES 
                                  (:indicador_evaluacion_id, :estudiante_id, :calificacion)
                                  ON DUPLICATE KEY UPDATE calificacion = :calificacion";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':indicador_evaluacion_id', $indicador_evaluacion_id);
                        $stmt->bindParam(':estudiante_id', $estudiante_id);
                        $stmt->bindParam(':calificacion', $calificacion);
                        
                        if ($stmt->execute()) {
                            $saved++;
                        }
                    } catch(PDOException $e) {
                        $errors++;
                    }
                }
            }
            
            if ($saved > 0) {
                $message = '<div class="alert alert-success">Se guardaron ' . $saved . ' calificaciones correctamente.</div>';
            }
            if ($errors > 0) {
                $message .= '<div class="alert alert-error">Hubo ' . $errors . ' errores al guardar.</div>';
            }
        }
    }
}

// Obtener cursos disponibles
$cursos = [];
if ($_SESSION['user_type'] == 'super_admin') {
    $query = "SELECT ud.*, pe.nombre as programa_nombre 
              FROM unidades_didacticas ud
              LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
              WHERE ud.estado = 'activo'
              ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $conn->prepare($query);
} else {
    $query = "SELECT ud.*, pe.nombre as programa_nombre 
              FROM unidades_didacticas ud
              LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
              WHERE ud.docente_id = :docente_id 
              AND ud.estado = 'activo'
              ORDER BY ud.periodo_lectivo DESC, ud.nombre";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':docente_id', $_SESSION['user_id']);
}
$stmt->execute();
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay un curso seleccionado, obtener los datos del reporte
$reportData = null;
if ($curso_id > 0) {
    // Obtener información del curso
    $query = "SELECT ud.*, 
                     pe.nombre as programa_nombre,
                     CONCAT(u.apellidos, ', ', u.nombres) as docente_nombre
              FROM unidades_didacticas ud
              LEFT JOIN programas_estudio pe ON ud.programa_id = pe.id
              LEFT JOIN usuarios u ON ud.docente_id = u.id
              WHERE ud.id = :curso_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':curso_id', $curso_id);
    $stmt->execute();
    $curso_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener indicadores de logro
    $query = "SELECT * FROM indicadores_logro 
              WHERE unidad_didactica_id = :curso_id 
              ORDER BY numero_indicador";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':curso_id', $curso_id);
    $stmt->execute();
    $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener sesiones con sus indicadores de evaluación
    $query = "SELECT s.*, ie.id as indicador_evaluacion_id, 
                     ie.indicador_logro_id, ie.nombre as evaluacion_nombre,
                     il.numero_indicador
              FROM sesiones s
              LEFT JOIN indicadores_evaluacion ie ON s.id = ie.sesion_id
              LEFT JOIN indicadores_logro il ON ie.indicador_logro_id = il.id
              WHERE s.unidad_didactica_id = :curso_id
              ORDER BY s.numero_sesion, il.numero_indicador";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':curso_id', $curso_id);
    $stmt->execute();
    $sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar sesiones por indicador
    $sesionesPorIndicador = [];
    foreach ($sesiones as $sesion) {
        if ($sesion['indicador_logro_id']) {
            $sesionesPorIndicador[$sesion['indicador_logro_id']][] = $sesion;
        }
    }
    
    // Obtener estudiantes matriculados
    $query = "SELECT u.id, u.dni, 
                     CONCAT(u.apellidos, ' ', u.nombres) as nombre_completo
              FROM matriculas m
              JOIN usuarios u ON m.estudiante_id = u.id
              WHERE m.unidad_didactica_id = :curso_id
              AND m.estado = 'activo'
              ORDER BY u.apellidos, u.nombres";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':curso_id', $curso_id);
    $stmt->execute();
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todas las evaluaciones
    $query = "SELECT es.*, ie.sesion_id, ie.indicador_logro_id
              FROM evaluaciones_sesion es
              JOIN indicadores_evaluacion ie ON es.indicador_evaluacion_id = ie.id
              JOIN sesiones s ON ie.sesion_id = s.id
              WHERE s.unidad_didactica_id = :curso_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':curso_id', $curso_id);
    $stmt->execute();
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar evaluaciones por estudiante y sesión
    $evaluacionesMatrix = [];
    foreach ($evaluaciones as $eval) {
        $evaluacionesMatrix[$eval['estudiante_id']][$eval['indicador_evaluacion_id']] = $eval['calificacion'];
    }
    
    // Preparar datos del reporte
    $reportData = [
        'curso' => $curso_info,
        'indicadores' => $indicadores,
        'sesiones' => $sesiones,
        'sesionesPorIndicador' => $sesionesPorIndicador,
        'estudiantes' => $estudiantes,
        'evaluaciones' => $evaluacionesMatrix
    ];
}

// Si se solicita exportar a Excel
if ($export == 'excel' && $reportData) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Reporte_Evaluaciones_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Calcular total de columnas
    $totalCols = 2; // N° y Nombre
    foreach ($reportData['indicadores'] as $indicador) {
        $totalCols += count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []) + 1; // +1 para promedio
    }
    $totalCols++; // +1 para promedio final
    
    // Generar HTML para Excel
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Encabezados institucionales
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '" style="text-align:center; font-weight:bold;">INSTITUTO DE EDUCACIÓN SUPERIOR PÚBLICO "ALTO HUALLAGA" - TOCACHE</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '" style="text-align:center;">PROGRAMA DE ESTUDIOS: ' . htmlspecialchars($reportData['curso']['programa_nombre']) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '" style="text-align:center; font-weight:bold;">FICHA AUXILIAR DE EVALUACIÓN</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '">PERIODO LECTIVO: ' . htmlspecialchars($reportData['curso']['periodo_lectivo']) . ' | PERIODO ACADÉMICO: ' . htmlspecialchars($reportData['curso']['periodo_academico']) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '">UNIDAD DIDÁCTICA: ' . htmlspecialchars($reportData['curso']['nombre']) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '">DOCENTE: ' . htmlspecialchars($reportData['curso']['docente_nombre']) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '">INFORMACIÓN: La participación activa en clases teórico práctico es deber del o la estudiante, quedando bajo su responsabilidad</td>';
    echo '</tr>';
    echo '<tr><td colspan="' . $totalCols . '">&nbsp;</td></tr>';
    
    // Primera fila de encabezados
    echo '<tr style="background-color:#d4edda;">';
    echo '<th rowspan="3">N° DE ORDEN</th>';
    echo '<th rowspan="3">APELLIDOS Y NOMBRES</th>';
    
    // Calcular colspan para INDICADORES DE LOGRO
    $indicadoresColspan = 0;
    foreach ($reportData['indicadores'] as $indicador) {
        $indicadoresColspan += count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []) + 1;
    }
    echo '<th colspan="' . $indicadoresColspan . '">INDICADORES DE LOGRO</th>';
    echo '<th rowspan="3">PF. CT</th>';
    echo '</tr>';
    
    // Segunda fila de encabezados - Indicadores
    echo '<tr style="background-color:#d4edda;">';
    foreach ($reportData['indicadores'] as $indicador) {
        $numSesiones = count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []);
        $colspan = $numSesiones + 1;
        echo '<th colspan="' . $colspan . '">Indicador de Logro N°' . $indicador['numero_indicador'] . '</th>';
    }
    echo '</tr>';
    
    // Tercera fila de encabezados - Sesiones y promedios
    echo '<tr style="background-color:#d4edda;">';
    foreach ($reportData['indicadores'] as $indicador) {
        if (isset($reportData['sesionesPorIndicador'][$indicador['id']])) {
            $sesionesIndicador = $reportData['sesionesPorIndicador'][$indicador['id']];
            // Ordenar sesiones por número
            usort($sesionesIndicador, function($a, $b) {
                return $a['numero_sesion'] - $b['numero_sesion'];
            });
            foreach ($sesionesIndicador as $sesion) {
                echo '<th>Sesión N° ' . $sesion['numero_sesion'] . '.</th>';
            }
        }
        echo '<th style="background-color:#b8e6b8;">PROMEDIO DEL INDICADOR LOGRO ' . $indicador['numero_indicador'] . '</th>';
    }
    echo '</tr>';
    
    // Filas de estudiantes
    $num = 1;
    foreach ($reportData['estudiantes'] as $estudiante) {
        echo '<tr>';
        echo '<td style="text-align:center;">' . str_pad($num++, 2, '0', STR_PAD_LEFT) . '</td>';
        echo '<td>' . htmlspecialchars($estudiante['nombre_completo']) . '</td>';
        
        $promediosIndicadores = [];
        
        foreach ($reportData['indicadores'] as $indicador) {
            $notasIndicador = [];
            $sesionesIndicador = $reportData['sesionesPorIndicador'][$indicador['id']] ?? [];
            
            // Ordenar sesiones por número
            usort($sesionesIndicador, function($a, $b) {
                return $a['numero_sesion'] - $b['numero_sesion'];
            });
            
            foreach ($sesionesIndicador as $sesion) {
                $nota = $reportData['evaluaciones'][$estudiante['id']][$sesion['indicador_evaluacion_id']] ?? 0;
                echo '<td style="text-align:center; font-weight:bold;">' . str_pad($nota, 2, '0', STR_PAD_LEFT) . '</td>';
                if ($nota > 0) {
                    $notasIndicador[] = $nota;
                }
            }
            
            $promedioIndicador = count($notasIndicador) > 0 ? round(array_sum($notasIndicador) / count($notasIndicador), 0) : 0;
            $promediosIndicadores[] = $promedioIndicador;
            echo '<td style="text-align:center; font-weight:bold; background-color:#d1f2d1;">' . str_pad($promedioIndicador, 2, '0', STR_PAD_LEFT) . '</td>';
        }
        
        // Promedio final solo con indicadores que tienen notas
        $promediosConNotas = array_filter($promediosIndicadores, function($p) { return $p > 0; });
        $promedioFinal = count($promediosConNotas) > 0 ? round(array_sum($promediosConNotas) / count($promediosConNotas), 0) : 0;
        $color = $promedioFinal >= 13 ? '#d4edda' : '#f8d7da';
        echo '<td style="text-align:center; font-weight:bold; background-color:' . $color . ';">' . str_pad($promedioFinal, 2, '0', STR_PAD_LEFT) . '</td>';
        echo '</tr>';
    }
    
    // Agregar filas vacías hasta llegar a 40
    for ($i = $num; $i <= 40; $i++) {
        echo '<tr>';
        echo '<td style="text-align:center;">' . str_pad($i, 2, '0', STR_PAD_LEFT) . '</td>';
        echo '<td>0</td>';
        
        foreach ($reportData['indicadores'] as $indicador) {
            $sesionesIndicador = $reportData['sesionesPorIndicador'][$indicador['id']] ?? [];
            usort($sesionesIndicador, function($a, $b) {
                return $a['numero_sesion'] - $b['numero_sesion'];
            });
            foreach ($sesionesIndicador as $sesion) {
                echo '<td style="text-align:center; font-weight:bold;">00</td>';
            }
            echo '<td style="text-align:center; font-weight:bold; background-color:#d1f2d1;">00</td>';
        }
        
        echo '<td style="text-align:center; font-weight:bold;">00</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Evaluaciones - Sistema Académico</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #667eea;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 20px;
            border: 2px solid #000;
        }
        
        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
        
        .report-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 11px;
        }
        
        .report-table .student-name {
            text-align: left;
            padding-left: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .report-table .average {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .report-table .final-average {
            font-weight: bold;
        }
        
        .report-table .final-average.approved {
            background-color: #d4edda;
        }
        
        .report-table .final-average.failed {
            background-color: #f8d7da;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .report-header h2 {
            color: #333;
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .report-header h3 {
            color: #666;
            font-weight: normal;
            margin-bottom: 3px;
            font-size: 16px;
        }
        
        .report-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .report-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .grade-input {
            width: 40px;
            padding: 2px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            .report-table {
                font-size: 9px;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header no-print">
        <div class="header-content">
            <h1>Reporte de Evaluaciones</h1>
            <a href="../dashboard.php" class="btn btn-back">Volver al Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <div class="card no-print">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="curso_id">Seleccionar Curso:</label>
                    <select name="curso_id" id="curso_id" onchange="this.form.submit()">
                        <option value="">-- Seleccione un curso --</option>
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo $curso['id']; ?>" <?php echo $curso_id == $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['codigo'] . ' - ' . $curso['nombre'] . ' (' . $curso['periodo_lectivo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if ($reportData): ?>
            <div class="card">
                <div class="tabs no-print">
                    <button class="tab active" onclick="showTab('report', this)">Reporte de Evaluaciones</button>
                    <button class="tab" onclick="showTab('indicators', this)">Gestionar Indicadores</button>
                    <button class="tab" onclick="showTab('sessions', this)">Gestionar Sesiones</button>
                    <button class="tab" onclick="showTab('grades', this)">Registrar Calificaciones</button>
                </div>
                
                <!-- Tab: Reporte -->
                <div id="report" class="tab-content active">
                    <div class="report-header">
                        <h2>INSTITUTO DE EDUCACIÓN SUPERIOR PÚBLICO</h2>
                        <h2>"ALTO HUALLAGA" - TOCACHE</h2>
                        <h3>PROGRAMA DE ESTUDIOS: <?php echo htmlspecialchars($reportData['curso']['programa_nombre']); ?></h3>
                        <h3 style="font-weight:bold;">FICHA AUXILIAR DE EVALUACIÓN</h3>
                    </div>
                    
                    <div class="report-info">
                        <p><strong>PROGRAMA DE ESTUDIO:</strong> <?php echo htmlspecialchars($reportData['curso']['programa_nombre']); ?></p>
                        <p><strong>PERIODO LECTIVO:</strong> <?php echo htmlspecialchars($reportData['curso']['periodo_lectivo']); ?> | 
                           <strong>PERIODO ACADÉMICO:</strong> <?php echo htmlspecialchars($reportData['curso']['periodo_academico']); ?></p>
                        <p><strong>UNIDAD DIDÁCTICA:</strong> <?php echo htmlspecialchars($reportData['curso']['nombre']); ?></p>
                        <p><strong>DOCENTE:</strong> <?php echo htmlspecialchars($reportData['curso']['docente_nombre']); ?></p>
                        <p><strong>INFORMACIÓN:</strong> La participación activa en clases teórico práctico es deber del o la estudiante, quedando bajo su responsabilidad</p>
                    </div>
                    
                    <div class="no-print" style="margin-bottom: 20px;">
                        <a href="?curso_id=<?php echo $curso_id; ?>&export=excel" class="btn btn-success">Exportar a Excel</a>
                        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
                    </div>
                    
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr style="background-color: #e8f5e9;">
                                    <th rowspan="3" style="width: 40px; vertical-align: middle;">N° DE<br>ORDEN</th>
                                    <th rowspan="3" style="width: 250px; vertical-align: middle;">APELLIDOS Y NOMBRES</th>
                                    <th colspan="<?php 
                                        $totalCols = 0;
                                        foreach ($reportData['indicadores'] as $indicador) {
                                            $totalCols += count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []) + 1; // +1 para el promedio
                                        }
                                        echo $totalCols;
                                    ?>">INDICADORES DE LOGRO</th>
                                    <th rowspan="3" style="width: 80px; vertical-align: middle; background-color: #ffddc1;">PROMEDIO FINAL<br>(Capacidad Terminal)<br>PF. CT</th>
                                </tr>
                                <tr style="background-color: #e8f5e9;">
                                    <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                        <?php 
                                        $numSesiones = count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []);
                                        $colspan = $numSesiones + 1; // +1 para incluir el promedio
                                        ?>
                                        <th colspan="<?php echo $colspan; ?>">
                                            Indicador de Logro N°<?php echo $indicador['numero_indicador']; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr style="background-color: #e8f5e9;">
                                    <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                        <?php if (isset($reportData['sesionesPorIndicador'][$indicador['id']])): ?>
                                            <?php foreach ($reportData['sesionesPorIndicador'][$indicador['id']] as $sesion): ?>
                                                <th style="width: 45px; font-size: 10px;">Sesión N°<br><?php echo $sesion['numero_sesion']; ?>.</th>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <th style="width: 60px; font-size: 10px; background-color: #d4edda;">PROMEDIO DEL<br>INDICADOR<br>LOGRO <?php echo $indicador['numero_indicador']; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $num = 1; ?>
                                <?php foreach ($reportData['estudiantes'] as $estudiante): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo str_pad($num++, 2, '0', STR_PAD_LEFT); ?></td>
                                        <td class="student-name"><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></td>
                                        
                                        <?php $promediosIndicadores = []; ?>
                                        
                                        <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                            <?php $notasIndicador = []; ?>
                                            
                                            <?php if (isset($reportData['sesionesPorIndicador'][$indicador['id']])): ?>
                                                <?php foreach ($reportData['sesionesPorIndicador'][$indicador['id']] as $sesion): ?>
                                                    <?php 
                                                    $nota = $reportData['evaluaciones'][$estudiante['id']][$sesion['indicador_evaluacion_id']] ?? 0;
                                                    if ($nota > 0) {
                                                        $notasIndicador[] = $nota;
                                                    }
                                                    ?>
                                                    <td style="text-align: center;">
                                                        <strong><?php echo str_pad($nota, 2, '0', STR_PAD_LEFT); ?></strong>
                                                    </td>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            $promedioIndicador = count($notasIndicador) > 0 ? round(array_sum($notasIndicador) / count($notasIndicador), 0) : 0;
                                            $promediosIndicadores[] = $promedioIndicador;
                                            ?>
                                            <td style="text-align: center; font-weight: bold; background-color: #f0f0f0;">
                                                <strong><?php echo str_pad($promedioIndicador, 2, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <?php 
                                        // Calcular promedio final solo con los indicadores que tienen notas
                                        $promediosConNotas = array_filter($promediosIndicadores, function($p) { return $p > 0; });
                                        $promedioFinal = count($promediosConNotas) > 0 ? round(array_sum($promediosConNotas) / count($promediosConNotas), 0) : 0;
                                        $classPromedio = $promedioFinal >= 13 ? 'approved' : 'failed';
                                        ?>
                                        <td style="text-align: center; font-weight: bold;" class="final-average <?php echo $classPromedio; ?>">
                                            <strong><?php echo str_pad($promedioFinal, 2, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- Filas vacías para completar hasta 40 estudiantes -->
                                <?php for ($i = $num; $i <= 40; $i++): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></td>
                                        <td class="student-name">0</td>
                                        <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                            <?php if (isset($reportData['sesionesPorIndicador'][$indicador['id']])): ?>
                                                <?php foreach ($reportData['sesionesPorIndicador'][$indicador['id']] as $sesion): ?>
                                                    <td style="text-align: center;"><strong>00</strong></td>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <td style="text-align: center; background-color: #f0f0f0;"><strong>00</strong></td>
                                        <?php endforeach; ?>
                                        <td style="text-align: center;"><strong>00</strong></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab: Gestionar Indicadores -->
                <div id="indicators" class="tab-content">
                    <h3>Gestionar Indicadores de Logro</h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_indicator">
                        <input type="hidden" name="unidad_didactica_id" value="<?php echo $curso_id; ?>">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div class="form-group">
                                <label>Número de Indicador:</label>
                                <input type="number" name="numero_indicador" min="1" max="10" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Nombre del Indicador:</label>
                                <input type="text" name="nombre" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Peso (%):</label>
                                <input type="number" name="peso" min="0" max="100" step="0.01" value="100" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Descripción:</label>
                            <input type="text" name="descripcion">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Crear Indicador</button>
                    </form>
                    
                    <h4 style="margin-top: 30px;">Indicadores Existentes:</h4>
                    <table class="report-table" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Peso (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                <tr>
                                    <td><?php echo $indicador['numero_indicador']; ?></td>
                                    <td><?php echo htmlspecialchars($indicador['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($indicador['descripcion']); ?></td>
                                    <td><?php echo $indicador['peso']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Tab: Gestionar Sesiones -->
                <div id="sessions" class="tab-content">
                    <h3>Gestionar Sesiones de Clase</h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_session">
                        <input type="hidden" name="unidad_didactica_id" value="<?php echo $curso_id; ?>">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div class="form-group">
                                <label>Número de Sesión:</label>
                                <input type="number" name="numero_sesion" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Indicador de Logro:</label>
                                <select name="indicador_logro_id" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                        <option value="<?php echo $indicador['id']; ?>">
                                            Indicador <?php echo $indicador['numero_indicador']; ?> - <?php echo htmlspecialchars($indicador['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha:</label>
                                <input type="date" name="fecha" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Título de la Sesión:</label>
                            <input type="text" name="titulo" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Crear Sesión</button>
                    </form>
                    
                    <h4 style="margin-top: 30px;">Sesiones Existentes:</h4>
                    <table class="report-table" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>N° Sesión</th>
                                <th>Título</th>
                                <th>Fecha</th>
                                <th>Indicador</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['sesiones'] as $sesion): ?>
                                <tr>
                                    <td><?php echo $sesion['numero_sesion']; ?></td>
                                    <td><?php echo htmlspecialchars($sesion['titulo']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($sesion['fecha'])); ?></td>
                                    <td>Indicador <?php echo $sesion['numero_indicador']; ?></td>
                                    <td><?php echo ucfirst($sesion['estado']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Tab: Registrar Calificaciones -->
                <div id="grades" class="tab-content">
                    <h3>Registrar Calificaciones</h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save_grades">
                        
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">N°</th>
                                        <th rowspan="2">Estudiante</th>
                                        <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                            <?php $numSesiones = count($reportData['sesionesPorIndicador'][$indicador['id']] ?? []); ?>
                                            <?php if ($numSesiones > 0): ?>
                                                <th colspan="<?php echo $numSesiones; ?>">
                                                    Indicador <?php echo $indicador['numero_indicador']; ?>
                                                </th>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                            <?php if (isset($reportData['sesionesPorIndicador'][$indicador['id']])): ?>
                                                <?php foreach ($reportData['sesionesPorIndicador'][$indicador['id']] as $sesion): ?>
                                                    <th>S<?php echo $sesion['numero_sesion']; ?></th>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $num = 1; ?>
                                    <?php foreach ($reportData['estudiantes'] as $estudiante): ?>
                                        <tr>
                                            <td><?php echo $num++; ?></td>
                                            <td class="student-name"><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></td>
                                            
                                            <?php foreach ($reportData['indicadores'] as $indicador): ?>
                                                <?php if (isset($reportData['sesionesPorIndicador'][$indicador['id']])): ?>
                                                    <?php foreach ($reportData['sesionesPorIndicador'][$indicador['id']] as $sesion): ?>
                                                        <?php 
                                                        $key = $sesion['indicador_evaluacion_id'] . '_' . $estudiante['id'];
                                                        $nota = $reportData['evaluaciones'][$estudiante['id']][$sesion['indicador_evaluacion_id']] ?? '';
                                                        ?>
                                                        <td>
                                                            <input type="number" 
                                                                   name="evaluacion[<?php echo $key; ?>]" 
                                                                   class="grade-input"
                                                                   min="0" 
                                                                   max="20" 
                                                                   step="1"
                                                                   value="<?php echo $nota; ?>">
                                                        </td>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn btn-success" style="margin-top: 20px;">Guardar Calificaciones</button>
                    </form>
                </div>
            </div>
        <?php elseif ($curso_id > 0): ?>
            <div class="card">
                <p>No se encontraron datos para este curso. Por favor, configure los indicadores de logro y sesiones primero.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function showTab(tabName, tabElement) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            tabElement.classList.add('active');
        }
    </script>
</body>
</html>