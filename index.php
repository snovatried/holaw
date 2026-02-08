<?php
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
$showError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="assets/css/general.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <form action="auth/validarlogin.php" method="POST">
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="contra" placeholder="Contrasena" required>
        <button type="submit">Iniciar sesion</button>
    </form>

    <div style="margin-top: 1rem; text-align: center;">
        <p>o inicia con Google</p>
        <div id="g_id_onload"
             data-client_id="<?= htmlspecialchars(getenv('GOOGLE_CLIENT_ID') ?: '', ENT_QUOTES, 'UTF-8') ?>"
             data-callback="handleGoogleCredential"
             data-auto_prompt="false">
        </div>
        <div class="g_id_signin" data-type="standard"></div>
    </div>

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        async function handleGoogleCredential(response) {
            const payload = new URLSearchParams();
            payload.append('credential', response.credential);

            const result = await fetch('auth/google_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });

            if (!result.ok) {
                alert('No se pudo iniciar sesión con Google.');
                return;
            }

            const data = await result.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        }
    </script>
    <script src="assets/js/validaciones.js"></script>
</body>
</html>
