<?php
header('Content-Type: application/json; charset=utf-8');

$openFdaBase = 'https://api.fda.gov/drug/ndc.json';
$maxMedicamentos = 300;

$fallbackMedicamentos = [
    ['nombre' => 'Paracetamol', 'tipo' => 'tablet', 'dosis' => '500 mg'],
    ['nombre' => 'Ibuprofeno', 'tipo' => 'tablet', 'dosis' => '400 mg'],
    ['nombre' => 'Amoxicilina', 'tipo' => 'capsule', 'dosis' => '500 mg'],
    ['nombre' => 'Loratadina', 'tipo' => 'tablet', 'dosis' => '10 mg'],
    ['nombre' => 'Omeprazol', 'tipo' => 'capsule', 'dosis' => '20 mg'],
];

function textoLower(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

function obtenerJson(string $url, ?string &$error = null): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $httpStatus = 'sin código HTTP';
        global $http_response_header;
        if (isset($http_response_header[0])) {
            $httpStatus = trim((string) $http_response_header[0]);
        }

        $lastError = error_get_last();
        $detalle = trim((string) ($lastError['message'] ?? 'error de conexión'));
        $error = 'Fallo al consultar URL (' . $httpStatus . '): ' . $detalle;
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $error = 'Respuesta no JSON o JSON inválido en URL: ' . $url;
        return null;
    }

    return $data;
}


function obtenerDosis(array $item): string
{
    $dosis = trim((string) ($item['strength'] ?? ''));
    if ($dosis !== '') {
        return $dosis;
    }

    $ingredientes = $item['active_ingredients'] ?? null;
    if (!is_array($ingredientes)) {
        return 'No especificada';
    }

    $partes = [];
    foreach ($ingredientes as $ingrediente) {
        if (!is_array($ingrediente)) {
            continue;
        }

        $nombre = trim((string) ($ingrediente['name'] ?? ''));
        $fuerza = trim((string) ($ingrediente['strength'] ?? ''));

        if ($fuerza === '') {
            continue;
        }

        $partes[] = $nombre !== '' ? ($nombre . ' ' . $fuerza) : $fuerza;
    }

    if (count($partes) === 0) {
        return 'No especificada';
    }

    $partes = array_values(array_unique($partes));
    return implode(' + ', $partes);
}

function esFormaComestible(string $tipo): bool
{
    $tipoLower = textoLower($tipo);
    if ($tipoLower === '') {
        return false;
    }

    $formasBloqueadas = [
        'solution', 'solucion', 'solución', 'syrup', 'jarabe',
        'suspension', 'suspensión', 'elixir', 'drop', 'drops', 'liquid',
    ];

    foreach ($formasBloqueadas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return false;
        }
    }

    $formasPermitidas = [
        'tablet', 'capsule', 'caplet', 'pill', 'chewable', 'lozenge', 'troche',
        'comprimido', 'capsula', 'cápsula', 'pastilla', 'gragea', 'oral',
    ];

    foreach ($formasPermitidas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return true;
        }
    }

    return false;
}

$terminosBusqueda = ['paracetamol', 'ibuprofen', 'amoxicillin', 'omeprazole', 'loratadine', 'metformin'];
$medicamentos = [];
$seen = [];
$erroresApi = [];

foreach ($terminosBusqueda as $termino) {
    $url = $openFdaBase
        . '?search=generic_name:' . urlencode('"' . $termino . '"')
        . '&limit=100';

    $errorApi = null;
    $data = obtenerJson($url, $errorApi);

    if (!$data || !is_array($data['results'] ?? null)) {
        if ($errorApi !== null) {
            $erroresApi[] = '[término=' . $termino . '] ' . $errorApi;
        }
        continue;
    }

    foreach ($data['results'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nombre = trim((string) ($item['brand_name'] ?? $item['generic_name'] ?? ''));
        $tipo = trim((string) ($item['dosage_form'] ?? ''));
        $dosis = obtenerDosis($item);

        if ($nombre === '' || !esFormaComestible($tipo)) {
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
            'dosis' => $dosis,
        ];

        if (count($medicamentos) >= $maxMedicamentos) {
            break 2;
        }
    }
}

if (count($medicamentos) === 0) {
    echo json_encode([
        'origen' => 'Respaldo local (falló openFDA)',
        'total' => count($fallbackMedicamentos),
        'medicamentos' => $fallbackMedicamentos,
        'warning' => 'No se pudo leer la API openFDA',
        'errores' => array_slice($erroresApi, 0, 10),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'origen' => 'openFDA (NDC)',
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
], JSON_UNESCAPED_UNICODE);
