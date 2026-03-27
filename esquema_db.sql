-- ControlMaq - Sistema de Control para Alquiler de Tractores y Máquinas
-- Empresa de Alquiler de Máquinas

-- 1. Usuarios (Dueño/Admin y Empleados)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    user_login VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    telefono VARCHAR(20),
    rol ENUM('admin', 'empleado') DEFAULT 'empleado',
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Máquinas/Tractores
CREATE TABLE IF NOT EXISTS maquinas (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Obras
CREATE TABLE IF NOT EXISTS obras (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Trabajos Registrados
CREATE TABLE IF NOT EXISTS trabajos (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Gastos Diarios por Empleado
CREATE TABLE IF NOT EXISTS gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Reportes Diarios (texto/audio del empleado)
CREATE TABLE IF NOT EXISTS reportes_diarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    texto TEXT,
    audio_url VARCHAR(500),
    procesado TINYINT(1) DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Memoria del Chat (para entender contexto)
CREATE TABLE IF NOT EXISTS memoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    clave VARCHAR(100),
    valor TEXT,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Configuración de Quincenas
CREATE TABLE IF NOT EXISTS quincenas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('abierta', 'cerrada', 'pagada') DEFAULT 'abierta',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === DATOS INICIALES ===

-- Usuario Administrador (cambiar contraseña luego)
INSERT IGNORE INTO usuarios (id, nombre, user_login, rol) VALUES 
(1, 'Néstor (Admin)', 'admin', 'admin');

-- Máquinas de ejemplo
INSERT IGNORE INTO maquinas (id, nombre, marca, modelo, precio_hora, precio_dia) VALUES 
(1, 'Tractor John Deere', 'John Deere', '5080E', 150000, 1200000),
(2, 'Tractor Massey Ferguson', 'Massey Ferguson', '275', 120000, 1000000),
(3, 'Retroexcavadora', 'Caterpillar', '416E', 200000, 1600000);

-- Cliente de ejemplo
INSERT IGNORE INTO clientes (id, nombre, telefono) VALUES 
(1, 'Cliente General', '0991xxxxxx');
