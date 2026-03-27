<?php
/**
 * Test de Webhook - CodeStudioAp
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO DE WEBHOOK ===\n";

try {
    require_once __DIR__ . '/../config.php';
    echo "1. Config cargado.\n";
    
    // Simular un mensaje
    $u_id = 1; // Admin
    $msg = "Prueba de 50.000";
    $monto = 50000;
    
    echo "2. Probando INSERT en actividades...\n";
    $st = $pdo->prepare("INSERT INTO actividades (usuario_id, lote_id, tipo_registro, tipo_actividad, cantidad, unidad, monto, fecha) VALUES (?, 1, 'Financiero', ?, 1, 'Unidad', ?, CURDATE())");
    $st->execute([$u_id, $msg, $monto]);
    echo "   - [OK] Registro insertado ID: " . $pdo->lastInsertId() . "\n";

    echo "3. Probando SELECT resumen...\n";
    $st = $pdo->prepare("SELECT COUNT(*) as t FROM actividades WHERE usuario_id = ?");
    $st->execute([$u_id]);
    echo "   - [OK] Registros totales: " . $st->fetchColumn() . "\n";

    echo "\n=== TODO FUNCIONA CORRECTAMENTE EN EL BACKEND ===\n";

} catch (Throwable $t) {
    echo "\n❌ ERROR DETECTADO: " . $t->getMessage() . "\n";
    echo "En línea: " . $t->getLine() . " de " . $t->getFile() . "\n";
}
?>
