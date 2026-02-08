<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$sql = 'INSERT INTO programacion (id_usuario, id_medicamento, hora_dispenso, frecuencia, cantidad) VALUES (?, ?, ?, ?, ?)';
$stmt = $conexion->prepare($sql);
$stmt->execute([
    $_SESSION['id_usuario'],
    $_POST['id_medicamento'],
    $_POST['hora_dispenso'],
    $_POST['frecuencia'],
    $_POST['cantidad'],
]);

header('Location: crear.php');
exit;
