<?php
require '../config/conexion.php';

$sql = 'INSERT INTO medicamentos (nombre, tipo, dosis, cantidad_total, fecha_vencimiento) VALUES (?, ?, ?, ?, ?)';
$stmt = $conexion->prepare($sql);
$stmt->execute([
    $_POST['nombre'],
    $_POST['tipo'],
    $_POST['dosis'],
    $_POST['cantidad_total'],
    $_POST['fecha_vencimiento'],
]);

header('Location: listar.php');
exit;
