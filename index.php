<?php
// Verificar si el sistema está instalado
if (!file_exists('config/database.php')) {
    // Si no existe el archivo de configuración, redirigir al instalador
    header('Location: install_database.php');
    exit();
}

// Si ya está instalado, redirigir al login
header('Location: login.php');
exit();
?>