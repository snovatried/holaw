<?php
session_start();
require '../config/conexion.php';
require '../config/notificaciones.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$rol = (string) $_SESSION['rol'];
$idActual = (int) ($_SESSION['id_usuario'] ?? 0);
$mensaje = '';
$error = '';
$asuntoPruebaDefault = 'Prueba de notificación - Dispensador de Medicina';
$cuerpoPruebaDefault = "Este es un correo de prueba para validar la configuración de notificaciones.\n\nSi recibiste este mensaje, la configuración de correo está funcionando.";

$ownerPlantilla = (int) ($_SESSION['plantilla_prueba_owner_id'] ?? 0);
if ($ownerPlantilla > 0 && $ownerPlantilla !== $idActual) {
    unset($_SESSION['asunto_prueba_correo'], $_SESSION['cuerpo_prueba_correo'], $_SESSION['plantilla_prueba_owner_id']);
}

$asuntoPruebaSesion = trim((string) ($_SESSION['asunto_prueba_correo'] ?? ''));
$cuerpoPruebaSesion = trim((string) ($_SESSION['cuerpo_prueba_correo'] ?? ''));
$asuntoPrueba = $asuntoPruebaSesion !== '' ? $asuntoPruebaSesion : $asuntoPruebaDefault;
$cuerpoPrueba = $cuerpoPruebaSesion !== '' ? $cuerpoPruebaSesion : $cuerpoPruebaDefault;

function enviarCorreoPrueba(string $destino, string $asunto, string $cuerpo, string $from = ''): bool
{
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
    ];

    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . $from;
    }

    return (bool) @mail($destino, $asunto, $cuerpo, implode("\r\n", $headers));
}

