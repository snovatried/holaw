<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$rol = $_SESSION['rol'];
$dashboard = $rol === 'admin' ? '../dashboard/admin.php' : '../dashboard/cuidador.php';
$showSuccess = isset($_GET['ok']);

$hasIdPaciente = false;
$checkColumn = $conexion->query("SHOW COLUMNS FROM programacion LIKE 'id_paciente'");
if ($checkColumn && $checkColumn->fetch()) {
    $hasIdPaciente = true;
}

$medicamentos = $conexion->query('SELECT id_medicamento, nombre, dosis FROM medicamentos ORDER BY nombre')->fetchAll();
$pacientes = [];

if ($hasIdPaciente) {
    if ($rol === 'admin') {
        $stmtPacientes = $conexion->query("SELECT id_usuario, nombre FROM usuarios WHERE rol = 'paciente' AND estado = 'activo' ORDER BY nombre");
        $pacientes = $stmtPacientes->fetchAll();
    } else {
        $hasRelTable = $conexion->query("SHOW TABLES LIKE 'cuidadores_pacientes'")->fetch();
        if ($hasRelTable) {
            $sqlPacientes = "
                SELECT u.id_usuario, u.nombre
                FROM cuidadores_pacientes cp
                JOIN usuarios u ON u.id_usuario = cp.id_paciente
                WHERE cp.id_cuidador = ? AND u.estado = 'activo'
                ORDER BY u.nombre
            ";
            $stmtPacientes = $conexion->prepare($sqlPacientes);
            $stmtPacientes->execute([$_SESSION['id_usuario']]);
            $pacientes = $stmtPacientes->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar dispensos</title>
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
<div class="container">
    <section class="card">
        <h1>Programar medicamento</h1>
        <p><a href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') ?>">← Volver dashboard</a></p>

        <?php if ($showSuccess): ?>
            <div class="alert alert-success">Programación guardada correctamente.</div>
        <?php endif; ?>

        <?php if (!$hasIdPaciente): ?>
            <div class="alert alert-error">Falta actualizar la BD para programar por paciente. Revisa README (migración SQL).</div>
        <?php endif; ?>

        <form action="guardar.php" method="POST" onsubmit="return validarCantidad();">
            <?php if ($hasIdPaciente): ?>
                <label for="id_paciente">Paciente</label>
                <select id="id_paciente" name="id_paciente" required>
                    <option value="">Selecciona un paciente</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= (int) $p['id_usuario'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="id_medicamento">Medicamento</label>
            <select id="id_medicamento" name="id_medicamento" required>
                <option value="">Selecciona uno</option>
                <?php foreach ($medicamentos as $m): ?>
                    <option value="<?= (int)$m['id_medicamento'] ?>">
                        <?= htmlspecialchars($m['nombre'] . ' - ' . ($m['dosis'] ?: 'sin dosis')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="hora_dispenso">Hora de dispenso</label>
            <input id="hora_dispenso" type="time" name="hora_dispenso" required>

            <label for="frecuencia">Frecuencia</label>
            <input id="frecuencia" type="text" name="frecuencia" placeholder="Ejemplo: cada 8 horas" required>

            <label for="cantidad">Cantidad</label>
            <input id="cantidad" type="number" name="cantidad" min="1" required>

            <button type="submit">Programar</button>
        </form>
    </section>
</div>
<script src="../assets/js/validaciones.js"></script>
</body>
</html>
