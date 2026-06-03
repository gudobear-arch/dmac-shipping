<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['employees_edit','employees_permissions','employees_deactivate']);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../controllers/EmployeeController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-employees.php');
    exit();
}

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);
$controller = new EmployeeController($employeeModel, $db);

$result = $controller->updateEmployeeAccess($_POST);

if ($result['success']) {
    header('Location: manage-employees.php?updated=1');
    exit();
}

header('Location: manage-employees.php?error=' . urlencode($result['error'] ?? 'update'));
exit();
?>
