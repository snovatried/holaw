<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/css/general.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <form action="auth/validarlogin.php" method="POST">
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="contra" placeholder="Contrasena" required>
        <button type="submit">Iniciar sesion</button>
    </form>
    <script src="assets/js/validaciones.js"></script>
</body>
</html>
