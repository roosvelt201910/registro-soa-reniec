<?php
// Iniciar sesión al principio
session_start();

// Verificar si ya está logueado
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($dni) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        try {
            // Configuración de base de datos
            $host = "localhost";
            $db_name = "iespaltohuallaga_regauxiliar_bd";
            $username = "iespaltohuallaga_user_regaux";
            $db_password = ")wBRCeID[ldb%b^K";
            
            $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $db_password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "SELECT id, dni, nombres, apellidos, tipo_usuario, estado, password FROM usuarios WHERE dni = :dni AND estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar contraseña
                $password_valid = false;
                
                if (password_verify($password, $user['password'])) {
                    $password_valid = true;
                }
                elseif ($password === $user['password']) {
                    $password_valid = true;
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE usuarios SET password = :password WHERE id = :id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':password', $hashed_password);
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                }
                
                if ($password_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_dni'] = $user['dni'];
                    $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
                    $_SESSION['user_type'] = $user['tipo_usuario'];
                    $_SESSION['logged_in'] = true;
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'DNI o contraseña incorrectos.';
                }
            } else {
                $error = 'DNI o contraseña incorrectos.';
            }
        } catch(PDOException $e) {
            $error = 'Error de conexión a la base de datos. Verifique la configuración.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Académico - IESTP Alto Huallaga</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
             --primary: #003d82;
            --primary-light: #0052b4;
            --accent: #1569d0ff;
            --accent-light: #818cf8;
            --purple: #3a85edff;
            --purple-dark: #2872d9ff;
            --gradient-start: #667eea;
            --gradient-end: #4b69a2ff;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            --white: #ffffff;
            --light-bg: #f0f4f8;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Panel izquierdo - Bienvenida */
        .welcome-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-panel::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            animation: float 20s ease-in-out infinite;
        }
        
        .welcome-panel::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            bottom: -100px;
            right: -100px;
            animation: float 15s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }
        
        .welcome-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 500px;
        }
        
        .welcome-title {
            color: white;
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.25rem;
            line-height: 1.8;
            font-weight: 500;
        }
        
        .welcome-illustration {
            margin-top: 3rem;
            animation: slideInUp 0.8s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .illustration-container {
            position: relative;
            width: 420px;
            height: 420px;
            margin: 0 auto;
        }
        
        .illustration-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }
        
        .illustration-element {
            position: absolute;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: floatElement 3s ease-in-out infinite;
            transition: var(--transition);
        }
        
        .illustration-element:hover {
            transform: scale(1.15) translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes floatElement {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        .element-1 {
            width: 85px;
            height: 85px;
            top: 40px;
            left: 50px;
            animation-delay: 0s;
        }
        
        .element-2 {
            width: 75px;
            height: 75px;
            top: 50px;
            right: 70px;
            animation-delay: 0.5s;
        }
        
        .element-3 {
            width: 80px;
            height: 80px;
            bottom: 90px;
            left: 35px;
            animation-delay: 1s;
        }
        
        .element-4 {
            width: 90px;
            height: 90px;
            bottom: 70px;
            right: 50px;
            animation-delay: 1.5s;
        }
        
        .element-icon {
            font-size: 2.25rem;
            background: linear-gradient(135deg, var(--accent), var(--purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .central-character {
            position: absolute;
            width: 220px;
            height: 220px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
            border: 6px solid rgba(255, 255, 255, 0.2);
        }
        
        .central-icon {
            font-size: 5.5rem;
            color: white;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Panel derecho - Login */
        .login-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: white;
        }
        
        .login-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .logo-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 2.25rem;
            font-weight: 900;
            color: var(--gray-900);
            margin-bottom: 2.5rem;
            letter-spacing: -0.02em;
        }
        
        .logo-text {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo-plus {
            color: #ef4444;
            font-weight: 900;
        }
        
        .greeting {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }
        
        .greeting-subtitle {
            color: var(--gray-600);
            font-size: 1.0625rem;
            font-weight: 500;
            line-height: 1.6;
        }
        
        .greeting-subtitle strong {
            color: var(--gray-900);
            font-weight: 700;
        }
        
        .form-section {
            margin-top: 3rem;
        }
        
        .form-group {
            margin-bottom: 1.875rem;
        }
        
        .form-label {
            display: block;
            color: var(--gray-900);
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 3.5rem 1rem 1.125rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-900);
            background: white;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-input:hover {
            border-color: var(--gray-300);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }
        
        .input-icon {
            position: absolute;
            right: 1.125rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.25rem;
        }
        
        .toggle-password {
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--accent);
        }
        
        .input-hint {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-top: 0.625rem;
            color: var(--gray-500);
            font-size: 0.8125rem;
            line-height: 1.5;
        }
        
        .input-hint i {
            font-size: 0.875rem;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 0.875rem;
        }
        
        .forgot-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--purple);
            text-decoration: underline;
        }
        
        .captcha-section {
            margin: 2rem 0;
            padding: 1.75rem;
            background: white;
            border-radius: 16px;
            border: 2px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }
        
        .captcha-label {
            display: block;
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        
        .captcha-box {
            display: flex;
            align-items: stretch;
            gap: 1rem;
        }
        
        .captcha-display {
            flex: 0 0 auto;
            min-width: 240px;
            background: white;
            border: 3px solid var(--accent);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            font-family: 'Courier New', monospace;
            font-size: 1.875rem;
            font-weight: 700;
            letter-spacing: 0.35em;
            text-align: center;
            color: var(--accent);
            user-select: none;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .captcha-refresh {
            flex: 0 0 auto;
            width: 56px;
            height: auto;
            background: linear-gradient(135deg, var(--accent) 0%, var(--purple) 100%);
            color: white;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            box-shadow: var(--shadow-md);
        }
        
        .captcha-refresh:hover {
            transform: rotate(180deg) scale(1.05);
            box-shadow: var(--shadow-lg);
        }
        
        .captcha-refresh:active {
            transform: rotate(180deg) scale(0.98);
        }
        
        .captcha-input {
            flex: 1;
            min-width: 0;
            padding: 1.25rem 1.5rem;
            border: 2px solid var(--gray-200);
            border-radius: 16px;
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            transition: var(--transition);
            background: white;
        }
        
        .captcha-input:hover {
            border-color: var(--gray-300);
        }
        
        .captcha-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .submit-button {
            width: 100%;
            padding: 1.125rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--purple) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.0625rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
            letter-spacing: 0.02em;
            position: relative;
            overflow: hidden;
        }
        
        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }
        
        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.5);
        }
        
        .submit-button:hover::before {
            left: 100%;
        }
        
        .submit-button:active {
            transform: translateY(-1px);
        }
        
        .help-section {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
            text-align: center;
        }
        
        .help-text {
            color: var(--gray-600);
            font-size: 0.9375rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .help-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .help-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--purple);
        }
        
        /* Estados de validación */
        .form-input.valid {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .form-input.invalid {
            border-color: var(--error);
            background: rgba(239, 68, 68, 0.05);
        }
        
        .captcha-input.valid {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .captcha-input.invalid {
            border-color: var(--error);
            background: rgba(239, 68, 68, 0.05);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .welcome-panel {
                display: none;
            }
            
            .login-panel {
                flex: none;
                width: 100%;
            }
        }
        
        @media (max-width: 640px) {
            .login-panel {
                padding: 2rem 1.5rem;
            }
            
            .logo-brand {
                font-size: 1.875rem;
            }
            
            .greeting {
                font-size: 2rem;
            }
            
            .greeting-subtitle {
                font-size: 1rem;
            }
            
            .captcha-section {
                padding: 1.5rem;
            }
            
            .captcha-box {
                flex-direction: column;
                gap: 1rem;
            }
            
            .captcha-display {
                width: 100%;
                min-width: 100%;
                font-size: 1.5rem;
            }
            
            .captcha-refresh {
                width: 100%;
                height: 56px;
            }
            
            .captcha-input {
                width: 100%;
                font-size: 1.25rem;
            }
        }
        
        @media (max-width: 380px) {
            .login-panel {
                padding: 1.5rem 1rem;
            }
            
            .greeting {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Panel izquierdo - Bienvenida -->
    <div class="welcome-panel">
        <div class="welcome-content">
            <h1 class="welcome-title">¡Te damos la bienvenida!</h1>
            <p class="welcome-subtitle">
                Gestiona Calificaciones, realiza consultas y explora tus notas de forma sencilla.
            </p>
        </div>
        
        <div class="welcome-illustration">
            <div class="illustration-container">
                <div class="illustration-bg"></div>
                
                <div class="illustration-element element-1">
                    <i class="fas fa-file-alt element-icon"></i>
                </div>
                
                <div class="illustration-element element-2">
                    <i class="fas fa-calendar-check element-icon"></i>
                </div>
                
                <div class="illustration-element element-3">
                    <i class="fas fa-pen element-icon"></i>
                </div>
                
                <div class="illustration-element element-4">
                    <i class="fas fa-check-circle element-icon"></i>
                </div>
                
                <div class="central-character">
                    <i class="fas fa-user-graduate central-icon"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel derecho - Login -->
    <div class="login-panel">
        <div class="login-container">
            <div class="logo-section">
                <div class="logo-brand">
                    <span class="logo-text">REGISTRO AUXILIAR DOCENTE</span>
                    <span class="logo-plus">+</span>
                    <span class="logo-text">Portal del Estudiante</span>
                </div>
                
                <h1 class="greeting">¡Hola!</h1>
                <p class="greeting-subtitle">
                    Ingresa tus datos para <strong>iniciar sesión</strong>.
                </p>
            </div>
            
            <form method="POST" action="" id="loginForm" class="form-section">
                <div class="form-group">
                    <label class="form-label" for="dni">
                        Usuario: DNI del Docente 
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="dni" 
                            name="dni" 
                            class="form-input"
                            maxlength="8" 
                            pattern="[0-9]{8}" 
                            required 
                            placeholder="U2301685"
                            autocomplete="username"
                            inputmode="numeric">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                    <div class="input-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Ejemplo de usuario: 76543210 (no digitar el @iespaltohuallaga.edu.pe)</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">
                        Contraseña
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input"
                            required 
                            placeholder="••••••••••••"
                            autocomplete="current-password">
                        <i class="fas fa-eye toggle-password input-icon" id="togglePassword"></i>
                    </div>
                    <div class="forgot-password">
                        <a href="#" class="forgot-link" id="forgotLink">Restablecer contraseña</a>
                    </div>
                </div>
                
                <div class="captcha-section">
                    <label class="captcha-label">Verificación de seguridad</label>
                    <div class="captcha-box">
                        <div class="captcha-display" id="captchaDisplay" aria-label="Código de verificación">ABCD12</div>
                        <button type="button" class="captcha-refresh" id="refreshCaptcha" aria-label="Generar nuevo código" title="Generar nuevo código">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <input 
                            type="text" 
                            class="captcha-input" 
                            id="captchaInput" 
                            placeholder="Código"
                            maxlength="6"
                            required
                            autocomplete="off"
                            aria-label="Ingrese el código de verificación">
                    </div>
                </div>
                
                <button type="submit" class="submit-button">
                    Iniciar sesión
                </button>
            </form>
            
            <div class="help-section">
                <p class="help-text">¿Necesitas ayuda con tu cuenta?</p>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; align-items: center;">
                    <a href="#" class="help-link" id="helpLink">
                        <i class="fas fa-question-circle"></i>
                        <span>Centro de ayuda</span>
                    </a>
                    <a href="https://registroauxiliar.iespaltohuallaga.edu.pe/student/notas.php" class="help-link" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(124, 58, 237, 0.1));">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Portal del Estudiante</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <script>
        // Variables globales
        let currentCaptcha = '';
        let formSubmitted = false;
        
        // Generar captcha aleatorio
        function generateCaptcha() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let captcha = '';
            for (let i = 0; i < 6; i++) {
                captcha += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return captcha;
        }
        
        // Actualizar captcha
        function updateCaptcha() {
            currentCaptcha = generateCaptcha();
            document.getElementById('captchaDisplay').textContent = currentCaptcha;
            document.getElementById('captchaInput').value = '';
            console.log('Nuevo captcha generado:', currentCaptcha);
        }
        
        // Toast mejorado
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Portal Académico IESTP Alto Huallaga');
            
            // Generar captcha inicial
            updateCaptcha();
            
            // Refresh captcha
            document.getElementById('refreshCaptcha').addEventListener('click', function() {
                updateCaptcha();
                Toast.fire({
                    icon: 'success',
                    title: 'Nuevo código generado'
                });
            });
            
            // Mostrar error PHP
            <?php if (!empty($error)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Autenticación',
                    text: '<?php echo addslashes($error); ?>',
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#6366f1',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    }
                });
            <?php endif; ?>
            
            // Validación DNI en tiempo real
            const dniInput = document.getElementById('dni');
            dniInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 8) {
                    value = value.substring(0, 8);
                    Toast.fire({
                        icon: 'warning',
                        title: 'El DNI debe tener 8 dígitos'
                    });
                }
                e.target.value = value;
                
                // Estados de validación
                e.target.classList.remove('valid', 'invalid');
                if (value.length === 8) {
                    e.target.classList.add('valid');
                } else if (value.length > 0) {
                    e.target.classList.add('invalid');
                }
            });
            
            // Toggle contraseña
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type');
                
                if (type === 'password') {
                    passwordInput.setAttribute('type', 'text');
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                    Toast.fire({
                        icon: 'info',
                        title: 'Contraseña visible'
                    });
                } else {
                    passwordInput.setAttribute('type', 'password');
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                    Toast.fire({
                        icon: 'info',
                        title: 'Contraseña oculta'
                    });
                }
            });
            
            // Validación captcha en tiempo real
            const captchaInput = document.getElementById('captchaInput');
            captchaInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.toUpperCase();
                
                // Estados de validación
                e.target.classList.remove('valid', 'invalid');
                if (e.target.value.length === 6) {
                    if (e.target.value === currentCaptcha) {
                        e.target.classList.add('valid');
                    } else {
                        e.target.classList.add('invalid');
                    }
                }
            });
            
            // Link olvidaste contraseña
            document.getElementById('forgotLink').addEventListener('click', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    icon: 'info',
                    title: 'Restablecer Contraseña',
                    html: `
                        <div style="text-align: left; padding: 1rem; line-height: 1.8;">
                            <p style="margin-bottom: 1rem; color: #4b5563;">
                                Para restablecer su contraseña, por favor contacte al administrador del sistema.
                            </p>
                            <div style="padding: 1rem; background: #f0f4f8; border-radius: 8px; border-left: 4px solid #6366f1;">
                                <p style="margin: 0; font-size: 0.9375rem; color: #374151;">
                                    <strong style="color: #6366f1;">📧 Correo:</strong> soporte@iespaltohuallaga.edu.pe<br>
                                    <strong style="color: #6366f1;">📞 Teléfono:</strong> (042) 470 012<br>
                                    <strong style="color: #6366f1;">💬 Mensaje:</strong> 950 980 740
                                </p>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#6366f1'
                });
            });
            
            // Submit formulario
            const loginForm = document.getElementById('loginForm');
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Prevenir múltiples envíos
                if (formSubmitted) {
                    return false;
                }
                
                const dni = dniInput.value.trim();
                const password = passwordInput.value.trim();
                const captchaValue = captchaInput.value.trim().toUpperCase();
                
                console.log('Validando formulario...');
                
                // Validar campos vacíos
                if (!dni || !password || !captchaValue) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Campos Incompletos',
                        text: 'Por favor, complete todos los campos requeridos.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#6366f1'
                    });
                    return;
                }
                
                // Validar DNI
                if (dni.length !== 8 || !/^\d{8}$/.test(dni)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'DNI Inválido',
                        text: 'El DNI debe contener exactamente 8 dígitos numéricos.',
                        confirmButtonText: 'Cerrar',
                        confirmButtonColor: '#6366f1'
                    });
                    dniInput.focus();
                    return;
                }
                
                // Validar captcha
                if (captchaValue !== currentCaptcha) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Código Incorrecto',
                        html: `
                            <div style="text-align: left; padding: 1rem;">
                                <p style="margin-bottom: 1rem; color: #4b5563;">
                                    El código de verificación no coincide.
                                </p>
                                <p style="font-size: 0.875rem; color: #6b7280;">
                                    <strong>Ingresado:</strong> <code style="color: #ef4444; font-weight: 700; background: #fee2e2; padding: 0.25rem 0.5rem; border-radius: 4px;">${captchaValue}</code><br>
                                    <strong>Correcto:</strong> <code style="color: #10b981; font-weight: 700; background: #d1fae5; padding: 0.25rem 0.5rem; border-radius: 4px;">${currentCaptcha}</code>
                                </p>
                            </div>
                        `,
                        confirmButtonText: 'Cerrar',
                        confirmButtonColor: '#6366f1'
                    }).then(() => {
                        updateCaptcha();
                        captchaInput.focus();
                    });
                    return;
                }
                
                // Todo correcto, enviar formulario
                formSubmitted = true;
                
                Swal.fire({
                    title: 'Validando Credenciales',
                    html: '<div style="padding: 1rem;">Verificando su identidad...</div>',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Enviar después de 1.5 segundos
                setTimeout(() => {
                    this.submit();
                }, 1500);
            });
            
            // Ayuda contextual
            document.getElementById('helpLink').addEventListener('click', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: '<i class="fas fa-question-circle"></i> Centro de Ayuda',
                    html: `
                        <div style="text-align: left; line-height: 1.8; padding: 1rem;">
                            <h4 style="color: #6366f1; margin-bottom: 1rem; font-weight: 700;">
                                <i class="fas fa-info-circle"></i> Instrucciones de Acceso
                            </h4>
                            <ol style="padding-left: 1.5rem; margin-bottom: 1.5rem; color: #4b5563;">
                                <li style="margin-bottom: 0.75rem;">Ingrese su DNI del Docente (8 dígitos numéricos)</li>
                                <li style="margin-bottom: 0.75rem;">Ingrese su contraseña personal</li>
                                <li style="margin-bottom: 0.75rem;">Complete el código de verificación mostrado</li>
                                <li style="margin-bottom: 0.75rem;">Haga clic en "Iniciar sesión"</li>
                            </ol>
                            
                            <h4 style="color: #6366f1; margin-bottom: 1rem; font-weight: 700;">
                                <i class="fas fa-lightbulb"></i> Consejos Importantes
                            </h4>
                            <ul style="list-style: none; padding-left: 0; margin-bottom: 1.5rem; color: #4b5563;">
                                <li style="margin-bottom: 0.75rem;">✓ Verifique que su dni esté correctamente digitado</li>
                                <li style="margin-bottom: 0.75rem;">✓ La contraseña distingue mayúsculas y minúsculas</li>
                                <li style="margin-bottom: 0.75rem;">✓ El código de verificación también es sensible a mayúsculas</li>
                                <li style="margin-bottom: 0.75rem;">✓ Puede generar un nuevo código con el botón de actualizar</li>
                            </ul>
                            
                            <h4 style="color: #6366f1; margin-bottom: 1rem; font-weight: 700;">
                                <i class="fas fa-shield-alt"></i> Seguridad
                            </h4>
                            <ul style="list-style: none; padding-left: 0; color: #4b5563;">
                                <li style="margin-bottom: 0.75rem;">✓ Sus datos están protegidos con encriptación</li>
                                <li style="margin-bottom: 0.75rem;">✓ No comparta sus credenciales con terceros</li>
                                <li style="margin-bottom: 0.75rem;">✓ Cierre sesión al terminar de usar el sistema</li>
                            </ul>
                            
                            <div style="margin-top: 1.5rem; padding: 1rem; background: #f0f4f8; border-radius: 8px; border-left: 4px solid #6366f1;">
                                <p style="margin: 0; font-size: 0.875rem; color: #4b5563;">
                                    <strong style="color: #6366f1;">¿Olvidó su contraseña?</strong><br>
                                    Use la opción "Restablecer contraseña" o contacte al administrador del sistema.
                                </p>
                            </div>
                            
                            <div style="margin-top: 1rem; text-align: center; font-size: 0.75rem; color: #9ca3af;">
                                Presione <kbd style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-weight: 600;">F1</kbd> 
                                en cualquier momento para mostrar esta ayuda
                            </div>
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#6366f1',
                    width: '700px',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    }
                });
            });
            
            // Atajo F1 para ayuda
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F1') {
                    e.preventDefault();
                    document.getElementById('helpLink').click();
                }
            });
            
            // Autoenfoque en DNI
            setTimeout(() => {
                dniInput.focus();
            }, 600);
            
            // Mensaje de bienvenida
            <?php if (empty($error)): ?>
            setTimeout(() => {
                Toast.fire({
                    icon: 'info',
                    title: '¡Bienvenido al Portal Académico!'
                });
            }, 1000);
            <?php endif; ?>
            
            console.log('✓ Sistema inicializado correctamente');
            console.log('💡 Presione F1 para ver la ayuda');
        });
    </script>
</body>
</html>