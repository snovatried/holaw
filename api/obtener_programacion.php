<?php
require "../config/conexion.php";

header("Content-Type: application/json");

$sql = "
SELECT 
    p.id_programacion,
    p.hora_dispenso,
    p.estado,
    m.nombre,
    p.cantidad
FROM programacion p
JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
WHERE p.estado = 'activo'
ORDER BY p.hora_dispenso ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "estado" => "ok",
        "programaciones" => $resultado
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("obtener_programacion error: " . $e->getMessage());
    echo json_encode([
        "estado" => "error",
        "programaciones" => [],
        "mensaje" => "Error al obtener programación"
    ]);
}
