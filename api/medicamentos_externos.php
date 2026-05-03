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

    $map = [
        'Á' => 'á', 'É' => 'é', 'Í' => 'í', 'Ó' => 'ó', 'Ú' => 'ú',
        'À' => 'à', 'È' => 'è', 'Ì' => 'ì', 'Ò' => 'ò', 'Ù' => 'ù',
        'Ä' => 'ä', 'Ë' => 'ë', 'Ï' => 'ï', 'Ö' => 'ö', 'Ü' => 'ü',
        'Â' => 'â', 'Ê' => 'ê', 'Î' => 'î', 'Ô' => 'ô', 'Û' => 'û',
        'Ã' => 'ã', 'Õ' => 'õ', 'Ñ' => 'ñ', 'Ç' => 'ç',
    ];

    return strtr(strtolower($texto), $map);
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

function normalizarClave(string $texto): string
{
    $texto = textoLower($texto);
    return preg_replace('/[^a-z0-9]+/u', '_', $texto) ?? '';
}

function valorATexto(mixed $valor): string
{
    if (is_string($valor) || is_numeric($valor)) {
        return trim((string) $valor);
    }

    if (is_array($valor)) {
        $partes = [];
        foreach ($valor as $item) {
            $txt = valorATexto($item);
            if ($txt !== '') {
                $partes[] = $txt;
            }
        }
        return trim(implode(' ', $partes));
    }

    return '';
}

function buscarCampo(array $item, array $aliases): string
{
    $indice = [];
    foreach ($item as $key => $valor) {
        $indice[normalizarClave((string) $key)] = valorATexto($valor);
    }

    foreach ($aliases as $alias) {
        $clave = normalizarClave($alias);
        if (!array_key_exists($clave, $indice)) {
            continue;
        }

        $valor = trim($indice[$clave]);
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
        'crema', 'pomada', 'gel', 'parche', 'gotas', 'solución', 'solucion',
        'suspensión', 'suspension', 'supositorio', 'champú', 'loción', 'locion', 'tópico', 'topico',
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
$erroresApi = [];

$maxPaginasPorTermino = 8;

foreach ($terminosBusqueda as $termino) {
    for ($pagina = 1; $pagina <= $maxPaginasPorTermino; $pagina++) {
        $url = $apiCimaBase . '/medicamentos?nombre=' . urlencode($termino) . '&pagina=' . $pagina;
        $errorApi = null;
        $data = obtenerJson($url, $errorApi);

        if (!$data || !is_array($data['resultados'] ?? null)) {
            if ($errorApi !== null) {
                $erroresApi[] = '[término=' . $termino . ', página=' . $pagina . '] ' . $errorApi;
            } else {
                $erroresApi[] = '[término=' . $termino . ', página=' . $pagina . '] Respuesta sin campo resultados';
            }
            break;
        }

        $resultados = $data['resultados'];
        if (count($resultados) === 0) {
            break;
        }

        foreach ($resultados as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nombre = buscarCampo($item, ['nombre', 'nombre_comercial', 'medicamento', 'nregistro']);
        $tipo = buscarCampo($item, ['forma_farmaceutica', 'forma_farmaceutica_simplificada', 'formaFarmaceutica', 'via_administracion', 'viaAdministracion', 'viasAdministracion']);
        $dosis = buscarCampo($item, ['dosis', 'concentracion', 'principio_activo', 'pactivos']);

        if ($nombre === '') {
            continue;
        }

        if ($tipo === '') {
            $tipo = $nombre;
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
            break 3;
        }
    }

        if (count($resultados) < 25) {
            break;
        }
    }
}

if (count($medicamentos) === 0) {
    echo json_encode([
        'origen' => 'Respaldo local (falló CIMA en español)',
        'total' => count($fallbackMedicamentos),
        'medicamentos' => $fallbackMedicamentos,
        'warning' => 'No se pudo leer la API CIMA de medicamentos en español',
        'errores' => array_slice($erroresApi, 0, 10),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'origen' => 'CIMA (AEMPS) - API de medicamentos en español',
    'total' => count($medicamentos),
    'medicamentos' => array_values($medicamentos),
], JSON_UNESCAPED_UNICODE);
