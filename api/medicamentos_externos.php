<?php
header('Content-Type: application/json');

$apiFdaBase = 'https://api.fda.gov/drug/ndc.json';
$apiCkanBase = 'https://datosabiertos.gob.ec/api/3/action';
$apiLimit = 200;
$maxPages = 3;
$maxMedicamentos = 300;
$soloEcuador = !isset($_GET['solo_ecuador']) || $_GET['solo_ecuador'] !== '0';

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

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($texto, 'UTF-8');
    }

    // Fallback UTF-8 sin mbstring: cubre acentos y Ñ para reglas de detección.
    $map = [
        'Á' => 'á', 'É' => 'é', 'Í' => 'í', 'Ó' => 'ó', 'Ú' => 'ú',
        'À' => 'à', 'È' => 'è', 'Ì' => 'ì', 'Ò' => 'ò', 'Ù' => 'ù',
        'Ä' => 'ä', 'Ë' => 'ë', 'Ï' => 'ï', 'Ö' => 'ö', 'Ü' => 'ü',
        'Â' => 'â', 'Ê' => 'ê', 'Î' => 'î', 'Ô' => 'ô', 'Û' => 'û',
        'Ã' => 'ã', 'Õ' => 'õ', 'Ñ' => 'ñ', 'Ç' => 'ç',
    ];

    return strtr(strtolower($texto), $map);
}

function esFormaComestible(string $tipo): bool
{
    $tipoLower = textoLower($tipo);
    if ($tipoLower === '') {
        return false;
    }

    $formasPermitidas = [
        'tablet',
        'capsule',
        'caplet',
        'pill',
        'comprimido',
        'cápsula',
        'capsula',
        'gragea',
        'pastilla',
        'chewable',
        'lozenge',
        'troche',
        'oral',
        'sólido oral',
        'solido oral',
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
        'syrup',
        'jarabe',
        'spray',
        'aerosol',
        'inhal',
        'injection',
        'injectable',
        'cream',
        'ointment',
        'gel',
        'patch',
        'drops',
        'solution',
        'solucion',
        'solución',
        'suspensión',
        'suspension',
        'suppository',
        'shampoo',
        'lotion',
        'líquido',
        'liquido',
        'topico',
        'tópico',
    ];

    foreach ($bloqueadas as $forma) {
        if (str_contains($tipoLower, $forma)) {
            return true;
        }
    }

    return false;
}

function normalizarClave(string $texto): string
{
    $texto = textoLower($texto);
    return preg_replace('/\s+/', '_', $texto) ?? '';
}

function normalizarNombreMedicamento(string $texto): string
{
    $texto = textoLower($texto);
    $texto = preg_replace('/[^a-z0-9áéíóúñ\s]/u', ' ', $texto) ?? '';
    $texto = preg_replace('/\s+/', ' ', trim($texto)) ?? '';
    return $texto;
}

function buscarCampo(array $item, array $aliases): string
{
    $index = [];
    foreach ($item as $key => $value) {
        $index[normalizarClave((string) $key)] = $value;
    }

    foreach ($aliases as $alias) {
        $clave = normalizarClave($alias);
        if (!array_key_exists($clave, $index)) {
            continue;
        }

        $valor = trim((string) $index[$clave]);
        if ($valor !== '') {
            return $valor;
        }
    }

    return '';
}

function extraerTipoDesdeTexto(string $texto): string
{
    $textoLower = textoLower($texto);

    $mapa = [
        'sólido oral' => ['sólido oral', 'solido oral'],
        'tablet' => ['tablet'],
        'capsule' => ['capsule', 'cápsula', 'capsula'],
        'pill' => ['pill', 'pastilla', 'comprimido'],
    ];

    foreach ($mapa as $tipo => $patrones) {
        foreach ($patrones as $patron) {
            if (str_contains($textoLower, $patron)) {
                return $tipo;
            }
        }
    }

    return '';
}

function obtenerJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
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

