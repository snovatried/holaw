<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $rol = trim($_POST['rol'] ?? 'paciente');
    $estado = trim($_POST['estado'] ?? 'activo');
    $correo = trim($_POST['correo'] ?? '');

    $rolesValidos = ['admin', 'cuidador', 'paciente'];
    $estadosValidos = ['activo', 'inactivo'];

    if ($nombre === '' || $usuario === '' || $contrasena === '') {
        $error = 'Nombre, usuario y contraseña son obligatorios.';
    } elseif (!in_array($rol, $rolesValidos, true)) {
        $error = 'Rol inválido.';
    } elseif (!in_array($estado, $estadosValidos, true)) {
        $error = 'Estado inválido.';
    } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } else {
        $checkColumn = $pdo->prepare(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'usuarios' AND column_name = 'correo' LIMIT 1"
        );
        $checkColumn->execute();
        $hasCorreo = (bool) $checkColumn->fetchColumn();

        $checkUsuario = $pdo->prepare('SELECT 1 FROM usuarios WHERE usuario = ? LIMIT 1');
        $checkUsuario->execute([$usuario]);

        if ($checkUsuario->fetchColumn()) {
            $error = 'El usuario ya existe, intenta con otro.';
        } else {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);

            if ($hasCorreo) {
                $sql = 'INSERT INTO usuarios (nombre, usuario, contrasena, rol, estado, correo) VALUES (?, ?, ?, ?, ?, ?)';
                $params = [$nombre, $usuario, $hash, $rol, $estado, $correo !== '' ? $correo : null];
            } else {
                $sql = 'INSERT INTO usuarios (nombre, usuario, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?)';
                $params = [$nombre, $usuario, $hash, $rol, $estado];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $mensaje = 'Usuario creado correctamente.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear usuario</title>
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
    <link rel="stylesheet" href="../assets/css/forms.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/forms.css')) ?>">
</head>
<body>
<main class="container" style="max-width: 720px; padding: 24px 16px;">
    <section class="card">
        <h1>Crear nuevo usuario</h1>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="nombre">Nombre completo</label>
            <input id="nombre" type="text" name="nombre" required>

            <label for="usuario">Usuario (login)</label>
            <input id="usuario" type="text" name="usuario" required>

            <label for="correo">Correo (opcional)</label>
            <input id="correo" type="email" name="correo" placeholder="ejemplo@gmail.com">

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" type="password" name="contrasena" required>

            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="paciente">Paciente</option>
                <option value="cuidador">Cuidador</option>
                <option value="admin">Administrador</option>
            </select>

            <label for="estado">Estado</label>
            <select id="estado" name="estado" required>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

            <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;">
                <button type="submit">Guardar usuario</button>
                <a class="btn btn-secondary" href="../dashboard/index.php">Volver al dashboard</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
