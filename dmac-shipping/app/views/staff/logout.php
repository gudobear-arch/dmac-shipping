<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';

try {
    if (isset($_SESSION['employee_id'])) {
        $db = (new Database())->getConnection();
        logActivity($db, 'employee', (int)$_SESSION['employee_id'], 'Logged out', 'Employee/Admin logged out.');
    }
} catch (Exception $e) {
    error_log('Staff logout log error: ' . $e->getMessage());
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: ../auth/login.php');
exit();
?>
