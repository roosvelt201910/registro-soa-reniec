<?php
// admin/backup.php - Sistema de Backup Ultra Completo con Todas las Funciones Habilitadas
session_start();

// ==================== CONFIGURACIÓN DE BASE DE DATOS ====================
$db_host = 'localhost';
$db_user = 'iespaltohuallaga_user_regaux';
$db_pass = ')wBRCeID[ldb%b^K';
$db_name = 'iespaltohuallaga_regauxiliar_bd';

// Crear conexión
$conexion = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Establecer charset UTF-8
$conexion->set_charset("utf8mb4");
date_default_timezone_set('America/Lima');

// ==================== VERIFICACIÓN DE PERMISOS ====================
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] != 'super_admin') {
    // Si no hay sesión, establecer valores por defecto para pruebas
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['tipo_usuario'] = 'super_admin';
        $_SESSION['nombres'] = 'Super';
        $_SESSION['apellidos'] = 'Administrador';
    }
}

// Directorio de backups
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
    // Crear archivo .htaccess para proteger el directorio
    file_put_contents($backup_dir . '.htaccess', 'Deny from all');
    // Crear archivo index.php vacío para mayor seguridad
    file_put_contents($backup_dir . 'index.php', '<?php // Silence is golden');
}

$message = '';
$messageType = '';

// ==================== FUNCIÓN PARA CREAR BACKUP ====================
function createBackup($conexion, $db_host, $db_user, $db_pass, $db_name, $backup_dir) {
    $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = $backup_dir . $backup_file;
    
    // Obtener todas las tablas
    $tables = array();
    $result = $conexion->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $output = "-- =============================================\n";
    $output .= "-- Sistema Académico - Backup de Base de Datos\n";
    $output .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Base de datos: " . $db_name . "\n";
    $output .= "-- Host: " . $db_host . "\n";
    $output .= "-- =============================================\n\n";
    
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "SET AUTOCOMMIT = 0;\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $output .= "/*!40101 SET NAMES utf8mb4 */;\n\n";
    
    // Crear base de datos
    $output .= "-- Crear base de datos si no existe\n";
    $output .= "CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
    $output .= "USE `$db_name`;\n\n";
    
    // Desactivar verificación de foreign keys temporalmente
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    // Recorrer todas las tablas
    foreach ($tables as $table) {
        // Estructura de la tabla
        $result = $conexion->query('SHOW CREATE TABLE ' . $table);
        $row = $result->fetch_row();
        
        $output .= "-- =============================================\n";
        $output .= "-- Estructura de tabla para `$table`\n";
        $output .= "-- =============================================\n\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row[1] . ";\n\n";
        
        // Datos de la tabla
        $result = $conexion->query('SELECT * FROM ' . $table);
        $num_fields = $result->field_count;
        $num_rows = $result->num_rows;
        
        if ($num_rows > 0) {
            $output .= "-- =============================================\n";
            $output .= "-- Volcado de datos para la tabla `$table`\n";
            $output .= "-- =============================================\n\n";
            
            // Obtener información de los campos
            $field_info = $conexion->query("SHOW COLUMNS FROM $table");
            $fields = array();
            while ($field = $field_info->fetch_assoc()) {
                $fields[] = $field['Field'];
            }
            
            // Insertar datos en lotes de 100 registros
            $batch_size = 100;
            $current_batch = 0;
            
            while ($row = $result->fetch_row()) {
                if ($current_batch % $batch_size == 0) {
                    if ($current_batch > 0) {
                        $output = rtrim($output, ",\n") . ";\n\n";
                    }
                    $output .= "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES\n";
                }
                
                $output .= "(";
                for ($j = 0; $j < $num_fields; $j++) {
                    if (isset($row[$j])) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        $row[$j] = str_replace("\r", "\\r", $row[$j]);
                        $row[$j] = str_replace("\t", "\\t", $row[$j]);
                        $output .= '"' . $row[$j] . '"';
                    } else {
                        $output .= 'NULL';
                    }
                    if ($j < ($num_fields - 1)) {
                        $output .= ',';
                    }
                }
                $output .= "),\n";
                $current_batch++;
            }
            
            if ($current_batch > 0) {
                $output = rtrim($output, ",\n") . ";\n\n";
            }
        }
    }
    
    // Reactivar verificación de foreign keys
    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "COMMIT;\n\n";
    
    $output .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
    
    // Guardar archivo
    if (file_put_contents($backup_path, $output)) {
        // Comprimir si el archivo es muy grande (más de 5MB)
        if (filesize($backup_path) > 5242880) {
            $zip = new ZipArchive();
            $zip_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backup_path, $backup_file);
                $zip->close();
                unlink($backup_path); // Eliminar archivo SQL sin comprimir
                return ['success' => true, 'filename' => basename($zip_file), 'size' => filesize($zip_file), 'compressed' => true];
            }
        }
        return ['success' => true, 'filename' => $backup_file, 'size' => filesize($backup_path), 'compressed' => false];
    } else {
        return ['success' => false, 'error' => 'No se pudo crear el archivo de backup'];
    }
}

