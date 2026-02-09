<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$sql = 'INSERT INTO medicamentos (nombre, tipo, dosis, cantidad_total, fecha_vencimiento) VALUES (?, ?, ?, ?, ?)';
$stmt = $conexion->prepare($sql);
$stmt->execute([
    $_POST['nombre'],
    $_POST['tipo'],
    $_POST['dosis'],
    $_POST['cantidad_total'],
    $_POST['fecha_vencimiento'],
]);

header('Location: listar.php?ok=1');
exit;
