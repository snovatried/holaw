<?php
header('Content-Type: application/json; charset=utf-8');

$apiCimaBase = 'https://cima.aemps.es/cima/rest';
$maxMedicamentos = 300;

$fallbackMedicamentos = [
    ['nombre' => 'Paracetamol', 'tipo' => 'comprimido', 'dosis' => '500 mg'],
    ['nombre' => 'Ibuprofeno', 'tipo' => 'comprimido', 'dosis' => '400 mg'],
    ['nombre' => 'Amoxicilina', 'tipo' => 'cápsula', 'dosis' => '500 mg'],
    ['nombre' => 'Loratadina', 'tipo' => 'comprimido', 'dosis' => '10 mg'],
    ['nombre' => 'Omeprazol', 'tipo' => 'cápsula', 'dosis' => '20 mg'],
];

function textoLower(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($texto, 'UTF-8');
    }

    return strtolower($texto);
}

function obtenerJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function buscarCampo(array $item, array $aliases): string
{
    foreach ($aliases as $alias) {
        if (!array_key_exists($alias, $item)) {
            continue;
        }

        $valor = trim((string) $item[$alias]);
        if ($valor !== '') {
            return $valor;
        }
    }

    return '';
}

function esFormaComestible(string $tipo): bool
{
    $tipoLower = textoLower($tipo);
    if ($tipoLower === '') {
        return false;
    }

    $formasPermitidas = [
        'comprimido', 'cápsula', 'capsula', 'gragea', 'pastilla',
        'oral', 'tableta', 'tablets', 'capsule',
    ];

    foreach ($formasPermitidas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return true;
        }
    }

    return false;
}

function esFormaExcluida(string $tipo): bool
{
    $tipoLower = textoLower($tipo);
    if ($tipoLower === '') {
        return true;
    }

    $bloqueadas = [
        'jarabe', 'spray', 'aerosol', 'inyectable', 'inyección',
        'crema', 'pomada', 'gel', 'parche', 'gotas', 'solución',
        'suspensión', 'supositorio', 'champú', 'loción', 'tópico',
    ];

    foreach ($bloqueadas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return true;
        }
    }

    return false;
}

$terminosBusqueda = ['a', 'e', 'i', 'o', 'u', 'paracetamol', 'ibuprofeno', 'amoxicilina'];
$medicamentos = [];
$seen = [];

foreach ($terminosBusqueda as $termino) {
    $url = $apiCimaBase . '/medicamentos?nombre=' . urlencode($termino);
    $data = obtenerJson($url);

    if (!$data || !is_array($data['resultados'] ?? null)) {
        continue;
    }

    foreach ($data['resultados'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nombre = buscarCampo($item, ['nombre', 'nregistro']);
        $tipo = buscarCampo($item, ['forma_farmaceutica', 'via_administracion']);
        $dosis = buscarCampo($item, ['dosis', 'concentracion']);

        if ($nombre === '' || $tipo === '') {
            continue;
        }

        if (esFormaExcluida($tipo) || !esFormaComestible($tipo)) {
            continue;
        }

        $key = textoLower($nombre . '|' . $tipo . '|' . $dosis);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $medicamentos[] = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'dosis' => $dosis !== '' ? $dosis : 'No especificada',
        ];

        if (count($medicamentos) >= $maxMedicamentos) {
            break 2;
        }
    }
}

if (count($medicamentos) === 0) {
    echo json_encode([
        'origen' => 'Respaldo local (falló CIMA en español)',
        'total' => count($fallbackMedicamentos),
        'medicamentos' => $fallbackMedicamentos,
        'warning' => 'No se pudo leer la API CIMA de medicamentos en español',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'origen' => 'CIMA (AEMPS) - API de medicamentos en español',
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
], JSON_UNESCAPED_UNICODE);
