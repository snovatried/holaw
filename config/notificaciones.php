<?php

function obtenerRemitenteNotificacion(PDO $pdo): array
{
    $correo = trim((string) getenv('MAIL_FROM'));
    $nombre = trim((string) getenv('MAIL_FROM_NAME'));

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

        $stmt = $pdo->query("SELECT nombre_remitente, correo_remitente FROM configuracion_correos_salida WHERE activo = TRUE ORDER BY id_config ASC LIMIT 1");
        $cfg = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($cfg && !empty($cfg['correo_remitente']) && filter_var($cfg['correo_remitente'], FILTER_VALIDATE_EMAIL)) {
            $correo = (string) $cfg['correo_remitente'];
            $nombre = trim((string) ($cfg['nombre_remitente'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('No se pudo leer configuracion_correos_salida: ' . $e->getMessage());
    }

    return [
        'correo' => $correo,
        'nombre' => $nombre,
    ];
}

function enviarCorreoDispenso(PDO $pdo, int $idProgramacion, string $resultado, ?string $observaciones): void
{
    $checkPaciente = $pdo->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'programacion' AND column_name = 'id_paciente' LIMIT 1"
    );
    $checkPaciente->execute();
    $hasIdPaciente = (bool) $checkPaciente->fetchColumn();

    $checkCorreo = $pdo->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'usuarios' AND column_name = 'correo' LIMIT 1"
    );
    $checkCorreo->execute();
    $hasCorreo = (bool) $checkCorreo->fetchColumn();

    $correoExpr = $hasCorreo
        ? "COALESCE(NULLIF(TRIM(u.correo), ''), NULLIF(TRIM(u.usuario), ''))"
        : "NULLIF(TRIM(u.usuario), '')";
    $usuarioObjetivoExpr = $hasIdPaciente ? 'COALESCE(p.id_paciente, p.id_usuario)' : 'p.id_usuario';

    $sql = "
        SELECT DISTINCT {$correoExpr} AS correo,
               u.nombre AS destinatario,
               m.nombre AS medicamento,
               p.hora_dispenso,
               p.cantidad
        FROM programacion p
        JOIN usuarios u ON u.id_usuario = {$usuarioObjetivoExpr}
        JOIN medicamentos m ON m.id_medicamento = p.id_medicamento
        WHERE p.id_programacion = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idProgramacion]);
    $programacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$programacion) {
        return;
    }

    $correo = trim((string) ($programacion['correo'] ?? ''));
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $destinatario = (string) ($programacion['destinatario'] ?? 'Usuario');
    $medicamento = (string) ($programacion['medicamento'] ?? 'medicamento');
    $hora = (string) ($programacion['hora_dispenso'] ?? '');
    $cantidad = (string) ($programacion['cantidad'] ?? '');
    $obs = trim((string) ($observaciones ?? ''));
    $obsTexto = $obs !== '' ? $obs : 'Sin observaciones.';

    $asunto = "Notificación de dispenso: {$resultado}";
    $mensaje = "Hola {$destinatario},\n\n" .
        "Se registró un dispenso con resultado '{$resultado}'.\n" .
        "Medicamento: {$medicamento}.\n" .
        "Hora programada: {$hora}.\n" .
        "Cantidad: {$cantidad}.\n" .
        "Observaciones: {$obsTexto}\n\n" .
        "Mensaje automático del sistema Dispensador de Medicina.";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
    ];

    $remitente = obtenerRemitenteNotificacion($pdo);
    $from = trim((string) ($remitente['correo'] ?? ''));
    $fromName = trim((string) ($remitente['nombre'] ?? ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = $fromName !== ''
            ? 'From: ' . str_replace(["\r", "\n"], '', $fromName) . ' <' . $from . '>'
            : 'From: ' . $from;
    }

    $enviado = @mail($correo, $asunto, $mensaje, implode("\r\n", $headers));

    if (!$enviado) {
        error_log("No se pudo enviar correo de dispenso a {$correo}");
    }
}
