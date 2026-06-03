<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['employees_edit','employees_permissions']);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assign-position.php');
    exit();
}

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

$empId = (int)($_POST['emp_ID'] ?? 0);
$roles = $_POST['roles'] ?? [];
if (!is_array($roles)) {
    $roles = [];
}
$roles = array_values(array_unique(array_filter(array_map('intval', $roles))));

if ($empId <= 0 || empty($roles)) {
    header('Location: assign-position.php?emp_ID=' . urlencode((string)$empId) . '&error=missing');
    exit();
}

$employee = $employeeModel->getEmployeeById($empId);
if (!$employee || (int)$employee['is_super_admin'] === 1) {
    header('Location: manage-employees.php?error=protected');
    exit();
}

try {
    $employeeModel->setEmployeeRoles($empId, $roles);
    logActivity($db, 'employee', $_SESSION['employee_id'] ?? null, 'Assigned employee roles', 'Updated roles for employee ID #' . $empId);
    header('Location: manage-employees.php?position_updated=1');
    exit();
} catch (Exception $e) {
    error_log('Assign position error: ' . $e->getMessage());
    header('Location: assign-position.php?emp_ID=' . urlencode((string)$empId) . '&error=server');
    exit();
}
?>
