<?php
/**
 * Guardar Gasto - API
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $data = $_POST;
    
    $empleado_id = (int)$data['empleado_id'];
    $fecha = $data['fecha'];
    $concepto = $data['concepto'];
    $monto = (float)$data['monto'];
    
    $st = $pdo->prepare("INSERT INTO gastos (empleado_id, fecha, concepto, monto) VALUES (?, ?, ?, ?)");
    $st->execute([$empleado_id, $fecha, $concepto, $monto]);
    
    echo json_encode(['status' => 'success', 'message' => 'Gasto registrado correctamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
