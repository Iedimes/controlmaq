<?php
// Configuración de base de datos MariaDB
$db_host = 'localhost';
$db_name = 'controlmaq';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Configuración de Gemini AI (Free Tier)
$gemini_api_key = ''; // El usuario debe poner su API Key aquí

