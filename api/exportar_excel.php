<?php
/**
 * Exportar a Excel
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    die('No autorizado');
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-15');
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_obra = $_GET['obra'] ?? '';
$filtro_maquina = $_GET['maquina'] ?? '';

$where = "WHERE t.fecha BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];

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

$st = $pdo->prepare("SELECT t.*, u.nombre as empleado, m.nombre as maquina, o.nombre as obra 
    FROM trabajos t 
    LEFT JOIN usuarios u ON t.empleado_id = u.id 
    LEFT JOIN maquinas m ON t.maquina_id = m.id 
    LEFT JOIN obras o ON t.obra_id = o.id
    $where
    ORDER BY t.fecha DESC, t.id DESC");
$st->execute($params);
$trabajos = $st->fetchAll();

// Headers Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="planilla_' . $fecha_inicio . '_' . $fecha_fin . '.xls"');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<?mso-application progid=\"Excel.Sheet\"?>
<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"
    xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">
<Worksheet ss:Name=\"Planilla\">
<Table>";

echo "<Row>";
echo "<Cell><Data ss:Type=\"String\">Fecha</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Empleado</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Obra</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Máquina</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Horas</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Viáticos</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Adicionales</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Tipo</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Mano de Obra</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Total</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Descripción</Data></Cell>";
echo "</Row>";

$total_horas = 0;
$total_mo = 0;
$total_viaticos = 0;
$total_adicionales = 0;

foreach ($trabajos as $t) {
    $total = $t['monto'] + ($t['viaticos'] ?? 0) + ($t['adicionales'] ?? 0);
    $total_horas += $t['horas_trabajadas'];
    $total_mo += $t['monto'];
    $total_viatics = ($t['viaticos'] ?? 0);
    $total_adics = ($t['adicionales'] ?? 0);
    
    echo "<Row>";
    echo "<Cell><Data ss:Type=\"String\">" . date('d/m/Y', strtotime($t['fecha'])) . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"String\">" . ($t['empleado'] ?? '') . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"String\">" . ($t['obra'] ?? '') . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"String\">" . ($t['maquina'] ?? '') . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"Number\">" . $t['horas_trabajadas'] . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"Number\">" . $total_viatics . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"Number\">" . $total_adics . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"String\">" . $t['tipo_pago'] . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"Number\">" . $t['monto'] . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"Number\">" . $total . "</Data></Cell>";
    echo "<Cell><Data ss:Type=\"String\">" . ($t['descripcion'] ?? '') . "</Data></Cell>";
    echo "</Row>";
}

// Totales
echo "<Row>";
echo "<Cell><Data ss:Type=\"String\"><strong>TOTALES</strong></Data></Cell>";
echo "<Cell></Cell><Cell></Cell><Cell></Cell>";
echo "<Cell><Data ss:Type=\"Number\">" . $total_horas . "</Data></Cell>";
echo "<Cell><Data ss:Type=\"Number\">" . $total_viaticos . "</Data></Cell>";
echo "<Cell><Data ss:Type=\"Number\">" . $total_adicionales . "</Data></Cell>";
echo "<Cell></Cell>";
echo "<Cell><Data ss:Type=\"Number\">" . $total_mo . "</Data></Cell>";
echo "<Cell><Data ss:Type=\"Number\">" . ($total_mo + $total_viaticos + $total_adicionales) . "</Data></Cell>";
echo "<Cell></Cell>";
echo "</Row>";

echo "</Table></Worksheet></Workbook>";
?>
