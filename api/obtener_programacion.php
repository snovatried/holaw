<?php
require "../config/conexion.php";

header("Content-Type: application/json");

$sql = "
SELECT 
    p.id_programacion,
    m.nombre,
    p.cantidad
FROM programacion p
JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
WHERE p.hora_dispenso = CURTIME()
AND p.estado = 'activo'
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resultado);