// ==================== FUNCIÓN PARA OPTIMIZAR BASE DE DATOS ====================
function optimizeDatabase($conexion) {
    $tables = array();
    $result = $conexion->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $optimized = 0;
    $errors = 0;
    
    foreach ($tables as $table) {
        if ($conexion->query("OPTIMIZE TABLE `$table`")) {
            $optimized++;
        } else {
            $errors++;
        }
    }
    
    return ['optimized' => $optimized, 'errors' => $errors];
}

// ==================== FUNCIÓN PARA LIMPIAR BACKUPS ANTIGUOS ====================
function cleanOldBackups($backup_dir, $days = 30) {
    $deleted = 0;
    $errors = 0;
    $time_limit = time() - ($days * 24 * 60 * 60);
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'sql' || pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
                $filepath = $backup_dir . $file;
                if (filemtime($filepath) < $time_limit) {
                    if (unlink($filepath)) {
                        $deleted++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
    }
    
    return ['deleted' => $deleted, 'errors' => $errors];
}

// ==================== PROCESAR ACCIONES ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                $result = createBackup($conexion, $db_host, $db_user, $db_pass, $db_name, $backup_dir);
                if ($result['success']) {
                    $compressed = $result['compressed'] ? ' (comprimido)' : '';
                    $message = "✅ Backup creado exitosamente: " . $result['filename'] . $compressed;
                    $messageType = 'success';
                } else {
                    $message = "❌ Error al crear backup: " . $result['error'];
                    $messageType = 'error';
                }
                break;
                
            case 'delete_backup':
                $filename = $_POST['filename'];
                $filepath = $backup_dir . $filename;
                if (file_exists($filepath) && unlink($filepath)) {
                    $message = "✅ Backup eliminado exitosamente";
                    $messageType = 'success';
                } else {
                    $message = "❌ Error al eliminar el backup";
                    $messageType = 'error';
                }
                break;
                
            case 'restore_backup':
                $filename = $_POST['filename'];
                $filepath = $backup_dir . $filename;
                
                if (file_exists($filepath)) {
                    // Si es un archivo ZIP, descomprimirlo primero
                    if (pathinfo($filename, PATHINFO_EXTENSION) == 'zip') {
                        $zip = new ZipArchive();
                        if ($zip->open($filepath) === TRUE) {
                            $temp_dir = $backup_dir . 'temp_' . uniqid() . '/';
                            mkdir($temp_dir);
                            $zip->extractTo($temp_dir);
                            $zip->close();
                            
                            // Buscar el archivo SQL extraído
                            $extracted_files = scandir($temp_dir);
                            foreach ($extracted_files as $file) {
                                if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                                    $sql = file_get_contents($temp_dir . $file);
                                    break;
                                }
                            }
                            
                            // Limpiar archivos temporales
                            array_map('unlink', glob($temp_dir . '*'));
                            rmdir($temp_dir);
                        } else {
                            $message = "❌ Error al descomprimir el archivo";
                            $messageType = 'error';
                            break;
                        }
                    } else {
                        $sql = file_get_contents($filepath);
                    }
                    
                    // Ejecutar el SQL
                    $conexion->query('SET FOREIGN_KEY_CHECKS=0');
                    
                    // Usar multi_query para ejecutar múltiples consultas
                    if ($conexion->multi_query($sql)) {
                        do {
                            // Procesar cada resultado
                            if ($result = $conexion->store_result()) {
                                $result->free();
                            }
                        } while ($conexion->more_results() && $conexion->next_result());
                        
                        $message = "✅ Base de datos restaurada exitosamente desde: " . $filename;
                        $messageType = 'success';
                    } else {
                        $message = "❌ Error al restaurar la base de datos: " . $conexion->error;
                        $messageType = 'error';
                    }
                    
                    $conexion->query('SET FOREIGN_KEY_CHECKS=1');
                } else {
                    $message = "❌ Archivo de backup no encontrado";
                    $messageType = 'error';
                }
                break;
                
            case 'optimize_database':
                $result = optimizeDatabase($conexion);
                $message = "✅ Base de datos optimizada: " . $result['optimized'] . " tablas procesadas";
                if ($result['errors'] > 0) {
                    $message .= " (con " . $result['errors'] . " errores)";
                }
                $messageType = 'success';
                break;
                
            case 'clean_old_backups':
                $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
                $result = cleanOldBackups($backup_dir, $days);
                if ($result['deleted'] > 0) {
                    $message = "✅ Se eliminaron " . $result['deleted'] . " backups antiguos (más de $days días)";
                    $messageType = 'success';
                } else {
                    $message = "ℹ️ No hay backups antiguos para eliminar";
                    $messageType = 'info';
                }
                if ($result['errors'] > 0) {
                    $message .= " (con " . $result['errors'] . " errores)";
                }
                break;
        }
    }
}

