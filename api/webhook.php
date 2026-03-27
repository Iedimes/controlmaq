<?php
/**
 * Webhook del Chat v4.0 - ControlMaq (Alquiler de Tractores)
 */
header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../config.php';

try {
    $u_id = $_SESSION['usuario_id'] ?? null;
    $u_nom = $_SESSION['nombre'] ?? 'Usuario';
    
    if (!$u_id) {
        ob_clean(); http_response_code(401); 
        echo json_encode(["status" => "error", "mensaje" => "Sesión expirada"]); exit;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $msg = $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? '';
    
    if ($msg) {
        $texto = mb_strtolower($msg, 'UTF-8');
        
        // --- COMANDOS ESPECIALES ---
        
        // "Resumen" o "Resumen de hoy"
        if (preg_match('/resumen/', $texto)) {
            $st = $pdo->prepare("SELECT SUM(horas_trabajadas) as h, SUM(monto) as m FROM trabajos WHERE fecha = CURDATE()");
            $st->execute();
            $r = $st->fetch();
            
            $st = $pdo->prepare("SELECT SUM(monto) as g FROM gastos WHERE fecha = CURDATE()");
            $st->execute();
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
        // "Mis trabajos" o "qué hice"
        elseif (preg_match('/(mis trabajos|que hice|hoy trabajé)/', $texto)) {
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
        // Patrón para registrar trabajo: "Trabajé X horas" o "X horas en obra con tractor"
        elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:horas?|hs?|hr)/', $texto, $m)) {
            $horas = (float)$m[1];
            
            // Buscar obra mencionada
            $obra_id = 1; // Default
            $st = $pdo->query("SELECT id, nombre FROM obras WHERE estado = 'activa'");
            $obras = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($obras as $id => $nombre) {
                if (stripos($texto, $nombre) !== false) {
                    $obra_id = $id;
                    break;
                }
            }
            
            // Buscar máquina
            $maquina_id = 1;
            $st = $pdo->query("SELECT id, nombre FROM maquinas WHERE activo = 1");
            $maquinas = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($maquinas as $id => $nombre) {
                if (stripos($texto, $nombre) !== false || stripos($texto, 'tractor') !== false) {
                    $maquina_id = $id;
                    break;
                }
            }
            
            // Calcular monto (por hora)
            $st = $pdo->prepare("SELECT precio_hora FROM maquinas WHERE id = ?");
            $st->execute([$maquina_id]);
            $precio_hora = $st->fetchColumn() ?: 100000;
            $monto = $horas * $precio_hora;
            
            $st = $pdo->prepare("INSERT INTO trabajos (empleado_id, obra_id, maquina_id, fecha, horas_trabajadas, tipo_pago, monto, descripcion) VALUES (?, ?, ?, CURDATE(), ?, 'hora', ?, ?)");
            $st->execute([$u_id, $obra_id, $maquina_id, $horas, $monto, mb_substr($msg, 0, 200)]);
            
            $resp = "✅ *Trabajo registrado*\n\n";
            $resp .= "⏱️ {$horas} horas\n";
            $resp .= "💰 {$monto} Gs\n\n";
            $resp .= "¿Tenés algún gasto hoy?";
            $read = true;
        }
        // Patrón para registrar gasto: "Gasté X" o "Gasté X en..."
        elseif (preg_match('/gast[eé]\s+(\d+(?:\.\d+)?)/', $texto, $m)) {
            $monto = (float)$m[1];
            
            // Extraer concepto
            $concepto = 'Gasto';
            if (preg_match('/gast[eé]\s+\d+.*?\s+en\s+(.+)/i', $msg, $c)) {
                $concepto = trim($c[1]);
            }
            
            $st = $pdo->prepare("INSERT INTO gastos (empleado_id, fecha, concepto, monto) VALUES (?, CURDATE(), ?, ?)");
            $st->execute([$u_id, $concepto, $monto]);
            
            $resp = "✅ *Gasto registrado*\n\n";
            $resp .= "💸 " . number_format($monto, 0, ',', '.') . " Gs\n";
            $resp .= "📝 {$concepto}";
            $read = true;
        }
        // "Ayuda" o "help"
        elseif (strpos($texto, 'ayuda') !== false || strpos($texto, 'help') !== false) {
            $resp = "📖 *Comandos disponibles*\n\n";
            $resp .= "• \"Trabajé 8 horas\" - Registrar horas\n";
            $resp .= "• \"Gasté 200000 en gasoil\" - Registrar gasto\n";
            $resp .= "• \"Resumen\" - Ver balance de hoy\n";
            $resp .= "• \"Mis trabajos\" - Ver lo que hiciste hoy\n";
            $resp .= "• \"Panel\" - Ir al panel de control";
            $read = false;
        }
        // Por defecto: guardar como reporte
        else {
            $st = $pdo->prepare("INSERT INTO reportes_diarios (empleado_id, fecha, texto) VALUES (?, CURDATE(), ?)");
            $st->execute([$u_id, $msg]);
            
            $resp = "✅ *Reporte guardado*\n\n";
            $resp .= "Gracias por tu reporte. Lo tengo registrado.\n\n";
            $resp .= "¿Querés agregar algo más?";
            $read = true;
        }

        ob_clean();
        echo json_encode(["status" => "success", "mensaje" => $resp, "read_aloud" => $read, "lang" => "es"]);
    }
} catch (Throwable $e) {
    ob_clean(); echo json_encode(["status" => "error", "mensaje" => "Error: " . $e->getMessage()]);
}
?>
