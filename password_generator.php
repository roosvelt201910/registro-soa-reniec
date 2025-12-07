<?php
// Herramienta para generar hash de contraseña personalizada
// Para usuario DNI: 47259954 (Roosvelt Enriquez gamez)

$user_dni = '47259954';
$user_name = 'Roosvelt Enriquez gamez';
$generated_hash = '';
$selected_password = '';

if ($_POST && isset($_POST['new_password'])) {
    $selected_password = $_POST['new_password'];
    $generated_hash = password_hash($selected_password, PASSWORD_DEFAULT);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Contraseña - Usuario <?php echo $user_dni; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #007bff;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .suggestions {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .suggestions h4 {
            margin-bottom: 0.5rem;
            color: #0066cc;
        }
        
        .suggestion-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            margin: 2px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .suggestion-btn:hover {
            background: #138496;
        }
        
        .result {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .result h4 {
            margin-bottom: 1rem;
            color: #0f5132;
        }
        
        .hash-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            border: 1px solid #dee2e6;
            margin: 10px 0;
        }
        
        .sql-code {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #4a5568;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .copy-btn:hover {
            background: #2d3748;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .step {
            background: #f8f9fa;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .step h5 {
            color: #155724;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Generador de Contraseña</h1>
            <p>Crear nueva contraseña para usuario</p>
        </div>
        
        <div class="user-info">
            <h4>👤 Información del Usuario</h4>
            <p><strong>DNI:</strong> <?php echo $user_dni; ?></p>
            <p><strong>Nombre:</strong> <?php echo $user_name; ?></p>
            <p><strong>Tipo:</strong> Docente</p>
        </div>
        
        <div class="suggestions">
            <h4>💡 Sugerencias de Contraseña</h4>
            <p>Haz clic en una sugerencia para usarla:</p>
            <button type="button" class="suggestion-btn" onclick="setPassword('roosvelt123')">roosvelt123</button>
            <button type="button" class="suggestion-btn" onclick="setPassword('enriquez2025')">enriquez2025</button>
            <button type="button" class="suggestion-btn" onclick="setPassword('docente47259954')">docente47259954</button>
            <button type="button" class="suggestion-btn" onclick="setPassword('Roosvelt@2025')">Roosvelt@2025</button>
            <button type="button" class="suggestion-btn" onclick="setPassword('admin123')">admin123</button>
            <button type="button" class="suggestion-btn" onclick="setPassword('password123')">password123</button>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="new_password">🔑 Nueva Contraseña</label>
                <input type="text" id="new_password" name="new_password" class="form-input" 
                       value="<?php echo htmlspecialchars($selected_password); ?>" 
                       placeholder="Ingrese la nueva contraseña" required>
                <small style="color: #666; font-size: 14px;">
                    Recomendación: Al menos 8 caracteres, incluya números y letras
                </small>
            </div>
            
            <button type="submit" class="btn">
                🚀 Generar Hash de Contraseña
            </button>
        </form>
        
        <?php if ($generated_hash): ?>
        <div class="result">
            <h4>✅ ¡Hash Generado Exitosamente!</h4>
            
            <p><strong>Contraseña elegida:</strong> <code><?php echo htmlspecialchars($selected_password); ?></code></p>
            
            <p><strong>Hash generado:</strong></p>
            <div class="hash-display">
                <?php echo $generated_hash; ?>
            </div>
            
            <div class="step">
                <h5>📋 Paso 1: Copiar y ejecutar este SQL en phpMyAdmin</h5>
                <div class="sql-code">
                    <button class="copy-btn" onclick="copySQLCode()">Copiar</button>
                    <div id="sqlCode">UPDATE usuarios 
SET password = '<?php echo $generated_hash; ?>' 
WHERE dni = '<?php echo $user_dni; ?>';</div>
                </div>
            </div>
            
            <div class="step">
                <h5>🔑 Paso 2: Nuevas credenciales de acceso</h5>
                <p><strong>DNI:</strong> <?php echo $user_dni; ?></p>
                <p><strong>Contraseña:</strong> <?php echo htmlspecialchars($selected_password); ?></p>
            </div>
            
            <div class="warning">
                <strong>⚠️ Importante:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <li>Ejecuta el SQL en phpMyAdmin</li>
                    <li>Guarda la nueva contraseña en lugar seguro</li>
                    <li>Informa al usuario sus nuevas credenciales</li>
                    <li>Recomienda cambiar la contraseña desde su perfil</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; text-align: center;">
            <a href="../login.php" style="color: #007bff; text-decoration: none; font-weight: bold;">
                ← Volver al Login
            </a>
        </div>
    </div>
    
    <script>
        function setPassword(password) {
            document.getElementById('new_password').value = password;
            document.getElementById('new_password').focus();
            
            // Efecto visual
            const input = document.getElementById('new_password');
            input.style.borderColor = '#28a745';
            input.style.boxShadow = '0 0 10px rgba(40,167,69,0.3)';
            
            setTimeout(() => {
                input.style.borderColor = '#e9ecef';
                input.style.boxShadow = 'none';
            }, 1000);
        }
        
        function copySQLCode() {
            const sqlText = document.getElementById('sqlCode').textContent;
            navigator.clipboard.writeText(sqlText).then(() => {
                const copyBtn = document.querySelector('.copy-btn');
                const originalText = copyBtn.textContent;
                copyBtn.textContent = '✅ Copiado';
                copyBtn.style.background = '#28a745';
                
                setTimeout(() => {
                    copyBtn.textContent = originalText;
                    copyBtn.style.background = '#4a5568';
                }, 2000);
            }).catch(() => {
                alert('Error al copiar. Selecciona y copia manualmente el código SQL.');
            });
        }
        
        // Auto-focus en el campo de contraseña
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('new_password');
            if (!passwordField.value) {
                passwordField.focus();
            }
        });
        
        // Validación en tiempo real
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const isStrong = password.length >= 8 && /[a-zA-Z]/.test(password) && /[0-9]/.test(password);
            
            if (isStrong) {
                this.style.borderColor = '#28a745';
                this.style.boxShadow = '0 0 10px rgba(40,167,69,0.2)';
            } else if (password.length > 0) {
                this.style.borderColor = '#ffc107';
                this.style.boxShadow = '0 0 10px rgba(255,193,7,0.2)';
            } else {
                this.style.borderColor = '#e9ecef';
                this.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>