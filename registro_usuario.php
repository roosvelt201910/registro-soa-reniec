<?php
// registro_usuario.php - Sistema de Registro Académico con Auto-completado DNI
session_start();

// ==================== CONFIGURACIÓN DE BASE DE DATOS ====================
$db_host = 'localhost';
$db_user = 'michelle_arqos';
$db_pass = '$[sTJWL]CEkSIHMs';
$db_name = 'michelle_arqos';

// Crear conexión
$conexion = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Establecer charset UTF-8
$conexion->set_charset("utf8mb4");
date_default_timezone_set('America/Lima');

// ==================== FUNCIONES DE CONSULTA DNI ====================
function consultarDNIDirecto($dni) {
    $token = 'sk_10367.GU4w1AirWvIqPecMayNcvlSK3RbE5H4v';
    
    error_log("Consultando DNI: " . $dni . " - " . date('Y-m-d H:i:s'));
    
    $datosPrueba = [
        '71882580' => ['nombres' => 'ENMANUEL ALEJANDRO', 'apellidos' => 'MILLONES '],
        '12345678' => ['nombres' => 'JUAN CARLOS', 'apellidos' => 'PEREZ LOPEZ'],
        '87654321' => ['nombres' => 'MARIA ELENA', 'apellidos' => 'SANCHEZ TORRES'],
        '11111111' => ['nombres' => 'ANA SOFIA', 'apellidos' => 'MENDOZA VARGAS'],
        '22222222' => ['nombres' => 'PEDRO ANTONIO', 'apellidos' => 'FLORES CASTILLO'],
        '33333333' => ['nombres' => 'LUCIA BEATRIZ', 'apellidos' => 'GUTIERREZ RAMOS'],
        '44444444' => ['nombres' => 'MIGUEL ANGEL', 'apellidos' => 'TORRES SILVA'],
        '55555555' => ['nombres' => 'CARMEN ROSA', 'apellidos' => 'VEGA MORALES'],
        '66666666' => ['nombres' => 'JOSE LUIS', 'apellidos' => 'HERRERA CASTRO'],
        '77777777' => ['nombres' => 'PATRICIA ELENA', 'apellidos' => 'ROJAS VARGAS'],
        '46027897' => ['nombres' => 'ERACLEO JUAN', 'apellidos' => 'HUAMANI MENDOZA']
    ];
    
    if (!empty($token) && $token !== 'TU_NUEVO_TOKEN_DECOLECTA') {
        try {
            $url = "https://api.decolecta.com/v1/reniec/dni?numero=" . $dni;
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'User-Agent: Sistema-Registro-Academico/3.0'
            ];
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 15,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (isset($data['first_name']) && isset($data['first_last_name'])) {
                    $nombres = strtoupper(trim($data['first_name']));
                    $apellidoPaterno = strtoupper(trim($data['first_last_name']));
                    $apellidoMaterno = strtoupper(trim($data['second_last_name'] ?? ''));
                    $apellidos = trim($apellidoPaterno . ' ' . $apellidoMaterno);
                    
                    error_log("DNI encontrado en Decolecta: " . $dni);
                    return [
                        'success' => true,
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'service' => 'decolecta_reniec'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error consultando Decolecta: " . $e->getMessage());
        }
    }
    
    if (isset($datosPrueba[$dni])) {
        error_log("DNI encontrado en datos de prueba: " . $dni);
        return [
            'success' => true,
            'nombres' => $datosPrueba[$dni]['nombres'],
            'apellidos' => $datosPrueba[$dni]['apellidos'],
            'service' => 'datos_prueba'
        ];
    }
    
    try {
        $tokenAlt = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6InRlc3RAdGVzdC5jb20ifQ.TSLLVm2Fsd5jpVBJZsVJfbLNLcDblrcE_2QlkmYoZAE';
        $urlAlt = "https://dniruc.apisperu.com/api/v1/dni/" . $dni . "?token=" . $tokenAlt;
        
        $contextAlt = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => 'User-Agent: Sistema-Registro-Academico/3.0'
            ]
        ]);
        
        $responseAlt = @file_get_contents($urlAlt, false, $contextAlt);
        
        if ($responseAlt !== false) {
            $dataAlt = json_decode($responseAlt, true);
            if (isset($dataAlt['nombres']) && !empty($dataAlt['nombres'])) {
                error_log("DNI encontrado en API alternativa: " . $dni);
                return [
                    'success' => true,
                    'nombres' => strtoupper(trim($dataAlt['nombres'])),
                    'apellidos' => strtoupper(trim($dataAlt['apellidoPaterno'] . ' ' . $dataAlt['apellidoMaterno'])),
                    'service' => 'api_alternativa'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error consultando API alternativa: " . $e->getMessage());
    }
    
    error_log("DNI no encontrado: " . $dni);
    return ['success' => false, 'error' => 'DNI no encontrado en ninguna fuente'];
}

// ==================== VARIABLES ====================
$mensaje = '';
$tipo_mensaje = '';
$ultimo_registro = null;
$registro_exitoso = false;
$datos_dni = null;

// ==================== CONSULTA DNI AUTOMÁTICA ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['consultar_dni'])) {
    $dni = trim($_POST['dni']);
    
    if (preg_match('/^[0-9]{8}$/', $dni)) {
        $datos_dni = consultarDNIDirecto($dni);
        
        if ($datos_dni['success']) {
            $_SESSION['dni_data'] = $datos_dni;
            $_SESSION['dni_consultado'] = $dni;
        }
    } else {
        $datos_dni = ['success' => false, 'error' => 'DNI debe tener exactamente 8 dígitos'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($datos_dni, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== PROCESAR REGISTRO ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar'])) {
    $dni = trim($_POST['dni']);
    $nombres = strtoupper(trim($_POST['nombres']));
    $apellidos = strtoupper(trim($_POST['apellidos']));
    $email = trim($_POST['email']);
    $tipo_usuario = $_POST['tipo_usuario'];
    
    $password = $dni;
    
    $errores = [];
    
    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        $errores[] = "El DNI debe tener exactamente 8 dígitos numéricos";
    }
    
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $nombres)) {
        $errores[] = "Los nombres solo pueden contener letras y espacios";
    }
    
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellidos)) {
        $errores[] = "Los apellidos solo pueden contener letras y espacios";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del email no es válido";
    }
    
    if (!in_array($tipo_usuario, ['estudiante', 'docente'])) {
        $errores[] = "Tipo de usuario no válido";
    }
    
    $stmt = $conexion->prepare("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario_existente = $result->fetch_assoc();
        $errores[] = "Ya existe un usuario con DNI $dni: " . $usuario_existente['nombre_completo'];
    }
    
    if (empty($errores)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $conexion->begin_transaction();
        
        try {
            $stmt = $conexion->prepare("INSERT INTO usuarios (dni, nombres, apellidos, email, password, tipo_usuario, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, 'activo', NOW())");
            $stmt->bind_param("ssssss", $dni, $nombres, $apellidos, $email, $password_hash, $tipo_usuario);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar usuario");
            }
            
            $usuario_id = $conexion->insert_id;
            
            $curso_matriculado = null;
            if ($tipo_usuario == 'estudiante' && !empty($_POST['unidad_didactica_id'])) {
                $unidad_didactica_id = $_POST['unidad_didactica_id'];
                
                $stmt_curso = $conexion->prepare("SELECT nombre FROM unidades_didacticas WHERE id = ?");
                $stmt_curso->bind_param("i", $unidad_didactica_id);
                $stmt_curso->execute();
                $curso_result = $stmt_curso->get_result();
                if ($curso_row = $curso_result->fetch_assoc()) {
                    $curso_matriculado = $curso_row['nombre'];
                }
                
                $stmt_matricula = $conexion->prepare("INSERT INTO matriculas (estudiante_id, unidad_didactica_id, fecha_matricula, estado) VALUES (?, ?, NOW(), 'activo')");
                $stmt_matricula->bind_param("ii", $usuario_id, $unidad_didactica_id);
                
                if (!$stmt_matricula->execute()) {
                    throw new Exception("Error al matricular estudiante");
                }
            }
            
            $conexion->commit();
            
            error_log("Registro exitoso - DNI: $dni, Nombres: $nombres $apellidos, Tipo: $tipo_usuario");
            
            $ultimo_registro = [
                'dni' => $dni,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'tipo_usuario' => $tipo_usuario,
                'password' => $dni,
                'curso' => $curso_matriculado
            ];
            
            $registro_exitoso = true;
            $tipo_mensaje = "success";
            
            unset($_SESSION['dni_data']);
            unset($_SESSION['dni_consultado']);
            
            $dni = $nombres = $apellidos = $email = '';
            
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "Error al registrar: " . $e->getMessage();
            $tipo_mensaje = "error";
            error_log("Error en registro: " . $e->getMessage());
        }
    } else {
        $mensaje = "Por favor corrija los siguientes errores:<br>• " . implode("<br>• ", $errores);
        $tipo_mensaje = "error";
    }
}

