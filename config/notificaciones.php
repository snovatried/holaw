<?php

function enviarCorreoDispenso(PDO $pdo, int $idProgramacion, string $resultado, ?string $observaciones): void
{
    $checkCorreo = $pdo->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'usuarios' AND column_name = 'correo' LIMIT 1"
    );
    $checkCorreo->execute();
    $hasCorreo = (bool) $checkCorreo->fetchColumn();

    $correoExpr = $hasCorreo
        ? "COALESCE(NULLIF(TRIM(u.correo), ''), NULLIF(TRIM(u.usuario), ''))"
        : "NULLIF(TRIM(u.usuario), '')";

    $sql = "
        SELECT DISTINCT {$correoExpr} AS correo,
               u.nombre AS destinatario,
               m.nombre AS medicamento,
               p.hora_dispenso,
               p.cantidad
        FROM programacion p
        JOIN usuarios u ON u.id_usuario = p.id_usuario
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

    $from = trim((string) getenv('MAIL_FROM'));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . $from;
    }

    $enviado = @mail($correo, $asunto, $mensaje, implode("\r\n", $headers));

    if (!$enviado) {
        error_log("No se pudo enviar correo de dispenso a {$correo}");
    }
}
