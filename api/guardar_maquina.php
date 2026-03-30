<?php
/**
 * Guardar Máquina - API
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id = isset($_POST['id']) && $_POST['id'] ? (int)$_POST['id'] : null;
    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $patente = trim($_POST['patente'] ?? '');
    $precio_hora = (float)($_POST['precio_hora'] ?? 0);
    $precio_dia = (float)($_POST['precio_dia'] ?? 0);
    $estado = $_POST['estado'] ?? 'disponible';
    $action = $_POST['action'] ?? 'save';

    if (!$nombre) {
        echo json_encode(['status' => 'error', 'message' => 'Nombre requerido']);
        exit;
    }

    if ($action === 'delete' && $id) {
        $st = $pdo->prepare("DELETE FROM maquinas WHERE id = ?");
        $st->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Máquina eliminada']);
        exit;
    }

    if ($id) {
        $st = $pdo->prepare("UPDATE maquinas SET nombre = ?, marca = ?, modelo = ?, patente = ?, precio_hora = ?, precio_dia = ?, estado = ? WHERE id = ?");
        $st->execute([$nombre, $marca, $modelo, $patente, $precio_hora, $precio_dia, $estado, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Máquina actualizada']);
    } else {
        $st = $pdo->prepare("INSERT INTO maquinas (nombre, marca, modelo, patente, precio_hora, precio_dia, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$nombre, $marca, $modelo, $patente, $precio_hora, $precio_dia, $estado]);
        echo json_encode(['status' => 'success', 'message' => 'Máquina creada']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
