<?php
/**
 * Guardar Usuario - API
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
    $user_login = trim($_POST['user_login'] ?? '');
    $password = $_POST['password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $rol = $_POST['rol'] ?? 'empleado';

    if (!$nombre || !$user_login) {
        echo json_encode(['status' => 'error', 'message' => 'Nombre y usuario son requeridos']);
        exit;
    }

    if ($id) {
        // Actualizar
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare("UPDATE usuarios SET nombre = ?, user_login = ?, password_hash = ?, telefono = ?, rol = ? WHERE id = ?");
            $st->execute([$nombre, $user_login, $hash, $telefono, $rol, $id]);
        } else {
            $st = $pdo->prepare("UPDATE usuarios SET nombre = ?, user_login = ?, telefono = ?, rol = ? WHERE id = ?");
            $st->execute([$nombre, $user_login, $telefono, $rol, $id]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado']);
    } else {
        // Nuevo
        if (!$password) {
            echo json_encode(['status' => 'error', 'message' => 'Contraseña requerida']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare("INSERT INTO usuarios (nombre, user_login, password_hash, telefono, rol) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$nombre, $user_login, $hash, $telefono, $rol]);
        echo json_encode(['status' => 'success', 'message' => 'Usuario creado']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
