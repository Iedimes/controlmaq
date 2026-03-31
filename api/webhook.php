<?php
/**
 * Webhook del Chat v6.0 - ControlMaq
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config.php';

function normalizar($texto) {
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

function extraerNumero($texto) {
    preg_match_all('/\d+(?:[.,]\d+)?/', $texto, $matches);
    if (!empty($matches[0])) {
        $num = end($matches[0]);
        return (float)str_replace(',', '.', $num);
    }
    return null;
}

function buscarObra($texto, $pdo) {
    $st = $pdo->query("SELECT id, nombre FROM obras WHERE estado = 'activa'");
    $obras = $st->fetchAll(PDO::FETCH_ASSOC);
    $texto_norm = normalizar($texto);
    $texto_clean = str_replace(['en','el','la','de','del','con','para','por'], '', $texto_norm);
    $texto_clean = trim(preg_replace('/\s+/', ' ', $texto_clean));
    foreach ($obras as $obra) {
        $nombre_norm = normalizar($obra['nombre']);
        $palabras = explode(' ', $texto_clean);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 2 && strpos($nombre_norm, $palabra) !== false) {
                return $obra['id'];
            }
        }
    }
    return null;
}

function buscarMaquina($texto, $pdo) {
    $st = $pdo->query("SELECT id, nombre, marca FROM maquinas WHERE activo = 1");
    $maquinas = $st->fetchAll(PDO::FETCH_ASSOC);
    $texto_norm = normalizar($texto);
    $texto_clean = str_replace(['con','el','la','en','un','una','tractor','retro'], '', $texto_norm);
    $texto_clean = trim(preg_replace('/\s+/', ' ', $texto_clean));
    foreach ($maquinas as $m) {
        $nombre_norm = normalizar($m['nombre']);
        $marca_norm = normalizar($m['marca']);
        $palabras = explode(' ', $texto_clean);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 2) {
                if (strpos($nombre_norm, $palabra) !== false || strpos($marca_norm, $palabra) !== false) {
                    return $m['id'];
                }
            }
        }
    }
    return null;
}

try {
    $u_id = $_SESSION['usuario_id'] ?? null;
    $u_nom = $_SESSION['nombre'] ?? 'Usuario';
    $u_rol = $_SESSION['rol'] ?? 'empleado';
    
    if (!$u_id) {
        echo json_encode(["status" => "error", "mensaje" => "Sesión expirada"]);
        exit;
    }

    // Manejar imagen
    if (!empty($_FILES['imagen'])) {
        $file = $_FILES['imagen'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(["status" => "error", "mensaje" => "Formato no soportado"]);
            exit;
        }
        $filename = 'IMG_' . time() . '_' . $u_id . '.' . $ext;
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            if (!isset($_SESSION['horom_inicial'])) {
                $_SESSION['ultima_foto'] = 'inicial';
                $resp = "📷 *Foto del horómetro INICIAL*\n\n¿Cuál es el horómetro inicial?";
            } else {
                $_SESSION['ultima_foto'] = 'final';
                $resp = "📷 *Foto del horómetro FINAL*\n\n¿Cuál es el horómetro final?";
            }
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $msg = trim($data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? '');
    
    if (!$msg) {
        echo json_encode(["status" => "error", "mensaje" => "Sin mensaje"]);
        exit;
    }
    
    $texto = normalizar($msg);
    $resp = "";
    $read = false;

    // === COMANDOS DE ADMIN ===
    if ($u_rol === 'admin') {
        // Eliminar obra
        if (preg_match('/eliminar.*obra/', $texto)) {
            preg_match('/eliminar.*obra\s+(.+)/i', $msg, $m);
            if (!empty($m[1])) {
                $nombre_buscar = trim($m[1]);
                $st = $pdo->prepare("SELECT id FROM obras WHERE nombre LIKE ? LIMIT 1");
                $st->execute(["%$nombre_buscar%"]);
                $obra = $st->fetch();
                if ($obra) {
                    // Verificar si tiene trabajos
                    $st = $pdo->prepare("SELECT COUNT(*) FROM trabajos WHERE obra_id = ?");
                    $st->execute([$obra['id']]);
                    if ($st->fetchColumn() > 0) {
                        $resp = "❌ No se puede eliminar. La obra '$nombre_buscar' tiene trabajos registrados.";
                    } else {
                        $pdo->prepare("DELETE FROM obras WHERE id = ?")->execute([$obra['id']]);
                        $resp = "✅ Obra eliminada: $nombre_buscar";
                    }
                } else {
                    $resp = "❌ No encontré el obra: $nombre_buscar";
                }
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        // Eliminar máquina
        if (preg_match('/eliminar.*maquina/', $texto)) {
            preg_match('/eliminar.*maquina\s+(.+)/i', $msg, $m);
            if (!empty($m[1])) {
                $nombre_buscar = trim($m[1]);
                $st = $pdo->prepare("SELECT id FROM maquinas WHERE nombre LIKE ? LIMIT 1");
                $st->execute(["%$nombre_buscar%"]);
                $maq = $st->fetch();
                if ($maq) {
                    // Verificar si tiene trabajos
                    $st = $pdo->prepare("SELECT COUNT(*) FROM trabajos WHERE maquina_id = ?");
                    $st->execute([$maq['id']]);
                    if ($st->fetchColumn() > 0) {
                        $resp = "❌ No se puede eliminar. La máquina '$nombre_buscar' tiene trabajos registrados.";
                    } else {
                        $pdo->prepare("DELETE FROM maquinas WHERE id = ?")->execute([$maq['id']]);
                        $resp = "✅ Máquina eliminada: $nombre_buscar";
                    }
                } else {
                    $resp = "❌ No encontré la máquina: $nombre_buscar";
                }
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        // Nueva obra
        if (preg_match('/(nueva|crear).*obra/', $texto)) {
            preg_match('/(nueva|crear).*obra\s+(.+)/i', $msg, $m);
            if (!empty($m[2])) {
                $nombre_nueva = trim($m[2]);
                $st = $pdo->prepare("SELECT id FROM obras WHERE nombre = ?");
                $st->execute([$nombre_nueva]);
                if ($st->fetch()) {
                    $resp = "❌ Ya existe el obra: $nombre_nueva";
                } else {
                    $pdo->prepare("INSERT INTO obras (cliente_id, nombre, estado) VALUES (1, ?, 'activa')")->execute([$nombre_nueva]);
                    $resp = "✅ Obra creada: $nombre_nueva";
                }
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        // Nueva máquina
        if (preg_match('/(nueva|crear).*maquina/', $texto)) {
            preg_match('/(nueva|crear).*maquina\s+(.+)/i', $msg, $m);
            if (!empty($m[2])) {
                $nombre_nueva = trim($m[2]);
                $st = $pdo->prepare("SELECT id FROM maquinas WHERE nombre = ?");
                $st->execute([$nombre_nueva]);
                if ($st->fetch()) {
                    $resp = "❌ Ya existe la máquina: $nombre_nueva";
                } else {
                    $pdo->prepare("INSERT INTO maquinas (nombre, activo) VALUES (?, 1)")->execute([$nombre_nueva]);
                    $resp = "✅ Máquina creada: $nombre_nueva";
                }
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
    }

    // === PROCESAR HORÓMETRO ===
    if (isset($_SESSION['ultima_foto'])) {
        preg_match_all('/(\d+(?:[.,]\d+)?)/', $msg, $nums);
        if (!empty($nums[1])) {
            $num = (float)str_replace(',', '.', $nums[1][0]);
            $tipo = $_SESSION['ultima_foto'];
            
            if ($tipo === 'inicial') {
                $_SESSION['horom_inicial'] = $num;
                unset($_SESSION['ultima_foto']);
                $resp = "✅ *Horómetro inicial: $num*\n\nAhora envianos la 📷 foto FINAL";
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            } elseif ($tipo === 'final' && isset($_SESSION['horom_inicial'])) {
                $inicial = $_SESSION['horom_inicial'];
                $final = $num;
                $horas = $final - $inicial;
                
                if ($horas <= 0) {
                    unset($_SESSION['horom_inicial']);
                    $resp = "El horómetro final debe ser mayor al inicial. Intentá de nuevo.";
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                }
                
                $_SESSION['confirm_data'] = json_encode([
                    'type' => 'trabajo_foto',
                    'horas' => $horas,
                    'horom_inicial' => $inicial,
                    'horom_final' => $final,
                    'step' => 'obra'
                ]);
                unset($_SESSION['ultima_foto'], $_SESSION['horom_inicial']);
                
                $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                $resp = "⏱️ *$horas horas* (horómetro: $inicial → $final)\n\n";
                $resp .= "¿En qué obra trabajaste?\n\nObras:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
    }

    // === CONTINUAR REGISTRO DE TRABAJO ===
    if (isset($_SESSION['confirm_data'])) {
        $confirm = json_decode($_SESSION['confirm_data'], true);
        $es_trabajo = in_array($confirm['type'] ?? '', ['trabajo', 'trabajo_foto']);
        
        // Combustible - siempre mostrar opciones de máquina
if (($confirm['type'] ?? '') === 'combustible' && ($confirm['step'] ?? '') === 'maquina') {
            $maquina_id = buscarMaquina($texto, $pdo);
            if ($maquina_id) {
                $confirm['maquina_id'] = $maquina_id;
                $confirm['step'] = 'confirmar';
                $_SESSION['confirm_data'] = json_encode($confirm);
                
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$maquina_id]);
                $maq_nombre = $st->fetchColumn();
                
                $resp = "⛽ *Combustible: {$confirm['litros']} litros*\n🚜 $maq_nombre\n\nRespondé \"SI\" para confirmar.";
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            } else {
                $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
                $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                $resp = "⛽ *Combustible: {$confirm['litros']} litros*\n\n¿A qué máquina le cargaste?\n\n";
                $resp .= implode("\n", array_map(fn($m) => "• $m", $maqs));
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        // Incidente - seleccionar máquina
        if (($confirm['type'] ?? '') === 'incidente' && ($confirm['step'] ?? '') === 'maquina') {
            $maquina_id = null;
            if (!preg_match('/ninguna?|no|fue/', $texto)) {
                $maquina_id = buscarMaquina($texto, $pdo);
            }
            
            $confirm['maquina_id'] = $maquina_id;
            $confirm['step'] = 'confirmar';
            $_SESSION['confirm_data'] = json_encode($confirm);
            
            $tipos_nombre = ['lluvia' => '🌧️ Lluvia', 'breakdown' => '🔧 Breakdown', 'mantenimiento' => '🔩 Mantenimiento', 'ausente' => '❌ Ausente'];
            $tipo = $confirm['tipo'];
            
            $resp = "📋 *{$tipos_nombre[$tipo]}*";
            if ($tipo === 'ausente') {
                $resp .= "\n👤 Ausencia registrada";
            } elseif ($maquina_id) {
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$maquina_id]);
                $resp .= "\n🚜 " . $st->fetchColumn();
            } else {
                $resp .= "\n🚜 Sin máquina específica";
            }
            $resp .= "\n\nRespondé \"SI\" para confirmar.";
            
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
        
        // Cambio de máquina - seleccionar obra
        if (($confirm['type'] ?? '') === 'cambio_maquina' && ($confirm['step'] ?? '') === 'maquina') {
            $maquina_id = buscarMaquina($texto, $pdo);
            if ($maquina_id) {
                $confirm['maquina_id'] = $maquina_id;
                $confirm['step'] = 'obra';
                $_SESSION['confirm_data'] = json_encode($confirm);
                
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$maquina_id]);
                $maq_nombre = $st->fetchColumn();
                
                $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa' ORDER BY nombre");
                $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                
                $resp = "🔄 *Cambio a: $maq_nombre*\n\n";
                $resp .= "¿En qué obra?\n\n";
                $resp .= implode("\n", array_map(fn($o) => "• $o", $obras));
                
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            } else {
                $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
                $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                $resp = "No encontré esa máquina. Elegí:\n" . implode("\n", array_map(fn($m) => "• $m", $maqs));
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        // Cambio de máquina - confirmar obra
        if (($confirm['type'] ?? '') === 'cambio_maquina' && ($confirm['step'] ?? '') === 'obra') {
            $obra_id = buscarObra($texto, $pdo);
            if ($obra_id) {
                $confirm['obra_id'] = $obra_id;
                $confirm['step'] = 'confirmar';
                $_SESSION['confirm_data'] = json_encode($confirm);
                
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$confirm['maquina_id']]);
                $maq_nombre = $st->fetchColumn();
                
                $st = $pdo->prepare("SELECT nombre FROM obras WHERE id = ?");
                $st->execute([$obra_id]);
                $obra_nombre = $st->fetchColumn();
                
                $resp = "🔄 *Confirmar Cambio*\n\n🚜 $maq_nombre\n🏗️ $obra_nombre\n\nRespondé \"SI\" para confirmar.";
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            } else {
                $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa' ORDER BY nombre");
                $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                $resp = "No encontré esa obra. Elegí:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                exit;
            }
        }
        
        if ($es_trabajo) {
            if (($confirm['step'] ?? '') === 'obra') {
                $obra_id = buscarObra($texto, $pdo);
                if ($obra_id) {
                    $confirm['obra_id'] = $obra_id;
                    $confirm['step'] = 'maquina';
                    $_SESSION['confirm_data'] = json_encode($confirm);
                    
                    $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1");
                    $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "¿Con qué máquina trabajaste?\n\nMáquinas:\n" . implode("\n", array_map(fn($m) => "• $m", $maqs));
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                } else {
                    $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                    $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "No encontré esa obra. Elegí:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                }
            } elseif (($confirm['step'] ?? '') === 'maquina') {
                $maquina_id = buscarMaquina($texto, $pdo);
                if ($maquina_id) {
                    $confirm['maquina_id'] = $maquina_id;
                    $confirm['step'] = 'confirmar';
                    $_SESSION['confirm_data'] = json_encode($confirm);
                    
                    $st = $pdo->prepare("SELECT precio_hora FROM maquinas WHERE id = ?");
                    $st->execute([$maquina_id]);
                    $precio_hora = $st->fetchColumn() ?: 100000;
                    $monto = $confirm['horas'] * $precio_hora;
                    
                    $st = $pdo->prepare("SELECT nombre FROM obras WHERE id = ?");
                    $st->execute([$confirm['obra_id']]);
                    $obra_nombre = $st->fetchColumn() ?: 'obra';
                    
                    $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                    $st->execute([$maquina_id]);
                    $maq_nombre = $st->fetchColumn() ?: 'máquina';
                    
                    $resp = "📋 *Confirmar*\n\n⏱️ {$confirm['horas']} horas\n🏗️ $obra_nombre\n🚜 $maq_nombre\n💰 " . number_format($monto, 0, ',', '.') . " Gs\n\nRespondé \"SI\" para confirmar.";
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                } else {
                    $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1");
                    $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "No encontré esa máquina. Elegí:\n" . implode("\n", array_map(fn($m) => "• $m", $maqs));
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                }
            }
        }
    }

    // === RESPUESTAS ===
    if (preg_match('/^(si|siconfirmo|confirmo|ok|si|dale|bueno)$/', $texto) && isset($_SESSION['confirm_data'])) {
        $confirm = json_decode($_SESSION['confirm_data'], true);
        
        if (in_array($confirm['type'] ?? '', ['trabajo','trabajo_foto'])) {
            $horas = $confirm['horas'];
            $obra_id = $confirm['obra_id'] ?? 1;
            $maquina_id = $confirm['maquina_id'] ?? 1;
            
            $st = $pdo->prepare("SELECT precio_hora FROM maquinas WHERE id = ?");
            $st->execute([$maquina_id]);
            $precio_hora = $st->fetchColumn() ?: 100000;
            $monto = $horas * $precio_hora;
            
            $desc = $confirm['type'] === 'trabajo_foto' ? 'Registrado por foto' : 'Confirmado por chat';
            $st = $pdo->prepare("INSERT INTO trabajos (empleado_id, obra_id, maquina_id, fecha, horas_trabajadas, tipo_pago, monto, descripcion) VALUES (?, ?, ?, CURDATE(), ?, 'hora', ?, ?)");
            $st->execute([$u_id, $obra_id, $maquina_id, $horas, $monto, $desc]);
            
            $horom_info = '';
            if ($confirm['type'] === 'trabajo_foto') {
                $horom_info = "\n(horómetro: " . ($confirm['horom_inicial'] ?? '-') . " → " . ($confirm['horom_final'] ?? '-') . ")";
            }
            
            $resp = "✅ *Trabajo guardado*\n\n⏱️ {$horas} horas = " . number_format($monto, 0, ',', '.') . " Gs$horom_info\n\n¿Tenés algún gasto hoy?";
            unset($_SESSION['confirm_data']);
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        } elseif (($confirm['type'] ?? '') === 'gasto') {
            $st = $pdo->prepare("INSERT INTO gastos (empleado_id, fecha, concepto, monto) VALUES (?, CURDATE(), ?, ?)");
            $st->execute([$u_id, $confirm['concepto'], $confirm['monto']]);
            $resp = "✅ *Gasto guardado*\n\n💸 " . number_format($confirm['monto'], 0, ',', '.') . " Gs en {$confirm['concepto']}";
            unset($_SESSION['confirm_data']);
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        } elseif (($confirm['type'] ?? '') === 'combustible') {
            $st = $pdo->prepare("INSERT INTO combustibles (empleado_id, maquina_id, obra_id, fecha, litros, tipo) VALUES (?, ?, ?, CURDATE(), ?, 'carga')");
            $st->execute([$u_id, $confirm['maquina_id'] ?? null, $confirm['obra_id'] ?? null, $confirm['litros']]);
            $resp = "✅ *Combustible registrado*\n\n⛽ {$confirm['litros']} litros";
            if (!empty($confirm['maquina_id'])) {
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$confirm['maquina_id']]);
                $resp .= " para " . $st->fetchColumn();
            }
            $resp .= "\n\n¿Tenés algo más que registrar?";
            unset($_SESSION['confirm_data']);
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        } elseif (($confirm['type'] ?? '') === 'incidente') {
            $st = $pdo->prepare("INSERT INTO incidentes (empleado_id, maquina_id, fecha, tipo) VALUES (?, ?, CURDATE(), ?)");
            $st->execute([$u_id, $confirm['maquina_id'] ?? null, $confirm['tipo']]);
            
            $tipos_nombre = ['lluvia' => '🌧️ Lluvia', 'breakdown' => '🔧 Breakdown', 'mantenimiento' => '🔩 Mantenimiento'];
            $resp = "✅ *Incidente registrado*\n\n";
            $resp .= $tipos_nombre[$confirm['tipo']];
            if (!empty($confirm['maquina_id'])) {
                $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
                $st->execute([$confirm['maquina_id']]);
                $resp .= " - " . $st->fetchColumn();
            }
            $resp .= "\n\n¿Tenés algo más que registrar?";
            unset($_SESSION['confirm_data']);
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        } elseif (($confirm['type'] ?? '') === 'cambio_maquina') {
            $st = $pdo->prepare("SELECT nombre FROM maquinas WHERE id = ?");
            $st->execute([$confirm['maquina_id']]);
            $maq_nombre = $st->fetchColumn();
            
            $st = $pdo->prepare("SELECT nombre FROM obras WHERE id = ?");
            $st->execute([$confirm['obra_id']]);
            $obra_nombre = $st->fetchColumn();
            
            // Guardar en memoria la máquina y obra actual
            $st = $pdo->prepare("INSERT INTO memoria (empleado_id, clave, valor) VALUES (?, 'maquina_actual', ?) ON DUPLICATE KEY UPDATE valor = ?");
            $st->execute([$u_id, $confirm['maquina_id'], $confirm['maquina_id']]);
            
            $st = $pdo->prepare("INSERT INTO memoria (empleado_id, clave, valor) VALUES (?, 'obra_actual', ?) ON DUPLICATE KEY UPDATE valor = ?");
            $st->execute([$u_id, $confirm['obra_id'], $confirm['obra_id']]);
            
            $resp = "✅ *Cambio registrado*\n\n🚜 $maq_nombre\n🏗️ $obra_nombre\n\nAhora las horas que registres serán con esta máquina y obra.";
            unset($_SESSION['confirm_data']);
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        }
    }
    
    if (preg_match('/^(no|nada|ya está|eso es todo|eso es todo)$/', $texto)) {
        unset($_SESSION['confirm_data']);
        $resp = "Ok, cualquier cosa me avisás. ¡Hasta luego! 👋";
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
        exit;
    }
    
    if (preg_match('/^(si|si dale|claro|si sí)/', $texto) && !isset($_SESSION['confirm_data'])) {
        $resp = "¿Qué querés registrar?\n\n• \"trabajé 8 horas\"\n• \"gasté 200000\"\n• \"cargué 300 litros\"\n• \"llovió\" / \"no trabajé\"";
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }

    // === COMANDOS ===
    if (preg_match('/resum/', $texto)) {
        if ($u_rol === 'admin') {
            // Resumen general para admin
            $st = $pdo->query("SELECT SUM(horas_trabajadas) as h, SUM(monto) as m FROM trabajos WHERE fecha = CURDATE()");
            $r = $st->fetch();
            
            $st = $pdo->query("SELECT SUM(monto) as g FROM gastos WHERE fecha = CURDATE()");
            $g = $st->fetch();
            
            $st = $pdo->query("SELECT SUM(litros) as c FROM combustibles WHERE fecha = CURDATE()");
            $c = $st->fetch();
            
            $st = $pdo->query("SELECT COUNT(*) as cnt FROM incidentes WHERE fecha = CURDATE()");
            $inc = $st->fetch();
            
            $horas = $r['h'] ?? 0;
            $trabajos = $r['m'] ?? 0;
            $gastos = $g['g'] ?? 0;
            $combustible = $c['c'] ?? 0;
            $incidentes = $inc['cnt'] ?? 0;
            $neto = $trabajos - $gastos;
            
            $resp = "📊 *RESUMEN GENERAL - HOY*\n\n";
            $resp .= "⏱️ *Horas trabajadas:* $horas\n";
            $resp .= "💰 *Ingresos por trabajos:* " . number_format($trabajos, 0, ',', '.') . " Gs\n";
            $resp .= "💸 *Gastos registrados:* " . number_format($gastos, 0, ',', '.') . " Gs\n";
            $resp .= "⛽ *Combustible cargado:* " . number_format($combustible, 0, ',', '.') . " L\n";
            $resp .= "🚫 *Incidentes:* $incidentes\n";
            $resp .= "✅ *NETO:* " . number_format($neto, 0, ',', '.') . " Gs";
            
            // Agregar detalle por empleado
            $st = $pdo->query("SELECT u.nombre, SUM(t.horas_trabajadas) as h, SUM(t.monto) as m, SUM(g.monto) as gast
                FROM trabajos t 
                LEFT JOIN usuarios u ON t.empleado_id = u.id
                LEFT JOIN gastos g ON g.empleado_id = u.id AND g.fecha = CURDATE()
                WHERE t.fecha = CURDATE() 
                GROUP BY u.nombre");
            $por_empleado = $st->fetchAll();
            if (!empty($por_empleado)) {
                $resp .= "\n\n👥 *POR EMPLEADO:*";
                foreach ($por_empleado as $e) {
                    $gasto_emp = $e['gast'] ?? 0;
                    $neto_emp = $e['m'] - $gasto_emp;
                    $resp .= "\n• {$e['nombre']}: {$e['h']}hs → " . number_format($e['m'], 0, ',', '.') . " Gs (gastos: " . number_format($gasto_emp, 0, ',', '.') . " Gs)";
                }
            }
            
            // Agregar detalle por obra
            $st = $pdo->query("SELECT o.nombre, SUM(t.horas_trabajadas) as h, SUM(t.monto) as m
                FROM trabajos t 
                LEFT JOIN obras o ON t.obra_id = o.id
                WHERE t.fecha = CURDATE() 
                GROUP BY o.nombre");
            $por_obra = $st->fetchAll();
            if (!empty($por_obra)) {
                $resp .= "\n\n🏗️ *POR OBRA:*";
                foreach ($por_obra as $o) {
                    $resp .= "\n• {$o['nombre']}: {$o['h']}hs = " . number_format($o['m'], 0, ',', '.') . " Gs";
                }
            }
            
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        } else {
            // Resumen personal para empleado
            $st = $pdo->prepare("SELECT SUM(horas_trabajadas) as h, SUM(monto) as m FROM trabajos WHERE fecha = CURDATE() AND empleado_id = ?");
            $st->execute([$u_id]);
            $r = $st->fetch();
            
            $st = $pdo->prepare("SELECT SUM(monto) as g FROM gastos WHERE fecha = CURDATE() AND empleado_id = ?");
            $st->execute([$u_id]);
            $g = $st->fetch();
            
            $st = $pdo->prepare("SELECT SUM(litros) as c FROM combustibles WHERE fecha = CURDATE() AND empleado_id = ?");
            $st->execute([$u_id]);
            $c = $st->fetch();
            
            $horas = $r['h'] ?? 0;
            $trabajos = $r['m'] ?? 0;
            $gastos = $g['g'] ?? 0;
            $combustible = $c['c'] ?? 0;
            $neto = $trabajos - $gastos;
            
            $resp = "📊 *Resumen de Hoy*\n\n";
            $resp .= "⏱️ Horas trabajadas: $horas\n";
            $resp .= "💰 Ingreso por trabajos: " . number_format($trabajos, 0, ',', '.') . " Gs\n";
            $resp .= "💸 Gastos realizados: " . number_format($gastos, 0, ',', '.') . " Gs\n";
            $resp .= "⛽ Combustible: " . number_format($combustible, 0, ',', '.') . " L\n";
            $resp .= "✅ Neto: " . number_format($neto, 0, ',', '.') . " Gs";
            
            // Detalle de trabajos
            $st = $pdo->prepare("SELECT t.horas_trabajadas, t.monto, o.nombre as obra, m.nombre as maquina 
                FROM trabajos t 
                LEFT JOIN obras o ON t.obra_id = o.id
                LEFT JOIN maquinas m ON t.maquina_id = m.id
                WHERE t.fecha = CURDATE() AND t.empleado_id = ?");
            $st->execute([$u_id]);
            $mis_trabajos = $st->fetchAll();
            if (!empty($mis_trabajos)) {
                $resp .= "\n\n📋 *Trabajos:*";
                foreach ($mis_trabajos as $t) {
                    $resp .= "\n• {$t['horas_trabajadas']}hs en {$t['obra']} ({$t['maquina']}): " . number_format($t['monto'], 0, ',', '.') . " Gs";
                }
            }
            
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
            exit;
        }
    }
    
    if (preg_match('/(mis trabaj|trabajos mios|todos.*trabaj)/', $texto)) {
        if ($u_rol === 'admin') {
            $st = $pdo->query("SELECT t.*, o.nombre as obra, m.nombre as maquina, u.nombre as empleado FROM trabajos t LEFT JOIN obras o ON t.obra_id = o.id LEFT JOIN maquinas m ON t.maquina_id = m.id LEFT JOIN usuarios u ON t.empleado_id = u.id WHERE t.fecha = CURDATE() ORDER BY t.id DESC");
            $mis_trabajos = $st->fetchAll();
            
            if (empty($mis_trabajos)) {
                $resp = "No hay trabajos registrados hoy.";
            } else {
                $resp = "📋 *TODOS los trabajos de Hoy*\n\n";
                foreach ($mis_trabajos as $t) {
                    $resp .= "• {$t['empleado']}: {$t['horas_trabajadas']}hs en {$t['obra']} ({$t['maquina']}) = " . number_format($t['monto'], 0, ',', '.') . " Gs\n";
                }
            }
        } else {
            $st = $pdo->prepare("SELECT t.*, o.nombre as obra, m.nombre as maquina FROM trabajos t LEFT JOIN obras o ON t.obra_id = o.id LEFT JOIN maquinas m ON t.maquina_id = m.id WHERE t.empleado_id = ? AND t.fecha = CURDATE() ORDER BY t.id DESC");
            $st->execute([$u_id]);
            $mis_trabajos = $st->fetchAll();
            
            if (empty($mis_trabajos)) {
                $resp = "No tenés trabajos registrados hoy.";
            } else {
                $resp = "📋 *Tus trabajos de hoy*\n\n";
                foreach ($mis_trabajos as $t) {
                    $resp .= "• {$t['horas_trabajadas']}hs en {$t['obra']} ({$t['maquina']}) = " . number_format($t['monto'], 0, ',', '.') . " Gs\n";
                }
            }
        }
        
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
        exit;
    }
    
    if (preg_match('/(obras|lista)/', $texto)) {
        $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa' ORDER BY nombre");
        $obras = $st->fetchAll(PDO::FETCH_COLUMN);
        $resp = "🏗️ *Obras disponibles*\n\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }
    
    if (preg_match('/(ayuda|help)/', $texto)) {
        $resp = "📖 *Comandos*\n\n• \"trabaje 8 horas\" - Registrar\n• \"gasté 200000\" - Registrar gasto\n• \"cargué 300 litros\" - Cargar combustible\n• \"cambié de máquina\" - Cambiar máquina/obra\n• \"llovió\" / \"no trabajé\" - Registrar incidente\n• \"resumen\" - Ver balance\n• \"mis trabajos\" - Ver mis trabajos\n• 📷 - Enviar foto del horómetro";
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }

    // === CAMBIAR DE MÁQUINA/OBRA ===
    if (preg_match('/cambi[aeé]|cambio.*maquina|nueva.*maquina|ahora.*maquina/', $texto)) {
        $_SESSION['confirm_data'] = json_encode([
            'type' => 'cambio_maquina',
            'step' => 'maquina'
        ]);
        
        $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
        $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
        
        $resp = "🔄 *Cambio de Máquina*\n\n";
        $resp .= "¿A qué máquina te cambiaste?\n\n";
        $resp .= implode("\n", array_map(fn($m) => "• $m", $maqs));
        
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }
    
    // === REGISTRAR TRABAJO ===
    if (preg_match('/trabaj[aeé]/', $texto)) {
        preg_match('/(\d+(?:[.,]\d+)?)\s*(?:horas?|hs?|hr)/', $msg, $horas_match);
        
        if (!$horas_match) {
            $resp = "¿Cuántas horas trabajaste? Ej: \"trabajé 8 horas\"";
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
        
        $horas = (float)str_replace(',', '.', $horas_match[1]);
        $_SESSION['confirm_data'] = json_encode(['type' => 'trabajo', 'horas' => $horas, 'step' => 'obra']);
        
        $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
        $obras = $st->fetchAll(PDO::FETCH_COLUMN);
        $resp = "¿En qué obra trabajaste $horas horas?\n\nObras:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }
    
    // === REGISTRAR COMBUSTIBLE ===
    if (preg_match('/(carg[aeé]|combustible|gasoil|nafta|litros)/', $texto)) {
        preg_match('/(\d+(?:[.,]\d+)?)\s*(?:litros?|l)/i', $msg, $litros_match);
        
        if (!$litros_match) {
            $resp = "¿Cuántos litros cargaste? Ej: \"cargué 300 litros\"";
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
        
        $litros = (float)str_replace(',', '.', $litros_match[1]);
        
        // Siempre preguntar por la máquina (no detectar)
        $_SESSION['confirm_data'] = json_encode([
            'type' => 'combustible',
            'litros' => $litros,
            'maquina_id' => null,
            'step' => 'maquina'
        ]);
        
        $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
        $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
        
        $resp = "⛽ *$litros litros*\n\n";
        $resp .= "¿A qué máquina le cargaste?\n\n";
        $resp .= implode("\n", array_map(fn($m) => "• $m", $maqs));
        
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }
    
    // === REGISTRAR INCIDENTE (lluvia, breakdown, mantenimiento, ausente) ===
    if (preg_match('/(llovi[oó]|lluvia|rompi[oó]|descompuesta|rotura|breakdown|mantenimiento|sin trabajar|sin trabajo|ausente|falte|no trabaj[ée])/', $texto)) {
        $tipo = null;
        if (preg_match('/llovi|lluvia/', $texto)) {
            $tipo = 'lluvia';
        } elseif (preg_match('/rompi|descompuesta|rotura|breakdown/', $texto)) {
            $tipo = 'breakdown';
        } elseif (preg_match('/mantenimiento/', $texto)) {
            $tipo = 'mantenimiento';
        } elseif (preg_match('/ausente|falte|no trabaj|sin trabajar|sin trabajo/', $texto)) {
            $tipo = 'ausente';
        }
        
        if ($tipo) {
            $maquina_id = null;
            
            $_SESSION['confirm_data'] = json_encode([
                'type' => 'incidente',
                'tipo' => $tipo,
                'maquina_id' => $maquina_id,
                'step' => 'maquina'
            ]);
            
            $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
            $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
            
            $tipos_nombre = ['lluvia' => '🌧️ Lluvia', 'breakdown' => '🔧 Breakdown', 'mantenimiento' => '🔩 Mantenimiento', 'ausente' => '❌ Ausente'];
            
            if ($tipo === 'ausente') {
                $resp = "📋 *Ausencia*\n\n¿Confirmás que no trabajaste hoy?\n\nRespondé \"SI\"";
            } else {
                $resp = "📋 *{$tipos_nombre[$tipo]}*\n\n";
                $resp .= "¿Qué máquina afectó?\n\n";
                $resp .= implode("\n", array_map(fn($m) => "• $m", $maqs));
                $resp .= "\n\n(O escribí \"ninguna\" si no fue una máquina)";
            }
            
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
    }
    
    // === REGISTRAR GASTO ===
    if (preg_match('/(gast[ae]|compr[ée]|pagu[é])/', $texto)) {
        $monto = extraerNumero($msg);
        
        if (!$monto) {
            $resp = "¿Cuánto gastaste? Ej: \"gasté 200000\"";
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        }
        
        $concepto = 'Gasto';
        if (preg_match('/gast[ae]\s+[\d.,]+\s*(?:en|de)?\s*(.+)/i', $msg, $c)) {
            $concepto = trim($c[1]);
        }
        
        $_SESSION['confirm_data'] = json_encode(['type' => 'gasto', 'monto' => $monto, 'concepto' => $concepto]);
        $resp = "¿Confirmás gasto de " . number_format($monto, 0, ',', '.') . " Gs en $concepto?\n\nRespondé \"SI\"";
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
        exit;
    }

    // === POR DEFECTO ===
    $st = $pdo->prepare("INSERT INTO reportes_diarios (empleado_id, fecha, texto) VALUES (?, CURDATE(), ?)");
    $st->execute([$u_id, $msg]);
    $resp = "✅ *Reporte guardado*\n\nGracias. ¿Querés agregar algo más?";
    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => true, "lang" => "es"]);
    
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "mensaje" => "Error: " . $e->getMessage()]);
}
?>
