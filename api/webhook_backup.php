<?php
/**
 * Webhook del Chat v5.0 - ControlMaq (Con inteligencia mejorada)
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
    
    // Quitar palabras comunes
    $texto_clean = str_replace(['en','el','la','de','del','con','para','por'], '', $texto_norm);
    $texto_clean = trim(preg_replace('/\s+/', ' ', $texto_clean));
    
    foreach ($obras as $obra) {
        $nombre_norm = normalizar($obra['nombre']);
        // Buscar si alguna palabra del texto está en el nombre
        $palabras = explode(' ', $texto_clean);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 2 && strpos($nombre_norm, $palabra) !== false) {
                return $obra['id'];
            }
        }
        // También buscar coincidencia parcial
        if (similar_text($texto_clean, $nombre_norm) > strlen($nombre_norm) * 0.5) {
            return $obra['id'];
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
        
        // Buscar por palabras clave
        $palabras = explode(' ', $texto_clean);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 2) {
                if (strpos($nombre_norm, $palabra) !== false || strpos($marca_norm, $palabra) !== false) {
                    return $m['id'];
                }
            }
        }
        // Coincidencia parcial
        if (similar_text($texto_clean, $nombre_norm) > strlen($nombre_norm) * 0.4) {
            return $m['id'];
        }
    }
    return null;
}

try {
    $u_id = $_SESSION['usuario_id'] ?? null;
    $u_nom = $_SESSION['nombre'] ?? 'Usuario';
    
    if (!$u_id) {
        echo json_encode(["status" => "error", "mensaje" => "Sesión expirada"]); 
        exit;
    }

    // Verificar si es una imagen
    if (!empty($_FILES['imagen'])) {
        $file = $_FILES['imagen'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo json_encode(["status" => "error", "mensaje" => "Formato no soportado"]);
            exit;
        }
        
        $filename = 'IMG_' . time() . '_' . $u_id . '.' . $ext;
        $upload_dir = __DIR__ . '/../uploads/';
        
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            // Guardar que hay una foto esperando y pedir horómetro
            if (!isset($_SESSION['horom_inicial']) && !isset($_SESSION['horom_final'])) {
                // Primera foto - horómetro inicial
                $_SESSION['ultima_foto'] = 'inicial';
                $resp = "📷 *Foto del horómetro INICIAL recibida*\n\n";
                $resp .= "¿Cuál es el horómetro inicial?";
            } else {
                // Segunda foto - horómetro final
                $_SESSION['ultima_foto'] = 'final';
                $resp = "📷 *Foto del horómetro FINAL recibida*\n\n";
                $resp .= "¿Cuál es el horómetro final?";
            }
            
            echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
            exit;
        } else {
            echo json_encode(["status" => "error", "mensaje" => "Error al subir imagen"]);
            exit;
        }
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $msg = trim($data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? '');
    
    if ($msg) {
        $texto = normalizar($msg);
        $confirmation_needed = false;
        $confirm_data = null;
        
        // === PROCESAR HORÓMETRO SI HAY SESIÓN ACTIVA ===
        if (isset($_SESSION['ultima_foto'])) {
            // Buscar cualquier número en el mensaje
            preg_match_all('/(\d+(?:[.,]\d+)?)/', $msg, $nums);
            
            if (!empty($nums[1])) {
                $num = (float)str_replace(',', '.', $nums[1][0]);
                $tipo_foto = $_SESSION['ultima_foto'];
                
                if ($tipo_foto === 'inicial') {
                    // Primera foto - horómetro inicial
                    $_SESSION['horom_inicial'] = $num;
                    $resp = "✅ *Horómetro inicial: $num*\n\n";
                    $resp .= "Ahora envianos la 📷 foto del horómetro FINAL";
                    unset($_SESSION['ultima_foto']);
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                } elseif ($tipo_foto === 'final' && isset($_SESSION['horom_inicial'])) {
                    // Segunda foto - horómetro final
                    $inicial = $_SESSION['horom_inicial'];
                    $final = $num;
                    $horas = $final - $inicial;
                    
                    if ($horas <= 0) {
                        $resp = "El horómetro final ($final) debe ser mayor al inicial ($inicial). Intenta de nuevo.";
                        unset($_SESSION['horom_inicial']);
                        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                        exit;
                    }
                    
                    $confirm_data = ['type' => 'trabajo_foto', 'horas' => $horas, 'horom_inicial' => $inicial, 'horom_final' => $final];
                    $_SESSION['confirm_data'] = json_encode($confirm_data);
                    unset($_SESSION['ultima_foto'], $_SESSION['horom_inicial']);
                    
                    $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                    $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                    
                    $resp = "⏱️ Calculé *$horas horas* (horómetro: $inicial → $final)\n\n";
                    $resp .= "¿En qué obra trabajaste?\n\n";
                    $resp .= "Obras:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                    echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => false, "lang" => "es"]);
                    exit;
                }
            }
        }
        
        // === COMANDOS ===
        
        // Resumen
        if (preg_match('/resum/', $texto)) {
            $st = $pdo->prepare("SELECT SUM(horas_trabajadas) as h, SUM(monto) as m FROM trabajos WHERE fecha = CURDATE() AND empleado_id = ?");
            $st->execute([$u_id]);
            $r = $st->fetch();
            
            $st = $pdo->prepare("SELECT SUM(monto) as g FROM gastos WHERE fecha = CURDATE() AND empleado_id = ?");
            $st->execute([$u_id]);
            $g = $st->fetch();
            
            $horas = $r['h'] ?? 0;
            $trabajos = $r['m'] ?? 0;
            $gastos = $g['g'] ?? 0;
            $neto = $trabajos - $gastos;
            
            $resp = "📊 *Resumen de Hoy*\n\n";
            $resp .= "⏱️ Horas: $horas\n";
            $resp .= "💰 Trabajos: " . number_format($trabajos, 0, ',', '.') . " Gs\n";
            $resp .= "💸 Gastos: " . number_format($gastos, 0, ',', '.') . " Gs\n";
            $resp .= "✅ Neto: " . number_format($neto, 0, ',', '.') . " Gs";
            
            $read = true;
        }
        // Mis trabajos
        elseif (preg_match('/(mis trabaj|que hice|hoy trabaj|trabajos mios)/', $texto)) {
            $st = $pdo->prepare("SELECT t.*, o.nombre as obra, m.nombre as maquina 
                FROM trabajos t 
                LEFT JOIN obras o ON t.obra_id = o.id
                LEFT JOIN maquinas m ON t.maquina_id = m.id
                WHERE t.empleado_id = ? AND t.fecha = CURDATE() 
                ORDER BY t.id DESC");
            $st->execute([$u_id]);
            $mis_trabajos = $st->fetchAll();
            
            if (empty($mis_trabajos)) {
                $resp = "No tenés trabajos registrados hoy.";
            } else {
                $resp = "📋 *Tus trabajos de hoy*\n\n";
                foreach ($mis_trabajos as $t) {
                    $resp .= "• {$t['horas']}hs en {$t['obra']} ({$t['maquina']}) = " . number_format($t['monto'], 0, ',', '.') . " Gs\n";
                }
            }
            $read = true;
        }
        // Ayuda
        elseif (preg_match('/(ayuda|help|comandos|que puedo)/', $texto)) {
            $resp = "📖 *Comandos disponibles*\n\n";
            $resp .= "• \"Trabajé 8 horas en [obra]\" - Registrar horas\n";
            $resp .= "• \"Gasté 200000 en gasoil\" - Registrar gasto\n";
            $resp .= "• \"Resumen\" - Ver balance de hoy\n";
            $resp .= "• \"Mis trabajos\" - Ver tus trabajos\n";
            $resp .= "• \"Obras\" - Ver obras disponibles";
            $read = false;
        }
        // Listar obras
        elseif (preg_match('/(obras|proyectos|lista)/', $texto)) {
            $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa' ORDER BY nombre");
            $obras = $st->fetchAll(PDO::FETCH_COLUMN);
            if (empty($obras)) {
                $resp = "No hay obras activas.";
            } else {
                $resp = "🏗️ *Obras disponibles*\n\n";
                foreach ($obras as $o) {
                    $resp .= "• $o\n";
                }
            }
            $read = false;
        }
        // === RESPUESTAS NEGATIVAS ===
        elseif (preg_match('/^(no|nada|ya está|eso es todo|terminamos|nada mas|eso)$/', $texto)) {
            if (isset($_SESSION['confirm_data'])) {
                unset($_SESSION['confirm_data']);
            }
            $resp = "Ok, no guardé nada. ¿Querés algo más?";
            $read = true;
        }
        // === RESPUESTAS AFIRMATIVAS ===
        elseif (preg_match('/^(si|siconfirmo|confirmo|ok|esta bien|si confirmo|si correcto|si|dale|bueno)$/', $texto) && isset($_SESSION['confirm_data'])) {
            $confirm = json_decode($_SESSION['confirm_data'], true);
            
            if ($confirm['type'] === 'trabajo') {
                $horas = $confirm['horas'];
                $obra_id = $confirm['obra_id'] ?? 1;
                $maquina_id = $confirm['maquina_id'] ?? 1;
                
                $st = $pdo->prepare("SELECT precio_hora FROM maquinas WHERE id = ?");
                $st->execute([$maquina_id]);
                $precio_hora = $st->fetchColumn() ?: 100000;
                $monto = $horas * $precio_hora;
                
                $st = $pdo->prepare("INSERT INTO trabajos (empleado_id, obra_id, maquina_id, fecha, horas_trabajadas, tipo_pago, monto, descripcion) VALUES (?, ?, ?, CURDATE(), ?, 'hora', ?, ?)");
                $st->execute([$u_id, $obra_id, $maquina_id, $horas, $monto, 'Confirmado por chat']);
                
                $resp = "✅ *Trabajo confirmado*\n\n";
                $resp .= "⏱️ {$horas} horas = " . number_format($monto, 0, ',', '.') . " Gs\n\n";
                $resp .= "¿Tenés algún gasto hoy?";
                $read = true;
            } elseif ($confirm['type'] === 'gasto') {
                $st = $pdo->prepare("INSERT INTO gastos (empleado_id, fecha, concepto, monto) VALUES (?, CURDATE(), ?, ?)");
                $st->execute([$u_id, $confirm['concepto'], $confirm['monto']]);
                
                $resp = "✅ *Gasto confirmado*\n\n";
                $resp .= "💸 " . number_format($confirm['monto'], 0, ',', '.') . " Gs en {$confirm['concepto']}";
                $read = true;
            }
            
            unset($_SESSION['confirm_data']);
        }
        // === CONTINUAR REGISTRO DE TRABAJO ===
        elseif (isset($_SESSION['confirm_data'])) {
            $confirm = json_decode($_SESSION['confirm_data'], true);
            
            // Aceptar trabajo normal o trabajo_foto
            $es_trabajo = in_array($confirm['type'] ?? '', ['trabajo', 'trabajo_foto']);
            
            if (!$es_trabajo) {
                unset($_SESSION['confirm_data']);
                $resp = "Entendido. ¿Qué más?";
                $read = true;
            }
            // Si no tiene paso, empezar por obra
            elseif (!isset($confirm['step']) || $confirm['step'] === 'obra') {
            if (!isset($confirm['step']) || $confirm['step'] === 'obra') {
                $obra_id = buscarObra($texto, $pdo);
                if ($obra_id) {
                    $confirm['obra_id'] = $obra_id;
                    $confirm['step'] = 'maquina';
                    $_SESSION['confirm_data'] = json_encode($confirm);
                    
                    $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1");
                    $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "¿Con qué máquina trabajaste?\n\n";
                    $resp .= "Máquinas:\n" . implode("\n", array_map(fn($m) => "• $m", $maqs));
                    $read = false;
                } else {
                    $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                    $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "No encontré esa obra. Elegí una:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                    $read = false;
                }
            } elseif (isset($confirm['step']) && $confirm['step'] === 'maquina') {
                // Buscar máquina
                $maquina_id = buscarMaquina($texto, $pdo);
                if ($maquina_id) {
                    $confirm['maquina_id'] = $maquina_id;
                    $confirm['step'] = 'confirmar';
                    $_SESSION['confirm_data'] = json_encode($confirm);
                    
                    // Calcular monto
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
                    
                    $resp = "📋 *Confirmar trabajo*\n\n";
                    $resp .= "⏱️ {$confirm['horas']} horas\n";
                    $resp .= "🏗️ $obra_nombre\n";
                    $resp .= "🚜 $maq_nombre\n";
                    $resp .= "💰 " . number_format($monto, 0, ',', '.') . " Gs\n\n";
                    $resp .= "Respondé \"SI\" para confirmar.";
                    $read = false;
                } else {
                    $st = $pdo->query("SELECT nombre FROM maquinas WHERE activo = 1");
                    $maqs = $st->fetchAll(PDO::FETCH_COLUMN);
                    $resp = "No encontré esa máquina. Elegí una:\n" . implode("\n", array_map(fn($m) => "• $m", $maqs));
                    $read = false;
                }
            } elseif (isset($confirm['step']) && $confirm['step'] === 'confirmar') {
                // Este caso se maneja en respuestas afirmativas
                $resp = "Respondé \"SI\" para confirmar o \"NO\" para cancelar.";
                $read = false;
            } else {
                unset($_SESSION['confirm_data']);
                $resp = "Entendido. ¿Qué más?";
                $read = true;
            }
        }
        // === DETECTAR SOLO NÚMERO ===
        elseif (preg_match('/^[\s\d,.]+$/', $texto) && strlen($texto) < 20) {
            $nums = extraerNumero($texto);
            if ($nums) {
                $resp = "¿Qué es $nums? ¿Horas trabajadas o monto de gasto?\n\n";
                $resp .= "Ej: \"$nums horas\" o \"gasté $nums\"";
                $read = false;
            }
        }
        // === REGISTRAR TRABAJO ===
        elseif (preg_match('/trabaj[aeé]/', $texto)) {
            $horas_match = null;
            preg_match('/(\d+(?:[.,]\d+)?)\s*(?:horas?|hs?|hr)/', $msg, $horas_match);
            
            if (!$horas_match) {
                $resp = "¿Cuántas horas trabajaste hoy? Ej: \"8 horas\" o \"trabajé 8 hs\"";
                $read = false;
            } else {
                $horas = (float)str_replace(',', '.', $horas_match[1]);
                
                // Siempre preguntar obra y máquina, no adivinar
                $confirm_data = ['type' => 'trabajo', 'horas' => $horas, 'step' => 'obra'];
                $_SESSION['confirm_data'] = json_encode($confirm_data);
                
                $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                $resp = "¿En qué obra trabajaste $horas horas?\n\n";
                $resp .= "Obras disponibles:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                $read = false;
            }
        }
        // === DETECTAR HORÓMETRO DESPUÉS DE FOTO ===
        elseif (isset($_SESSION['ultima_foto']) && preg_match('/(\d+)/', $texto)) {
            // Buscar horómetro inicial y final
            preg_match('/(?:empecé|empece|arranqué|inicio|inicie|con)\s*(\d+(?:[.,]\d+)?)/i', $msg, $inicial);
            preg_match('/(?:terminé|termine|finalicé|acabé|terminé|termine)\s*(\d+(?:[.,]\d+)?)/i', $msg, $final);
            
            // También aceptar formato "1500 a 1510" o "de 1500 a 1510"
            if (!$inicial || !$final) {
                preg_match_all('/(\d+(?:[.,]\d+)?)/', $msg, $nums);
                if (count($nums[1]) >= 2) {
                    $inicial = [1 => $nums[1][0]];
                    $final = [1 => $nums[1][1]];
                }
            }
            
            if ($inicial && $final) {
                $horom_inicial = (float)str_replace(',', '.', $inicial[1]);
                $horom_final = (float)str_replace(',', '.', $final[1]);
                
                $horas = $horom_final - $horom_inicial;
                
                if ($horas <= 0) {
                    $resp = "El horómetro final debe ser mayor al inicial. Intentá de nuevo.";
                } else {
                    $confirm_data = ['type' => 'trabajo_foto', 'horas' => $horas, 'horom_inicial' => $horom_inicial, 'horom_final' => $horom_final];
                    $_SESSION['confirm_data'] = json_encode($confirm_data);
                    unset($_SESSION['ultima_foto']);
                    
                    $st = $pdo->query("SELECT nombre FROM obras WHERE estado = 'activa'");
                    $obras = $st->fetchAll(PDO::FETCH_COLUMN);
                    
                    $resp = "⏱️ Calculé *$horas horas* (horómetro: $horom_inicial → $horom_final)\n\n";
                    $resp .= "¿En qué obra trabajaste?\n\n";
                    $resp .= "Obras:\n" . implode("\n", array_map(fn($o) => "• $o", $obras));
                }
                $read = false;
            } else {
                $resp = "No entendí. Decí:\n\"Empecé con 1500 y terminé con 1510\"\n\nO solo escribí los dos números.";
                $read = false;
            }
        }
        // === REGISTRAR GASTO ===
        elseif (preg_match('/(gast[ae]|compr[ée]|pagu[é]|pagado|invertido|gastado)/', $texto)) {
            $monto = extraerNumero($msg);
            
            if (!$monto) {
                $resp = "¿Cuánto gastaste? Ej: \"Gasté 200000 en gasoil\"";
                $read = false;
            } else {
                // Extraer concepto
                $concepto = 'Gasto';
                if (preg_match('/gast[ae]\s+[\d.,]+\s*(?:en|de)?\s*(.+)/i', $msg, $c)) {
                    $concepto = trim($c[1]);
                }
                
                $resp = "¿Confirmás el gasto de *" . number_format($monto, 0, ',', '.') . " Gs* en *$concepto*?\n\n";
                $resp .= "Respodé \"SI\" para confirmar.";
                $confirm_data = json_encode(['type' => 'gasto', 'monto' => $monto, 'concepto' => $concepto]);
                $confirmation_needed = true;
                $read = false;
            }
        }
        // === CONFIRMACIÓN ===
        elseif (preg_match('/^(si|siconfirmo|confirmo|ok|esta bien|si ok|si correcto)/', $texto) && isset($_SESSION['confirm_data'])) {
            $confirm = json_decode($_SESSION['confirm_data'], true);
            
            if ($confirm['type'] === 'trabajo' || $confirm['type'] === 'trabajo_foto') {
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
                
                $resp = "✅ *Trabajo registrado*\n\n";
                $resp .= "⏱️ {$horas} horas = " . number_format($monto, 0, ',', '.') . " Gs";
                if ($confirm['type'] === 'trabajo_foto') {
                    $resp .= "\n(horómetro: " . ($confirm['horom_inicial'] ?? '-') . " → " . ($confirm['horom_final'] ?? '-') . ")";
                }
                $resp .= "\n\n¿Tenés algún gasto hoy?";
                $read = true;
            } elseif ($confirm['type'] === 'gasto') {
                $st = $pdo->prepare("INSERT INTO gastos (empleado_id, fecha, concepto, monto) VALUES (?, CURDATE(), ?, ?)");
                $st->execute([$u_id, $confirm['concepto'], $confirm['monto']]);
                
                $resp = "✅ *Gasto confirmado*\n\n";
                $resp .= "💸 " . number_format($confirm['monto'], 0, ',', '.') . " Gs en {$confirm['concepto']}";
                $read = true;
            }
            
            unset($_SESSION['confirm_data']);
        }
        // === POR DEFECTO: GUARDAR REPORTE ===
        else {
            $st = $pdo->prepare("INSERT INTO reportes_diarios (empleado_id, fecha, texto) VALUES (?, CURDATE(), ?)");
            $st->execute([$u_id, $msg]);
            
            $resp = "✅ *Reporte guardado*\n\n";
            $resp .= "Gracias. ¿Querés agregar algo más?";
            $read = true;
        }

        // Guardar datos de confirmación en sesión
        if ($confirmation_needed && $confirm_data) {
            $_SESSION['confirm_data'] = $confirm_data;
        }

        echo json_encode([
            "status" => "success", 
            "mensaje" => $resp, 
            "read_aloud" => $read ?? false, 
            "lang" => "es"
        ]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "mensaje" => "Error: " . $e->getMessage()]);
}
?>
