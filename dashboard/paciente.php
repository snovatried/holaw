<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Paciente</title>
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Dashboard Paciente</h1>
        <a class="btn btn-secondary" href="../auth/logout.php">Cerrar sesión</a>
    </div>

    <ul class="nav-links">
        <li><a href="../historial/ver.php">Ver mis dispensos</a></li>
    </ul>
</div>
</body>
</html>
