<?php
// Motor de Reglas Local Fieldata (100% Offline)
require_once __DIR__ . '/../config.php';

/**
 * Motor de Reglas Local Fieldata (100% Offline)
 * No requiere API Keys ni conexión a internet.
 */
function procesarMensajeCampo($contenido, $tipo = 'text') {
    if ($tipo === 'audio') {
        return [
            'tipo' => 'CONSULTA',
            'mensaje_especial' => 'He recibido tu nota de voz. En esta versión 100% privada, guardamos el audio para tu registro. Para procesar datos automáticante, por favor escríbelo.'
        ];
    }

    $texto = mb_strtolower($contenido, 'UTF-8');
    $resultado = ['tipo' => 'ORDEN', 'categoria' => 'ACTIVIDAD', 'lote' => 'Lote General', 'cantidad' => 0, 'unidad' => '', 'detalle' => $contenido];

    // 1. Detección de Consultas (Stock, Historia)
    if (preg_match('/(cuanto|cuántos|cuántas|stock|resumen|historial|paso|pasó|qued|tenemos)/u', $texto)) {
        $resultado['tipo'] = 'CONSULTA';
        $resultado['entidad'] = preg_match('/stock/u', $texto) ? 'stock' : 'actividades';
        return $resultado;
    }

    // 2. Diccionario de Verbos -> Categorías Agrícolas y Ganaderas (Expandido)
    $verbos = [
        'siembra' => ['sembr', 'plant', 'siembr', 'soil'], 
        'cosecha' => ['cosech', 'trill', 'recolecc', 'levant'],
        'parto' => ['naci', 'parto', 'pari', 'ternero', 'nace'],
        'compra' => ['compr', 'adquiri', 'pago'],
        'venta' => ['vendi', 'venda', 'entrega'],
        'movimiento' => ['movi', 'traslad', 'pasam', 'llevam', 'arre'],
        'gasto' => ['gast', 'factura', 'tiket', 'pague'],
        'cancelar' => ['cancelar', 'borrar', 'limpiar', 'olvida', 'anular'],
        'corregir' => ['no era', 'perdon', 'perdón', 'me equivoque', 'me equivoqué', 'corregir']
    ];

    foreach ($verbos as $cat => $raices) {
        foreach ($raices as $raiz) {
            if (strpos($texto, $raiz) !== false) {
                $resultado['categoria'] = strtoupper($cat);
                break 2;
            }
        }
    }

    // 3. Extracción de Cantidades (Números)
    if (preg_match('/(\d+(?:[\.,]\d+)?)/u', $texto, $matches)) {
        $resultado['cantidad'] = str_replace(',', '.', $matches[1]);
    }

    // 3.1 Inteligencia para GASTOS: Si es un gasto, buscar el "concepto" (lo que no es monto ni unidad)
    if (isset($resultado['categoria']) && $resultado['categoria'] === 'GASTO') {
        $palabras = explode(' ', $texto);
        $ignoradas = ['gaste', 'gasté', 'pague', 'pagué', 'en', 'de', 'para', 'gs', 'guarani', 'guaraníes', 'guaraní'];
        foreach ($palabras as $p) {
            $p_limpia = strtolower(trim($p, ".,!?"));
            if (!in_array($p_limpia, $ignoradas) && !is_numeric($p_limpia) && strlen($p_limpia) > 3) {
                $resultado['producto'] = ucfirst($p_limpia);
                break;
            }
        }
    }

    // 4. Extracción de Unidades y Moneda
    if (preg_match('/(ha|hectarea|hectárea|kg|kilo|litro|l|cabeza|animal|unidad|gs|guarani|guaraní)/ui', $texto, $matches)) {
        $u = strtolower($matches[1]);
        if (in_array($u, ['gs', 'guarani', 'guaraní'])) {
            $resultado['unidad'] = 'Gs.';
        } else {
            $resultado['unidad'] = $u;
        }
    }

    // 5. Detección de Lote (Lo que viene después de las palabras clave)
    if (preg_match('/(?:lote|potrero|campo)\s+([a-zA-Záéíóúñ0-9]+)/u', $texto, $matches)) {
        $resultado['lote'] = ucfirst($matches[1]);
    }

    // 6. Extracción de Producto/Cultivo
    if (preg_match_all('/(soja|maíz|maiz|trigo|girasol|gasoil|ternero|vacas|novillos|toros)/ui', $texto, $matches)) {
        // Si hay varios (ej: "no era soja era maiz"), tomamos el último
        $match = end($matches[0]);
        $resultado['producto'] = ucfirst($match);
    }

    // 6. Lógica Proactiva (¿Falta algo?)
    if ($resultado['tipo'] === 'ORDEN') {
        if ($resultado['categoria'] === 'ACTIVIDAD') {
            $resultado['pregunta_seguimiento'] = "¿Qué tipo de actividad fue? (Siembra, Parto, Gasto, etc.)";
        } elseif ($resultado['lote'] === 'Lote General' && $resultado['categoria'] !== 'GASTO') {
            $resultado['pregunta_seguimiento'] = "Perfecto, ¿en qué lote o potrero lo hiciste?";
        } elseif ($resultado['cantidad'] == 0 && in_array($resultado['categoria'], ['SIEMBRA', 'COMPRA', 'VENTA'])) {
            $resultado['pregunta_seguimiento'] = "¿Qué cantidad o cuántas hectáreas fueron?";
        }
    }

    return $resultado;
}

function llamarGemini($data) { return ["error" => "Offline"]; }
?>
