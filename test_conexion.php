<?php
// Script de prueba para verificar PHP y conexión a base de datos
require_once __DIR__ . '/config.php';

echo "<h1>Prueba de Entorno Fieldata</h1>";
echo "Versión de PHP: " . phpversion() . "<br>";

try {
    $stmt = $pdo->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    echo "Conexión a MariaDB: <span style='color:green;'>EXITOSA</span> (Versión: " . $row['version'] . ")<br>";
} catch (Exception $e) {
    echo "Conexión a MariaDB: <span style='color:red;'>FALLIDA</span> - " . $e->getMessage() . "<br>";
    echo "<i>Nota: Asegúrate de haber ejecutado el archivo esquema_db.sql en tu base de datos.</i><br>";
}

echo "<br><a href='index.php'>Volver al Inicio</a>";
?>
