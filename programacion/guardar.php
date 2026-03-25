<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'cuidador'], true)) {
    header('Location: ../index.php');
    exit;
}

$checkColumn = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1');
$checkColumn->execute(['public', 'programacion', 'id_paciente']);
$hasIdPaciente = (bool) $checkColumn->fetchColumn();

$checkColumn->execute(['public', 'programacion', 'id_compartimento']);
$hasIdCompartimento = (bool) $checkColumn->fetchColumn();
$checkColumn->execute(['public', 'programacion', 'duracion_dias']);
$hasDuracionDias = (bool) $checkColumn->fetchColumn();

if (!$hasIdCompartimento) {
    $pdo->exec('ALTER TABLE programacion ADD COLUMN id_compartimento INTEGER NULL');
    $hasIdCompartimento = true;
}
if (!$hasDuracionDias) {
    $pdo->exec('ALTER TABLE programacion ADD COLUMN duracion_dias INTEGER NULL');
    $hasDuracionDias = true;
}

$idCompartimento = $_POST['id_compartimento'] ?? null;
if ($idCompartimento === '' || $idCompartimento === null) {
    $idCompartimento = null;
}

if ($hasIdPaciente) {
    $idPaciente = $_POST['id_paciente'] ?? null;
    if (!$idPaciente) {
        header('Location: crear.php');
        exit;
    }

    $sql = 'INSERT INTO programacion (id_usuario, id_paciente, id_medicamento, id_compartimento, hora_dispenso, duracion_dias, cantidad) VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['id_usuario'],
        $idPaciente,
        $_POST['id_medicamento'],
        $idCompartimento,
        $_POST['hora_dispenso'],
        $_POST['duracion_dias'],
        $_POST['cantidad'],
    ]);
} else {
    $sql = 'INSERT INTO programacion (id_usuario, id_medicamento, id_compartimento, hora_dispenso, duracion_dias, cantidad) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['id_usuario'],
        $_POST['id_medicamento'],
        $idCompartimento,
        $_POST['hora_dispenso'],
        $_POST['duracion_dias'],
        $_POST['cantidad'],
    ]);
}

header('Location: crear.php?ok=1');
exit;
