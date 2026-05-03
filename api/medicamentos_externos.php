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

function esFormaComestible(string $tipo): bool
{
    $tipoLower = textoLower($tipo);
    if ($tipoLower === '') {
        return false;
    }

    $formasBloqueadas = [
        'solution', 'solucion', 'solución', 'syrup', 'jarabe', 'suspension', 'suspensión',
        'spray', 'aerosol', 'drops', 'drop', 'liquid', 'elixir', 'mouthwash', 'rinse',
    ];
    foreach ($formasBloqueadas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return false;
        }
    }

    $formasPermitidas = [
        'tablet', 'capsule', 'caplet', 'pill', 'chewable', 'lozenge', 'troche',
        'comprimido', 'capsula', 'cápsula', 'pastilla', 'gragea',
    ];

    foreach ($formasPermitidas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return true;
        }
    }

    return false;
}

$maxPaginas = 8; // 8 * 100 = hasta 800 registros crudos antes de filtrar.
$medicamentos = [];
$seen = [];
$erroresApi = [];

for ($pagina = 0; $pagina < $maxPaginas; $pagina++) {
    $skip = $pagina * 100;
    $url = $openFdaBase
        . '?limit=100&skip=' . $skip;

    $errorApi = null;
    $data = obtenerJson($url, $errorApi);

    if (!$data || !is_array($data['results'] ?? null)) {
        if ($errorApi !== null) {
            $erroresApi[] = '[página=' . ($pagina + 1) . '] ' . $errorApi;
        }
        continue;
    }

    foreach ($data['results'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nombre = trim((string) ($item['brand_name'] ?? $item['generic_name'] ?? ''));
        $tipo = trim((string) ($item['dosage_form'] ?? ''));
        $dosis = trim((string) ($item['strength'] ?? 'Dosis no informada por openFDA'));

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
