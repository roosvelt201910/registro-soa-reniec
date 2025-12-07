<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Servicios SOA - IESTP Alto Huallaga</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .service-category {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            border-left: 5px solid;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-category:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .category-principales {
            border-left-color: #e74c3c;
        }

        .category-soporte {
            border-left-color: #3498db;
        }

        .category-externos {
            border-left-color: #f39c12;
        }

        .category-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .service-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .service-item:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.1);
        }

        .service-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .service-type {
            display: inline-block;
            background: #ecf0f1;
            color: #7f8c8d;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-bottom: 10px;
        }

        .service-responsibility {
            color: #5a6c7d;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .service-operations {
            font-size: 0.9em;
        }

        .operations-title {
            font-weight: bold;
            color: #34495e;
            margin-bottom: 5px;
        }

        .operation-item {
            color: #7f8c8d;
            margin-left: 15px;
            margin-bottom: 3px;
            position: relative;
        }

        .operation-item:before {
            content: "•";
            color: #3498db;
            font-weight: bold;
            position: absolute;
            left: -15px;
        }

        .service-endpoint {
            background: #f1f2f6;
            color: #2f3542;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            margin-top: 10px;
            border-left: 3px solid #3498db;
        }

        .architecture-section {
            margin-top: 50px;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
        }

        .architecture-title {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        .flow-diagram {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            border: 2px dashed #bdc3c7;
        }

        .flow-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #ecf0f1;
            border-radius: 8px;
        }

        .flow-number {
            background: #3498db;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .patterns-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .pattern-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #9b59b6;
        }

        .pattern-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #8e44ad;
            margin-bottom: 10px;
        }

        .pattern-description {
            color: #5a6c7d;
            line-height: 1.5;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .metric-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .metric-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .tech-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .tech-item {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏛️ Inventario de Servicios SOA</h1>
            <p>Plataforma de Registro de Usuarios - IESTP Alto Huallaga</p>
        </div>

        <div class="content">
            <!-- Servicios Grid -->
            <div class="services-grid">
                <!-- Servicios Principales -->
                <div class="service-category category-principales">
                    <div class="category-title">🔧 Servicios Principales</div>
                    
                    <div class="service-item">
                        <div class="service-name">ReniecAdapterService</div>
                        <div class="service-type">Servicio Adaptador/Abstracción</div>
                        <div class="service-responsibility">
                            Encapsula la comunicación con la API de RENIEC para validación de identidad
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">consultarIdentidad(dni: String)</div>
                            <div class="operation-item">validarFormatoRespuesta(response: Object)</div>
                            <div class="operation-item">transformarDatos(reniecData: Object)</div>
                        </div>
                        <div class="service-endpoint">/api/reniec-adapter/consultar</div>
                    </div>

                    <div class="service-item">
                        <div class="service-name">UserManagementService</div>
                        <div class="service-type">Servicio de Entidad/Datos</div>
                        <div class="service-responsibility">
                            Gestiona el CRUD de usuarios en la base de datos institucional
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">crearUsuario(usuario: Usuario)</div>
                            <div class="operation-item">asignarRol(userId: String, rol: TipoRol)</div>
                            <div class="operation-item">generarCredenciales(userId: String)</div>
                            <div class="operation-item">verificarExistencia(dni: String)</div>
                        </div>
                        <div class="service-endpoint">/api/users</div>
                    </div>

                    <div class="service-item">
                        <div class="service-name">RegistrationOrchestrationService</div>
                        <div class="service-type">Servicio de Proceso/Orquestación</div>
                        <div class="service-responsibility">
                            Coordina el flujo completo de registro de usuarios
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">registrarEstudiante(dni: String, email: String)</div>
                            <div class="operation-item">registrarDocente(dni: String, email: String, especialidad: String)</div>
                            <div class="operation-item">validarYCrearUsuario(datosRegistro: RegistroRequest)</div>
                        </div>
                        <div class="service-endpoint">/api/registro</div>
                    </div>
                </div>

                <!-- Servicios de Soporte -->
                <div class="service-category category-soporte">
                    <div class="category-title">🛠️ Servicios de Soporte</div>
                    
                    <div class="service-item">
                        <div class="service-name">AuthenticationService</div>
                        <div class="service-type">Servicio Utilitario</div>
                        <div class="service-responsibility">
                            Gestiona autenticación y autorización de usuarios
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">generarToken(usuario: Usuario)</div>
                            <div class="operation-item">validarToken(token: String)</div>
                            <div class="operation-item">renovarToken(refreshToken: String)</div>
                        </div>
                        <div class="service-endpoint">/api/auth</div>
                    </div>

                    <div class="service-item">
                        <div class="service-name">NotificationService</div>
                        <div class="service-type">Servicio Utilitario</div>
                        <div class="service-responsibility">
                            Envía notificaciones por email y SMS a usuarios
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">enviarCredenciales(email: String, credenciales: Credenciales)</div>
                            <div class="operation-item">notificarRegistroExitoso(usuario: Usuario)</div>
                            <div class="operation-item">enviarErrorRegistro(email: String, error: String)</div>
                        </div>
                        <div class="service-endpoint">/api/notifications</div>
                    </div>

                    <div class="service-item">
                        <div class="service-name">AuditService</div>
                        <div class="service-type">Servicio de Monitoreo</div>
                        <div class="service-responsibility">
                            Registra eventos de auditoría y trazabilidad
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">registrarEvento(evento: AuditEvent)</div>
                            <div class="operation-item">consultarHistorial(filtros: AuditFilter)</div>
                        </div>
                        <div class="service-endpoint">/api/audit</div>
                    </div>
                </div>

                <!-- Servicios Externos -->
                <div class="service-category category-externos">
                    <div class="category-title">🌐 Servicios Externos</div>
                    
                    <div class="service-item">
                        <div class="service-name">RENIEC Public API</div>
                        <div class="service-type">Servicio Externo Gubernamental</div>
                        <div class="service-responsibility">
                            Proporciona datos oficiales de identidad ciudadana
                        </div>
                        <div class="service-operations">
                            <div class="operations-title">Operaciones:</div>
                            <div class="operation-item">Consulta por DNI</div>
                            <div class="operation-item">Validación de identidad</div>
                            <div class="operation-item">Datos personales básicos</div>
                        </div>
                        <div class="service-endpoint">https://api.reniec.gob.pe/v1/dni/{numero_dni}</div>
                    </div>
                </div>
            </div>

            <!-- Arquitectura y Flujo -->
            <div class="architecture-section">
                <div class="architecture-title">🏗️ Flujo de Registro Completo</div>
                
                <div class="flow-diagram">
                    <div class="flow-step">
                        <div class="flow-number">1</div>
                        <div>Usuario ingresa DNI y email → Frontend Web</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">2</div>
                        <div>Frontend → Registration Orchestrator Service</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">3</div>
                        <div>Registration Orchestrator → RENIEC Adapter Service</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">4</div>
                        <div>RENIEC Adapter → API RENIEC (Validación externa)</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">5</div>
                        <div>API RENIEC → RENIEC Adapter (Datos validados)</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">6</div>
                        <div>RENIEC Adapter → Registration Orchestrator</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">7</div>
                        <div>Registration Orchestrator → User Management (Crear usuario)</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">8</div>
                        <div>User Management → Auth Service (Generar credenciales)</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">9</div>
                        <div>Auth Service → Notification Service (Enviar credenciales)</div>
                    </div>
                    <div class="flow-step">
                        <div class="flow-number">10</div>
                        <div>Audit Service (Registrar evento) + Confirmación al Frontend</div>
                    </div>
                </div>
            </div>

            <!-- Patrones SOA -->
            <div class="architecture-section">
                <div class="architecture-title">🎯 Patrones SOA Implementados</div>
                
                <div class="patterns-section">
                    <div class="pattern-card">
                        <div class="pattern-name">Service Abstraction</div>
                        <div class="pattern-description">
                            El RENIEC Adapter Service encapsula la complejidad de la API externa, 
                            proporcionando una interfaz simplificada a los servicios internos.
                        </div>
                    </div>

                    <div class="pattern-card">
                        <div class="pattern-name">Service Composition</div>
                        <div class="pattern-description">
                            El Registration Orchestrator compone múltiples servicios atómicos 
                            para realizar el proceso completo de registro de usuarios.
                        </div>
                    </div>

                    <div class="pattern-card">
                        <div class="pattern-name">Service Autonomy</div>
                        <div class="pattern-description">
                            Cada servicio mantiene control sobre su lógica y datos, 
                            permitiendo desarrollo y mantenimiento independiente.
                        </div>
                    </div>

                    <div class="pattern-card">
                        <div class="pattern-name">Service Statelessness</div>
                        <div class="pattern-description">
                            Todos los servicios REST no mantienen estado entre llamadas, 
                            mejorando escalabilidad y confiabilidad.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stack Tecnológico -->
            <div class="architecture-section">
                <div class="architecture-title">💻 Stack Tecnológico</div>
                
                <div class="tech-stack">
                    <div class="tech-item">Java 17+</div>
                    <div class="tech-item">Spring Boot 3.2</div>
                    <div class="tech-item">Spring Cloud Gateway</div>
                    <div class="tech-item">PostgreSQL 15</div>
                    <div class="tech-item">Docker</div>
                    <div class="tech-item">Angular 17</div>
                    <div class="tech-item">REST API</div>
                    <div class="tech-item">JSON</div>
                    <div class="tech-item">Eureka Server</div>
                    <div class="tech-item">Maven</div>
                </div>
            </div>

            <!-- Métricas -->
            <div class="architecture-section">
                <div class="architecture-title">📊 Métricas del Sistema</div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value">6</div>
                        <div class="metric-label">Servicios Totales</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">3</div>
                        <div class="metric-label">Servicios Principales</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">3</div>
                        <div class="metric-label">Servicios de Soporte</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">99.5%</div>
                        <div class="metric-label">SLA Objetivo</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">< 3s</div>
                        <div class="metric-label">Tiempo Respuesta</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">100+</div>
                        <div class="metric-label">Usuarios Concurrentes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>