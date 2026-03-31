<?php
/**
 * Panel de Control - Planilla Central
 */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ./");
    exit;
}

$es_admin = ($_SESSION['rol'] ?? '') === 'admin';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$nombre = $_SESSION['nombre'] ?? 'Usuario';

// Filtros
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_obra = $_GET['obra'] ?? '';
$filtro_maquina = $_GET['maquina'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$filtro_fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

// Construir query con filtros
$where = "WHERE t.fecha BETWEEN ? AND ?";
$params = [$filtro_fecha_inicio, $filtro_fecha_fin];

if ($filtro_empleado) {
    $where .= " AND t.empleado_id = ?";
    $params[] = $filtro_empleado;
}
if ($filtro_obra) {
    $where .= " AND t.obra_id = ?";
    $params[] = $filtro_obra;
}
if ($filtro_maquina) {
    $where .= " AND t.maquina_id = ?";
    $params[] = $filtro_maquina;
}

// Obtener datos para la planilla
$st = $pdo->prepare("SELECT t.*, u.nombre as empleado, m.nombre as maquina, o.nombre as obra 
    FROM trabajos t 
    LEFT JOIN usuarios u ON t.empleado_id = u.id 
    LEFT JOIN maquinas m ON t.maquina_id = m.id 
    LEFT JOIN obras o ON t.obra_id = o.id
    $where
    ORDER BY t.fecha DESC, t.id DESC");
$st->execute($params);
$trabajos = $st->fetchAll();

// Obtener gastos
$st = $pdo->prepare("SELECT g.*, u.nombre as empleado 
    FROM gastos g 
    LEFT JOIN usuarios u ON g.empleado_id = u.id
    WHERE g.fecha BETWEEN ? AND ?
    ORDER BY g.fecha DESC");
$st->execute([$filtro_fecha_inicio, $filtro_fecha_fin]);
$gastos = $st->fetchAll();

// Obtener combustibles
$st = $pdo->prepare("SELECT c.*, u.nombre as empleado, m.nombre as maquina, o.nombre as obra 
    FROM combustibles c 
    LEFT JOIN usuarios u ON c.empleado_id = u.id
    LEFT JOIN maquinas m ON c.maquina_id = m.id
    LEFT JOIN obras o ON c.obra_id = o.id
    WHERE c.fecha BETWEEN ? AND ?
    ORDER BY c.fecha DESC");
$st->execute([$filtro_fecha_inicio, $filtro_fecha_fin]);
$combustibles = $st->fetchAll();

$st = $pdo->prepare("SELECT i.*, u.nombre as empleado, m.nombre as maquina 
    FROM incidentes i 
    LEFT JOIN usuarios u ON i.empleado_id = u.id
    LEFT JOIN maquinas m ON i.maquina_id = m.id
    WHERE i.fecha BETWEEN ? AND ?
    ORDER BY i.fecha DESC");
$st->execute([$filtro_fecha_inicio, $filtro_fecha_fin]);
$incidentes = $st->fetchAll();

// Asistencia de hoy
$st = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre");
$todos_empleados = $st->fetchAll();

$st = $pdo->prepare("SELECT empleado_id, presente, login_hora FROM asistencia WHERE fecha = CURDATE()");
$st->execute();
$asistencia_hoy = [];
while ($a = $st->fetch(PDO::FETCH_ASSOC)) {
    $asistencia_hoy[$a['empleado_id']] = $a;
}

// Totales
$total_horas = 0;
$total_monto = 0;
$total_gastos = 0;
$total_combustibles = 0;
$total_viaticos = 0;
$total_adicionales = 0;
$trabajos_count = count($trabajos);
foreach ($trabajos as $t) {
    $total_horas += $t['horas_trabajadas'];
    $total_monto += $t['monto'];
    $total_viaticos += $t['viaticos'] ?? 0;
    $total_adicionales += $t['adicionales'] ?? 0;
}
foreach ($gastos as $g) {
    $total_gastos += $g['monto'];
}
foreach ($combustibles as $c) {
    $total_combustibles += $c['litros'];
}

// Totales por empleado
$por_empleado = [];
foreach ($trabajos as $t) {
    $eid = $t['empleado_id'];
    if (!isset($por_empleado[$eid])) {
        $por_empleado[$eid] = ['nombre' => $t['empleado'], 'horas' => 0, 'monto' => 0, 'viaticos' => 0, 'adicionales' => 0];
    }
    $por_empleado[$eid]['horas'] += $t['horas_trabajadas'];
    $por_empleado[$eid]['monto'] += $t['monto'];
    $por_empleado[$eid]['viaticos'] += $t['viaticos'] ?? 0;
    $por_empleado[$eid]['adicionales'] += $t['adicionales'] ?? 0;
}

// Totales por obra
$por_obra = [];
foreach ($trabajos as $t) {
    $oid = $t['obra_id'];
    if (!isset($por_obra[$oid])) {
        $por_obra[$oid] = ['nombre' => $t['obra'] ?? 'Sin obra', 'horas' => 0, 'monto' => 0];
    }
    $por_obra[$oid]['horas'] += $t['horas_trabajadas'];
    $por_obra[$oid]['monto'] += $t['monto'];
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
            <a href="index.php?logout=1" style="color: #8696a0; cursor: pointer;" onclick="return confirm('¿Querés salir?')"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>

    <div class="container">
        <!-- Resumen de Período -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3>📅 Período: <?php echo date('d/m', strtotime($filtro_fecha_inicio)) . ' - ' . date('d/m', strtotime($filtro_fecha_fin)); ?></h3>
            </div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_horas); ?></div>
                    <div class="stat-label">Horas Totales</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($trabajos_count); ?></div>
                    <div class="stat-label">Trabajos</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_monto); ?> Gs</div>
                    <div class="stat-label">Mano de Obra</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_viaticos); ?> Gs</div>
                    <div class="stat-label">Viáticos</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_adicionales); ?> Gs</div>
                    <div class="stat-label">Adicionales</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo fmt($total_gastos); ?> Gs</div>
                    <div class="stat-label">Gastos</div>
                </div>
                <div class="stat" style="background: var(--primary); color: #0b141a;">
                    <div class="stat-value" style="color: #0b141a;"><?php echo fmt($total_monto + $total_viaticos + $total_adicionales - $total_gastos); ?></div>
                    <div class="stat-label" style="color: #0b141a;">TOTAL NETO</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="action-bar">
            <button class="btn" onclick="openModal('modal-trabajo')"><i class="fas fa-plus"></i> Trabajo</button>
            <button class="btn btn-outline" onclick="openModal('modal-gasto')"><i class="fas fa-minus"></i> Gasto</button>
            <button class="btn btn-outline" onclick="openModal('modal-obra')"><i class="fas fa-hard-hat"></i> Obras</button>
            <button class="btn btn-outline" onclick="openModal('modal-maquina')"><i class="fas fa-tractor"></i> Máquinas</button>
            <button class="btn btn-outline" onclick="openModal('modal-usuarios')"><i class="fas fa-user-cog"></i> Usuarios</button>
            <button class="btn btn-outline" onclick="toggleFiltros()" style="background:#f39c12;border:none;"><i class="fas fa-filter"></i> Filtros</button>
            <a href="api/exportar_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline" style="background:#27ae60;border:none;text-decoration:none;"><i class="fas fa-file-excel"></i> Excel</a>
        </div>

        <!-- Filtros -->
        <div id="filtros-panel" class="card" style="display:none;">
            <h3>🔍 Filtros</h3>
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
                <div class="form-group">
                    <label>Empleado</label>
                    <select name="empleado">
                        <option value="">Todos</option>
                        <?php foreach($empleados as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $filtro_empleado==$e['id']?'selected':''; ?>><?php echo $e['nombre']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Obra</label>
                    <select name="obra">
                        <option value="">Todas</option>
                        <?php foreach($obras as $o): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo $filtro_obra==$o['id']?'selected':''; ?>><?php echo $o['nombre']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Máquina</label>
                    <select name="maquina">
                        <option value="">Todas</option>
                        <?php foreach($maquinas as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $filtro_maquina==$m['id']?'selected':''; ?>><?php echo $m['nombre']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn" style="width:100%;">Aplicar</button>
                </div>
            </form>
        </div>

        <!-- Pestañas -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('trabajos')">Trabajos</button>
            <button class="tab" onclick="showTab('gastos')">Gastos</button>
            <button class="tab" onclick="showTab('combustibles')">Combustible</button>
            <button class="tab" onclick="showTab('incidentes')">Incidentes</button>
            <button class="tab" onclick="showTab('asistencia')">Asistencia</button>
            <button class="tab" onclick="showTab('resumen')">Por Empleado</button>
            <button class="tab" onclick="showTab('por_obra')">Por Obra</button>
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
                            <th>Viáticos</th>
                            <th>Adicional</th>
                            <th>Tipo</th>
                            <th>Total</th>
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
                            <td><?php echo fmt($t['viaticos'] ?? 0); ?></td>
                            <td><?php echo fmt($t['adicionales'] ?? 0); ?></td>
                            <td><span class="badge badge-<?php echo $t['tipo_pago']; ?>"><?php echo strtoupper($t['tipo_pago']); ?></span></td>
                            <td><strong><?php echo fmt($t['monto'] + ($t['viaticos'] ?? 0) + ($t['adicionales'] ?? 0)); ?></strong></td>
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

        <!-- Tabla de Combustibles -->
        <div id="tab-combustibles" class="card" style="display: none;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h3>⛽ Combustible</h3>
                <button class="btn" onclick="openModal('modal-nuevo-combustible')">+ Cargar</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Máquina</th>
                        <th>Obra</th>
                        <th>Litros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($combustibles)): ?>
                    <tr><td colspan="5" style="text-align: center; color: #8696a0;">No hay combustible registrado</td></tr>
                    <?php else: foreach ($combustibles as $c): ?>
                    <tr>
                        <td><?php echo date('d/m', strtotime($c['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($c['empleado']); ?></td>
                        <td><?php echo htmlspecialchars($c['maquina'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($c['obra'] ?? '-'); ?></td>
                        <td><strong><?php echo number_format($c['litros'], 0, ',', '.'); ?> L</strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabla de Incidentes -->
        <div id="tab-incidentes" class="card" style="display: none;">
            <h3>🚫 Incidentes / Días No Trabajados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Tipo</th>
                        <th>Máquina</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incidentes)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #8696a0;">No hay incidentes registrados</td></tr>
                    <?php else: foreach ($incidentes as $i): ?>
                    <tr>
                        <td><?php echo date('d/m', strtotime($i['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($i['empleado']); ?></td>
                        <td>
                            <?php 
                            $tipos = ['lluvia' => '🌧️ Lluvia', 'breakdown' => '🔧 Breakdown', 'mantenimiento' => '🔩 Mantenimiento', 'ausente' => '❌ Ausente'];
                            echo $tipos[$i['tipo']] ?? $i['tipo']; 
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($i['maquina'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabla de Asistencia -->
        <div id="tab-asistencia" class="card" style="display: none;">
            <h3>📅 Asistencia de Hoy (<?php echo date('d/m/Y'); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Estado</th>
                        <th>Hora Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todos_empleados as $emp): 
                        $asist = $asistencia_hoy[$emp['id']] ?? ['presente' => 0, 'login_hora' => null];
                        $presente = $asist['presente'] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['nombre']); ?></td>
                        <td>
                            <?php if ($presente): ?>
                            <span style="color: #4caf50; font-weight: bold;">✅ Presente</span>
                            <?php else: ?>
                            <span style="color: #f44336; font-weight: bold;">❌ Ausente</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $asist['login_hora'] ? date('H:i', strtotime($asist['login_hora'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
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

        <!-- Resumen por Obra -->
        <div id="tab-por_obra" class="card" style="display: none;">
            <h3>🏗️ Resumen por Obra</h3>
            <table>
                <thead>
                    <tr>
                        <th>Obra</th>
                        <th>Horas</th>
                        <th>Trabajos</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($por_obra as $o): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($o['nombre']); ?></td>
                        <td><?php echo $o['horas']; ?></td>
                        <td><?php echo count(array_filter($trabajos, fn($t) => $t['obra_id'] == array_search($o, $por_obra))); ?></td>
                        <td><?php echo fmt($o['monto']); ?> Gs</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($por_obra)): ?>
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
            <button class="btn" style="width:100%; margin-bottom:15px;" onclick="openModal('modal-nueva-maquina')">+ Nueva Máquina</button>
            <table>
                <thead><tr><th>Nombre</th><th>Marca</th><th>Precio/Hora</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($maquinas as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($m['marca']); ?></td>
                        <td><?php echo fmt($m['precio_hora']); ?></td>
                        <td><?php echo $m['estado']; ?></td>
                        <td>
                            <button onclick="editarMaquina(<?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nombre']); ?>', '<?php echo htmlspecialchars($m['marca']); ?>', '<?php echo htmlspecialchars($m['modelo'] ?? ''); ?>', '<?php echo htmlspecialchars($m['patente'] ?? ''); ?>', <?php echo $m['precio_hora']; ?>, <?php echo $m['precio_dia']; ?>, '<?php echo $m['estado']; ?>')" style="background:var(--primary);border:none;padding:4px 8px;border-radius:4px;cursor:pointer;">✏️</button>
                            <button onclick="eliminarMaquina(<?php echo $m['id']; ?>)" style="background:#ff5e5e;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nueva/Editar Máquina -->
    <div id="modal-nueva-maquina" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="titulo-maquina">Nueva Máquina</h3>
                <button onclick="closeModal('modal-nueva-maquina')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form id="form-maquina">
                <input type="hidden" name="id" id="maquina_id">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" id="maquina_nombre" required>
                </div>
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" id="maquina_marca">
                </div>
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="modelo" id="maquina_modelo">
                </div>
                <div class="form-group">
                    <label>Patente</label>
                    <input type="text" name="patente" id="maquina_patente">
                </div>
                <div class="form-group">
                    <label>Precio Hora (Gs)</label>
                    <input type="number" name="precio_hora" id="maquina_precio_hora">
                </div>
                <div class="form-group">
                    <label>Precio Día (Gs)</label>
                    <input type="number" name="precio_dia" id="maquina_precio_dia">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="maquina_estado">
                        <option value="disponible">Disponible</option>
                        <option value="alquilado">Alquilado</option>
                        <option value="mantenimiento">Mantenimiento</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Obras -->
    <div id="modal-obra" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏗️ Obras</h3>
                <button onclick="closeModal('modal-obra')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <button class="btn" style="width:100%; margin-bottom:15px;" onclick="openModal('modal-nueva-obra')">+ Nueva Obra</button>
            <table>
                <thead><tr><th>Nombre</th><th>Precio/Hora</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <?php 
                    $st = $pdo->query("SELECT o.*, c.nombre as cliente_nombre FROM obras o LEFT JOIN clientes c ON o.cliente_id = c.id ORDER BY o.nombre");
                    $todas_obras = $st->fetchAll();
                    foreach ($todas_obras as $o): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($o['nombre']); ?></td>
                        <td><?php echo fmt($o['precio_hora']); ?></td>
                        <td><?php echo $o['estado']; ?></td>
                        <td>
                            <button onclick="editarObra(<?php echo $o['id']; ?>, '<?php echo htmlspecialchars($o['nombre']); ?>', <?php echo $o['cliente_id']; ?>, <?php echo $o['precio_hora']; ?>, <?php echo $o['precio_dia']; ?>, '<?php echo $o['estado']; ?>')" style="background:var(--primary);border:none;padding:4px 8px;border-radius:4px;cursor:pointer;">✏️</button>
                            <button onclick="eliminarObra(<?php echo $o['id']; ?>)" style="background:#ff5e5e;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nueva/Editar Obra -->
    <div id="modal-nueva-obra" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="titulo-obra">Nueva Obra</h3>
                <button onclick="closeModal('modal-nueva-obra')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form id="form-obra">
                <input type="hidden" name="id" id="obra_id">
                <div class="form-group">
                    <label>Nombre de la Obra</label>
                    <input type="text" name="nombre" id="obra_nombre" required>
                </div>
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente_id" id="obra_cliente">
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Precio Hora (Gs)</label>
                    <input type="number" name="precio_hora" id="obra_precio_hora">
                </div>
                <div class="form-group">
                    <label>Precio Día (Gs)</label>
                    <input type="number" name="precio_dia" id="obra_precio_dia">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="obra_estado">
                        <option value="activa">Activa</option>
                        <option value="pausada">Pausada</option>
                        <option value="finalizada">Finalizada</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
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

    <!-- Modal Usuarios -->
    <div id="modal-usuarios" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👷 Gestión de Usuarios</h3>
                <button onclick="closeModal('modal-usuarios')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <button class="btn" style="width:100%; margin-bottom:15px;" onclick="openModal('modal-nuevo-usuario')">+ Nuevo Usuario</button>
            <table>
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($empleados as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($e['user_login']); ?></td>
                        <td><?php echo htmlspecialchars($e['telefono'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($e['rol']); ?></td>
                        <td><?php echo $e['activo'] ? '✓' : '✕'; ?></td>
                        <td><button onclick="editarUsuario(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['nombre']); ?>', '<?php echo htmlspecialchars($e['user_login']); ?>', '<?php echo htmlspecialchars($e['telefono'] ?? ''); ?>')" style="background:var(--primary);border:none;padding:5px 10px;border-radius:5px;cursor:pointer;">✏️</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Usuario -->
    <div id="modal-nuevo-usuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="titulo-usuario">Nuevo Usuario</h3>
                <button onclick="closeModal('modal-nuevo-usuario')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form id="form-usuario" method="POST" action="api/guardar_usuario.php">
                <input type="hidden" name="id" id="usuario_id">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" id="usuario_nombre" required>
                </div>
                <div class="form-group">
                    <label>Usuario (login)</label>
                    <input type="text" name="user_login" id="usuario_login" required>
                </div>
                <div class="form-group">
                    <label>Contraseña <span style="color:#8696a0">(dejar vacío para mantener)</span></label>
                    <input type="password" name="password" id="usuario_password">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="usuario_telefono">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" id="usuario_rol">
                        <option value="empleado">Empleado</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo Combustible -->
    <div id="modal-nuevo-combustible" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⛽ Cargar Combustible</h3>
                <button onclick="closeModal('modal-nuevo-combustible')" style="background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
            </div>
            <form id="form-combustible" method="POST" action="api/guardar_combustible.php">
                <div class="form-group">
                    <label>Empleado</label>
                    <select name="empleado_id" required>
                        <option value="">Seleccionar...</option>
                        <?php 
                        $st = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre");
                        while ($emp = $st->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Máquina</label>
                    <select name="maquina_id">
                        <option value="">Seleccionar...</option>
                        <?php 
                        $st = $pdo->query("SELECT id, nombre FROM maquinas WHERE activo = 1 ORDER BY nombre");
                        while ($maq = $st->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $maq['id']; ?>"><?php echo htmlspecialchars($maq['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Litros</label>
                    <input type="number" name="litros" step="0.01" required placeholder="Ej: 300">
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar</button>
            </form>
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
        function toggleFiltros() {
            const f = document.getElementById('filtros-panel');
            f.style.display = f.style.display === 'none' ? 'block' : 'none';
        }
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        
        function editarUsuario(id, nombre, login, telefono) {
            document.getElementById('titulo-usuario').innerText = 'Editar Usuario';
            document.getElementById('usuario_id').value = id;
            document.getElementById('usuario_nombre').value = nombre;
            document.getElementById('usuario_login').value = login;
            document.getElementById('usuario_password').value = '';
            document.getElementById('usuario_telefono').value = telefono || '';
            document.getElementById('usuario_rol').value = 'empleado';
            openModal('modal-nuevo-usuario');
        }
        
        document.getElementById('form-usuario').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('api/guardar_usuario.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        });
        
        document.getElementById('form-combustible').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('api/guardar_combustible.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        });
        
        // Máquinas
        function editarMaquina(id, nombre, marca, modelo, patente, precio_hora, precio_dia, estado) {
            document.getElementById('titulo-maquina').innerText = 'Editar Máquina';
            document.getElementById('maquina_id').value = id;
            document.getElementById('maquina_nombre').value = nombre;
            document.getElementById('maquina_marca').value = marca;
            document.getElementById('maquina_modelo').value = modelo;
            document.getElementById('maquina_patente').value = patente;
            document.getElementById('maquina_precio_hora').value = precio_hora;
            document.getElementById('maquina_precio_dia').value = precio_dia;
            document.getElementById('maquina_estado').value = estado;
            openModal('modal-nueva-maquina');
        }
        
        document.getElementById('form-maquina').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('api/guardar_maquina.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        });
        
        async function eliminarMaquina(id) {
            if (!confirm('¿Eliminar esta máquina?')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete');
            const res = await fetch('api/guardar_maquina.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            alert(data.message);
            location.reload();
        }
        
        // Obras
        function editarObra(id, nombre, cliente_id, precio_hora, precio_dia, estado) {
            document.getElementById('titulo-obra').innerText = 'Editar Obra';
            document.getElementById('obra_id').value = id;
            document.getElementById('obra_nombre').value = nombre;
            document.getElementById('obra_cliente').value = cliente_id;
            document.getElementById('obra_precio_hora').value = precio_hora;
            document.getElementById('obra_precio_dia').value = precio_dia;
            document.getElementById('obra_estado').value = estado;
            openModal('modal-nueva-obra');
        }
        
        document.getElementById('form-obra').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('api/guardar_obra.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        });
        
        async function eliminarObra(id) {
            if (!confirm('¿Eliminar esta obra?')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete');
            const res = await fetch('api/guardar_obra.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            alert(data.message);
            location.reload();
        }
    </script>
</body>
</html>