function diagnosticoMail(): array
{
    $smtp = trim((string) ini_get('SMTP'));
    $smtpPort = trim((string) ini_get('smtp_port'));
    $sendmailPath = trim((string) ini_get('sendmail_path'));
    $smtpHostEnv = trim((string) getenv('SMTP_HOST'));
    $smtpPortEnv = trim((string) getenv('SMTP_PORT'));
    $smtpFromEnv = trim((string) (getenv('SMTP_FROM') ?: getenv('MAIL_FROM')));
    $smtpUserEnv = trim((string) getenv('SMTP_USER'));
    $smtpPassEnv = trim((string) getenv('SMTP_PASS'));
    $usaMsmtp = ($sendmailPath !== '' && stripos($sendmailPath, 'msmtp') !== false);

    $resumen = [];
    if ($usaMsmtp) {
        $resumen[] = $sendmailPath !== '' ? "transporte=msmtp ({$sendmailPath})" : 'transporte=msmtp';
        $resumen[] = $smtp !== '' ? "php.ini SMTP(informativo)={$smtp}" : 'php.ini SMTP(informativo)=no configurado';
        $resumen[] = $smtpPort !== '' ? "php.ini smtp_port(informativo)={$smtpPort}" : 'php.ini smtp_port(informativo)=no configurado';
    } else {
        $resumen[] = $smtp !== '' ? "SMTP={$smtp}" : 'SMTP=no configurado';
        $resumen[] = $smtpPort !== '' ? "smtp_port={$smtpPort}" : 'smtp_port=no configurado';
        $resumen[] = $sendmailPath !== '' ? "sendmail_path={$sendmailPath}" : 'sendmail_path=no configurado';
    }
    $resumen[] = $smtpHostEnv !== '' ? "SMTP_HOST={$smtpHostEnv}" : 'SMTP_HOST=no configurado';
    $resumen[] = $smtpPortEnv !== '' ? "SMTP_PORT={$smtpPortEnv}" : 'SMTP_PORT=no configurado';
    $resumen[] = $smtpFromEnv !== '' ? "SMTP_FROM/MAIL_FROM={$smtpFromEnv}" : 'SMTP_FROM/MAIL_FROM=no configurado';
    $resumen[] = $smtpUserEnv !== '' ? 'SMTP_USER=configurado' : 'SMTP_USER=no configurado';
    $resumen[] = $smtpPassEnv !== '' ? 'SMTP_PASS=configurado' : 'SMTP_PASS=no configurado';

    $alertas = [];
    if ($usaMsmtp) {
        $alertas[] = 'En Linux/Render con sendmail_path=msmtp, PHP usa msmtp y no el SMTP/smtp_port de php.ini.';
    }
    if ($usaMsmtp && $smtpPort !== '' && $smtpPortEnv !== '' && $smtpPort !== $smtpPortEnv) {
        $alertas[] = 'Hay dos puertos visibles: php.ini smtp_port es solo informativo; el puerto real para envío con msmtp es SMTP_PORT.';
    }
    if ($smtpHostEnv === '') {
        $alertas[] = 'Falta SMTP_HOST en variables de entorno.';
    }
    if ($smtpFromEnv === '') {
        $alertas[] = 'Falta SMTP_FROM o MAIL_FROM en variables de entorno.';
    }
    if (($smtpHostEnv === 'localhost' || $smtpHostEnv === '127.0.0.1') && $smtpPortEnv === '25') {
        $alertas[] = 'Si usas localhost:25, verifica que exista un servidor SMTP escuchando dentro del contenedor.';
    }
    if ($smtpHostEnv !== '' && $smtpHostEnv !== 'localhost' && $smtpHostEnv !== '127.0.0.1' && $smtpUserEnv === '') {
        $alertas[] = 'SMTP_USER no está configurado. En Render/proveedores externos normalmente se requiere autenticación.';
    }
    if ($smtpHostEnv === 'smtp.gmail.com' && $smtpUserEnv !== '' && $smtpPassEnv === '') {
        $alertas[] = 'Gmail requiere SMTP_PASS (normalmente App Password de 16 caracteres, no la contraseña normal).';
    }

    $faltaTransporte = ($smtp === '' && $sendmailPath === '');
    $msmtpConfigOk = is_readable('/etc/msmtprc');
    if (!$msmtpConfigOk) {
        $alertas[] = 'No existe /etc/msmtprc (msmtp no tiene cuenta SMTP cargada).';
    }

    return [
        'falta_transporte' => $faltaTransporte,
        'msmtp_config_ok' => $msmtpConfigOk,
        'alertas' => $alertas,
        'resumen' => implode(' | ', $resumen),
    ];
}

function obtenerDetalleMsmtp(): string
{
    $rutaLog = '/tmp/msmtp.log';
    if (!is_readable($rutaLog)) {
        return '';
    }

    $maxBytes = 65536; // lectura acotada (~64KB) para evitar costo variable con logs grandes
    $size = @filesize($rutaLog);
    if ($size === false || $size <= 0) {
        return '';
    }

    $fh = @fopen($rutaLog, 'rb');
    if ($fh === false) {
        return '';
    }

    $offset = max(0, $size - $maxBytes);
    if (@fseek($fh, $offset, SEEK_SET) !== 0) {
        fclose($fh);
        return '';
    }

    $contenido = @stream_get_contents($fh);
    fclose($fh);

    if ($contenido === false || trim($contenido) === '') {
        return '';
    }

    if ($offset > 0) {
        $posSalto = strpos($contenido, "\n");
        if ($posSalto !== false) {
            $contenido = substr($contenido, $posSalto + 1);
        }
    }

    $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
    if (!is_array($lineas) || empty($lineas)) {
        return '';
    }

    $ultimas = array_slice($lineas, -8);
    $texto = trim(implode(' | ', array_filter(array_map('trim', $ultimas), static fn ($v) => $v !== '')));

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, 700);
    }

    return substr($texto, 0, 700);
}

