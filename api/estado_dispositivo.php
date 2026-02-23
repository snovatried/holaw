<?php
require "../config/conexion.php";

header("Content-Type: application/json");

$estado = $_POST['estado'] ?? 'conectado';
$nombre = $_POST['nombre_dispositivo'] ?? 'ESP32 Dispensador';
$idConfig = (int)($_POST['id_configuracion'] ?? 1);

if (!in_array($estado, ['conectado', 'desconectado'], true)) {
    $estado = 'conectado';
}

try {
    $sql = "UPDATE configuracion_dispositivo
            SET estado = ?,
                nombre_dispositivo = ?,
                ultimo_ping = NOW()
            WHERE id_configuracion = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$estado, $nombre, $idConfig]);

    echo json_encode([
        "estado" => "ok",
        "dispositivo" => $nombre,
        "conexion" => $estado,
        "id_configuracion" => $idConfig
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("estado_dispositivo error: " . $e->getMessage());
    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo actualizar estado"
    ]);
}
