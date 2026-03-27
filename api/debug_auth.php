<?php
/**
 * Diagnóstico y Reseteo de Password - CodeStudioAp
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== REPARACIÓN DE CREDENCIALES ===\n";

try {
    require_once __DIR__ . '/../config.php';
    
    echo "1. Reseteando password de 'admin'...\n";
    $nuevo_hash = password_hash('Nestor2026', PASSWORD_BCRYPT);
    
    // Intentamos actualizar si existe, o insertar si no.
    $st = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE user_login = 'admin'");
    $st->execute([$nuevo_hash]);
    
    if ($st->rowCount() > 0) {
        echo "   - [OK] Password actualizado para 'admin'.\n";
    } else {
        echo "   - [INFO] No se encontró 'admin' para actualizar, intentando insertar...\n";
        $pdo->prepare("INSERT IGNORE INTO usuarios (nombre, user_login, password_hash, rol) VALUES ('Néstor (Admin)', 'admin', ?, 'admin')")
            ->execute([$nuevo_hash]);
        echo "   - [OK] Usuario insertado/verificado.\n";
    }

    echo "2. Verificando estado final...\n";
    $check = $pdo->query("SELECT user_login, password_hash FROM usuarios WHERE user_login = 'admin'")->fetch();
    echo "   - Usuario: " . $check['user_login'] . "\n";
    echo "   - Hash generado: " . substr($check['password_hash'], 0, 10) . "...\n";

    echo "\n=== ¡CREDENCIALES LISTAS! ===\n";
    echo "Ahora prueba loguearte con: admin / Nestor2026";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
?>
