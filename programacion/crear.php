<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$medicamentos = $conexion->query('SELECT id_medicamento, nombre, dosis FROM medicamentos ORDER BY nombre')->fetchAll();
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
        <p><a href="../dashboard/admin.php">← Volver dashboard</a></p>

        <form action="guardar.php" method="POST" onsubmit="return validarCantidad();">
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
