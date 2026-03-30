<?php
/**
 * Guardar Combustible - API
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $data = $_POST;
    
    $empleado_id = (int)$data['empleado_id'];
    $maquina_id = (int)$data['maquina_id'] ?: null;
    $fecha = $data['fecha'];
    $litros = (float)$data['litros'];
    
    $st = $pdo->prepare("INSERT INTO combustibles (empleado_id, maquina_id, fecha, litros, tipo) VALUES (?, ?, ?, ?, 'carga')");
    $st->execute([$empleado_id, $maquina_id, $fecha, $litros]);
    
    echo json_encode(['status' => 'success', 'message' => 'Combustible registrado correctamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
