<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso No Autorizado - Sistema Académico</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #03145bff 0%, #0b2649ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .error-container {
            background: white;
            padding: 60px 40px;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .error-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
            margin: 0 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        
        .instituto-info {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #888;
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 40px 20px;
                margin: 20px;
            }
            
            .error-icon {
                font-size: 60px;
            }
            
            .error-title {
                font-size: 24px;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🚫</div>
        <h1 class="error-title">Acceso No Autorizado</h1>
        <p class="error-message">
            Lo sentimos, no tienes los permisos necesarios para acceder a esta página. 
            Por favor, contacta con el administrador del sistema si crees que esto es un error.
        </p>
        
        <div>
            <a href="javascript:history.back()" class="btn btn-secondary">← Volver Atrás</a>
            <a href="dashboard.php" class="btn">Ir al Dashboard</a>
        </div>
        
        <div class="instituto-info">
            <p><strong>Instituto de Educación Superior Tecnológico Público</strong></p>
            <p>"Alto Huallaga" - Tocache</p>
        </div>
    </div>
</body>
</html>