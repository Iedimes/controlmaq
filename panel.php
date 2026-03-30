<?php
/**
 * Panel de Control - Planilla Central
 */
session_start();

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ./");
    exit;
}

$es_admin = ($_SESSION['rol'] ?? '') === 'admin';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$nombre = $_SESSION['nombre'] ?? 'Usuario';

// Obtener quincena actual
$quincena_actual = null;
$st = $pdo->prepare("SELECT * FROM quincenas WHERE estado = 'abierta' ORDER BY fecha_inicio DESC LIMIT 1");
$st->execute();
$quincena_actual = $st->fetch();

// Si no hay quincena abierta, crear una
if (!$quincena_actual) {
    $dia_actual = date('j');
    if ($dia_actual <= 15) {
        $inicio = date('Y-m-01');
        $fin = date('Y-m-15');
    } else {
        $inicio = date('Y-m-16');
        $fin = date('Y-m-t');
    }
    $pdo->prepare("INSERT INTO quincenas (fecha_inicio, fecha_fin) VALUES (?, ?)")->execute([$inicio, $fin]);
    $st = $pdo->prepare("SELECT * FROM quincenas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
    $st->execute();
    $quincena_actual = $st->fetch();
}

// Obtener datos para la planilla
$st = $pdo->prepare("SELECT t.*, u.nombre as empleado, m.nombre as maquina, o.nombre as obra 
    FROM trabajos t 
    LEFT JOIN usuarios u ON t.empleado_id = u.id 
    LEFT JOIN maquinas m ON t.maquina_id = m.id 
    LEFT JOIN obras o ON t.obra_id = o.id
    WHERE t.fecha BETWEEN ? AND ?
    ORDER BY t.fecha DESC, t.id DESC");
$st->execute([$quincena_actual['fecha_inicio'], $quincena_actual['fecha_fin']]);
$trabajos = $st->fetchAll();

// Obtener gastos
$st = $pdo->prepare("SELECT g.*, u.nombre as empleado 
    FROM gastos g 
    LEFT JOIN usuarios u ON g.empleado_id = u.id
    WHERE g.fecha BETWEEN ? AND ?
    ORDER BY g.fecha DESC");
$st->execute([$quincena_actual['fecha_inicio'], $quincena_actual['fecha_fin']]);
$gastos = $st->fetchAll();

// Totales
$total_horas = 0;
$total_monto = 0;
$total_gastos = 0;
foreach ($trabajos as $t) {
    $total_horas += $t['horas_trabajadas'];
    $total_monto += $t['monto'];
}
foreach ($gastos as $g) {
    $total_gastos += $g['monto'];
}

// Obtener listas para filtros
$st = $pdo->query("SELECT * FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre");
$empleados = $st->fetchAll();

$st = $pdo->query("SELECT * FROM maquinas WHERE activo = 1 ORDER BY nombre");
$maquinas = $st->fetchAll();

$st = $pdo->query("SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre");
$clientes = $st->fetchAll();

$st = $pdo->query("SELECT o.*, c.nombre as cliente_nombre FROM obras o LEFT JOIN clientes c ON o.cliente_id = c.id WHERE o.estado = 'activa' ORDER BY o.nombre");
$obras = $st->fetchAll();

// Función para formatear números
function fmt($n) { return number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ControlMaq - Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25d366; --bg-dark: #0b141a; --header: #202c33; --card: #1e2a32; --text: #e9edef; }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; -webkit-tap-highlight-color: transparent; }
        html, body { 
            margin: 0; padding: 0; background: var(--bg-dark); color: var(--text); 
            min-height: 100vh; width: 100%;
        }
        
        header { 
            background: var(--header); padding: 12px 15px; 
            display: flex; align-items: center; justify-content: space-between; 
            border-bottom: 1px solid #333; position: sticky; top: 0; z-index: 100;
        }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-icon { 
            width: 38px; height: 38px; background: var(--primary); 
            border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; color: #0b141a; font-weight: bold; font-size: 1.1rem;
        }
        .user-info { display: flex; align-items: center; gap: 10px; }
        
        .container { 
            padding: 15px; max-width: 100%; margin: 0 auto; 
            width: 100%; overflow-x: hidden;
        }
        
        .card { 
            background: var(--card); border-radius: 12px; 
            padding: 15px; margin-bottom: 15px; 
            width: 100%; max-width: 800px; margin-left: auto; margin-right: auto;
        }
        .card h3 { margin: 0 0 10px 0; color: var(--primary); font-size: 1rem; }
        
        .stats { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 8px; 
            width: 100%; max-width: 800px; margin: 0 auto;
        }
        .stat { background: #2a3942; padding: 10px 5px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 1.1rem; font-weight: 600; color: var(--primary); }
        .stat-label { font-size: 0.65rem; color: #8696a0; }
        
        .btn { 
            background: var(--primary); color: #0b141a; border: none; 
            padding: 10px 12px; border-radius: 8px; cursor: pointer; 
            font-weight: 600; font-size: 0.8rem; white-space: nowrap;
        }
        .btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        
        table { 
            width: 100%; max-width: 100%; border-collapse: collapse; 
            font-size: 0.75rem; display: block; overflow-x: auto;
        }
        th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #333; white-space: nowrap; }
        th { color: #8696a0; font-weight: 500; font-size: 0.7rem; }
        
        .tabs { display: flex; gap: 5px; margin-bottom: 15px; overflow-x: auto; padding-bottom: 5px; }
        .tab { padding: 8px 14px; background: #2a3942; border: none; color: #8696a0; border-radius: 20px; cursor: pointer; white-space: nowrap; font-size: 0.85rem; }
        .tab.active { background: var(--primary); color: #0b141a; }
        
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; }
        .badge-hora { background: #005c4b; }
        .badge-dia { background: #004b3d; }
        
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; color: #8696a0; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 10px; background: #2a3942; 
            border: 1px solid #333; color: white; border-radius: 8px; font-size: 1rem;
        }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; padding: 10px; }
        .modal.active { display: flex; }
        .modal-content { 
            background: var(--card); border-radius: 12px; padding: 15px; 
            width: 100%; max-width: 380px; max-height: 85vh; overflow-y: auto; 
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        
        .action-bar { 
            display: flex; gap: 6px; margin-bottom: 15px; 
            flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px;
            width: 100%; max-width: 800px; margin: 0 auto 15px auto;
        }
        
        .msg { word-wrap: break-word; }
        .msg.in { background: var(--msg-in); align-self: flex-start; }
        .msg.out { background: var(--msg-out); align-self: flex-end; }
        
        @media (max-width: 600px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .stat-value { font-size: 1rem; }
            .container { padding: 10px 8px; }
            .card { padding: 12px; border-radius: 10px; }
            header { padding: 10px 12px; }
            .logo-icon { width: 34px; height: 34px; font-size: 1rem; }
            #chat-panel { width: calc(100% - 30px); right:15px; bottom:60px; height:350px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-tractor"></i></div>
            <div>
                <div style="font-weight: 600; font-size: 1rem;">ControlMaq</div>
                <div style="color: var(--primary); font-size: 0.7rem;">Panel de Control</div>
            </div>
        </div>
        <div class="user-info">
            <button onclick="toggleChat()" style="background:none;border:none;color:var(--primary);font-size:1.2rem;cursor:pointer;margin-right:10px;"><i class="fas fa-comments"></i></button>
            <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($nombre); ?></span>
            <a href="index.php?logout=1" style="color: #8696a0;"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>

    <div class="container">
        <!-- Resumen de Quincena -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3>📅 Quincena: <?php echo date('d/m', strtotime($quincena_actual['fecha_inicio'])) . ' - ' . date('d/m', strtotime($quincena_actual['fecha_fin'])); ?></h3>
                <span class="badge" style="background: var(--primary); color: #0b141a;"><?php echo strtoupper($quincena_actual['estado']); ?></span>
            </div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_horas); ?></div>
                    <div class="stat-label">Horas</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_monto); ?> Gs</div>
                    <div class="stat-label">Total Trabajos</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_gastos); ?> Gs</div>
                    <div class="stat-label">Gastos</div>
                </div>
                <div class="stat" style="background: var(--primary); color: #0b141a;">
                    <div class="stat-value" style="color: #0b141a;"><?php echo fmt($total_monto - $total_gastos); ?></div>
                    <div class="stat-label" style="color: #0b141a;">Neto</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="action-bar">
            <button class="btn" onclick="openModal('modal-trabajo')"><i class="fas fa-plus"></i> Nuevo Trabajo</button>
            <button class="btn btn-outline" onclick="openModal('modal-gasto')"><i class="fas fa-minus"></i> Registrar Gasto</button>
            <button class="btn btn-outline" onclick="openModal('modal-maquina')"><i class="fas fa-tractor"></i> Máquinas</button>
            <button class="btn btn-outline" onclick="openModal('modal-cliente')"><i class="fas fa-users"></i> Clientes</button>
            <button class="btn btn-outline" onclick="openModal('modal-empleados')"><i class="fas fa-user-friends"></i> Empleados</button>
        </div>

        <!-- Pestañas -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('trabajos')">Trabajos</button>
            <button class="tab" onclick="showTab('gastos')">Gastos</button>
            <button class="tab" onclick="showTab('resumen')">Resumen por Empleado</button>
        </div>

        <!-- Tabla de Trabajos -->
        <div id="tab-trabajos" class="card">
            <h3>💼 Registro de Trabajos</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Empleado</th>
                            <th>Obra</th>
                            <th>Máquina</th>
                            <th>Horas</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trabajos)): ?>
                        <tr><td colspan="7" style="text-align: center; color: #8696a0;">No hay trabajos registrados esta quincena</td></tr>
                        <?php else: foreach ($trabajos as $t): ?>
                        <tr>
                            <td><?php echo date('d/m', strtotime($t['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($t['empleado'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($t['obra'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($t['maquina'] ?? '-'); ?></td>
                            <td><?php echo $t['horas_trabajadas']; ?></td>
                            <td><span class="badge badge-<?php echo $t['tipo_pago']; ?>"><?php echo strtoupper($t['tipo_pago']); ?></span></td>
                            <td><?php echo fmt($t['monto']); ?> Gs</td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla de Gastos -->
        <div id="tab-gastos" class="card" style="display: none;">
            <h3>💸 Gastos Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gastos)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #8696a0;">No hay gastos registrados</td></tr>
                    <?php else: foreach ($gastos as $g): ?>
                    <tr>
                        <td><?php echo date('d/m', strtotime($g['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($g['empleado']); ?></td>
                        <td><?php echo htmlspecialchars($g['concepto']); ?></td>
                        <td><?php echo fmt($g['monto']); ?> Gs</td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Resumen por Empleado -->
        <div id="tab-resumen" class="card" style="display: none;">
            <h3>👥 Resumen por Empleado</h3>
            <table>
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Horas</th>
                        <th>Trabajos</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $resumen = [];
                    foreach ($trabajos as $t) {
                        $eid = $t['empleado_id'];
                        if (!isset($resumen[$eid])) {
                            $resumen[$eid] = ['nombre' => $t['empleado'], 'horas' => 0, 'cantidad' => 0, 'monto' => 0];
                        }
                        $resumen[$eid]['horas'] += $t['horas_trabajadas'];
                        $resumen[$eid]['cantidad']++;
                        $resumen[$eid]['monto'] += $t['monto'];
                    }
                    foreach ($resumen as $r):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                        <td><?php echo $r['horas']; ?></td>
                        <td><?php echo $r['cantidad']; ?></td>
                        <td><?php echo fmt($r['monto']); ?> Gs</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($resumen)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #8696a0;">Sin datos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo Trabajo -->
    <div id="modal-trabajo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nuevo Trabajo</h3>
                <button onclick="closeModal('modal-trabajo')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form method="POST" action="api/guardar_trabajo.php">
                <div class="form-group">
                    <label>Empleado</label>
                    <select name="empleado_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($empleados as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Obra</label>
                    <select name="obra_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($obras as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['nombre'] . ' (' . $o['cliente_nombre'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Máquina</label>
                    <select name="maquina_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($maquinas as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Horas trabajadas</label>
                    <input type="number" name="horas" step="0.5" min="0" placeholder="Ej: 8" required>
                </div>
                <div class="form-group">
                    <label>Tipo de pago</label>
                    <select name="tipo_pago" required>
                        <option value="hora">Por Hora</option>
                        <option value="dia">Por Día</option>
                        <option value="porcentaje">Por Porcentaje</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto (Gs)</label>
                    <input type="number" name="monto" placeholder="Monto total" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Detalles del trabajo..."></textarea>
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Gasto -->
    <div id="modal-gasto" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registrar Gasto</h3>
                <button onclick="closeModal('modal-gasto')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form method="POST" action="api/guardar_gasto.php">
                <div class="form-group">
                    <label>Empleado</label>
                    <select name="empleado_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($empleados as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Concepto</label>
                    <input type="text" name="concepto" placeholder="Ej: Gasoil, Repuesto, Alimentación" required>
                </div>
                <div class="form-group">
                    <label>Monto (Gs)</label>
                    <input type="number" name="monto" placeholder="Ej: 200000" required>
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Máquinas -->
    <div id="modal-maquina" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🚜 Máquinas</h3>
                <button onclick="closeModal('modal-maquina')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <table>
                <thead><tr><th>Nombre</th><th>Marca</th><th>Precio/Hora</th></tr></thead>
                <tbody>
                    <?php foreach ($maquinas as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($m['marca']); ?></td>
                        <td><?php echo fmt($m['precio_hora']); ?> Gs</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Clientes -->
    <div id="modal-cliente" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👥 Clientes</h3>
                <button onclick="closeModal('modal-cliente')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <table>
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Obras</th></tr></thead>
                <tbody>
                    <?php foreach ($clientes as $c): 
                        $st = $pdo->prepare("SELECT COUNT(*) FROM obras WHERE cliente_id = ?");
                        $st->execute([$c['id']]);
                        $num_obras = $st->fetchColumn();
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($c['telefono']); ?></td>
                        <td><?php echo $num_obras; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Empleados -->
    <div id="modal-empleados" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👷 Empleados</h3>
                <button onclick="closeModal('modal-empleados')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <table>
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Teléfono</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($empleados as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($e['user_login']); ?></td>
                        <td><?php echo htmlspecialchars($e['telefono']); ?></td>
                        <td><?php echo $e['activo'] ? 'Activo' : 'Inactivo'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chat Flotante -->
    <div id="chat-panel" style="display:none; position:fixed; bottom:70px; right:15px; width:320px; height:400px; background:var(--card); border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.5); z-index:1000; flex-direction:column; overflow:hidden;">
        <div style="background:var(--header); padding:10px; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight:600;">💬 Chat</span>
            <button onclick="toggleChat()" style="background:none;border:none;color:#fff;cursor:pointer;">✕</button>
        </div>
        <div id="chat-messages" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px;">
            <div class="msg in" style="background:var(--msg-in); padding:8px 12px; border-radius:10px; max-width:90%; font-size:0.85rem;">
                Hola <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?>! Escribí o usá el micrófono.
            </div>
        </div>
        <div style="background:var(--header); padding:10px; display:flex; gap:8px; align-items:center;">
            <button id="voice-btn-panel" onclick="startVoicePanel()" style="background:none;border:none;color:var(--primary);font-size:1.2rem;cursor:pointer;"><i class="fas fa-microphone"></i></button>
            <input id="msg-input-panel" type="text" placeholder="Mensaje..." style="flex:1; padding:8px; background:#2a3942; border:none; color:white; border-radius:15px; outline:none;" onkeypress="if(event.key==='Enter')sendPanel()">
            <button onclick="sendPanel()" style="background:var(--primary); border:none; width:32px; height:32px; border-radius:50%; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <script>
        let chatVisible = false;
        function toggleChat() {
            chatVisible = !chatVisible;
            document.getElementById('chat-panel').style.display = chatVisible ? 'flex' : 'none';
        }

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        function startVoicePanel() {
            if (Recognition) {
                const rec = new Recognition();
                rec.lang = 'es-ES';
                rec.onresult = (e) => {
                    document.getElementById('msg-input-panel').value = e.results[0][0].transcript;
                    sendPanel();
                };
                rec.start();
            }
        }

        async function sendPanel() {
            const i = document.getElementById('msg-input-panel'), t = i.value.trim(); 
            if(!t) return;
            i.value = ''; 
            addMsgPanel(t, 'out');
            try {
                const r = await fetch('api/webhook.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entry: [{ changes: [{ value: { messages: [{ text: { body: t } }] } }] }] })
                });
                const d = await r.json(); 
                addMsgPanel(d.mensaje || d.error || 'Error', 'in');
            } catch (e) { 
                addMsgPanel("Error de servidor", "in"); 
            }
        }
        function addMsgPanel(t, c) {
            const d = document.getElementById('chat-messages'), m = document.createElement('div');
            m.className = 'msg ' + c; m.style.maxWidth = '90%'; m.style.padding = '8px 12px'; m.style.borderRadius = '10px'; m.style.fontSize = '0.85rem';
            m.innerText = t; d.appendChild(m); 
            d.scrollTop = d.scrollHeight;
        }

        function showTab(tab) {
            document.querySelectorAll('.card[id^="tab-"]').forEach(el => el.style.display = 'none');
            document.getElementById('tab-' + tab).style.display = 'block';
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            event.target.classList.add('active');
        }
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    </script>
</body>
</html>
