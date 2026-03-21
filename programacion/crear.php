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
$checkColumn = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1');
$checkColumn->execute(['public', 'programacion', 'id_paciente']);
if ($checkColumn->fetchColumn()) {
    $hasIdPaciente = true;
}

$medicamentos = $pdo->query('SELECT id_medicamento, nombre, dosis FROM medicamentos ORDER BY nombre')->fetchAll();
$pacientes = [];


$hasCompartimentosTable = (bool) $pdo->query("SELECT to_regclass('public.compartimentos') IS NOT NULL")->fetchColumn();
$compartimentos = [];
if ($hasCompartimentosTable) {
    $sqlCompartimentos = "
        SELECT c.id_compartimento, c.id_medicamento, c.cantidad_actual, m.nombre AS medicamento
        FROM compartimentos c
        LEFT JOIN medicamentos m ON m.id_medicamento = c.id_medicamento
        ORDER BY c.id_compartimento
    ";
    $compartimentos = $pdo->query($sqlCompartimentos)->fetchAll();
}

if ($hasIdPaciente) {
    if ($rol === 'admin') {
        $stmtPacientes = $pdo->query("SELECT id_usuario, nombre FROM usuarios WHERE rol = 'paciente' AND estado = 'activo' ORDER BY nombre");
        $pacientes = $stmtPacientes->fetchAll();
    } else {
        $hasRelTable = $pdo->query("SELECT to_regclass('public.cuidadores_pacientes') IS NOT NULL")->fetchColumn();
        if ($hasRelTable) {
            $sqlPacientes = "
                SELECT u.id_usuario, u.nombre
                FROM cuidadores_pacientes cp
                JOIN usuarios u ON u.id_usuario = cp.id_paciente
                WHERE cp.id_cuidador = ? AND u.estado = 'activo'
                ORDER BY u.nombre
            ";
            $stmtPacientes = $pdo->prepare($sqlPacientes);
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
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
    <link rel="stylesheet" href="../assets/css/forms.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/forms.css')) ?>">
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
                    <option value="<?= (int) $m['id_medicamento'] ?>">
                        <?= htmlspecialchars($m['nombre'] . ' - ' . ($m['dosis'] ?: 'sin dosis')) ?>
                    </option>
                <?php endforeach; ?>
            </select>


            <label for="id_compartimento">Compartimento de salida</label>
            <?php if (count($compartimentos) > 0): ?>
                <select id="id_compartimento" name="id_compartimento" required>
                    <option value="">Selecciona un compartimento</option>
                    <?php foreach ($compartimentos as $c): ?>
                        <option value="<?= (int) $c['id_compartimento'] ?>" data-medicamento="<?= (int) ($c['id_medicamento'] ?? 0) ?>">
                            <?= htmlspecialchars('Comp. ' . $c['id_compartimento'] . ' - ' . ($c['medicamento'] ?: 'Sin medicamento') . ' (stock: ' . ($c['cantidad_actual'] ?? 0) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input id="id_compartimento" type="number" name="id_compartimento" min="1" placeholder="Ejemplo: 1" required>
                <small style="color: var(--muted);">No se encontró catálogo de compartimentos. Ingresa el número manualmente.</small>
            <?php endif; ?>

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

<script>
(() => {
    const medicamento = document.getElementById('id_medicamento');
    const compartimento = document.getElementById('id_compartimento');
    if (!medicamento || !compartimento || compartimento.tagName !== 'SELECT') return;

    const toggleCompartimentos = () => {
        const medId = medicamento.value;
        Array.from(compartimento.options).forEach((opt, idx) => {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            const medOpt = opt.getAttribute('data-medicamento');
            opt.hidden = medId !== '' && medOpt !== '0' && medOpt !== medId;
        });
        if (compartimento.selectedOptions[0] && compartimento.selectedOptions[0].hidden) {
            compartimento.value = '';
        }
    };

    medicamento.addEventListener('change', toggleCompartimentos);
    toggleCompartimentos();
})();
</script>

</body>
</html>
