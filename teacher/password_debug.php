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

echo "<h2>Debug de Contraseñas - Usuario ID: $teacher_id</h2>";

// Obtener información del usuario
$stmt = $conn->prepare("SELECT id, dni, nombres, apellidos, password FROM usuarios WHERE id = ?");
$stmt->execute([$teacher_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "<h3>Información del Usuario:</h3>";
    echo "<p><strong>ID:</strong> " . $user['id'] . "</p>";
    echo "<p><strong>DNI:</strong> " . $user['dni'] . "</p>";
    echo "<p><strong>Nombre:</strong> " . $user['nombres'] . " " . $user['apellidos'] . "</p>";
    echo "<p><strong>Hash actual:</strong> " . substr($user['password'], 0, 50) . "...</p>";
    echo "<p><strong>Longitud del hash:</strong> " . strlen($user['password']) . " caracteres</p>";
    
    // Verificar si es un hash válido
    $hash_info = password_get_info($user['password']);
    echo "<p><strong>Tipo de hash:</strong> " . $hash_info['algoName'] . "</p>";
    echo "<p><strong>¿Es hash válido?:</strong> " . ($hash_info['algoName'] !== 'unknown' ? 'SÍ' : 'NO') . "</p>";
} else {
    echo "<p>❌ No se encontró el usuario</p>";
    exit();
}

// Procesar test de contraseña
if ($_POST && isset($_POST['test_password'])) {
    $test_password = $_POST['test_password'];
    
    echo "<h3>Test de Contraseña:</h3>";
    echo "<p><strong>Contraseña ingresada:</strong> " . htmlspecialchars($test_password) . "</p>";
    echo "<p><strong>Longitud:</strong> " . strlen($test_password) . " caracteres</p>";
    
    // Test de verificación
    $verify_result = password_verify($test_password, $user['password']);
    echo "<p><strong>Resultado password_verify():</strong> " . ($verify_result ? '✅ CORRECTO' : '❌ INCORRECTO') . "</p>";
    
    // Test con hash manual para comparar
    $manual_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "<p><strong>Hash nuevo generado:</strong> " . substr($manual_hash, 0, 50) . "...</p>";
    
    // Test de verificación con el hash nuevo
    $manual_verify = password_verify($test_password, $manual_hash);
    echo "<p><strong>Verificación con hash nuevo:</strong> " . ($manual_verify ? '✅ FUNCIONA' : '❌ NO FUNCIONA') . "</p>";
    
    // Si el hash actual no funciona, ofrecer actualizar
    if (!$verify_result && $manual_verify) {
        echo "<div style='background: #fffbf3; border: 1px solid #ffa500; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>⚠️ Problema detectado:</h4>";
        echo "<p>Tu contraseña actual no tiene un hash válido. Esto puede pasar si:</p>";
        echo "<ul>";
        echo "<li>La contraseña se guardó sin encriptar</li>";
        echo "<li>Se usó un método de hash obsoleto</li>";
        echo "<li>Hubo un error en la migración de datos</li>";
        echo "</ul>";
        echo "<p><strong>Solución:</strong> Podemos actualizar tu contraseña con un hash seguro.</p>";
        echo "<form method='POST' style='margin-top: 10px;'>";
        echo "<input type='hidden' name='fix_password' value='1'>";
        echo "<input type='hidden' name='new_password' value='" . htmlspecialchars($test_password) . "'>";
        echo "<button type='submit' style='background: #ffa500; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Arreglar mi contraseña</button>";
        echo "</form>";
        echo "</div>";
    }
}

// Procesar arreglo de contraseña
if ($_POST && isset($_POST['fix_password'])) {
    $new_password = $_POST['new_password'];
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    if ($update_stmt->execute([$new_hash, $teacher_id])) {
        echo "<div style='background: #d4edda; border: 1px solid #10b981; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>✅ Contraseña actualizada exitosamente</h4>";
        echo "<p>Tu contraseña ahora tiene un hash seguro. Puedes usar el perfil normal.</p>";
        echo "<a href='profile.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Perfil</a>";
        echo "</div>";
    } else {
        echo "<p>❌ Error al actualizar la contraseña</p>";
    }
}

// Verificar contraseñas comunes que podrían estar sin hash
$common_passwords = ['password', '123456', 'admin', 'docente', 'profesor'];
echo "<h3>Test de Contraseñas Comunes:</h3>";
foreach ($common_passwords as $pwd) {
    $is_direct_match = ($user['password'] === $pwd);
    echo "<p><strong>'$pwd':</strong> " . ($is_direct_match ? '⚠️ COINCIDE DIRECTA (sin hash)' : '✅ No coincide') . "</p>";
    
    if ($is_direct_match) {
        echo "<div style='background: #f8d7da; border: 1px solid #ef4444; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "<h4>🚨 ALERTA DE SEGURIDAD</h4>";
        echo "<p>Tu contraseña está guardada sin encriptar. Esto es muy inseguro.</p>";
        echo "<form method='POST' style='margin-top: 10px;'>";
        echo "<input type='hidden' name='fix_password' value='1'>";
        echo "<input type='hidden' name='new_password' value='$pwd'>";
        echo "<button type='submit' style='background: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Encriptar mi contraseña AHORA</button>";
        echo "</form>";
        echo "</div>";
        break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug de Contraseñas</title>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form { 
            background: #f9f9f9; 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 5px; 
            border: 1px solid #ddd;
        }
        .input { 
            width: 100%; 
            padding: 10px; 
            margin: 5px 0; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            border-radius: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form">
            <h3>🔍 Probar Contraseña Actual</h3>
            <p>Ingresa tu contraseña actual para diagnosticar el problema:</p>
            <form method="POST">
                <input type="password" name="test_password" class="input" placeholder="Tu contraseña actual" required>
                <button type="submit" class="btn">Probar Contraseña</button>
            </form>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="profile.php" style="color: #007bff; text-decoration: none;">← Volver al Perfil</a>
        </div>
    </div>
</body>
</html>