<?php
/**
 * Guardar Obra - API
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
    $cliente_id = (int)($_POST['cliente_id'] ?? 1);
    $precio_hora = (float)($_POST['precio_hora'] ?? 0);
    $precio_dia = (float)($_POST['precio_dia'] ?? 0);
    $estado = $_POST['estado'] ?? 'activa';
    $action = $_POST['action'] ?? 'save';

    if (!$nombre) {
        echo json_encode(['status' => 'error', 'message' => 'Nombre requerido']);
        exit;
    }

    if ($action === 'delete' && $id) {
        $st = $pdo->prepare("DELETE FROM obras WHERE id = ?");
        $st->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Obra eliminada']);
        exit;
    }

    if ($id) {
        $st = $pdo->prepare("UPDATE obras SET nombre = ?, cliente_id = ?, precio_hora = ?, precio_dia = ?, estado = ? WHERE id = ?");
        $st->execute([$nombre, $cliente_id, $precio_hora, $precio_dia, $estado, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Obra actualizada']);
    } else {
        $st = $pdo->prepare("INSERT INTO obras (nombre, cliente_id, precio_hora, precio_dia, estado) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$nombre, $cliente_id, $precio_hora, $precio_dia, $estado]);
        echo json_encode(['status' => 'success', 'message' => 'Obra creada']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
