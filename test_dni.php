<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Archivo dni_lookup.php test<br>";
echo "Método: " . $_SERVER['REQUEST_METHOD'] . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    echo "Input recibido: " . $input . "<br>";
    
    $decoded = json_decode($input, true);
    echo "JSON decodificado: ";
    var_dump($decoded);
} else {
    echo "Usar método POST";
}
?>