// ==================== OBTENER DATOS ====================
$programas = $conexion->query("SELECT * FROM programas_estudio WHERE estado = 'activo' ORDER BY nombre");

$unidades_didacticas = $conexion->query("
    SELECT ud.*, pe.nombre as programa_nombre,
           (SELECT COUNT(*) FROM matriculas WHERE unidad_didactica_id = ud.id AND estado = 'activo') as estudiantes_matriculados
    FROM unidades_didacticas ud 
    JOIN programas_estudio pe ON ud.programa_id = pe.id 
    WHERE ud.estado = 'activo' 
    ORDER BY pe.nombre, ud.periodo_lectivo DESC, ud.nombre
");

$stats = $conexion->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tipo_usuario = 'estudiante' THEN 1 ELSE 0 END) as estudiantes,
        SUM(CASE WHEN tipo_usuario = 'docente' THEN 1 ELSE 0 END) as docentes
    FROM usuarios 
    WHERE estado = 'activo'
")->fetch_assoc();

$ultimos_registros = $conexion->query("
    SELECT dni, CONCAT(apellidos, ', ', nombres) as nombre_completo, tipo_usuario, fecha_creacion 
    FROM usuarios 
    ORDER BY fecha_creacion DESC 
    LIMIT 5
");

$form_data = [
    'dni' => $_SESSION['dni_consultado'] ?? '',
    'nombres' => ($_SESSION['dni_data']['nombres'] ?? ''),
    'apellidos' => ($_SESSION['dni_data']['apellidos'] ?? '')
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Registro Académico | IESTP "Alto Huallaga"</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Paleta Corporativa Institucional */
            --primary-900: #0a1628;
            --primary-800: #0f2744;
            --primary-700: #143860;
            --primary-600: #1a4a7c;
            --primary-500: #1e5c98;
            --primary-400: #2a7bc8;
            --primary-300: #4a9ae8;
            --primary-200: #7ab8f5;
            --primary-100: #b3d7fa;
            --primary-50: #e8f4fd;
            
            /* Acentos Dorados Institucionales */
            --accent-gold: #c9a227;
            --accent-gold-light: #e6c655;
            --accent-gold-dark: #a68520;
            
            /* Estados */
            --success-500: #10a37f;
            --success-100: #d4f5ec;
            --error-500: #dc2626;
            --error-100: #fce8e8;
            --warning-500: #f59e0b;
            --warning-100: #fef3cd;
            
            /* Neutrales */
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
            
            /* Sombras */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            /* Transiciones */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        /* Header Institucional */
        .institutional-header {
            background: linear-gradient(135deg, var(--primary-900) 0%, var(--primary-700) 50%, var(--primary-800) 100%);
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        
        .institutional-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(42, 123, 200, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(201, 162, 39, 0.1) 0%, transparent 40%),
                radial-gradient(ellipse at 60% 80%, rgba(74, 154, 232, 0.1) 0%, transparent 40%);
            pointer-events: none;
        }
        
        .header-top-bar {
            background: rgba(0, 0, 0, 0.2);
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
        }
        
        .header-top-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .header-top-content a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color var(--transition-fast);
        }
        
        .header-top-content a:hover {
            color: var(--accent-gold-light);
        }
        
        .header-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            z-index: 1;
        }
        
        .logo-container {
            flex-shrink: 0;
        }
        
        .logo-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-gold) 0%, var(--accent-gold-dark) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--white);
            box-shadow: 0 4px 20px rgba(201, 162, 39, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-text {
            flex: 1;
        }
        
        .institution-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--white);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .institution-subtitle {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
        }
        
        .header-badge {
            background: linear-gradient(135deg, var(--accent-gold) 0%, var(--accent-gold-dark) 100%);
            color: var(--primary-900);
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 15px rgba(201, 162, 39, 0.4);
        }
        
        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px 48px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
        }
        
        /* Cards Base */
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
            padding: 20px 24px;
            color: var(--white);
            position: relative;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 24px;
            right: 24px;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold));
            border-radius: 3px 3px 0 0;
        }
        
        .card-header h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header h2 i {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--white) 100%);
            border: 1px solid var(--primary-100);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-400), var(--primary-500));
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-200);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-600) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: var(--white);
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(30, 92, 152, 0.3);
        }
        
        .stat-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-700);
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Form Styles */
        .form-section {
            margin-bottom: 24px;
        }
        
        .form-section-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-700);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-100);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-section-title i {
            color: var(--primary-500);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }
        
        .form-group label .required {
            color: var(--error-500);
            font-weight: 700;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--gray-800);
            background: var(--gray-50);
            transition: all var(--transition-fast);
        }
        
        .form-control:hover {
            border-color: var(--gray-300);
            background: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-400);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(30, 92, 152, 0.1);
        }
        
        .form-control::placeholder {
            color: var(--gray-400);
        }
        
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 48px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        /* DNI Input Group */
        .dni-input-group {
            display: flex;
            gap: 12px;
        }
        
        .dni-input-group .form-control {
            flex: 1;
        }
        
        .btn-reniec {
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-fast);
            white-space: nowrap;
        }
        
        .btn-reniec:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-600) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-reniec:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .dni-feedback {
            margin-top: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dni-feedback.success { color: var(--success-500); }
        .dni-feedback.warning { color: var(--warning-500); }
        .dni-feedback.error { color: var(--error-500); }
        .dni-feedback.info { color: var(--primary-500); }
        
        /* Auto-filled state */
        .form-control.auto-filled {
            background: var(--success-100);
            border-color: var(--success-500);
        }
        
        .auto-fill-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--success-500);
            background: var(--success-100);
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
        }
        
        /* Matricula Section */
        .matricula-section {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--white) 100%);
            border: 2px solid var(--primary-200);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        
        .matricula-section.visible {
            display: block;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .matricula-section .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: var(--primary-700);
            font-weight: 700;
        }
        
        .matricula-section .section-header i {
            font-size: 1.25rem;
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all var(--transition-base);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(15, 39, 68, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert i {
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .alert.error {
            background: var(--error-100);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: var(--error-500);
        }
        
        .alert.success {
            background: var(--success-100);
            border: 1px solid rgba(16, 163, 127, 0.2);
            color: var(--success-500);
        }
        
        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-900) 100%);
            color: var(--white);
            padding: 20px;
            border-radius: 12px;
            margin-top: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .info-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--accent-gold), var(--accent-gold-light));
        }
        
        .info-box h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-gold-light);
        }
        
        .info-box ul {
            list-style: none;
            font-size: 0.85rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.85);
        }
        
        .info-box ul li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .info-box ul li i {
            color: var(--accent-gold);
            font-size: 0.7rem;
            margin-top: 6px;
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        /* Recent Registrations */
        .recent-list {
            list-style: none;
        }
        
        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--gray-100);
            transition: all var(--transition-fast);
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-item:hover {
            padding-left: 8px;
        }
        
        .recent-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .recent-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-100) 0%, var(--primary-200) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-600);
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .recent-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.9rem;
            line-height: 1.3;
        }
        
        .recent-type {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .recent-type.estudiante {
            background: var(--primary-100);
            color: var(--primary-700);
        }
        
        .recent-type.docente {
            background: var(--accent-gold-light);
            color: var(--primary-900);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        
        .quick-action-btn:hover {
            background: var(--primary-50);
            border-color: var(--primary-200);
            color: var(--primary-700);
            transform: translateX(4px);
        }
        
        .quick-action-btn i {
            width: 32px;
            height: 32px;
            background: var(--white);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }
        
        /* Success Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 22, 40, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .success-modal {
            background: var(--white);
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            box-shadow: var(--shadow-2xl);
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes modalPop {
            0% {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .success-modal-header {
            background: linear-gradient(135deg, var(--success-500) 0%, #059669 100%);
            padding: 32px 24px;
            text-align: center;
            color: var(--white);
            position: relative;
        }
        
        .success-icon-container {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .success-icon-container i {
            font-size: 2.5rem;
        }
        
        .success-modal-header h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .success-modal-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .success-modal-body {
            padding: 24px;
        }
        
        .credential-card {
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-900) 100%);
            border-radius: 16px;
            padding: 24px;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        
        .credential-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(201, 162, 39, 0.1) 0%, transparent 70%);
        }
        
        .credential-card h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--accent-gold-light);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .credential-row:last-child {
            margin-bottom: 0;
        }
        
        .credential-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .credential-value {
            font-family: 'Plus Jakarta Sans', monospace;
            font-weight: 700;
            color: var(--white);
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 6px;
        }
        
        .btn-close-modal {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }
        
        .btn-close-modal:hover {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-600) 100%);
            transform: translateY(-2px);
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 22, 40, 0.9);
            backdrop-filter: blur(8px);
            z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 24px;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .loader-ring {
            width: 64px;
            height: 64px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: var(--white);
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Footer */
        .footer {
            background: var(--primary-900);
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            padding: 24px;
            font-size: 0.85rem;
        }
        
        .footer a {
            color: var(--accent-gold-light);
            text-decoration: none;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .header-main {
                flex-direction: column;
                text-align: center;
                padding: 24px;
            }
            
            .header-badge {
                margin-top: 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .dni-input-group {
                flex-direction: column;
            }
            
            .btn-reniec {
                justify-content: center;
            }
            
            .header-top-content {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }
        
        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            animation: confettiFall 3.5s linear forwards;
            z-index: 10000;
        }
        
        @keyframes confettiFall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Inactivity Modal Styles */
        .inactivity-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 22, 40, 0.95);
            backdrop-filter: blur(10px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .inactivity-overlay.active {
            display: flex;
        }
        
        .inactivity-modal {
            background: var(--white);
            border-radius: 24px;
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            animation: modalShake 0.5s ease-out;
        }
        
        @keyframes modalShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .inactivity-header {
            background: linear-gradient(135deg, var(--error-500) 0%, #b91c1c 100%);
            padding: 32px 24px;
            text-align: center;
            color: var(--white);
            position: relative;
        }
        
        .inactivity-icon {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulseWarning 1s ease-in-out infinite;
        }
        
        @keyframes pulseWarning {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            50% { 
                transform: scale(1.05); 
                box-shadow: 0 0 0 20px rgba(255, 255, 255, 0);
            }
        }
        
        .inactivity-icon i {
            font-size: 2.5rem;
        }
        
        .inactivity-header h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .inactivity-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .inactivity-body {
            padding: 32px 24px;
            text-align: center;
        }
        
        .countdown-container {
            margin-bottom: 24px;
        }
        
        .countdown-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 12px;
            font-weight: 500;
        }
        
        .countdown-timer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .countdown-digit {
            background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-900) 100%);
            color: var(--white);
            font-family: 'Plus Jakarta Sans', monospace;
            font-size: 3rem;
            font-weight: 800;
            width: 80px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(15, 39, 68, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .countdown-digit::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .countdown-digit.warning {
            background: linear-gradient(135deg, var(--warning-500) 0%, #d97706 100%);
            animation: pulseDigit 0.5s ease-in-out infinite;
        }
        
        .countdown-digit.danger {
            background: linear-gradient(135deg, var(--error-500) 0%, #b91c1c 100%);
            animation: pulseDigit 0.3s ease-in-out infinite;
        }
        
        @keyframes pulseDigit {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .countdown-separator {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-700);
        }
        
        .countdown-unit {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 8px;
            font-weight: 600;
        }
        
        .inactivity-message {
            color: var(--gray-600);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .inactivity-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn-stay {
            flex: 1;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--success-500) 0%, #059669 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }
        
        .btn-stay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 163, 127, 0.4);
        }
        
        .btn-logout {
            padding: 16px 24px;
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }
        
        .btn-logout:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }
        
        /* Activity indicator */
        .activity-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--white);
            padding: 12px 20px;
            border-radius: 30px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
            z-index: 100;
            opacity: 0;
            transform: translateY(20px);
            transition: all var(--transition-base);
            border: 2px solid var(--gray-200);
        }
        
        .activity-indicator.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .activity-indicator.warning {
            border-color: var(--warning-500);
            background: var(--warning-100);
            color: var(--warning-500);
        }
        
        .activity-indicator i {
            font-size: 1rem;
        }
        
        .activity-progress {
            width: 60px;
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .activity-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--success-500), var(--primary-500));
            border-radius: 3px;
            transition: width 1s linear;
        }
        
        .activity-progress-bar.warning {
            background: linear-gradient(90deg, var(--warning-500), var(--error-500));
        }
    </style>
