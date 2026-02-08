<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$where = '';
$params = [];
if (($_SESSION['rol'] ?? '') === 'paciente') {
    $where = 'WHERE p.id_usuario = ?';
    $params[] = $_SESSION['id_usuario'];
}

$sql = "
SELECT
    h.fecha,
    h.hora,
    m.nombre AS medicamento,
    h.resultado,
    h.observaciones
FROM historial_dispenso h
JOIN programacion p ON h.id_programacion = p.id_programacion
JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
{$where}
ORDER BY h.fecha DESC, h.hora DESC
";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll();

$dashboard = '../dashboard/paciente.php';
if (($_SESSION['rol'] ?? '') === 'admin') {
    $dashboard = '../dashboard/admin.php';
} elseif (($_SESSION['rol'] ?? '') === 'cuidador') {
    $dashboard = '../dashboard/cuidador.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial</title>
    <link rel="stylesheet" href="../assets/css/general.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Historial de dispensos</h1>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($dashboard) ?>">Volver</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Medicamento</th>
                <th>Resultado</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($historial) === 0): ?>
                <tr><td colspan="5">Sin registros todavía.</td></tr>
            <?php else: ?>
                <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$h['fecha']) ?></td>
                    <td><?= htmlspecialchars((string)$h['hora']) ?></td>
                    <td><?= htmlspecialchars((string)$h['medicamento']) ?></td>
                    <td><?= htmlspecialchars((string)$h['resultado']) ?></td>
                    <td><?= htmlspecialchars((string)($h['observaciones'] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
