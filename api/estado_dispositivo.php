<?php
require "../config/conexion.php";

header("Content-Type: application/json");

$sql = "UPDATE configuracion_dispositivo
SET estado = 'conectado',
    ultimo_ping = NOW()
WHERE id_configuracion = 1";

$pdo->prepare($sql)->execute();

echo json_encode([
    "estado" => "online"
]);
