<?php
/**
 * Script de Instalación - Crea la base de datos y tablas
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'controlmaq';

try {
    // Conectar sin base de datos para crearla
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Base de datos '$db_name' creada/verificada<br>";
    
    // Usar la base de datos
    $pdo->exec("USE $db_name");
    
    // Crear tablas
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        user_login VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255),
        telefono VARCHAR(20),
        rol ENUM('admin', 'empleado') DEFAULT 'empleado',
        activo TINYINT(1) DEFAULT 1,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS maquinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        marca VARCHAR(50),
        modelo VARCHAR(50),
        patente VARCHAR(20),
        precio_hora DECIMAL(15,2) DEFAULT 0,
        precio_dia DECIMAL(15,2) DEFAULT 0,
        estado ENUM('disponible', 'alquilado', 'mantenimiento') DEFAULT 'disponible',
        activo TINYINT(1) DEFAULT 1,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        telefono VARCHAR(20),
        email VARCHAR(100),
        direccion VARCHAR(255),
        activo TINYINT(1) DEFAULT 1,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS obras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        direccion VARCHAR(255),
        precio_hora DECIMAL(15,2) DEFAULT 0,
        precio_dia DECIMAL(15,2) DEFAULT 0,
        porcentaje_comision DECIMAL(5,2) DEFAULT 0,
        estado ENUM('activa', 'pausada', 'finalizada') DEFAULT 'activa',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS trabajos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        obra_id INT,
        empleado_id INT,
        maquina_id INT,
        fecha DATE NOT NULL,
        hora_inicio TIME,
        hora_fin TIME,
        horas_trabajadas DECIMAL(5,2) DEFAULT 0,
        tipo_pago ENUM('hora', 'dia', 'porcentaje') DEFAULT 'hora',
        monto DECIMAL(15,2) DEFAULT 0,
        descripcion TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (obra_id) REFERENCES obras(id),
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id),
        FOREIGN KEY (maquina_id) REFERENCES maquinas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS gastos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        concepto VARCHAR(255) NOT NULL,
        monto DECIMAL(15,2) NOT NULL,
        descripcion TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS reportes_diarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        texto TEXT,
        audio_url VARCHAR(500),
        procesado TINYINT(1) DEFAULT 0,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS memoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        clave VARCHAR(100),
        valor TEXT,
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS quincenas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        estado ENUM('abierta', 'cerrada', 'pagada') DEFAULT 'abierta',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS combustibles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        maquina_id INT,
        obra_id INT,
        fecha DATE NOT NULL,
        litros DECIMAL(10,2) NOT NULL,
        precio_unitario DECIMAL(10,2) DEFAULT 0,
        monto DECIMAL(15,2) DEFAULT 0,
        tipo ENUM('carga', 'saldo') DEFAULT 'carga',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id),
        FOREIGN KEY (maquina_id) REFERENCES maquinas(id),
        FOREIGN KEY (obra_id) REFERENCES obras(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS incidentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        maquina_id INT,
        fecha DATE NOT NULL,
        tipo ENUM('lluvia', 'breakdown', 'mantenimiento', 'ausente') NOT NULL,
        descripcion TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id),
        FOREIGN KEY (maquina_id) REFERENCES maquinas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS asistencia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        presente TINYINT(1) DEFAULT 0,
        login_hora TIME,
        FOREIGN KEY (empleado_id) REFERENCES usuarios(id),
        UNIQUE KEY unique_fecha_empleado (empleado_id, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "✅ Tablas creadas exitosamente<br>";
    
    // Insertar datos iniciales
    $pdo->exec("INSERT IGNORE INTO usuarios (id, nombre, user_login, password_hash, rol) VALUES 
        (1, 'Néstor (Admin)', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin')");
    
    $pdo->exec("INSERT IGNORE INTO maquinas (id, nombre, marca, modelo, precio_hora, precio_dia) VALUES 
        (1, 'Tractor John Deere', 'John Deere', '5080E', 150000, 1200000),
        (2, 'Tractor Massey Ferguson', 'Massey Ferguson', '275', 120000, 1000000),
        (3, 'Retroexcavadora', 'Caterpillar', '416E', 200000, 1600000)");
    
    $pdo->exec("INSERT IGNORE INTO clientes (id, nombre, telefono) VALUES (1, 'Cliente General', '0991xxxxxx')");
    
    echo "✅ Datos iniciales insertados<br>";
    echo "<br><strong>🎉 Instalación completa!</strong><br>";
    echo "Usuario: admin<br>";
    echo "Contraseña: admin123<br>";
    echo "<br><a href='./'>Ir al sistema</a>";
    
} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
