<?php
require_once '../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'docente') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$database = new Database();
$conn = $database->getConnection();

echo "<h2>🔐 Recuperación de Contraseña - Usuario ID: $teacher_id</h2>";

// Obtener información del usuario
$stmt = $conn->prepare("SELECT id, dni, nombres, apellidos, password FROM usuarios WHERE id = ?");
$stmt->execute([$teacher_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p>❌ Usuario no encontrado</p>";
    exit();
}

echo "<div style='background: #e8f4fd; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #2196F3;'>";
echo "<h3>👤 Tu información:</h3>";
echo "<p><strong>Nombre:</strong> " . $user['nombres'] . " " . $user['apellidos'] . "</p>";
echo "<p><strong>DNI:</strong> " . $user['dni'] . "</p>";
echo "</div>";

// Lista de contraseñas comunes para probar
$common_passwords = [
    'password',
    '123456',
    '123456789',
    'qwerty',
    'abc123',
    'admin',
    'docente',
    'profesor',
    'teacher',
    'user',
    '12345',
    'prueba',
    'test',
    $user['dni'], // Su propio DNI
    $user['nombres'], // Su nombre
    $user['apellidos'], // Su apellido
    strtolower($user['nombres']), // Nombre en minúsculas
    strtolower($user['apellidos']), // Apellido en minúsculas
    $user['dni'] . '123', // DNI + 123
    'admin123',
    'docente123',
    'profesor123',
    '2024',
    '2025',
    'instituto',
    'huallaga',
    'tocache'
];

// Eliminar duplicados y vacíos
$common_passwords = array_unique(array_filter($common_passwords));

// Procesar test automático
if ($_POST && isset($_POST['auto_test'])) {
    echo "<h3>🔍 Probando contraseñas automáticamente...</h3>";
    echo "<div style='background: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 8px; max-height: 400px; overflow-y: auto;'>";
    
    $found_password = false;
    
    foreach ($common_passwords as $test_pwd) {
        if (empty($test_pwd)) continue;
        
        $verify_result = password_verify($test_pwd, $user['password']);
        
        if ($verify_result) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
            echo "<strong>🎉 ¡ENCONTRADA!</strong> Tu contraseña es: <code style='background: #fff; padding: 2px 5px; border-radius: 3px; font-weight: bold;'>$test_pwd</code>";
            echo "<br><small>Ahora puedes usar esta contraseña en el perfil normal para cambiarla.</small>";
            echo "</div>";
            $found_password = true;
            break;
        } else {
            echo "<span style='color: #666;'>❌ $test_pwd</span><br>";
        }
    }
    
    if (!$found_password) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>⚠️ No encontrada automáticamente</strong><br>";
        echo "Ninguna de las contraseñas comunes funcionó. Opciones:";
        echo "<ul>";
        echo "<li>Prueba manualmente con el formulario de abajo</li>";
        echo "<li>Contacta al administrador del sistema</li>";
        echo "<li>Usa la opción de resetear contraseña</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "</div>";
}

// Procesar test manual
if ($_POST && isset($_POST['manual_test'])) {
    $test_password = $_POST['test_password'];
    
    echo "<h3>🔍 Resultado del test manual:</h3>";
    
    $verify_result = password_verify($test_password, $user['password']);
    
    if ($verify_result) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>🎉 ¡CORRECTO!</h4>";
        echo "<p>La contraseña <strong>\"" . htmlspecialchars($test_password) . "\"</strong> es correcta.</p>";
        echo "<p>Ahora puedes:</p>";
        echo "<ul>";
        echo "<li><a href='profile.php' style='color: #28a745; font-weight: bold;'>Ir al perfil y cambiar tu contraseña</a></li>";
        echo "<li>Anotar esta contraseña en un lugar seguro</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>❌ Incorrecto</h4>";
        echo "<p>La contraseña <strong>\"" . htmlspecialchars($test_password) . "\"</strong> no es correcta.</p>";
        echo "<p>Intenta con otra contraseña o usa el test automático.</p>";
        echo "</div>";
    }
}

