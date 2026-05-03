<?php
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["estado" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

try {
    require "../config/conexion.php";

    $sql = "UPDATE configuracion_dispositivo
    SET estado = 'conectado',
        ultimo_ping = NOW()
    WHERE id_configuracion = 1";

    $pdo->prepare($sql)->execute();

    echo json_encode([
        "estado" => "online",
        "mensaje" => "Ping actualizado"
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("estado_dispositivo error: " . $e->getMessage());
    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo actualizar el estado",
        "detalle" => $e->getPrevious()?->getMessage() ?? $e->getMessage()
    ]);
}
