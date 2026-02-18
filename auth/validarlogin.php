<?php
session_start();
require '../config/conexion.php';

$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contra'] ?? '';

$sql = "SELECT * FROM usuarios WHERE usuario = ? AND estado = 'activo' LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario]);
$usuarioDB = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuarioDB && password_verify($contrasena, $usuarioDB['contrasena'])) {
    $_SESSION['id_usuario'] = $usuarioDB['id_usuario'];
    $_SESSION['rol'] = $usuarioDB['rol'];

    if ($usuarioDB['rol'] === 'admin') {
        header('Location: ../dashboard/admin.php');
    } elseif ($usuarioDB['rol'] === 'cuidador') {
        header('Location: ../dashboard/cuidador.php');
    } else {
        header('Location: ../dashboard/paciente.php');
    }
    exit;
}

header('Location: ../index.php?error=1');
exit;
