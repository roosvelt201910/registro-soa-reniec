<?php
// Habilitar visualización de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    // Iniciar sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    echo "<!-- Debug: Sesión iniciada -->\n";
    
    // Incluir configuración
    require_once '../config/database.php';
    echo "<!-- Debug: Config incluida -->\n";
    
    // Verificar sesión y tipo de usuario
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'docente') {
        header("Location: ../login.php");
        exit();
    }
    
    echo "<!-- Debug: Usuario verificado -->\n";
    
    $teacher_id = $_SESSION['user_id'];
    $message = '';
    $message_type = '';
    
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<!-- Debug: Conexión establecida -->\n";
    
    // Procesar formularios solo si hay POST
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        if ($_POST['action'] == 'update_profile') {
            $email = sanitizeInput($_POST['email']);
            
            try {
                // Verificar si el email ya existe para otro usuario
                $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->execute([$email, $teacher_id]);
                
                if ($stmt_check->rowCount() > 0) {
                    $message = 'El correo electrónico ya está registrado por otro usuario.';
                    $message_type = 'error';
                } else {
                    // Actualizar perfil
                    $sql_update = "UPDATE usuarios SET email = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    
                    if ($stmt_update->execute([$email, $teacher_id])) {
                        $message = 'Perfil actualizado exitosamente.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error al actualizar el perfil.';
                        $message_type = 'error';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
            
        } elseif ($_POST['action'] == 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $message = 'Las contraseñas nuevas no coinciden.';
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'La contraseña debe tener al menos 6 caracteres.';
                $message_type = 'error';
            } else {
                try {
                    // Verificar contraseña actual
                    $sql_verify = "SELECT password FROM usuarios WHERE id = ?";
                    $stmt_verify = $conn->prepare($sql_verify);
                    $stmt_verify->execute([$teacher_id]);
                    $user = $stmt_verify->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($current_password, $user['password'])) {
                        // Actualizar contraseña
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql_update_pass = "UPDATE usuarios SET password = ? WHERE id = ?";
                        $stmt_update_pass = $conn->prepare($sql_update_pass);
                        
                        if ($stmt_update_pass->execute([$hashed_password, $teacher_id])) {
                            $message = 'Contraseña actualizada exitosamente.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error al actualizar la contraseña.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'La contraseña actual es incorrecta.';
                        $message_type = 'error';
                    }
                } catch (PDOException $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
    
    // Obtener información del docente
    $sql_teacher = "SELECT dni, nombres, apellidos, email, fecha_creacion, estado FROM usuarios WHERE id = ?";
    $stmt_teacher = $conn->prepare($sql_teacher);
    $stmt_teacher->execute([$teacher_id]);
    $teacher_info = $stmt_teacher->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher_info) {
        throw new Exception("No se encontró información del docente");
    }
    
    echo "<!-- Debug: Datos del docente obtenidos -->\n";

} catch (Exception $e) {
    echo "<h1>Error del Sistema</h1>";
    echo "<p>Ha ocurrido un error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil Docente - Debug Version</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <h1>Mi Perfil Docente - Versión Debug</h1>
    
    <div class="card">
        <h2>Información del Sistema</h2>
        <p><strong>Usuario ID:</strong> <?php echo $teacher_id; ?></p>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($teacher_info['nombres'] . ' ' . $teacher_info['apellidos']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher_info['email']); ?></p>
        <p><strong>Estado:</strong> <?php echo htmlspecialchars($teacher_info['estado']); ?></p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Actualizar Email</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">DNI</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($teacher_info['dni']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($teacher_info['estado']); ?>" disabled>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($teacher_info['email']); ?>" required>
            </div>
            
            <button type="submit" class="btn">Actualizar Email</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Cambiar Contraseña</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label class="form-label">Contraseña Actual</label>
                <input type="password" name="current_password" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" name="new_password" class="form-input" required minlength="6">
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirmar Nueva Contraseña</label>
                <input type="password" name="confirm_password" class="form-input" required minlength="6">
            </div>
            
            <button type="submit" class="btn">Cambiar Contraseña</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Debug Info</h3>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Activa' : 'Inactiva'; ?></p>
        <p><strong>POST Data:</strong> <?php echo !empty($_POST) ? 'Presente' : 'Vacío'; ?></p>
        <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>