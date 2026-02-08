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
    <main class="login-wrap">
        <section class="card login-card">
            <h1 class="login-title">Dispensador de Medicina</h1>

            <?php if ($showError): ?>
                <div class="alert alert-error">Usuario o contraseña incorrectos.</div>
            <?php endif; ?>

            <form action="auth/validarlogin.php" method="POST">
                <label for="usuario">Usuario</label>
                <input id="usuario" type="text" name="usuario" placeholder="Usuario" required>

                <label for="contra">Contraseña</label>
                <input id="contra" type="password" name="contra" placeholder="Contraseña" required>

                <button type="submit">Iniciar sesión</button>
            </form>

            <div class="oauth-divider">o inicia con Google</div>

            <div
                id="g_id_onload"
                data-client_id="<?= htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8') ?>"
                data-callback="handleGoogleCredential"
                data-auto_prompt="false"
            ></div>
            <div class="g_id_signin" data-type="standard" data-width="360"></div>
        </section>
    </main>

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
