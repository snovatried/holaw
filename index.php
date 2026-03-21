<?php
$showError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/css/general.css')) ?>">
    <link rel="stylesheet" href="assets/css/forms.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/css/forms.css')) ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/css/login.css')) ?>">
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

        </section>
    </main>
    <script src="assets/js/validaciones.js"></script>
</body>
</html>
