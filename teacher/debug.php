<?php
// Habilitar visualización de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>Test de Debug</h2>";

// Test 1: Verificar que PHP funciona
echo "<p>✅ PHP está funcionando</p>";

// Test 2: Verificar inclusión del archivo de configuración
echo "<p>🔄 Intentando cargar config/database.php...</p>";
try {
    require_once '../config/database.php';
    echo "<p>✅ config/database.php cargado correctamente</p>";
} catch (Exception $e) {
    echo "<p>❌ Error cargando config: " . $e->getMessage() . "</p>";
    exit();
}

// Test 3: Verificar conexión a base de datos
echo "<p>🔄 Intentando conectar a la base de datos...</p>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
        echo "<p>✅ Conexión a base de datos exitosa</p>";
    } else {
        echo "<p>❌ No se pudo conectar a la base de datos</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error de conexión: " . $e->getMessage() . "</p>";
}

// Test 4: Verificar funciones
echo "<p>🔄 Verificando funciones...</p>";
if (function_exists('sanitizeInput')) {
    echo "<p>✅ Función sanitizeInput existe</p>";
} else {
    echo "<p>❌ Función sanitizeInput no existe</p>";
}

// Test 5: Verificar sesión
echo "<p>🔄 Verificando sesión...</p>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p>✅ Sesión activa</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p>✅ Usuario en sesión: ID = " . $_SESSION['user_id'] . "</p>";
    } else {
        echo "<p>⚠️ No hay usuario en sesión</p>";
    }
} else {
    echo "<p>❌ Sesión no activa</p>";
}

echo "<p>🎉 Debug completado</p>";
?>