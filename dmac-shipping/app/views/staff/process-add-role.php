<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('employees_create');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add-role.php');
    exit();
}

$db = (new Database())->getConnection();
$role = trim($_POST['role'] ?? '');

if ($role === '') {
    header('Location: add-role.php?error=missing');
    exit();
}

try {
    $check = $db->prepare("SELECT COUNT(*) FROM roles WHERE LOWER(role) = LOWER(?)");
    $check->execute([$role]);
    if ((int)$check->fetchColumn() > 0) {
        header('Location: add-role.php?error=exists');
        exit();
    }

    $stmt = $db->prepare("INSERT INTO roles (role) VALUES (?)");
    $stmt->execute([$role]);

    logActivity($db, 'employee', $_SESSION['employee_id'] ?? null, 'Added role', 'Added role: ' . $role);

    header('Location: manage-employees.php?role_added=1');
    exit();
} catch (Exception $e) {
    error_log('Add role error: ' . $e->getMessage());
    header('Location: add-role.php?error=server');
    exit();
}
?>