function obtenerCatalogoEcuador(string $apiCkanBase, int $maxItems = 2000): array
{
    $searchUrl = $apiCkanBase . '/package_search?q=' . urlencode('medicamentos arcsa') . '&rows=8';
    $searchData = obtenerJson($searchUrl);
    if (!$searchData || !($searchData['success'] ?? false)) {
        return [];
    }

    $resourceId = '';
    $results = $searchData['result']['results'] ?? [];
    foreach ($results as $dataset) {
        $datasetTitle = textoLower((string) ($dataset['title'] ?? ''));
        $datasetNotes = textoLower((string) ($dataset['notes'] ?? ''));
        $datasetEsMedicamentos = str_contains($datasetTitle, 'medicamento')
            || str_contains($datasetNotes, 'medicamento')
            || str_contains($datasetTitle, 'arcsa')
            || str_contains($datasetNotes, 'arcsa');

        foreach (($dataset['resources'] ?? []) as $resource) {
            $resourceFormat = textoLower((string) ($resource['format'] ?? ''));
            $resourceName = textoLower((string) ($resource['name'] ?? ''));
            $resourceDescription = textoLower((string) ($resource['description'] ?? ''));

            if (!($resource['datastore_active'] ?? false)) {
                continue;
            }

            $resourceEsMedicamentos = str_contains($resourceName, 'medicamento')
                || str_contains($resourceDescription, 'medicamento')
                || str_contains($resourceName, 'arcsa')
                || str_contains($resourceDescription, 'arcsa')
                || $datasetEsMedicamentos;

            if (!$resourceEsMedicamentos) {
                continue;
            }

            if (!in_array($resourceFormat, ['csv', 'xlsx', 'json', 'ods'], true)) {
                continue;
            }

            $resourceId = trim((string) ($resource['id'] ?? ''));
            if ($resourceId !== '') {
                break 2;
            }
        }
    }

    if ($resourceId === '') {
        return [];
    }

    $nombres = [];
    $limit = 200;
    for ($page = 0; $page < 5; $page++) {
        $offset = $page * $limit;
        $url = $apiCkanBase . '/datastore_search?resource_id=' . urlencode($resourceId) . '&limit=' . $limit . '&offset=' . $offset;
        $data = obtenerJson($url);
        if (!$data || !($data['success'] ?? false)) {
            break;
        }

        $rows = $data['result']['records'] ?? [];
        if (!is_array($rows) || count($rows) === 0) {
            break;
        }

        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nombre = buscarCampo($item, [
                'nombre_medicamento',
                'nombre_del_medicamento',
                'medicamento',
                'nombre_comercial',
                'principio_activo',
                'dci',
            ]);
            if ($nombre === '') {
                continue;
            }
            $nombres[normalizarNombreMedicamento($nombre)] = true;
            if (count($nombres) >= $maxItems) {
                break 2;
            }
        }
    }

    return $nombres;
}

$catalogoEcuador = $soloEcuador ? obtenerCatalogoEcuador($apiCkanBase) : [];
$catalogoEcuadorDisponible = count($catalogoEcuador) > 0;

if ($soloEcuador && !$catalogoEcuadorDisponible) {
    echo json_encode([
        'origen' => 'Fallback local (catálogo ARCSA no disponible)',
        'total' => count($fallbackMedicamentos),
        'medicamentos' => $fallbackMedicamentos,
        'warning' => 'No se pudo validar disponibilidad en Ecuador porque ARCSA no está disponible',
    ]);
    exit;
}

$medicamentos = [];
$seen = [];

for ($page = 0; $page < $maxPages; $page++) {
    $skip = $page * $apiLimit;
    $url = $apiFdaBase
        . '?limit=' . $apiLimit
        . '&skip=' . $skip;

    $data = obtenerJson($url);
    if (!$data || !is_array($data['results'] ?? null)) {
        if ($page === 0) {
            echo json_encode([
                'origen' => 'Fallback local (lectura de catálogo fallida)',
                'total' => count($fallbackMedicamentos),
                'medicamentos' => $fallbackMedicamentos,
                'warning' => 'No se pudo leer el catálogo de medicamentos de openFDA',
            ]);
            exit;
        }
        break;
    }

    $rows = $data['results'] ?? [];
    if (!is_array($rows) || count($rows) === 0) {
        break;
    }

    foreach ($rows as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nombre = buscarCampo($item, ['brand_name', 'generic_name', 'substance_name']);

        $tipo = buscarCampo($item, ['dosage_form', 'route']);

        $dosis = '';
        if (isset($item['active_ingredients']) && is_array($item['active_ingredients'])) {
            $ingredientes = $item['active_ingredients'];
            if (isset($ingredientes[0]['strength']) && trim((string) $ingredientes[0]['strength']) !== '') {
                $dosis = (string) $ingredientes[0]['strength'];
            }
        }

        if ($dosis === '') {
            $dosis = buscarCampo($item, ['strength']);
        }

        if ($nombre === '') {
            continue;
        }

        if ($soloEcuador) {
            $nombreNorm = normalizarNombreMedicamento($nombre);
            $genericNorm = normalizarNombreMedicamento(buscarCampo($item, ['generic_name', 'substance_name']));
            $coincide = isset($catalogoEcuador[$nombreNorm]) || ($genericNorm !== '' && isset($catalogoEcuador[$genericNorm]));

            if (!$coincide) {
                foreach ($catalogoEcuador as $catalogoNombre => $_) {
                    if (($nombreNorm !== '' && str_contains($catalogoNombre, $nombreNorm))
                        || ($genericNorm !== '' && str_contains($catalogoNombre, $genericNorm))
                        || ($nombreNorm !== '' && str_contains($nombreNorm, $catalogoNombre))
                        || ($genericNorm !== '' && str_contains($genericNorm, $catalogoNombre))) {
                        $coincide = true;
                        break;
                    }
                }
            }

            if (!$coincide) {
                continue;
            }
        }

        if ($tipo === '') {
            $tipo = extraerTipoDesdeTexto($nombre . ' ' . $dosis);
        }

        if (esFormaExcluida($tipo)) {
            continue;
        }

        if (!esFormaComestible($tipo)) {
            continue;
        }

        $key = textoLower($nombre . '|' . $tipo . '|' . $dosis);
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

    if (count($rows) < $apiLimit) {
        break;
    }
}

echo json_encode([
    'origen' => ($soloEcuador && $catalogoEcuadorDisponible) ? 'openFDA (NDC) filtrado con ARCSA Ecuador' : 'openFDA (NDC)',
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
]);
