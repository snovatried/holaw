<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$hasIdPaciente = false;
$checkColumn = $pdo->query("SHOW COLUMNS FROM programacion LIKE 'id_paciente'");
if ($checkColumn && $checkColumn->fetch()) {
    $hasIdPaciente = true;
}

if ($hasIdPaciente) {
    $idPaciente = $_POST['id_paciente'] ?? null;
    if (!$idPaciente) {
        header('Location: crear.php');
        exit;
    }

    $sql = 'INSERT INTO programacion (id_usuario, id_paciente, id_medicamento, hora_dispenso, frecuencia, cantidad) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['id_usuario'],
        $idPaciente,
        $_POST['id_medicamento'],
        $_POST['hora_dispenso'],
        $_POST['frecuencia'],
        $_POST['cantidad'],
    ]);
} else {
    $sql = 'INSERT INTO programacion (id_usuario, id_medicamento, hora_dispenso, frecuencia, cantidad) VALUES (?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['id_usuario'],
        $_POST['id_medicamento'],
        $_POST['hora_dispenso'],
        $_POST['frecuencia'],
        $_POST['cantidad'],
    ]);
}

header('Location: crear.php?ok=1');
exit;
