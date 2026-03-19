<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {
    header('Location: ../index.php');
    exit;
}
header('Location: ./index.php');
exit;
