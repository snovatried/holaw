<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'limpiar_historial') {
    try {
        if ($rol === 'paciente') {
            $sqlDelete = "
                DELETE FROM historial_dispenso h
                USING programacion p
                WHERE h.id_programacion = p.id_programacion
                  AND p.id_usuario = ?
            ";
            $stmtDelete = $pdo->prepare($sqlDelete);
            $stmtDelete->execute([$idUsuario]);
        } else {
            $stmtDelete = $pdo->prepare('DELETE FROM historial_dispenso');
            $stmtDelete->execute();
        }
        $mensaje = 'Historial limpiado correctamente.';
    } catch (Throwable $e) {
        $error = 'No se pudo limpiar el historial. Intenta nuevamente.';
    }
}

$where = '';
$params = [];
if ($rol === 'paciente') {
    $where = 'WHERE p.id_usuario = ?';
    $params[] = $idUsuario;
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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll();

$dashboard = '../dashboard/paciente.php';
if ($rol === 'admin') {
    $dashboard = '../dashboard/admin.php';
} elseif ($rol === 'cuidador') {
    $dashboard = '../dashboard/cuidador.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial</title>
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Historial de dispensos</h1>
        <div style="display:flex; gap:8px;">
            <form method="POST" onsubmit="return confirm('¿Seguro que deseas limpiar el historial visible?');">
                <input type="hidden" name="accion" value="limpiar_historial">
                <button class="btn" type="submit">Limpiar historial</button>
            </form>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($dashboard) ?>">Volver</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
