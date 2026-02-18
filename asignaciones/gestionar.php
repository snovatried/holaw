<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$mensaje = '';
$error = '';

function columnaExiste(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tabla} LIKE ?");
    $stmt->execute([$columna]);
    return (bool) $stmt->fetch();
}

function tablaExiste(PDO $pdo, string $tabla): bool {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$tabla]);
    return (bool) $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'migrar') {
        try {
            if (!columnaExiste($pdo, 'programacion', 'id_paciente')) {
                $pdo->exec('ALTER TABLE programacion ADD COLUMN id_paciente INT NULL AFTER id_usuario');
                $pdo->exec('ALTER TABLE programacion ADD INDEX idx_programacion_id_paciente (id_paciente)');
                $pdo->exec('ALTER TABLE programacion ADD CONSTRAINT fk_programacion_paciente FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario)');
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS cuidadores_pacientes (
                id_relacion INT AUTO_INCREMENT PRIMARY KEY,
                id_cuidador INT NOT NULL,
                id_paciente INT NOT NULL,
                fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cuidador_paciente (id_cuidador, id_paciente),
                CONSTRAINT fk_cp_cuidador FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario),
                CONSTRAINT fk_cp_paciente FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario)
            )");

            $mensaje = 'Migración aplicada correctamente.';
        } catch (Throwable $e) {
            $error = 'No se pudo aplicar la migración automáticamente. Ejecuta la SQL manual del README.';
        }
    }

    if ($accion === 'asignar') {
        $idCuidador = (int) ($_POST['id_cuidador'] ?? 0);
        $idPaciente = (int) ($_POST['id_paciente'] ?? 0);

        if ($idCuidador <= 0 || $idPaciente <= 0) {
            $error = 'Selecciona cuidador y paciente.';
        } elseif (!tablaExiste($pdo, 'cuidadores_pacientes')) {
            $error = 'Primero debes aplicar la migración.';
        } else {
            $stmt = $pdo->prepare('INSERT IGNORE INTO cuidadores_pacientes (id_cuidador, id_paciente) VALUES (?, ?)');
            $stmt->execute([$idCuidador, $idPaciente]);
            $mensaje = 'Asignación guardada.';
        }
    }
}

$tablaRelExiste = tablaExiste($pdo, 'cuidadores_pacientes');
$colPacienteExiste = columnaExiste($pdo, 'programacion', 'id_paciente');
$migracionLista = $tablaRelExiste && $colPacienteExiste;

$cuidadorStmt = $pdo->query("SELECT id_usuario, nombre FROM usuarios WHERE rol = 'cuidador' AND estado = 'activo' ORDER BY nombre");
$pacienteStmt = $pdo->query("SELECT id_usuario, nombre FROM usuarios WHERE rol = 'paciente' AND estado = 'activo' ORDER BY nombre");
$cuidadorList = $cuidadorStmt->fetchAll();
$pacienteList = $pacienteStmt->fetchAll();

$asignaciones = [];
if ($tablaRelExiste) {
    $asigSql = "
        SELECT cp.id_relacion, c.nombre AS cuidador, p.nombre AS paciente
        FROM cuidadores_pacientes cp
        JOIN usuarios c ON c.id_usuario = cp.id_cuidador
        JOIN usuarios p ON p.id_usuario = cp.id_paciente
        ORDER BY c.nombre, p.nombre
    ";
    $asignaciones = $pdo->query($asigSql)->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Pacientes</title>
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
<div class="container">
    <section class="card">
        <h1>Asignar paciente a cuidador</h1>
        <p><a href="../dashboard/admin.php">← Volver dashboard</a></p>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$migracionLista): ?>
            <div class="alert alert-error">La base de datos no tiene la migración para asignaciones.</div>
            <form method="POST">
                <input type="hidden" name="accion" value="migrar">
                <button type="submit">Aplicar migración automáticamente</button>
            </form>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="accion" value="asignar">

            <label for="id_cuidador">Cuidador</label>
            <select name="id_cuidador" id="id_cuidador" required>
                <option value="">Selecciona cuidador</option>
                <?php foreach ($cuidadorList as $c): ?>
                    <option value="<?= (int) $c['id_usuario'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="id_paciente">Paciente</label>
            <select name="id_paciente" id="id_paciente" required>
                <option value="">Selecciona paciente</option>
                <?php foreach ($pacienteList as $p): ?>
                    <option value="<?= (int) $p['id_usuario'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Guardar asignación</button>
        </form>
    </section>

    <section class="card" style="margin-top:16px;">
        <h2>Asignaciones actuales</h2>
        <table>
            <thead>
                <tr>
                    <th>Cuidador</th>
                    <th>Paciente</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($asignaciones) === 0): ?>
                <tr><td colspan="2">Sin asignaciones aún.</td></tr>
            <?php else: ?>
                <?php foreach ($asignaciones as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['cuidador']) ?></td>
                    <td><?= htmlspecialchars($a['paciente']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
