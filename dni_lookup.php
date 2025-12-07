<?php
/**
 * dni_lookup.php - Consulta DNI usando API oficial de Decolecta
 * Documentación: https://decolecta.gitbook.io/docs/servicios/integrations-2
 * Versión: 3.0
 * Fecha: Septiembre 2025
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configuración de zona horaria
date_default_timezone_set('America/Lima');

// Manejo de preflight OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== CONFIGURACIÓN API DECOLECTA ====================
class DecolectaConfig {
    // REEMPLAZA CON TU TOKEN REAL DE DECOLECTA
    const API_TOKEN = 'sk_10367.GU4w1AirWvIqPecMayNcvlSK3RbE5H4v';
    
    // API oficial de Decolecta según documentación
    const RENIEC_API_URL = 'https://api.decolecta.com/v1/reniec/dni';
    
    // Configuración
    const TIMEOUT_SECONDS = 20;
    const ENABLE_LOGGING = true;
    const LOG_FILE = 'decolecta_dni.log';
}

// ==================== FUNCIONES DE UTILIDAD ====================
function logDecolecta($message, $data = null) {
    if (!DecolectaConfig::ENABLE_LOGGING) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[$timestamp] [$ip] $message";
    
    if ($data) {
        $logEntry .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    $logEntry .= PHP_EOL;
    @file_put_contents(DecolectaConfig::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function validateDNI($dni) {
    // Limpiar DNI
    $dni = preg_replace('/[^0-9]/', '', $dni);
    
    // Validar longitud
    if (strlen($dni) !== 8) {
        return false;
    }
    
    // Validar que no sea secuencial obvio
    $invalidDnis = [
        '11111111', '22222222', '33333333', '44444444', 
        '55555555', '66666666', '77777777', '88888888', 
        '99999999', '00000000', '12345678', '87654321'
    ];
    
    return !in_array($dni, $invalidDnis);
}

// ==================== CONSULTA API OFICIAL DECOLECTA ====================
function consultarDecolectaOficial($dni) {
    logDecolecta("Iniciando consulta Decolecta oficial", ['dni' => $dni]);
    
    // Verificar token
    if (DecolectaConfig::API_TOKEN === 'sk_10367.GU4w1AirWvIqPecMayNcvlSK3RbE5H4v') {
        logDecolecta("ERROR: Token no configurado");
        return [
            'success' => false,
            'error' => 'Token de API no configurado',
            'code' => 'CONFIG_ERROR'
        ];
    }
    
    // Construir URL según documentación oficial
    $url = DecolectaConfig::RENIEC_API_URL . '?numero=' . $dni;
    
    logDecolecta("URL construida", ['url' => $url]);
    
    // Headers según documentación
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DecolectaConfig::API_TOKEN,
        'User-Agent: Sistema-Registro-Academico/3.0'
    ];
    
    // Configurar contexto HTTP
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => DecolectaConfig::TIMEOUT_SECONDS,
            'ignore_errors' => true
        ]
    ]);
    
    // Realizar petición
    $response = @file_get_contents($url, false, $context);
    
    // Obtener código de respuesta HTTP
    $httpCode = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = intval($matches[1]);
                break;
            }
        }
    }
    
    logDecolecta("Respuesta HTTP", [
        'http_code' => $httpCode,
        'response_length' => $response ? strlen($response) : 0,
        'success' => $response !== false
    ]);
    
    // Verificar si hubo error de conexión
    if ($response === false) {
        logDecolecta("Error de conexión con Decolecta");
        return [
            'success' => false,
            'error' => 'Error de conexión con servidor Decolecta',
            'code' => 'CONNECTION_ERROR',
            'http_code' => $httpCode
        ];
    }
    
    // Decodificar respuesta JSON
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logDecolecta("Error JSON", [
            'json_error' => json_last_error_msg(),
            'response_preview' => substr($response, 0, 200)
        ]);
        return [
            'success' => false,
            'error' => 'Respuesta inválida del servidor',
            'code' => 'JSON_ERROR',
            'http_code' => $httpCode
        ];
    }
    
    // Procesar respuesta exitosa según documentación
    if ($httpCode === 200 && $data) {
        // Verificar estructura de respuesta según documentación
        if (isset($data['first_name']) && isset($data['first_last_name'])) {
            
            // Limpiar y formatear datos
            $nombres = strtoupper(trim($data['first_name']));
            $apellidoPaterno = strtoupper(trim($data['first_last_name']));
            $apellidoMaterno = strtoupper(trim($data['second_last_name'] ?? ''));
            $apellidos = trim($apellidoPaterno . ' ' . $apellidoMaterno);
            
            $resultado = [
                'success' => true,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'nombre_completo' => trim($nombres . ' ' . $apellidos),
                'service' => 'decolecta_reniec',
                'timestamp' => date('Y-m-d H:i:s'),
                'dni' => $data['document_number'] ?? $dni
            ];
            
            logDecolecta("Consulta exitosa", $resultado);
            return $resultado;
        }
    }
    
    // Manejar errores específicos
    $errorMessage = 'DNI no encontrado';
    $errorCode = 'NOT_FOUND';
    
    if ($httpCode === 401) {
        $errorMessage = 'Token de API inválido o expirado';
        $errorCode = 'AUTH_ERROR';
    } elseif ($httpCode === 403) {
        $errorMessage = 'Acceso denegado - Verifique permisos del token';
        $errorCode = 'FORBIDDEN';
    } elseif ($httpCode === 429) {
        $errorMessage = 'Límite de consultas excedido';
        $errorCode = 'RATE_LIMIT';
    } elseif ($httpCode >= 500) {
        $errorMessage = 'Error del servidor Decolecta';
        $errorCode = 'SERVER_ERROR';
    } elseif (isset($data['error'])) {
        $errorMessage = $data['error'];
        $errorCode = 'API_ERROR';
    }
    
    logDecolecta("Error en consulta", [
        'error' => $errorMessage,
        'code' => $errorCode,
        'http_code' => $httpCode,
        'response_data' => $data
    ]);
    
    return [
        'success' => false,
        'error' => $errorMessage,
        'code' => $errorCode,
        'http_code' => $httpCode
    ];
}

// ==================== APIS DE RESPALDO ====================
function consultarAPIRespaldo($dni) {
    logDecolecta("Intentando API de respaldo", ['dni' => $dni]);
    
    // API alternativa confiable
    $tokenRespaldo = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6InRlc3RAdGVzdC5jb20ifQ.TSLLVm2Fsd5jpVBJZsVJfbLNLcDblrcE_2QlkmYoZAE';
    $urlRespaldo = "https://dniruc.apisperu.com/api/v1/dni/" . $dni . "?token=" . $tokenRespaldo;
    
    $contextRespaldo = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => 'User-Agent: Sistema-Registro-Academico/3.0'
        ]
    ]);
    
    $responseRespaldo = @file_get_contents($urlRespaldo, false, $contextRespaldo);
    
    if ($responseRespaldo !== false) {
        $dataRespaldo = json_decode($responseRespaldo, true);
        if (isset($dataRespaldo['nombres']) && !empty($dataRespaldo['nombres'])) {
            logDecolecta("Éxito en API respaldo", ['dni' => $dni]);
            return [
                'success' => true,
                'nombres' => strtoupper(trim($dataRespaldo['nombres'])),
                'apellidos' => strtoupper(trim($dataRespaldo['apellidoPaterno'] . ' ' . $dataRespaldo['apellidoMaterno'])),
                'service' => 'api_respaldo'
            ];
        }
    }
    
    return ['success' => false, 'error' => 'No encontrado en API de respaldo'];
}

// ==================== DATOS DE PRUEBA ====================
function obtenerDatosPrueba($dni) {
    $datosPrueba = [
        '71882580' => ['nombres' => 'CARLOS MIGUEL', 'apellidos' => 'RODRIGUEZ GARCIA'],
        '12345678' => ['nombres' => 'JUAN CARLOS', 'apellidos' => 'PEREZ LOPEZ'],
        '87654321' => ['nombres' => 'MARIA ELENA', 'apellidos' => 'SANCHEZ TORRES'],
        '11111111' => ['nombres' => 'ANA SOFIA', 'apellidos' => 'MENDOZA VARGAS'],
        '22222222' => ['nombres' => 'PEDRO ANTONIO', 'apellidos' => 'FLORES CASTILLO'],
        '33333333' => ['nombres' => 'LUCIA BEATRIZ', 'apellidos' => 'GUTIERREZ RAMOS'],
        '44444444' => ['nombres' => 'MIGUEL ANGEL', 'apellidos' => 'TORRES SILVA'],
        '55555555' => ['nombres' => 'CARMEN ROSA', 'apellidos' => 'VEGA MORALES'],
        '66666666' => ['nombres' => 'JOSE LUIS', 'apellidos' => 'HERRERA CASTRO'],
        '77777777' => ['nombres' => 'PATRICIA ELENA', 'apellidos' => 'ROJAS VARGAS'],
        '46027897' => ['nombres' => 'ROXANA KARINA', 'apellidos' => 'DELGADO CUELLAR'] // Ejemplo de la documentación
    ];
    
    if (isset($datosPrueba[$dni])) {
        logDecolecta("DNI encontrado en datos de prueba", ['dni' => $dni]);
        return [
            'success' => true,
            'nombres' => $datosPrueba[$dni]['nombres'],
            'apellidos' => $datosPrueba[$dni]['apellidos'],
            'service' => 'datos_prueba'
        ];
    }
    
    return ['success' => false, 'error' => 'DNI no encontrado en datos de prueba'];
}

// ==================== PROCESAMIENTO PRINCIPAL ====================

// Obtener DNI del request
$dni = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dni = $_GET['dni'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $dni = $input['dni'] ?? '';
}

// Log inicial
logDecolecta("Nueva consulta DNI", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'dni' => $dni,
    'timestamp' => date('Y-m-d H:i:s'),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Validar DNI
if (!validateDNI($dni)) {
    $response = [
        'success' => false,
        'error' => 'DNI inválido. Debe tener exactamente 8 dígitos.',
        'code' => 'INVALID_DNI',
        'dni_recibido' => $dni,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logDecolecta("DNI inválido", $response);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==================== ESTRATEGIA DE CONSULTA ====================
$resultado = null;
$intentos = [];

// 1. Intentar con API oficial de Decolecta
$resultado = consultarDecolectaOficial($dni);
$intentos[] = array_merge($resultado, ['service' => 'decolecta_oficial']);

// 2. Si falla Decolecta, intentar API de respaldo
if (!$resultado['success']) {
    $resultadoRespaldo = consultarAPIRespaldo($dni);
    $intentos[] = $resultadoRespaldo;
    
    if ($resultadoRespaldo['success']) {
        $resultado = $resultadoRespaldo;
    }
}

// 3. Como último recurso, usar datos de prueba
if (!$resultado['success']) {
    $resultadoPrueba = obtenerDatosPrueba($dni);
    $intentos[] = $resultadoPrueba;
    
    if ($resultadoPrueba['success']) {
        $resultado = $resultadoPrueba;
    }
}

// ==================== RESPUESTA FINAL ====================
$respuestaFinal = [
    'success' => $resultado['success'],
    'dni' => $dni,
    'timestamp' => date('Y-m-d H:i:s')
];

if ($resultado['success']) {
    $respuestaFinal['nombres'] = $resultado['nombres'];
    $respuestaFinal['apellidos'] = $resultado['apellidos'];
    $respuestaFinal['nombre_completo'] = trim($resultado['nombres'] . ' ' . $resultado['apellidos']);
    $respuestaFinal['service'] = $resultado['service'];
} else {
    $respuestaFinal['error'] = $resultado['error'] ?? 'DNI no encontrado en ninguna fuente';
    $respuestaFinal['code'] = $resultado['code'] ?? 'NOT_FOUND';
    
    if (isset($resultado['http_code'])) {
        $respuestaFinal['http_code'] = $resultado['http_code'];
    }
}

// Información de debug si se solicita
if (isset($_GET['debug']) || !$resultado['success']) {
    $respuestaFinal['debug'] = [
        'api_version' => '3.0_decolecta_oficial',
        'total_intentos' => count($intentos),
        'servicios_consultados' => array_column($intentos, 'service'),
        'decolecta_token_configurado' => DecolectaConfig::API_TOKEN !== 'sk_10367.GU4w1AirWvIqPecMayNcvlSK3RbE5H4v',
        'timestamp_servidor' => date('c')
    ];
    
    // Incluir detalles si hay fallas
    if (!$resultado['success']) {
        $respuestaFinal['debug']['intentos_detalle'] = $intentos;
    }
}

// Log del resultado final
logDecolecta("Resultado final", [
    'success' => $respuestaFinal['success'],
    'service' => $respuestaFinal['service'] ?? 'none',
    'has_data' => isset($respuestaFinal['nombres'])
]);

// Limpiar logs antiguos
if (DecolectaConfig::ENABLE_LOGGING && file_exists(DecolectaConfig::LOG_FILE)) {
    $logSize = @filesize(DecolectaConfig::LOG_FILE);
    if ($logSize && $logSize > 2097152) { // 2MB
        $lines = @file(DecolectaConfig::LOG_FILE);
        if ($lines) {
            $keepLines = array_slice($lines, -2000);
            @file_put_contents(DecolectaConfig::LOG_FILE, implode('', $keepLines));
        }
    }
}

// Enviar respuesta JSON
echo json_encode($respuestaFinal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>