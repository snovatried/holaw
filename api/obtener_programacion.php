<?php
require '../config/conexion.php';

header('Content-Type: application/json');

$checkColumn = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1');
$checkColumn->execute(['public', 'programacion', 'id_compartimento']);
$hasIdCompartimento = (bool) $checkColumn->fetchColumn();

$compartimentoSelect = $hasIdCompartimento ? 'COALESCE(p.id_compartimento, 0) AS id_compartimento,' : '0 AS id_compartimento,';

$sql = "
SELECT
    p.id_programacion,
    {$compartimentoSelect}
    m.nombre,
    p.cantidad
FROM programacion p
JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
WHERE p.hora_dispenso::time(0) = CURRENT_TIME::time(0)
AND p.estado = 'activo'
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resultado);
