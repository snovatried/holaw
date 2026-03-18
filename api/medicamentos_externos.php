<?php
header('Content-Type: application/json');

$baseUrl = 'https://api.fda.gov/drug/ndc.json';
$batchSize = 100;
$maxBatches = 5;
$maxMedicamentos = 300;

$context = stream_context_create([
    'http' => [
        'timeout' => 8,
    ],
]);

$medicamentos = [];
$seen = [];

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $skip = $batch * $batchSize;
    $url = $baseUrl . '?limit=' . $batchSize . '&skip=' . $skip;
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        if ($batch === 0) {
            http_response_code(502);
            echo json_encode(['error' => 'No se pudo consultar la API externa']);
            exit;
        }
        break;
    }

    $data = json_decode($response, true);
    $results = $data['results'] ?? [];

    if (count($results) === 0) {
        break;
    }

    foreach ($results as $item) {
        $nombre = trim($item['brand_name'] ?? '');
        $tipo = trim($item['dosage_form'] ?? '');
        $dosis = trim($item['active_ingredients'][0]['strength'] ?? '');

        if ($nombre === '') {
            continue;
        }

        $tipoLower = mb_strtolower($tipo, 'UTF-8');
        if (str_contains($tipoLower, 'syrup') || str_contains($tipoLower, 'jarabe')) {
            continue;
        }

        $key = mb_strtolower($nombre . '|' . $tipo . '|' . $dosis, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $medicamentos[] = [
            'nombre' => $nombre,
            'tipo' => $tipo ?: 'No especificado',
            'dosis' => $dosis ?: 'No especificada',
        ];

        if (count($medicamentos) >= $maxMedicamentos) {
            break 2;
        }
    }
}

echo json_encode([
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
]);
