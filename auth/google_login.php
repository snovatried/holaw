<?php
session_start();
require '../config/conexion.php';

header('Content-Type: application/json');

$credential = $_POST['credential'] ?? '';
if (!$credential) {
    http_response_code(400);
    echo json_encode(['error' => 'Credencial invalida']);
    exit;
}

$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$tokenInfoJson = @file_get_contents($tokenInfoUrl);
if ($tokenInfoJson === false) {
    http_response_code(401);
    echo json_encode(['error' => 'No se pudo validar el token']);
    exit;
}

$tokenInfo = json_decode($tokenInfoJson, true);
if (!is_array($tokenInfo) || empty($tokenInfo['email']) || empty($tokenInfo['sub'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Token invalido']);
    exit;
}

$clientId = getenv('GOOGLE_CLIENT_ID') ?: '';
if ($clientId && (($tokenInfo['aud'] ?? '') !== $clientId)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token no corresponde a este cliente']);
    exit;
}

$email = $tokenInfo['email'];
$nombre = $tokenInfo['name'] ?? $email;

$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado = 'activo' LIMIT 1");
$stmt->execute([$email]);
$usuarioDB = $stmt->fetch();

if (!$usuarioDB) {
    $passwordRandom = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insert = $conexion->prepare('INSERT INTO usuarios (nombre, usuario, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$nombre, $email, $passwordRandom, 'paciente', 'activo']);

    $idUsuario = $conexion->lastInsertId();
    $usuarioDB = [
        'id_usuario' => $idUsuario,
        'rol' => 'paciente',
    ];
}

$_SESSION['id_usuario'] = $usuarioDB['id_usuario'];
$_SESSION['rol'] = $usuarioDB['rol'];

$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
if ($basePath === '') {
    $basePath = '';
}

$redirect = $basePath . '/dashboard/paciente.php';
if ($usuarioDB['rol'] === 'admin') {
    $redirect = $basePath . '/dashboard/admin.php';
} elseif ($usuarioDB['rol'] === 'cuidador') {
    $redirect = $basePath . '/dashboard/cuidador.php';
}

echo json_encode(['redirect' => $redirect]);
