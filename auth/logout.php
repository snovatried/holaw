<?php
session_start();
unset($_SESSION['asunto_prueba_correo'], $_SESSION['cuerpo_prueba_correo'], $_SESSION['plantilla_prueba_owner_id']);
// Unset all session variables
$_SESSION = [];
// Destroy the session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Destroy the session
session_destroy();
// Redirect to login
header("Location: ../index.php");
exit;
