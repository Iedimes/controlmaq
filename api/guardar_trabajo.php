<?php
/**
 * Guardar Trabajo - API
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $data = $_POST;
    
    $empleado_id = (int)$data['empleado_id'];
    $obra_id = (int)$data['obra_id'];
    $maquina_id = (int)$data['maquina_id'];
    $fecha = $data['fecha'];
    $horas = (float)$data['horas'];
    $tipo_pago = $data['tipo_pago'];
    $monto = (float)$data['monto'];
    $descripcion = $data['descripcion'] ?? '';
    
    $st = $pdo->prepare("INSERT INTO trabajos (empleado_id, obra_id, maquina_id, fecha, horas_trabajadas, tipo_pago, monto, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$empleado_id, $obra_id, $maquina_id, $fecha, $horas, $tipo_pago, $monto, $descripcion]);
    
    echo json_encode(['status' => 'success', 'message' => 'Trabajo registrado correctamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
