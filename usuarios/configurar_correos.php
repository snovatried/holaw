<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$rol = (string) $_SESSION['rol'];
$idActual = (int) ($_SESSION['id_usuario'] ?? 0);
$mensaje = '';
$error = '';

function enviarCorreoPrueba(string $destino, string $from = ''): bool
{
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $asunto = 'Prueba de notificación - Dispensador de Medicina';
    $cuerpo = "Este es un correo de prueba para validar la configuración de notificaciones.\n\nSi recibiste este mensaje, la configuración de correo está funcionando.";
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
    ];

    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . $from;
    }

    return (bool) @mail($destino, $asunto, $cuerpo, implode("\r\n", $headers));
}

function asegurarColumnaCorreo(PDO $pdo): bool
{
    $check = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'usuarios' AND column_name = 'correo' LIMIT 1");
    $check->execute();
    if ($check->fetchColumn()) {
        return true;
    }

    try {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN correo VARCHAR(150) NULL');
        return true;
    } catch (Throwable $e) {
        error_log('No se pudo crear columna correo: ' . $e->getMessage());
        return false;
    }
}

$hasCorreo = asegurarColumnaCorreo($pdo);
if (!$hasCorreo) {
    $error = 'No se pudo habilitar la columna correo en usuarios. Contacta al administrador técnico.';
}

$idsPermitidos = [];

if ($rol === 'admin') {
    $idsPermitidos = array_map(
        'intval',
        $pdo->query('SELECT id_usuario FROM usuarios ORDER BY id_usuario')->fetchAll(PDO::FETCH_COLUMN)
    );
} else {
    $idsPermitidos[] = $idActual;
    $hasRelTable = (bool) $pdo->query("SELECT to_regclass('public.cuidadores_pacientes') IS NOT NULL")->fetchColumn();

    if ($hasRelTable) {
        $stmtRel = $pdo->prepare('SELECT id_paciente FROM cuidadores_pacientes WHERE id_cuidador = ?');
        $stmtRel->execute([$idActual]);
        $idsPacientes = $stmtRel->fetchAll(PDO::FETCH_COLUMN);
        foreach ($idsPacientes as $idPaciente) {
            $idsPermitidos[] = (int) $idPaciente;
        }
    }

    $idsPermitidos = array_values(array_unique($idsPermitidos));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasCorreo) {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'guardar_correo') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if (!in_array($idUsuario, $idsPermitidos, true)) {
            $error = 'No tienes permisos para editar ese usuario.';
        } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Formato de correo inválido.';
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET correo = ? WHERE id_usuario = ?');
            $stmt->execute([$correo !== '' ? $correo : null, $idUsuario]);
            $mensaje = 'Correo actualizado correctamente.';
        }
    }

    if ($accion === 'enviar_prueba') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if (!in_array($idUsuario, $idsPermitidos, true)) {
            $error = 'No tienes permisos para enviar pruebas a ese usuario.';
        } elseif ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Debes ingresar un correo válido antes de enviar la prueba.';
        } else {
            $from = trim((string) getenv('MAIL_FROM'));
            $ok = enviarCorreoPrueba($correo, $from);
            if ($ok) {
                $mensaje = "Correo de prueba enviado a {$correo}.";
            } else {
                $error = "No se pudo enviar el correo de prueba a {$correo}. Revisa la configuración SMTP de PHP.";
            }
        }
    }

    if ($accion === 'test_global' && $rol === 'admin') {
        $correoTest = 'aaronmachuca19@gmail.com';
        $stmt = $pdo->prepare('UPDATE usuarios SET correo = ?');
        $stmt->execute([$correoTest]);
        $mensaje = 'Se actualizó el correo de todos los usuarios con el valor de prueba.';
    }
}

$usuarios = [];
if (!empty($idsPermitidos) && $hasCorreo) {
    $placeholders = implode(',', array_fill(0, count($idsPermitidos), '?'));
    $sql = "SELECT id_usuario, nombre, usuario, rol, estado, correo FROM usuarios WHERE id_usuario IN ({$placeholders}) ORDER BY nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($idsPermitidos);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar correos</title>
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
    <link rel="stylesheet" href="../assets/css/forms.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/forms.css')) ?>">
</head>
<body>
<main class="container" style="max-width: 900px; padding: 24px 16px;">
    <section class="card">
        <h1>Configurar correos de notificación</h1>
        <p>Puedes guardar aquí los correos que recibirán avisos de dispenso.</p>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($rol === 'admin' && $hasCorreo): ?>
            <form method="POST" style="margin: 12px 0 20px;">
                <input type="hidden" name="accion" value="test_global">
                <button type="submit">Aplicar correo de prueba a todos</button>
                <p style="margin:8px 0 0;font-size:0.9rem;opacity:.9;">Valor aplicado: <code>aaronmachuca19@gmail.com</code>.</p>
            </form>
        <?php endif; ?>

        <p style="margin-top: 8px;"><strong>Sentencia SQL para test:</strong><br><code>UPDATE usuarios SET correo = 'aaronmachuca19@gmail.com';</code></p>

        <?php if ($hasCorreo && !empty($usuarios)): ?>
            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Correo notificación</th>
                    <th>Acción</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $u['nombre']) ?></td>
                        <td><?= htmlspecialchars((string) $u['usuario']) ?></td>
                        <td><?= htmlspecialchars((string) $u['rol']) ?></td>
                        <td><?= htmlspecialchars((string) $u['estado']) ?></td>
                        <td>
                            <form method="POST" style="display:flex;gap:8px;align-items:center;min-width:260px;">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                <input type="email" name="correo" value="<?= htmlspecialchars((string) ($u['correo'] ?? '')) ?>" placeholder="correo@gmail.com" style="margin:0;">
                        </td>
                        <td>
                                <button type="submit" name="accion" value="guardar_correo">Guardar</button>
                                <button type="submit" name="accion" value="enviar_prueba">Enviar prueba</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top:16px;">
            <a class="btn btn-secondary" href="../dashboard/index.php">Volver al dashboard</a>
        </div>
    </section>
</main>
</body>
</html>
