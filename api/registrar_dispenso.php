<?php
require "../config/conexion.php";
require "../config/notificaciones.php";

header("Content-Type: application/json");

// Basic validation
$id_programacion = $_POST['id_programacion'] ?? null;
$resultado = $_POST['resultado'] ?? null; // 'exitoso' or 'error'
$obs = $_POST['observaciones'] ?? null;

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
        "mensaje" => "Error al registrar el dispenso"
    ]);
}
