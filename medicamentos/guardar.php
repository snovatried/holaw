<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$tipo = trim((string) ($_POST['tipo'] ?? ''));
$tipoLower = mb_strtolower($tipo, 'UTF-8');
$permitidos = ['tablet', 'capsule', 'caplet', 'pill', 'comprimido', 'cápsula', 'capsula', 'gragea', 'pastilla', 'chewable', 'lozenge', 'troche', 'oral'];
$esComestible = false;
foreach ($permitidos as $forma) {
    if (str_contains($tipoLower, $forma)) {
        $esComestible = true;
        break;
    }
}

if (!$esComestible) {
    header('Location: agregar.php?error=tipo_no_comestible');
    exit;
}

$sql = 'INSERT INTO medicamentos (nombre, tipo, dosis, cantidad_total, fecha_vencimiento) VALUES (?, ?, ?, ?, ?)';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $_POST['nombre'],
    $tipo,
    $_POST['dosis'],
    $_POST['cantidad_total'],
    $_POST['fecha_vencimiento'],
]);

header('Location: listar.php?ok=1');
exit;
