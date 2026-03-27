<?php
/**
 * API de Autenticación - CodeStudioAp
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

class Auth {
    public static function iniciarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            $path = __DIR__ . "/../sesiones";
            if (!is_dir($path)) { @mkdir($path, 0755, true); }
            if (is_writable($path)) { session_save_path($path); }
            session_start(['cookie_httponly' => true]);
        }
    }
    public static function login($id, $nom, $rol) {
        self::iniciarSesion();
        $_SESSION['usuario_id'] = $id;
        $_SESSION['nombre'] = $nom;
        $_SESSION['rol'] = $rol;
    }
}

try {
    $input = file_get_contents("php://input");
    $d = json_decode($input, true);

    if (isset($d['action']) && $d['action'] === 'login') {
        $user = $d['user_login'] ?? '';
        $pass = $d['password'] ?? '';
        
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE user_login = ? LIMIT 1");
        $st->execute([$user]);
        $u = $st->fetch();
        
        if ($u && password_verify($pass, $u['password_hash'])) {
            Auth::login($u['id'], $u['nombre'], $u['rol']);
            echo json_encode(["status" => "success", "mensaje" => "¡Bienvenido!"]);
        } else {
            echo json_encode(["status" => "error", "mensaje" => "Credenciales incorrectas"]);
        }
    } else {
        echo json_encode(["status" => "error", "mensaje" => "Acción inválida"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "mensaje" => "Error de sistema: " . $e->getMessage()]);
}
exit;
?>
