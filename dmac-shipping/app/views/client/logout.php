<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';

try {
    if (isset($_SESSION['client_id'])) {
        $db = (new Database())->getConnection();
        logActivity($db, 'client', (int)$_SESSION['client_id'], 'Logged out', 'Client logged out.');
    }
} catch (Exception $e) {
    error_log('Client logout log error: ' . $e->getMessage());
}

$_SESSION = [];

if (ini_get("session_use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: ../auth/login.php");
exit();
?>