// Procesar reset de contraseña
if ($_POST && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password && strlen($new_password) >= 6) {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        
        if ($update_stmt->execute([$new_hash, $teacher_id])) {
            echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
            echo "<h4>✅ Contraseña reseteada exitosamente</h4>";
            echo "<p>Tu nueva contraseña es: <strong>\"" . htmlspecialchars($new_password) . "\"</strong></p>";
            echo "<p><a href='profile.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Perfil</a></p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
            echo "<h4>❌ Error al resetear</h4>";
            echo "<p>Hubo un problema al actualizar la contraseña. Intenta de nuevo.</p>";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>⚠️ Error de validación</h4>";
        echo "<p>Las contraseñas no coinciden o son muy cortas (mínimo 6 caracteres).</p>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recuperación de Contraseña</title>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial; 
            max-width: 900px; 
            margin: 20px auto; 
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .method { 
            background: #f8f9fa; 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 10px; 
            border: 2px solid #e9ecef;
        }
        .method:hover {
            border-color: #007bff;
            box-shadow: 0 2px 10px rgba(0,123,255,0.1);
        }
        .input { 
            width: 100%; 
            padding: 12px; 
            margin: 8px 0; 
            border: 2px solid #e9ecef; 
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0,123,255,0.3);
        }
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            cursor: pointer; 
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        h2 { 
            color: #333; 
            border-bottom: 3px solid #007bff; 
            padding-bottom: 15px; 
            margin-bottom: 30px;
        }
        h3 { 
            color: #495057; 
            margin-top: 30px; 
            margin-bottom: 15px;
        }
        .method-title {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .icon {
            font-size: 24px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Método 1: Test Automático -->
        <div class="method">
            <div class="method-title">
                <span class="icon">🤖</span>Método 1: Test Automático (Recomendado)
            </div>
            <p>Probará automáticamente las contraseñas más comunes incluyendo tu DNI, nombre, etc.</p>
            <form method="POST">
                <input type="hidden" name="auto_test" value="1">
                <button type="submit" class="btn btn-success">🔍 Probar Contraseñas Automáticamente</button>
            </form>
        </div>
        
        <!-- Método 2: Test Manual -->
        <div class="method">
            <div class="method-title">
                <span class="icon">✋</span>Método 2: Probar Contraseña Manualmente
            </div>
            <p>Si crees que recuerdas tu contraseña, pruébala aquí:</p>
            <form method="POST">
                <input type="hidden" name="manual_test" value="1">
                <input type="password" name="test_password" class="input" placeholder="Escribe tu contraseña aquí" required>
                <button type="submit" class="btn">🔍 Probar Esta Contraseña</button>
            </form>
        </div>
        
        <!-- Método 3: Reset -->
        <div class="method">
            <div class="method-title">
                <span class="icon">🔄</span>Método 3: Resetear Contraseña (Última opción)
            </div>
            <p><strong>⚠️ Cuidado:</strong> Esto cambiará tu contraseña permanentemente.</p>
            <form method="POST" onsubmit="return confirm('¿Estás seguro de que quieres resetear tu contraseña? Esta acción no se puede deshacer.')">
                <input type="hidden" name="reset_password" value="1">
                <input type="password" name="new_password" class="input" placeholder="Nueva contraseña (mínimo 6 caracteres)" required minlength="6">
                <input type="password" name="confirm_password" class="input" placeholder="Confirmar nueva contraseña" required minlength="6">
                <button type="submit" class="btn btn-danger">🔄 Resetear Mi Contraseña</button>
            </form>
        </div>
        
        <!-- Sugerencias -->
        <div style="background: #e7f3ff; padding: 20px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #007bff;">
            <h4>💡 Sugerencias de contraseñas comunes que podrías estar usando:</h4>
            <ul style="columns: 2; list-style-type: none; padding: 0;">
                <li>• password</li>
                <li>• 123456</li>
                <li>• admin</li>
                <li>• docente</li>
                <li>• profesor</li>
                <li>• <?php echo $user['dni']; ?> (tu DNI)</li>
                <li>• <?php echo strtolower($user['nombres']); ?> (tu nombre)</li>
                <li>• admin123</li>
                <li>• docente123</li>
                <li>• instituto</li>
                <li>• huallaga</li>
                <li>• tocache</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="profile.php" style="color: #007bff; text-decoration: none; font-weight: bold;">← Volver al Perfil</a>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <a href="../dashboard.php" style="color: #007bff; text-decoration: none; font-weight: bold;">🏠 Ir al Dashboard</a>
        </div>
    </div>
</body>
</html>