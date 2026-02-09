<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {
    header('Location: ../index.php');
    exit;
}

$hasIdPaciente = false;
$checkColumn = $conexion->query("SHOW COLUMNS FROM programacion LIKE 'id_paciente'");
if ($checkColumn && $checkColumn->fetch()) {
    $hasIdPaciente = true;
}

if ($hasIdPaciente) {
    $sql = "
        SELECT p.hora_dispenso, p.frecuencia, p.cantidad, m.nombre AS medicamento, m.dosis
        FROM programacion p
        JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
        WHERE p.estado = 'activo' AND (p.id_paciente = ? OR p.id_usuario = ?)
        ORDER BY CASE WHEN p.hora_dispenso >= CURTIME() THEN 0 ELSE 1 END, p.hora_dispenso ASC
        LIMIT 10
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$_SESSION['id_usuario'], $_SESSION['id_usuario']]);
} else {
    $sql = "
        SELECT p.hora_dispenso, p.frecuencia, p.cantidad, m.nombre AS medicamento, m.dosis
        FROM programacion p
        JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
        WHERE p.id_usuario = ? AND p.estado = 'activo'
        ORDER BY CASE WHEN p.hora_dispenso >= CURTIME() THEN 0 ELSE 1 END, p.hora_dispenso ASC
        LIMIT 10
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$_SESSION['id_usuario']]);
}
$proximos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Paciente</title>
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Dashboard Paciente</h1>
        <a class="btn btn-secondary" href="../auth/logout.php">Cerrar sesión</a>
    </div>

    <ul class="nav-links">
        <li><a href="../historial/ver.php">Ver mis dispensos</a></li>
    </ul>

    <section class="card" style="margin-top: 16px;">
        <h2>Próximos medicamentos</h2>
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Medicamento</th>
                    <th>Dosis</th>
                    <th>Cantidad</th>
                    <th>Frecuencia</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($proximos) === 0): ?>
                <tr><td colspan="5">No hay medicamentos programados.</td></tr>
            <?php else: ?>
                <?php foreach ($proximos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $p['hora_dispenso']) ?></td>
                    <td><?= htmlspecialchars((string) $p['medicamento']) ?></td>
                    <td><?= htmlspecialchars((string) ($p['dosis'] ?: 'No especificada')) ?></td>
                    <td><?= htmlspecialchars((string) $p['cantidad']) ?></td>
                    <td><?= htmlspecialchars((string) ($p['frecuencia'] ?: 'No especificada')) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
