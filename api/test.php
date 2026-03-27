<?php
/**
 * Test de POST - CodeStudioAp
 * Verifica si el servidor acepta peticiones POST con JSON.
 */
header('Content-Type: application/json');

// Leer el cuerpo para verificar persistencia
$input = file_get_contents("php://input");
$data = json_decode($input, true);

echo json_encode([
    "status" => "success",
    "mensaje" => "Conexión de prueba OK",
    "received" => $data
]);
exit;
?>
