<?php
header("Content-Type: application/json");

try {
    require "../config/conexion.php";
    require "../config/notificaciones.php";
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo inicializar la API",
        "detalle" => $e->getPrevious()?->getMessage() ?? $e->getMessage()
    ]);
    exit;
}

// Basic validation
$payload = $_POST;

if (empty($payload)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
    }
}

$id_programacion = $payload['id_programacion'] ?? null;
$resultado = $payload['resultado'] ?? null; // 'exitoso' or 'error'
$obs = $payload['observaciones'] ?? null;

if (!$id_programacion || !$resultado) {
    http_response_code(400);
    echo json_encode([
        "estado" => "error",
        "mensaje" => "Parámetros faltantes"
    ]);
    exit;
}

$sql = "INSERT INTO historial_dispenso
(id_programacion, fecha, hora, resultado, observaciones)
VALUES (?, CURRENT_DATE, CURRENT_TIME, ?, ?)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_programacion,
        $resultado,
        $obs
    ]);

    enviarCorreoDispenso($pdo, (int) $id_programacion, (string) $resultado, $obs);

    echo json_encode([
        "estado" => "ok",
        "mensaje" => "Dispenso registrado"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    // Log the real error on server; don't expose details to client
    error_log("registrar_dispenso error: " . $e->getMessage());
    echo json_encode([
        "estado" => "error",
        "mensaje" => "Error al registrar el dispenso",
        "detalle" => $e->getMessage()
    ]);
}
