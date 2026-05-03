<?php
header('Content-Type: application/json');

$checks = [
    'php' => [
        'ok' => true,
        'version' => PHP_VERSION,
    ],
    'database' => [
        'ok' => false,
    ],
];

try {
    require '../config/conexion.php';
    $stmt = $pdo->query('SELECT NOW() AS server_time');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $checks['database'] = [
        'ok' => true,
        'server_time' => $row['server_time'] ?? null,
    ];
} catch (Throwable $e) {
    $checks['database'] = [
        'ok' => false,
        'error' => 'No se pudo conectar a la base de datos',
    ];
}

$allOk = $checks['php']['ok'] && $checks['database']['ok'];
http_response_code($allOk ? 200 : 503);

echo json_encode([
    'status' => $allOk ? 'ok' : 'error',
    'timestamp' => gmdate('c'),
    'checks' => $checks,
]);
