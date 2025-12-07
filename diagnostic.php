<?php
// diagnostic.php - Coloca este archivo en la raíz del proyecto
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico del Sistema</h1>";
echo "<hr>";

// Test 1: PHP básico
echo "<h2>1. Test PHP Básico</h2>";
echo "✅ PHP versión: " . phpversion() . "<br>";
echo "✅ Fecha actual: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: Archivos requeridos
echo "<h2>2. Verificación de Archivos</h2>";
$required_files = [
    'config/database.php',
    'classes/Auth.php',
    'classes/Course.php',
    'classes/Attendance.php',
    'classes/Evaluation.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
    }
}

// Test 3: Inclusión de archivos
echo "<h2>3. Test de Inclusión de Archivos</h2>";
try {
    include_once 'config/database.php';
    echo "✅ database.php incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ Error en database.php: " . $e->getMessage() . "<br>";
}

try {
    include_once 'classes/Auth.php';
    echo "✅ Auth.php incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ Error en Auth.php: " . $e->getMessage() . "<br>";
}

// Test 4: Conexión a base de datos
echo "<h2>4. Test de Base de Datos</h2>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
        echo "✅ Conexión a base de datos OK<br>";
        
        // Test de tablas principales
        $tables = ['usuarios', 'unidades_didacticas', 'matriculas', 'sesiones', 'asistencias'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Tabla '$table' existe<br>";
            } else {
                echo "❌ Tabla '$table' NO existe<br>";
            }
        }
    } else {
        echo "❌ No se pudo conectar a la base de datos<br>";
    }
} catch (Exception $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "<br>";
}

// Test 5: Instanciar clases
echo "<h2>5. Test de Instanciación de Clases</h2>";
try {
    $auth = new Auth();
    echo "✅ Clase Auth instanciada<br>";
    
    $user = new User();
    echo "✅ Clase User instanciada<br>";
    
} catch (Exception $e) {
    echo "❌ Error instanciando clases: " . $e->getMessage() . "<br>";
}

try {
    include_once 'classes/Course.php';
    $course = new Course();
    echo "✅ Clase Course instanciada<br>";
} catch (Exception $e) {
    echo "❌ Error en Course: " . $e->getMessage() . "<br>";
}

try {
    include_once 'classes/Attendance.php';
    $attendance = new Attendance();
    echo "✅ Clase Attendance instanciada<br>";
} catch (Exception $e) {
    echo "❌ Error en Attendance: " . $e->getMessage() . "<br>";
}

// Test 6: Test de funciones
echo "<h2>6. Test de Funciones</h2>";
try {
    if (function_exists('sanitizeInput')) {
        echo "✅ Función sanitizeInput existe<br>";
        $test = sanitizeInput("  test<script>  ");
        echo "✅ sanitizeInput funciona: '$test'<br>";
    } else {
        echo "❌ Función sanitizeInput NO existe<br>";
    }
    
    if (function_exists('calculateAttendanceCondition')) {
        echo "✅ Función calculateAttendanceCondition existe<br>";
        $test = calculateAttendanceCondition(75);
        echo "✅ calculateAttendanceCondition funciona: '$test'<br>";
    } else {
        echo "❌ Función calculateAttendanceCondition NO existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en funciones: " . $e->getMessage() . "<br>";
}

// Test 7: Test específico de attendance.php
echo "<h2>7. Test específico de student/attendance.php</h2>";
if (file_exists('student/attendance.php')) {
    echo "✅ student/attendance.php existe<br>";
    
    // Verificar sintaxis básica
    $content = file_get_contents('student/attendance.php');
    if (strpos($content, '<?php') === 0) {
        echo "✅ Archivo PHP válido<br>";
        
        // Buscar posibles errores comunes
        if (strpos($content, 'require_once') !== false) {
            echo "✅ Tiene require_once<br>";
        }
        
        if (strpos($content, 'new Auth()') !== false) {
            echo "✅ Instancia Auth<br>";
        }
        
        if (strpos($content, 'new User()') !== false) {
            echo "✅ Instancia User<br>";
        }
        
    } else {
        echo "❌ No es un archivo PHP válido<br>";
    }
} else {
    echo "❌ student/attendance.php NO existe<br>";
}

// Test 8: Datos de prueba
echo "<h2>8. Test de Datos</h2>";
try {
    if (isset($conn)) {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'estudiante'");
        $result = $stmt->fetch();
        echo "✅ Total estudiantes: " . $result['total'] . "<br>";
        
        if ($result['total'] > 0) {
            $stmt = $conn->query("SELECT dni, nombres, apellidos FROM usuarios WHERE tipo_usuario = 'estudiante' LIMIT 3");
            $students = $stmt->fetchAll();
            echo "✅ Estudiantes de prueba:<br>";
            foreach ($students as $student) {
                echo "&nbsp;&nbsp;- DNI: " . $student['dni'] . " - " . $student['nombres'] . " " . $student['apellidos'] . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error consultando datos: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>🎯 Siguiente paso:</h2>";
echo "<p>Si todo está en ✅, el problema está en el código específico.</p>";
echo "<p><strong>Intenta acceder a:</strong></p>";
echo "<ul>";
echo "<li><a href='student/attendance.php?dni=11111111' target='_blank'>student/attendance.php?dni=11111111</a></li>";
echo "<li><a href='dashboard.php' target='_blank'>dashboard.php</a></li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
ul { margin-left: 20px; }
hr { margin: 20px 0; }
</style>