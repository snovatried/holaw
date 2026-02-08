<?php
header('Content-Type: application/json');

$url = 'https://api.fda.gov/drug/ndc.json?limit=100';
$response = @file_get_contents($url);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo consultar la API externa']);
    exit;
}

$data = json_decode($response, true);
$results = $data['results'] ?? [];

$medicamentos = [];
$seen = [];

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
        'dosis' => $dosis ?: 'No especificada'
    ];
}

echo json_encode(['medicamentos' => array_values($medicamentos)]);
