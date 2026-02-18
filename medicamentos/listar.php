<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$rol = $_SESSION['rol'] ?? 'paciente';
$dashboard = '../dashboard/paciente.php';
if ($rol === 'admin') {
    $dashboard = '../dashboard/admin.php';
} elseif ($rol === 'cuidador') {
    $dashboard = '../dashboard/cuidador.php';
}

$stmt = $pdo->query('SELECT id_medicamento, nombre, tipo, dosis, cantidad_total, fecha_vencimiento FROM medicamentos ORDER BY nombre');
$medicamentos = $stmt->fetchAll();
$showSuccess = isset($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicamentos</title>
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Listado de medicamentos</h1>
        <a class="btn" href="agregar.php">Agregar medicamento</a>
    </div>

    <?php if ($showSuccess): ?>
        <div class="alert alert-success">Medicamento guardado correctamente.</div>
    <?php endif; ?>

    <p><a href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') ?>">← Volver al dashboard</a></p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Dosis</th>
                <th>Cantidad</th>
                <th>Vence</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($medicamentos) === 0): ?>
            <tr><td colspan="6">No hay medicamentos registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($medicamentos as $m): ?>
            <tr>
                <td><?= htmlspecialchars((string) $m['id_medicamento']) ?></td>
                <td><?= htmlspecialchars($m['nombre']) ?></td>
                <td><?= htmlspecialchars((string) $m['tipo']) ?></td>
                <td><?= htmlspecialchars((string) $m['dosis']) ?></td>
                <td><?= htmlspecialchars((string) $m['cantidad_total']) ?></td>
                <td><?= htmlspecialchars((string) $m['fecha_vencimiento']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
