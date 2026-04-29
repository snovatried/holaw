<?php
header('Content-Type: application/json');

$apiCkanBase = 'https://datosabiertos.gob.ec/api/3/action';
$searchQuery = 'medicamentos arcsa';
$resourceRows = 200;
$maxPages = 5;
$maxMedicamentos = 300;

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

$searchUrl = $apiCkanBase . '/package_search?q=' . urlencode($searchQuery) . '&rows=8';
$searchData = obtenerJson($searchUrl);
if (!$searchData || !($searchData['success'] ?? false)) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo consultar la API externa de medicamentos de Ecuador']);
    exit;
}

$resourceId = '';
$results = $searchData['result']['results'] ?? [];
foreach ($results as $dataset) {
    foreach (($dataset['resources'] ?? []) as $resource) {
        $resourceFormat = textoLower((string) ($resource['format'] ?? ''));
        $name = textoLower((string) ($resource['name'] ?? ''));
        $desc = textoLower((string) ($resource['description'] ?? ''));

        if (!($resource['datastore_active'] ?? false)) {
            continue;
        }

        $esMedicamentos = str_contains($name, 'medicamento')
            || str_contains($desc, 'medicamento')
            || str_contains(textoLower((string) ($dataset['title'] ?? '')), 'medicamento');

        if (!$esMedicamentos) {
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
    http_response_code(502);
    echo json_encode(['error' => 'No se encontró un catálogo compatible en la API de Ecuador']);
    exit;
}

$medicamentos = [];
$seen = [];

for ($page = 0; $page < $maxPages; $page++) {
    $offset = $page * $resourceRows;
    $url = $apiCkanBase
        . '/datastore_search?resource_id=' . urlencode($resourceId)
        . '&limit=' . $resourceRows
        . '&offset=' . $offset;

    $data = obtenerJson($url);
    if (!$data || !($data['success'] ?? false)) {
        if ($page === 0) {
            http_response_code(502);
            echo json_encode(['error' => 'No se pudo leer el catálogo de medicamentos de Ecuador']);
            exit;
        }
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
            'producto',
            'descripcion',
            'dci',
        ]);

        $tipo = buscarCampo($item, [
            'forma_farmaceutica',
            'tipo',
            'presentacion',
            'via_administracion',
            'forma',
        ]);

        $dosis = buscarCampo($item, [
            'concentracion',
            'dosis',
            'dosificacion',
            'strength',
        ]);

        if ($nombre === '') {
            continue;
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

    if (count($rows) < $resourceRows) {
        break;
    }
}

echo json_encode([
    'origen' => 'Datos Abiertos Ecuador (ARCSA)',
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
]);