function asegurarTablaRemitentes(PDO $pdo): bool
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS configuracion_correos_salida (
                id_config SERIAL PRIMARY KEY,
                nombre_remitente VARCHAR(120) NULL,
                correo_remitente VARCHAR(190) NOT NULL UNIQUE,
                activo BOOLEAN NOT NULL DEFAULT FALSE,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        return true;
    } catch (Throwable $e) {
        error_log('No se pudo crear tabla configuracion_correos_salida: ' . $e->getMessage());
        return false;
    }
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
$hasTablaRemitentes = asegurarTablaRemitentes($pdo);
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

    if ($accion === 'guardar_plantilla_prueba') {
        $nuevoAsunto = trim((string) ($_POST['asunto_prueba'] ?? ''));
        $nuevoCuerpo = trim((string) ($_POST['cuerpo_prueba'] ?? ''));

        if ($nuevoAsunto === '' || $nuevoCuerpo === '') {
            $error = 'El asunto y el mensaje de la plantilla de prueba no pueden estar vacíos.';
        } else {
            $_SESSION['asunto_prueba_correo'] = $nuevoAsunto;
            $_SESSION['cuerpo_prueba_correo'] = $nuevoCuerpo;
            $_SESSION['plantilla_prueba_owner_id'] = $idActual;
            $asuntoPrueba = $nuevoAsunto;
            $cuerpoPrueba = $nuevoCuerpo;
            $mensaje = 'Plantilla de prueba guardada para esta sesión.';
        }
    }

    if ($accion === 'reset_plantilla_prueba') {
        unset($_SESSION['asunto_prueba_correo'], $_SESSION['cuerpo_prueba_correo'], $_SESSION['plantilla_prueba_owner_id']);
        $asuntoPrueba = $asuntoPruebaDefault;
        $cuerpoPrueba = $cuerpoPruebaDefault;
        $mensaje = 'Plantilla de prueba restablecida al valor inicial.';
    }

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
            $fromCfg = obtenerRemitenteNotificacion($pdo);
            $from = trim((string) ($fromCfg['correo'] ?? ''));
            $ok = enviarCorreoPrueba($correo, $asuntoPrueba, $cuerpoPrueba, $from);
            if ($ok) {
                $mensaje = "Correo de prueba enviado a {$correo}.";
            } else {
                $diag = diagnosticoMail();
                if ($diag['falta_transporte']) {
                    $error = "No se pudo enviar el correo de prueba a {$correo}. No hay transporte de correo configurado en PHP ({$diag['resumen']}).";
                } else {
                    $detalle = '';
                    if (!empty($diag['alertas'])) {
                        $detalle = ' Alertas: ' . implode(' ', $diag['alertas']);
                    }
                    if ($rol === 'admin') {
                        $detalleMsmtp = obtenerDetalleMsmtp();
                        if ($detalleMsmtp !== '') {
                            $detalle .= " Último log msmtp: {$detalleMsmtp}";
                        }
                    }
                    $error = "No se pudo enviar el correo de prueba a {$correo}. Revisa credenciales/servidor SMTP ({$diag['resumen']}).{$detalle}";
                }
            }
        }
    }

    if ($accion === 'test_global' && $rol === 'admin') {
        $correoTest = 'aaronmachuca19@gmail.com';
        $stmt = $pdo->prepare('UPDATE usuarios SET correo = ?');
        $stmt->execute([$correoTest]);
        $mensaje = 'Se actualizó el correo de todos los usuarios con el valor de prueba.';
    }

    if ($accion === 'agregar_remitente' && $rol === 'admin' && $hasTablaRemitentes) {
        $nombreRem = trim((string) ($_POST['nombre_remitente'] ?? ''));
        $correoRem = trim((string) ($_POST['correo_remitente'] ?? ''));
        if ($correoRem === '' || !filter_var($correoRem, FILTER_VALIDATE_EMAIL)) {
            $error = 'Debes ingresar un correo remitente válido.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO configuracion_correos_salida (nombre_remitente, correo_remitente, activo) VALUES (?, ?, FALSE) ON CONFLICT (correo_remitente) DO NOTHING');
            $stmt->execute([$nombreRem !== '' ? $nombreRem : null, $correoRem]);
            $mensaje = 'Remitente guardado. Ahora puedes activarlo.';
        }
    }

    if ($accion === 'activar_remitente' && $rol === 'admin' && $hasTablaRemitentes) {
        $idConfig = (int) ($_POST['id_config'] ?? 0);
        if ($idConfig > 0) {
            $pdo->beginTransaction();
            $pdo->exec('UPDATE configuracion_correos_salida SET activo = FALSE');
            $stmt = $pdo->prepare('UPDATE configuracion_correos_salida SET activo = TRUE WHERE id_config = ?');
            $stmt->execute([$idConfig]);
            $pdo->commit();
            $mensaje = 'Remitente activo actualizado correctamente.';
        }
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

$remitentes = [];
if ($hasTablaRemitentes && $rol === 'admin') {
    $stmt = $pdo->query('SELECT id_config, nombre_remitente, correo_remitente, activo FROM configuracion_correos_salida ORDER BY id_config ASC');
    $remitentes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$remitenteActual = obtenerRemitenteNotificacion($pdo);

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
        <p style="margin-top:8px;font-size:0.9rem;opacity:.85;">
            Diagnóstico actual de PHP mail: <code><?= htmlspecialchars(diagnosticoMail()['resumen']) ?></code>
        </p>
        <p style="margin-top:8px;font-size:0.9rem;opacity:.85;">
            Remitente activo: <code><?= htmlspecialchars((string) ($remitenteActual['correo'] ?: 'sin definir')) ?></code>
        </p>

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

        <section class="card" style="margin-top:16px;">
            <h2>Plantilla del correo de prueba (sin BD)</h2>
            <p>Personaliza asunto y mensaje desde esta página. Se guarda en tu sesión actual.</p>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_plantilla_prueba">
                <label for="asunto_prueba">Asunto de prueba</label>
                <input id="asunto_prueba" type="text" name="asunto_prueba" value="<?= htmlspecialchars($asuntoPrueba) ?>" required>

                <label for="cuerpo_prueba">Mensaje de prueba</label>
                <textarea id="cuerpo_prueba" name="cuerpo_prueba" rows="5" required><?= htmlspecialchars($cuerpoPrueba) ?></textarea>
                <button type="submit">Guardar plantilla temporal</button>
            </form>
            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="accion" value="reset_plantilla_prueba">
                <button type="submit" class="btn btn-secondary">Restablecer a valores iniciales</button>
            </form>
        </section>

        <p style="margin-top: 8px;"><strong>Sentencia SQL para test:</strong><br><code>UPDATE usuarios SET correo = 'aaronmachuca19@gmail.com';</code></p>

        <?php if ($rol === 'admin' && $hasTablaRemitentes): ?>
            <section class="card" style="margin-top:16px;">
                <h2>Remitentes de salida (SMTP/mail)</h2>
                <p>Configura varios correos remitentes y activa uno para los envíos.</p>
                <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                    <input type="hidden" name="accion" value="agregar_remitente">
                    <div>
                        <label for="nombre_remitente">Nombre remitente</label>
                        <input id="nombre_remitente" type="text" name="nombre_remitente" placeholder="Dispensador">
                    </div>
                    <div>
                        <label for="correo_remitente">Correo remitente</label>
                        <input id="correo_remitente" type="email" name="correo_remitente" placeholder="notificaciones@gmail.com" required>
                    </div>
                    <button type="submit">Guardar remitente</button>
                </form>

                <?php if (!empty($remitentes)): ?>
                    <table style="margin-top:12px;">
                        <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($remitentes as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['nombre_remitente'] ?: 'Sin nombre')) ?></td>
                                <td><?= htmlspecialchars((string) $r['correo_remitente']) ?></td>
                                <td><?= !empty($r['activo']) ? 'Activo' : 'Inactivo' ?></td>
                                <td>
                                    <?php if (empty($r['activo'])): ?>
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="activar_remitente">
                                            <input type="hidden" name="id_config" value="<?= (int) $r['id_config'] ?>">
                                            <button type="submit">Activar</button>
                                        </form>
                                    <?php else: ?>
                                        <span>En uso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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
