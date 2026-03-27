<?php
// Script para asegurar que existan datos base para las pruebas
require_once __DIR__ . '/../config.php';

try {
    // 1. Verificar si hay establecimientos
    $stmt = $pdo->query("SELECT COUNT(*) FROM establecimientos");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO establecimientos (nombre, ubicacion) VALUES ('Estancia Mi Ranchito', 'Chaco, Paraguay')");
        $est_id = $pdo->lastInsertId();
        
        // 2. Crear lotes por defecto
        $pdo->exec("INSERT INTO lotes (establecimiento_id, nombre, superficie_ha, uso_actual) VALUES 
            ($est_id, 'Lote Norte', 150.5, 'Agricultura'),
            ($est_id, 'Potrero Corral', 50.0, 'Ganaderia')");
            
        // 3. Crear insumos base
        $pdo->exec("INSERT INTO insumos (nombre, categoria, cantidad_actual, unidad_medida) VALUES 
            ('Gasoil Euro', 'Combustible', 1000, 'Litros'),
            ('Semilla de Soja RR', 'Semilla', 500, 'Bolsas')");
    }
} catch (Exception $e) {
    // Silencioso para no romper la app
}
?>