</head>
<body>
    <!-- Inactivity Warning Modal -->
    <div class="inactivity-overlay" id="inactivityModal">
        <div class="inactivity-modal">
            <div class="inactivity-header">
                <div class="inactivity-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h2>Sesión por Expirar</h2>
                <p>No hemos detectado actividad reciente</p>
            </div>
            <div class="inactivity-body">
                <div class="countdown-container">
                    <div class="countdown-label">La sesión se cerrará en:</div>
                    <div class="countdown-timer">
                        <div>
                            <div class="countdown-digit" id="countdownMinutes">00</div>
                            <div class="countdown-unit">Min</div>
                        </div>
                        <span class="countdown-separator">:</span>
                        <div>
                            <div class="countdown-digit" id="countdownSeconds">30</div>
                            <div class="countdown-unit">Seg</div>
                        </div>
                    </div>
                </div>
                <p class="inactivity-message">
                    Por su seguridad, la sesión se cerrará automáticamente debido a inactividad. 
                    Haga clic en "Continuar" para seguir trabajando.
                </p>
                <div class="inactivity-actions">
                    <button class="btn-stay" onclick="resetInactivityTimer()">
                        <i class="fas fa-check"></i> Continuar
                    </button>
                    <button class="btn-logout" onclick="logoutNow()">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity Indicator -->
    <div class="activity-indicator" id="activityIndicator">
        <i class="fas fa-user-clock"></i>
        <span>Sesión:</span>
        <span id="activityText" style="font-family: 'Plus Jakarta Sans', monospace; min-width: 45px;">02:00</span>
        <div class="activity-progress">
            <div class="activity-progress-bar" id="activityProgress" style="width: 100%;"></div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader-ring"></div>
        <div class="loading-text">Procesando registro...</div>
    </div>
    
    <!-- Success Modal -->
    <?php if ($registro_exitoso && $ultimo_registro): ?>
    <div class="modal-overlay" id="successModal">
        <div class="success-modal">
            <div class="success-modal-header">
                <div class="success-icon-container">
                    <i class="fas fa-check"></i>
                </div>
                <h2>¡Registro Exitoso!</h2>
                <p>El usuario ha sido registrado correctamente en el sistema</p>
            </div>
            <div class="success-modal-body">
                <div class="credential-card">
                    <h4><i class="fas fa-key"></i> Credenciales de Acceso</h4>
                    <div class="credential-row">
                        <span class="credential-label"><i class="fas fa-user"></i> Usuario (DNI)</span>
                        <span class="credential-value"><?php echo $ultimo_registro['dni']; ?></span>
                    </div>
                    <div class="credential-row">
                        <span class="credential-label"><i class="fas fa-lock"></i> Contraseña</span>
                        <span class="credential-value"><?php echo $ultimo_registro['password']; ?></span>
                    </div>
                    <div class="credential-row">
                        <span class="credential-label"><i class="fas fa-id-card"></i> Nombre</span>
                        <span class="credential-value"><?php echo $ultimo_registro['apellidos'] . ', ' . $ultimo_registro['nombres']; ?></span>
                    </div>
                    <div class="credential-row">
                        <span class="credential-label"><i class="fas fa-user-tag"></i> Tipo</span>
                        <span class="credential-value"><?php echo ucfirst($ultimo_registro['tipo_usuario']); ?></span>
                    </div>
                    <?php if ($ultimo_registro['curso']): ?>
                    <div class="credential-row">
                        <span class="credential-label"><i class="fas fa-book"></i> Curso</span>
                        <span class="credential-value"><?php echo $ultimo_registro['curso']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <button class="btn-close-modal" onclick="closeSuccessModal()">
                    <i class="fas fa-thumbs-up"></i> Entendido
                </button>
            </div>
        </div>
    </div>
    <script>
        // Confetti effect
        for(let i = 0; i < 50; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.background = ['#1e5c98', '#2a7bc8', '#c9a227', '#e6c655', '#10a37f'][Math.floor(Math.random() * 5)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 3500);
            }, i * 50);
        }
    </script>
    <?php endif; ?>
    
    <!-- Header Institucional -->
    <header class="institutional-header">
        <div class="header-top-bar">
            <div class="header-top-content">
                <span><i class="fas fa-map-marker-alt"></i> Tocache, San Martín - Perú</span>
                <span><i class="fas fa-phone"></i> (042) 123-456 | <a href="mailto:info@iestpaltohuallaga.edu.pe">info@iestpaltohuallaga.edu.pe</a></span>
            </div>
        </div>
        <div class="header-main">
            <div class="logo-container">
                <div class="logo-placeholder">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
            <div class="header-text">
                <h1 class="institution-name">IESTP "Alto Huallaga"</h1>
                <p class="institution-subtitle">Instituto de Educación Superior Tecnológico Público</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-shield-alt"></i> Sistema Académico
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main-container">
        <!-- Form Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Registro de Usuarios</h2>
            </div>
            <div class="card-body">
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-value"><?php echo $stats['estudiantes']; ?></div>
                        <div class="stat-label">Estudiantes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-value"><?php echo $stats['docentes']; ?></div>
                        <div class="stat-label">Docentes</div>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($mensaje && !$registro_exitoso): ?>
                <div class="alert <?php echo $tipo_mensaje; ?>">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <div><?php echo $mensaje; ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form method="POST" action="" id="registroForm" onsubmit="return validarFormulario()">
                    <input type="hidden" name="registrar" value="1">
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user-cog"></i> Tipo de Usuario
                        </div>
                        
                        <div class="form-group">
                            <label>Seleccione el tipo de usuario <span class="required">*</span></label>
                            <select name="tipo_usuario" id="tipo_usuario" class="form-control" required onchange="toggleMatriculaOptions()">
                                <option value="">-- Seleccione una opción --</option>
                                <option value="estudiante">Estudiante</option>
                                <option value="docente">Docente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-id-card"></i> Datos de Identificación
                        </div>
                        
                        <div class="form-group">
                            <label>Documento Nacional de Identidad (DNI) <span class="required">*</span></label>
                            <div class="dni-input-group">
                                <input type="text" 
                                       name="dni" 
                                       id="dni"
                                       class="form-control"
                                       maxlength="8" 
                                       pattern="[0-9]{8}" 
                                       placeholder="Ingrese 8 dígitos"
                                       value="<?php echo htmlspecialchars($form_data['dni']); ?>"
                                       required
                                       autocomplete="off"
                                       oninput="validateDNI(this)">
                                <button type="button" id="consultarBtn" class="btn-reniec" onclick="consultarDNI()" disabled>
                                    <i class="fas fa-search"></i> Consultar RENIEC
                                </button>
                            </div>
                            <div id="dni-feedback" class="dni-feedback"></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i> Datos Personales
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    Nombres <span class="required">*</span>
                                    <span id="nombres-badge" class="auto-fill-badge" style="display: none;">
                                        <i class="fas fa-robot"></i> Auto-completado
                                    </span>
                                </label>
                                <input type="text" 
                                       name="nombres" 
                                       id="nombres"
                                       class="form-control <?php echo !empty($form_data['nombres']) ? 'auto-filled' : ''; ?>"
                                       placeholder="Ej: Juan Carlos"
                                       value="<?php echo htmlspecialchars($form_data['nombres']); ?>"
                                       required
                                       autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    Apellidos <span class="required">*</span>
                                    <span id="apellidos-badge" class="auto-fill-badge" style="display: none;">
                                        <i class="fas fa-robot"></i> Auto-completado
                                    </span>
                                </label>
                                <input type="text" 
                                       name="apellidos" 
                                       id="apellidos"
                                       class="form-control <?php echo !empty($form_data['apellidos']) ? 'auto-filled' : ''; ?>"
                                       placeholder="Ej: García López"
                                       value="<?php echo htmlspecialchars($form_data['apellidos']); ?>"
                                       required
                                       autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Correo Electrónico (Opcional)</label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   class="form-control"
                                   placeholder="correo@ejemplo.com"
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <!-- Matricula Section (for students) -->
                    <div class="matricula-section" id="matricula-options">
                        <div class="section-header">
                            <i class="fas fa-book-open"></i>
                            <span>Matrícula en Curso (Opcional)</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Programa de Estudio</label>
                            <select name="programa_id" id="programa_id" class="form-control" onchange="filtrarUnidades()">
                                <option value="">-- Seleccione un programa --</option>
                                <?php 
                                if ($programas && $programas->num_rows > 0) {
                                    $programas->data_seek(0);
                                    while ($programa = $programas->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $programa['id']; ?>">
                                        <?php echo htmlspecialchars($programa['nombre']); ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Unidad Didáctica</label>
                            <select name="unidad_didactica_id" id="unidad_didactica_id" class="form-control">
                                <option value="">-- Primero seleccione un programa --</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Registrar Usuario
                    </button>
                </form>
                
                <!-- Info Box -->
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Información Importante</h4>
                    <ul>
                        <li><i class="fas fa-circle"></i> La contraseña será automáticamente el número de DNI</li>
                        <li><i class="fas fa-circle"></i> Use "Consultar RENIEC" para auto-completar nombres y apellidos</li>
                        <li><i class="fas fa-circle"></i> Los estudiantes pueden matricularse en cursos al momento del registro</li>
                        <li><i class="fas fa-circle"></i> Los campos marcados con (*) son obligatorios</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Recent Registrations -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Últimos Registros</h2>
                </div>
                <div class="card-body">
                    <?php if ($ultimos_registros && $ultimos_registros->num_rows > 0): ?>
                    <ul class="recent-list">
                        <?php while ($registro = $ultimos_registros->fetch_assoc()): ?>
                        <li class="recent-item">
                            <div class="recent-info">
                                <div class="recent-avatar">
                                    <?php echo strtoupper(substr($registro['nombre_completo'], 0, 2)); ?>
                                </div>
                                <span class="recent-name"><?php echo htmlspecialchars($registro['nombre_completo']); ?></span>
                            </div>
                            <span class="recent-type <?php echo $registro['tipo_usuario']; ?>">
                                <?php echo $registro['tipo_usuario'] == 'estudiante' ? 'Est.' : 'Doc.'; ?>
                            </span>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                    <?php else: ?>
                    <p style="text-align: center; color: var(--gray-500); padding: 20px;">
                        No hay registros recientes
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="#" class="quick-action-btn">
                            <i class="fas fa-list"></i>
                            <span>Ver Todos los Usuarios</span>
                        </a>
                        <a href="#" class="quick-action-btn">
                            <i class="fas fa-file-export"></i>
                            <span>Exportar Registros</span>
                        </a>
                        <a href="#" class="quick-action-btn">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                        <button type="button" class="quick-action-btn" onclick="testInactivityAlert()" style="width: 100%; text-align: left; cursor: pointer;">
                            <i class="fas fa-bell" style="color: var(--warning-500);"></i>
                            <span>Probar Alerta Inactividad</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> IESTP "Alto Huallaga" - Tocache, San Martín | 
        <a href="#">Términos de Uso</a> | <a href="#">Política de Privacidad</a></p>
    </footer>
    
    <script>
        console.log('Sistema de Registro Académico - IESTP Alto Huallaga');
        console.log('DNIs de prueba: 46027897, 71882580, 12345678, 87654321');
        
        // Datos de unidades didácticas
        const unidadesDidacticas = [
            <?php 
            if ($unidades_didacticas && $unidades_didacticas->num_rows > 0) {
                $unidades_didacticas->data_seek(0);
                while ($ud = $unidades_didacticas->fetch_assoc()): 
            ?>
            {
                id: <?php echo $ud['id']; ?>,
                nombre: "<?php echo addslashes($ud['nombre']); ?>",
                programa_id: <?php echo $ud['programa_id']; ?>,
                periodo: "<?php echo $ud['periodo_academico']; ?> - <?php echo $ud['periodo_lectivo']; ?>",
                matriculados: <?php echo $ud['estudiantes_matriculados']; ?>
            },
            <?php endwhile; } ?>
        ];
        
        function toggleMatriculaOptions() {
            const tipoUsuario = document.getElementById('tipo_usuario').value;
            const matriculaOptions = document.getElementById('matricula-options');
            
            if (tipoUsuario === 'estudiante') {
                matriculaOptions.classList.add('visible');
            } else {
                matriculaOptions.classList.remove('visible');
                document.getElementById('programa_id').value = '';
                document.getElementById('unidad_didactica_id').value = '';
            }
        }
        
        function filtrarUnidades() {
            const programaId = document.getElementById('programa_id').value;
            const selectUnidades = document.getElementById('unidad_didactica_id');
            
            selectUnidades.innerHTML = '<option value="">-- Seleccione una unidad didáctica --</option>';
            
            if (programaId) {
                const unidadesFiltradas = unidadesDidacticas.filter(ud => ud.programa_id == programaId);
                
                unidadesFiltradas.forEach(ud => {
                    const option = document.createElement('option');
                    option.value = ud.id;
                    option.textContent = `${ud.nombre} (${ud.periodo}) - ${ud.matriculados} est.`;
                    selectUnidades.appendChild(option);
                });
                
                if (unidadesFiltradas.length === 0) {
                    selectUnidades.innerHTML = '<option value="">No hay unidades disponibles</option>';
                }
            }
        }
        
        function validateDNI(input) {
            const dni = input.value.replace(/[^0-9]/g, '').slice(0, 8);
            input.value = dni;
            
            const feedback = document.getElementById('dni-feedback');
            const consultarBtn = document.getElementById('consultarBtn');
            
            if (dni.length === 8) {
                feedback.innerHTML = '<i class="fas fa-check-circle"></i> DNI válido - Puede consultar RENIEC';
                feedback.className = 'dni-feedback success';
                consultarBtn.disabled = false;
                input.style.borderColor = 'var(--success-500)';
            } else if (dni.length > 0) {
                feedback.innerHTML = `<i class="fas fa-info-circle"></i> Faltan ${8 - dni.length} dígitos`;
                feedback.className = 'dni-feedback warning';
                consultarBtn.disabled = true;
                input.style.borderColor = 'var(--warning-500)';
            } else {
                feedback.innerHTML = '';
                consultarBtn.disabled = true;
                input.style.borderColor = '';
            }
        }
        
        async function consultarDNI() {
            const dni = document.getElementById('dni').value;
            const consultarBtn = document.getElementById('consultarBtn');
            const feedback = document.getElementById('dni-feedback');
            const nombresInput = document.getElementById('nombres');
            const apellidosInput = document.getElementById('apellidos');
            const nombresBadge = document.getElementById('nombres-badge');
            const apellidosBadge = document.getElementById('apellidos-badge');
            
            if (dni.length !== 8) {
                alert('Ingrese un DNI válido de 8 dígitos');
                return;
            }
            
            consultarBtn.disabled = true;
            consultarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';
            feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando base de datos RENIEC...';
            feedback.className = 'dni-feedback info';
            
            nombresInput.disabled = true;
            apellidosInput.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('consultar_dni', '1');
                formData.append('dni', dni);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('Respuesta consulta DNI:', data);
                
                if (data.success) {
                    nombresInput.value = data.nombres;
                    apellidosInput.value = data.apellidos;
                    
                    nombresInput.classList.add('auto-filled');
                    apellidosInput.classList.add('auto-filled');
                    nombresBadge.style.display = 'inline-flex';
                    apellidosBadge.style.display = 'inline-flex';
                    
                    feedback.innerHTML = `<i class="fas fa-check-circle"></i> Datos encontrados correctamente`;
                    feedback.className = 'dni-feedback success';
                    
                } else {
                    feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> DNI no encontrado. Complete manualmente.';
                    feedback.className = 'dni-feedback warning';
                    
                    if (nombresInput.classList.contains('auto-filled')) {
                        nombresInput.value = '';
                        apellidosInput.value = '';
                        nombresInput.classList.remove('auto-filled');
                        apellidosInput.classList.remove('auto-filled');
                        nombresBadge.style.display = 'none';
                        apellidosBadge.style.display = 'none';
                    }
                    
                    nombresInput.focus();
                }
                
            } catch (error) {
                console.error('Error al consultar DNI:', error);
                feedback.innerHTML = '<i class="fas fa-times-circle"></i> Error de conexión. Complete manualmente.';
                feedback.className = 'dni-feedback error';
                      
            } finally {
                consultarBtn.disabled = false;
                consultarBtn.innerHTML = '<i class="fas fa-search"></i> Consultar RENIEC';
                nombresInput.disabled = false;
                apellidosInput.disabled = false;
            }
        }
        
        function validarFormulario() {
            const dni = document.getElementById('dni').value;
            const nombres = document.getElementById('nombres').value;
            const apellidos = document.getElementById('apellidos').value;
            const tipoUsuario = document.getElementById('tipo_usuario').value;
            
            if (!/^[0-9]{8}$/.test(dni)) {
                alert('El DNI debe tener exactamente 8 dígitos numéricos');
                document.getElementById('dni').focus();
                return false;
            }
            
            if (!tipoUsuario) {
                alert('Debe seleccionar un tipo de usuario');
                document.getElementById('tipo_usuario').focus();
                return false;
            }
            
            if (!nombres.trim()) {
                alert('Los nombres son obligatorios');
                document.getElementById('nombres').focus();
                return false;
            }
            
            if (!apellidos.trim()) {
                alert('Los apellidos son obligatorios');
                document.getElementById('apellidos').focus();
                return false;
            }
            
            document.getElementById('loadingOverlay').classList.add('active');
            return true;
        }
        
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.style.animation = 'fadeIn 0.3s ease-out reverse';
                setTimeout(() => {
                    modal.remove();
                    document.getElementById('registroForm').reset();
                    document.getElementById('dni-feedback').innerHTML = '';
                    document.getElementById('consultarBtn').disabled = true;
                    document.getElementById('matricula-options').classList.remove('visible');
                    document.getElementById('nombres-badge').style.display = 'none';
                    document.getElementById('apellidos-badge').style.display = 'none';
                    document.getElementById('nombres').classList.remove('auto-filled');
                    document.getElementById('apellidos').classList.remove('auto-filled');
                }, 300);
            }
        }
        
        // Auto-cerrar modal después de 20 segundos
        setTimeout(() => {
            if (document.getElementById('successModal')) {
                closeSuccessModal();
            }
        }, 20000);
        
        // Remover clase auto-filled al editar manualmente
        document.getElementById('nombres').addEventListener('input', function() {
            this.classList.remove('auto-filled');
            document.getElementById('nombres-badge').style.display = 'none';
        });
        
        document.getElementById('apellidos').addEventListener('input', function() {
            this.classList.remove('auto-filled');
            document.getElementById('apellidos-badge').style.display = 'none';
        });
        
        // Inicializar validación DNI si hay valor
        const dniInput = document.getElementById('dni');
        if (dniInput.value) {
            validateDNI(dniInput);
        }
        
        // ==================== SISTEMA DE INACTIVIDAD ====================
        (function() {
            'use strict';
            
            // CONFIGURACIÓN - Cambiar a 2 * 60 * 1000 para 2 minutos en producción
            const INACTIVITY_TIMEOUT = 2 * 60 * 1000; // 2 minutos en milisegundos
            const WARNING_COUNTDOWN = 30; // 30 segundos de cuenta regresiva
            const REDIRECT_URL = 'https://arqos.nexustelecom.pe/login.php';
            
            let inactivityTimer = null;
            let countdownTimer = null;
            let countdownInterval = null;
            let countdownValue = WARNING_COUNTDOWN;
            let lastActivityTime = Date.now();
            let isWarningShown = false;
            let isInitialized = false;
            
            // Elementos del DOM (se asignarán después de que cargue)
            let inactivityModal, countdownMinutes, countdownSeconds;
            let activityIndicator, activityText, activityProgress;
            
            // Función para formatear tiempo
            function formatTime(seconds) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return {
                    minutes: mins.toString().padStart(2, '0'),
                    seconds: secs.toString().padStart(2, '0')
                };
            }
            
            // Función para actualizar el indicador de actividad
            function updateActivityIndicator() {
                if (!activityProgress || !activityIndicator || !activityText) return;
                
                const elapsed = Date.now() - lastActivityTime;
                const remaining = INACTIVITY_TIMEOUT - elapsed;
                const percentage = Math.max(0, (remaining / INACTIVITY_TIMEOUT) * 100);
                
                activityProgress.style.width = percentage + '%';
                
                // Calcular tiempo restante en segundos
                const remainingSecs = Math.ceil(remaining / 1000);
                const time = formatTime(remainingSecs);
                activityText.textContent = time.minutes + ':' + time.seconds;
                
                if (percentage < 30) {
                    activityIndicator.classList.add('warning');
                    activityProgress.classList.add('warning');
                } else {
                    activityIndicator.classList.remove('warning');
                    activityProgress.classList.remove('warning');
                }
            }
            
            // Función para mostrar el modal de advertencia
            function showInactivityWarning() {
                console.log('🚨 Mostrando alerta de inactividad');
                
                if (isWarningShown) {
                    console.log('Alerta ya visible, ignorando');
                    return;
                }
                
                isWarningShown = true;
                countdownValue = WARNING_COUNTDOWN;
                
                if (inactivityModal) {
                    inactivityModal.classList.add('active');
                    inactivityModal.style.display = 'flex';
                    console.log('Modal activado');
                } else {
                    console.error('Modal no encontrado!');
                    return;
                }
            activityIndicator.classList.remove('visible');
                
                // Actualizar display inicial
                updateCountdownDisplay();
                
                // Iniciar cuenta regresiva
                countdownInterval = setInterval(function() {
                    countdownValue--;
                    console.log('Cuenta regresiva: ' + countdownValue);
                    updateCountdownDisplay();
                    
                    if (countdownValue <= 0) {
                        clearInterval(countdownInterval);
                        logoutNow();
                    }
                }, 1000);
                
                // Reproducir sonido de alerta
                playAlertSound();
            }
            
            // Función para actualizar el display de la cuenta regresiva
            function updateCountdownDisplay() {
                if (!countdownMinutes || !countdownSeconds) return;
                
                const time = formatTime(countdownValue);
                countdownMinutes.textContent = time.minutes;
                countdownSeconds.textContent = time.seconds;
                
                // Cambiar color según el tiempo restante
                const digits = [countdownMinutes, countdownSeconds];
                
                digits.forEach(function(digit) {
                    digit.classList.remove('warning', 'danger');
                    
                    if (countdownValue <= 10) {
                        digit.classList.add('danger');
                    } else if (countdownValue <= 20) {
                        digit.classList.add('warning');
                    }
                });
            }
            
            // Función para reproducir sonido de alerta
            function playAlertSound() {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.value = 0.3;
                    
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.2);
                    
                    setTimeout(function() {
                        const osc2 = audioContext.createOscillator();
                        const gain2 = audioContext.createGain();
                        osc2.connect(gain2);
                        gain2.connect(audioContext.destination);
                        osc2.frequency.value = 1000;
                        osc2.type = 'sine';
                        gain2.gain.value = 0.3;
                        osc2.start();
                        osc2.stop(audioContext.currentTime + 0.2);
                    }, 250);
                } catch (e) {
                    console.log('Audio no disponible');
                }
            }
            
            // Función para resetear el temporizador
            window.resetInactivityTimer = function() {
                console.log('✅ Timer reseteado por el usuario');
                
                // Ocultar modal
                isWarningShown = false;
                if (inactivityModal) {
                    inactivityModal.classList.remove('active');
                    inactivityModal.style.display = 'none';
                }
                
                // Detener cuenta regresiva
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                }
                
                // Resetear temporizador de inactividad
                lastActivityTime = Date.now();
                clearTimeout(inactivityTimer);
                startInactivityTimer();
                
                // Mostrar indicador de actividad
                if (activityIndicator) {
                    activityIndicator.classList.add('visible');
                }
                updateActivityIndicator();
            };
            
            // Función para cerrar sesión inmediatamente
            window.logoutNow = function() {
                console.log('🚪 Cerrando sesión...');
                
                // Detener todos los temporizadores
                clearTimeout(inactivityTimer);
                clearInterval(countdownInterval);
                
                // Mostrar mensaje de redirección
                const modalBody = document.querySelector('.inactivity-body');
                if (modalBody) {
                    modalBody.innerHTML = 
                        '<div style="text-align: center; padding: 40px 20px;">' +
                            '<div class="loader-ring" style="margin: 0 auto 20px;"></div>' +
                            '<p style="color: var(--gray-600); font-size: 1.1rem; font-weight: 500;">' +
                                'Redirigiendo al inicio de sesión...' +
                            '</p>' +
                        '</div>';
                }
                
                // Redirigir después de un breve delay
                setTimeout(function() {
                    window.location.href = REDIRECT_URL;
                }, 1500);
            };
            
            // Función para iniciar el temporizador de inactividad
            function startInactivityTimer() {
                console.log('⏱️ Timer iniciado - Alerta en ' + (INACTIVITY_TIMEOUT / 1000) + ' segundos');
                
                clearTimeout(inactivityTimer);
                
                inactivityTimer = setTimeout(function() {
                    console.log('⏰ Tiempo agotado! Mostrando alerta...');
                    showInactivityWarning();
                }, INACTIVITY_TIMEOUT);
            }
            
            // Función para registrar actividad del usuario
            function registerActivity(eventType) {
                if (isWarningShown) return; // No registrar si el modal está visible
                
                lastActivityTime = Date.now();
                
                // Resetear temporizador
                clearTimeout(inactivityTimer);
                startInactivityTimer();
            }
            
            // Eventos que detectan actividad del usuario (SIN mousemove para evitar resets constantes)
            const activityEvents = [
                'mousedown',
                'keydown',
                'scroll',
                'touchstart',
                'click',
                'wheel'
            ];
            
            // Throttle para no ejecutar en cada evento
            let activityThrottle = null;
            function throttledActivityHandler(e) {
                if (activityThrottle) return;
                
                activityThrottle = setTimeout(function() {
                    registerActivity(e.type);
                    activityThrottle = null;
                }, 500);
            }
            
            // Función para probar la alerta (expuesta globalmente)
            window.testInactivityAlert = function() {
                console.log('🧪 Probando alerta de inactividad...');
                isWarningShown = false;
                showInactivityWarning();
            };
            
            // Iniciar el sistema
            function initInactivitySystem() {
                if (isInitialized) return;
                isInitialized = true;
                
                console.log('🚀 Sistema de inactividad iniciado');
                console.log('⏱️ Tiempo de inactividad: ' + (INACTIVITY_TIMEOUT / 1000) + ' segundos');
                console.log('⏳ Tiempo de advertencia: ' + WARNING_COUNTDOWN + ' segundos');
                console.log('💡 Para probar: ejecuta testInactivityAlert() en la consola');
                
                // Obtener elementos del DOM
                inactivityModal = document.getElementById('inactivityModal');
                countdownMinutes = document.getElementById('countdownMinutes');
                countdownSeconds = document.getElementById('countdownSeconds');
                activityIndicator = document.getElementById('activityIndicator');
                activityText = document.getElementById('activityText');
                activityProgress = document.getElementById('activityProgress');
                
                if (!inactivityModal) {
                    console.error('❌ ERROR: No se encontró el modal de inactividad');
                    return;
                }
                
                console.log('✅ Elementos del DOM encontrados');
                
                // Agregar event listeners
                activityEvents.forEach(function(event) {
                    document.addEventListener(event, throttledActivityHandler, { passive: true });
                });
                
                // Iniciar temporizador
                startInactivityTimer();
                
                // Mostrar indicador después de un momento
                setTimeout(function() {
                    if (activityIndicator) {
                        activityIndicator.classList.add('visible');
                    }
                }, 1000);
                
                // Actualizar indicador cada segundo
                setInterval(updateActivityIndicator, 1000);
            }
            
            // Manejar visibilidad de la página
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible' && isInitialized) {
                    const elapsed = Date.now() - lastActivityTime;
                    if (elapsed >= INACTIVITY_TIMEOUT && !isWarningShown) {
                        showInactivityWarning();
                    }
                }
            });
            
            // Iniciar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initInactivitySystem);
            } else {
                initInactivitySystem();
            }
            
        })(); // Fin del IIFE
    </script>
</body>
</html>