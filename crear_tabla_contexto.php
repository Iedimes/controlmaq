<?php
require_once __DIR__ . '/config.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contexto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_wa VARCHAR(20) NOT NULL UNIQUE,
        categoria VARCHAR(50),
        cantidad DECIMAL(10,2),
        unidad VARCHAR(20),
        producto VARCHAR(50),
        lote VARCHAR(50),
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabla 'contexto' creada con éxito.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
