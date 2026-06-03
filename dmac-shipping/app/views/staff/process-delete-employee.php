<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireEmployeeLogin();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../controllers/EmployeeController.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);
$controller = new EmployeeController($employeeModel, $db);

function currentUserIsAdmin($db) {
    if (isSuperAdmin()) {
        return true;
    }

    $empId = (int)($_SESSION['employee_id'] ?? 0);
    if ($empId <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT COUNT(*)
                          FROM emprole er
                          JOIN roles r ON er.role_ID = r.role_ID
                          WHERE er.emp_ID = ?
                            AND LOWER(r.role) = 'admin'");
    $stmt->execute([$empId]);
    return (int)$stmt->fetchColumn() > 0;
}

if (!currentUserIsAdmin($db)) {
    header('Location: manage-employees.php?error=unauthorized');
    exit();
}

$empId = (int)($_GET['emp_ID'] ?? 0);
if ($empId <= 0) {
    header('Location: manage-employees.php?error=invalid');
    exit();
}

if ($empId === (int)($_SESSION['employee_id'] ?? 0)) {
    header('Location: manage-employees.php?error=self_delete');
    exit();
}

try {
    $result = $controller->deactivateEmployee($empId, (int)($_SESSION['employee_id'] ?? 0));
    if (!$result['success']) {
        header('Location: manage-employees.php?error=' . urlencode($result['error'] ?? 'server'));
        exit();
    }

    logActivity($db, 'employee', $_SESSION['employee_id'] ?? null, 'Disabled employee account', 'Disabled employee ID #' . $empId);

    header('Location: manage-employees.php?deleted=1');
    exit();
} catch (Exception $e) {
    error_log('Delete employee error: ' . $e->getMessage());
    header('Location: manage-employees.php?error=server');
    exit();
}
?>