// ==================== PROCESAR CARGA DE ARCHIVO ====================
if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
    $allowed_extensions = ['sql', 'zip'];
    $file_extension = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
    
    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = 'upload_' . date('Y-m-d_H-i-s') . '.' . $file_extension;
        $upload_path = $backup_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $upload_path)) {
            $message = "✅ Archivo cargado exitosamente: " . $new_filename;
            $messageType = 'success';
        } else {
            $message = "❌ Error al cargar el archivo";
            $messageType = 'error';
        }
    } else {
        $message = "❌ Tipo de archivo no permitido. Solo se aceptan archivos SQL y ZIP";
        $messageType = 'error';
    }
}

// ==================== DESCARGAR BACKUP ====================
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// ==================== OBTENER LISTA DE BACKUPS ====================
$backups = array();
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'sql' || pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
            $filepath = $backup_dir . $file;
            $backups[] = array(
                'filename' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            );
        }
    }
}

// ==================== OBTENER INFORMACIÓN DE LA BASE DE DATOS ====================
$db_size = 0;
$table_count = 0;
$record_count = 0;
$table_info = array();

$result = $conexion->query("SELECT table_name, data_length + index_length AS size, table_rows 
                           FROM information_schema.tables 
                           WHERE table_schema = '$db_name'");
while ($row = $result->fetch_assoc()) {
    $db_size += $row['size'];
    $table_count++;
    $record_count += $row['table_rows'];
    $table_info[] = $row;
}

// Calcular espacio disponible en disco
$free_space = disk_free_space($backup_dir);
$total_space = disk_total_space($backup_dir);
$used_space = $total_space - $free_space;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Sistema de Backup y Restauración - Sistema Académico</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
        }
        
        /* Partículas animadas */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
            }
        }
        
        /* Header Ultra */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
        }
        
        .header h1 {
            font-size: 2em;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }
        
        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1em;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .card h2 {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f0f0f0;
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            animation: expand 1s ease;
        }
        
        @keyframes expand {
            from { width: 0; }
            to { width: 100px; }
        }
        
        /* Action Buttons */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .action-btn:hover::before {
            width: 300%;
            height: 300%;
        }
        
        .action-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .action-btn.success {
            background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
        }
        
        .action-btn.warning {
            background: linear-gradient(135deg, #ffb300 0%, #ffc107 100%);
        }
        
        .action-btn.danger {
            background: linear-gradient(135deg, #ff5252 0%, #ff1744 100%);
        }
        
        .action-btn.info {
            background: linear-gradient(135deg, #00bcd4 0%, #00acc1 100%);
        }
        
        .action-icon {
            font-size: 2.5em;
        }
        
        /* Backup Table */
        .backup-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        
        .backup-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .backup-table th {
            padding: 18px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .backup-table th:first-child {
            border-top-left-radius: 15px;
        }
        
        .backup-table th:last-child {
            border-top-right-radius: 15px;
        }
        
        .backup-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .backup-table tbody tr {
            background: white;
            transition: all 0.3s;
        }
        
        .backup-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
            margin: 0 5px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
            color: white;
        }
        
        .btn-restore {
            background: linear-gradient(135deg, #ffb300 0%, #ffc107 100%);
            color: white;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ff5252 0%, #ff1744 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Alert Messages */
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            position: relative;
            animation: slideInAlert 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @keyframes slideInAlert {
            from {
                opacity: 0;
                transform: translateX(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #664d03;
            border-left: 5px solid #ffc107;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #b8daff 100%);
            color: #004085;
            border-left: 5px solid #17a2b8;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            animation: zoomIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .modal-header.danger {
            background: linear-gradient(135deg, #ff5252 0%, #ff1744 100%);
        }
        
        .modal-header.warning {
            background: linear-gradient(135deg, #ffb300 0%, #ffc107 100%);
        }
        
        .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .modal-footer {
            padding: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        /* Upload Area */
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            background: #e9ecef;
            border-color: #764ba2;
        }
        
        .upload-area.dragover {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        
        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 25px;
            height: 25px;
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 5em;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            animation: progressAnimation 2s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        @keyframes progressAnimation {
            from { width: 0; }
        }
        
        /* File Type Badge */
        .file-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .file-type.sql {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .file-type.zip {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .backup-table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas de fondo -->
    <div class="particles">
        <?php for($i = 0; $i < 30; $i++): ?>
            <div class="particle" style="
                width: <?php echo rand(5, 15); ?>px;
                height: <?php echo rand(5, 15); ?>px;
                left: <?php echo rand(0, 100); ?>%;
                top: <?php echo rand(0, 100); ?>%;
                animation-delay: <?php echo rand(0, 20); ?>s;
                animation-duration: <?php echo rand(15, 30); ?>s;
            "></div>
        <?php endfor; ?>
    </div>
    
    <div class="header">
        <div class="header-content">
            <h1>
                <span style="font-size: 1.5em;">🔐</span>
                Sistema de Backup y Restauración
            </h1>
            <div>
                <span style="margin-right: 20px; font-weight: 600;">
                    👤 <?php echo $_SESSION['apellidos'] . ', ' . $_SESSION['nombres']; ?>
                </span>
                <a href="../dashboard.php" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    ← Volver al Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <span style="font-size: 2em;">
                    <?php 
                    echo $messageType == 'success' ? '✅' : 
                         ($messageType == 'error' ? '❌' : 
                         ($messageType == 'warning' ? '⚠️' : 'ℹ️')); 
                    ?>
                </span>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas de la Base de Datos -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-number"><?php echo count($backups); ?></div>
                <div class="stat-label">Backups Disponibles</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo $table_count; ?></div>
                <div class="stat-label">Tablas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo number_format($record_count); ?></div>
                <div class="stat-label">Registros Totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💿</div>
                <div class="stat-number"><?php echo number_format($db_size / 1048576, 2); ?> MB</div>
                <div class="stat-label">Tamaño de BD</div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="card">
            <h2>
                <span>⚡</span>
                Acciones Rápidas
            </h2>
            
            <div class="action-grid">
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="action-btn success">
                        <span class="action-icon">💾</span>
                        <span>CREAR BACKUP</span>
                        <small>Genera una copia de seguridad completa</small>
                    </button>
                </form>
                
                <button class="action-btn info" onclick="showUploadModal()">
                    <span class="action-icon">📤</span>
                    <span>SUBIR BACKUP</span>
                    <small>Restaurar desde archivo local</small>
                </button>
                
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="action" value="optimize_database">
                    <button type="submit" class="action-btn warning" onclick="return confirm('¿Desea optimizar todas las tablas de la base de datos?');">
                        <span class="action-icon">⚙️</span>
                        <span>OPTIMIZAR BD</span>
                        <small>Mejorar rendimiento de tablas</small>
                    </button>
                </form>
                
                <button class="action-btn danger" onclick="showCleanupModal()">
                    <span class="action-icon">🧹</span>
                    <span>LIMPIAR ANTIGUOS</span>
                    <small>Eliminar backups obsoletos</small>
                </button>
            </div>
        </div>
        
        <!-- Lista de Backups -->
        <div class="card">
            <h2>
                <span>📁</span>
                Backups Disponibles (<?php echo count($backups); ?>)
            </h2>
            
            <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No hay backups disponibles</h3>
                    <p>Crea tu primer backup para proteger tu información</p>
                </div>
            <?php else: ?>
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Tipo</th>
                            <th>Fecha y Hora</th>
                            <th>Tamaño</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <strong>📄 <?php echo $backup['filename']; ?></strong>
                                </td>
                                <td>
                                    <span class="file-type <?php echo $backup['type']; ?>">
                                        <?php echo strtoupper($backup['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    📅 <?php echo date('d/m/Y H:i:s', $backup['date']); ?>
                                    <?php
                                    $age_days = floor((time() - $backup['date']) / 86400);
                                    if ($age_days > 30) {
                                        echo '<span style="color: #ff5252; font-weight: 600;"> (' . $age_days . ' días)</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    💿 <?php echo number_format($backup['size'] / 1024, 2); ?> KB
                                </td>
                                <td>
                                    <a href="?download=<?php echo $backup['filename']; ?>" class="btn btn-download">
                                        ⬇️ Descargar
                                    </a>
                                    <button class="btn btn-restore" onclick="confirmRestore('<?php echo $backup['filename']; ?>')">
                                        🔄 Restaurar
                                    </button>
                                    <button class="btn btn-delete" onclick="confirmDelete('<?php echo $backup['filename']; ?>')">
                                        🗑️ Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Información del Sistema -->
        <div class="card">
            <h2>
                <span>ℹ️</span>
                Información del Sistema
            </h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #667eea; margin-bottom: 15px;">📊 Estado del Almacenamiento</h4>
                    <?php
                    $total_backup_size = 0;
                    foreach ($backups as $backup) {
                        $total_backup_size += $backup['size'];
                    }
                    $percentage = ($db_size > 0) ? min(($total_backup_size / $db_size) * 100, 100) : 0;
                    ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%">
                            <?php echo number_format($total_backup_size / 1048576, 2); ?> MB de backups
                        </div>
                    </div>
                    <p style="margin-top: 10px; color: #6c757d;">
                        Espacio en disco: <?php echo number_format($free_space / 1073741824, 2); ?> GB libres
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #667eea; margin-bottom: 15px;">⏰ Información de Backups</h4>
                    <p style="font-size: 1.1em; color: #333; margin-bottom: 10px;">
                        <strong>Último Backup:</strong><br>
                        <?php 
                        if (!empty($backups)) {
                            echo "📅 " . date('d/m/Y H:i:s', $backups[0]['date']);
                        } else {
                            echo "No hay backups realizados";
                        }
                        ?>
                    </p>
                    <p style="color: #6c757d;">
                        <strong>Total de backups:</strong> <?php echo count($backups); ?><br>
                        <strong>Tamaño total:</strong> <?php echo number_format($total_backup_size / 1048576, 2); ?> MB
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #667eea; margin-bottom: 15px;">📋 Tablas de la Base de Datos</h4>
                    <div style="max-height: 150px; overflow-y: auto;">
                        <?php foreach ($table_info as $table): ?>
                            <p style="margin: 5px 0; color: #6c757d;">
                                • <?php echo $table['table_name']; ?> 
                                <span style="color: #999;">(<?php echo number_format($table['table_rows']); ?> registros)</span>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmación -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <h2 id="modalTitle">⚠️ Confirmar Acción</h2>
            </div>
            <div class="modal-body">
                <p id="modalMessage" style="font-size: 1.1em; color: #333;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn" style="background: #28a745; color: white;" onclick="confirmAction()">
                    ✅ Confirmar
                </button>
                <button class="btn" style="background: #dc3545; color: white;" onclick="closeModal()">
                    ❌ Cancelar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Carga de Archivo -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📤 Cargar Archivo de Backup</h2>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" id="uploadArea">
                        <span style="font-size: 3em;">📁</span>
                        <h3>Arrastra tu archivo aquí</h3>
                        <p>o haz clic para seleccionar</p>
                        <p style="color: #6c757d; font-size: 14px;">Formatos aceptados: SQL, ZIP</p>
                        <input type="file" name="backup_file" id="fileInput" accept=".sql,.zip" style="display: none;">
                    </div>
                    <div id="fileInfo" style="margin-top: 20px; display: none;">
                        <p><strong>Archivo seleccionado:</strong> <span id="fileName"></span></p>
                        <p><strong>Tamaño:</strong> <span id="fileSize"></span></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" style="background: #28a745; color: white;" onclick="uploadFile()">
                    ⬆️ Subir Archivo
                </button>
                <button class="btn" style="background: #dc3545; color: white;" onclick="closeUploadModal()">
                    ❌ Cancelar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Limpieza -->
    <div id="cleanupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header warning">
                <h2>🧹 Limpiar Backups Antiguos</h2>
            </div>
            <div class="modal-body">
                <form id="cleanupForm">
                    <div class="form-group">
                        <label>Eliminar backups con más de:</label>
                        <select name="days" id="cleanupDays">
                            <option value="7">7 días</option>
                            <option value="15">15 días</option>
                            <option value="30" selected>30 días</option>
                            <option value="60">60 días</option>
                            <option value="90">90 días</option>
                        </select>
                    </div>
                    <p style="color: #ff5252; font-weight: 600;">
                        ⚠️ Esta acción eliminará permanentemente los backups antiguos
                    </p>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" style="background: #ff5252; color: white;" onclick="executeCleanup()">
                    🗑️ Eliminar Antiguos
                </button>
                <button class="btn" style="background: #6c757d; color: white;" onclick="closeCleanupModal()">
                    ❌ Cancelar
                </button>
            </div>
        </div>
    </div>
    
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="filename" id="formFilename">
        <input type="hidden" name="days" id="formDays">
    </form>
    
    <script>
        let currentAction = '';
        let currentFilename = '';
        
        // Función para confirmar restauración
        function confirmRestore(filename) {
            currentAction = 'restore_backup';
            currentFilename = filename;
            document.getElementById('modalHeader').className = 'modal-header warning';
            document.getElementById('modalTitle').innerHTML = '🔄 Confirmar Restauración';
            document.getElementById('modalMessage').innerHTML = 
                '¿Está seguro de restaurar la base de datos desde el archivo:<br><strong>' + filename + '</strong>?<br><br>' +
                '⚠️ <strong>ADVERTENCIA:</strong> Esto reemplazará todos los datos actuales.<br>' +
                '⚠️ Se recomienda crear un backup antes de continuar.';
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        // Función para confirmar eliminación
        function confirmDelete(filename) {
            currentAction = 'delete_backup';
            currentFilename = filename;
            document.getElementById('modalHeader').className = 'modal-header danger';
            document.getElementById('modalTitle').innerHTML = '🗑️ Confirmar Eliminación';
            document.getElementById('modalMessage').innerHTML = 
                '¿Está seguro de eliminar el backup:<br><strong>' + filename + '</strong>?<br><br>' +
                '⚠️ Esta acción no se puede deshacer.';
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        // Confirmar acción
        function confirmAction() {
            document.getElementById('formAction').value = currentAction;
            document.getElementById('formFilename').value = currentFilename;
            document.getElementById('actionForm').submit();
        }
        
        // Cerrar modal
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // Mostrar modal de carga
        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }
        
        // Cerrar modal de carga
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('fileInput').value = '';
            document.getElementById('fileInfo').style.display = 'none';
        }
        
        // Mostrar modal de limpieza
        function showCleanupModal() {
            document.getElementById('cleanupModal').style.display = 'block';
        }
        
        // Cerrar modal de limpieza
        function closeCleanupModal() {
            document.getElementById('cleanupModal').style.display = 'none';
        }
        
        // Ejecutar limpieza
        function executeCleanup() {
            const days = document.getElementById('cleanupDays').value;
            document.getElementById('formAction').value = 'clean_old_backups';
            document.getElementById('formDays').value = days;
            document.getElementById('actionForm').submit();
        }
        
        // Subir archivo
        function uploadFile() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput.files.length > 0) {
                document.getElementById('uploadForm').submit();
            } else {
                alert('Por favor selecciona un archivo');
            }
        }
        
        // Configurar área de carga
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFileInfo(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });
        
        function showFileInfo(file) {
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            document.getElementById('fileInfo').style.display = 'block';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-ocultar alertas
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideInAlert 0.5s ease reverse';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Animación de entrada para las cards
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    </script>
</body>
</html>