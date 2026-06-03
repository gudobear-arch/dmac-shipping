<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('employees_create');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../controllers/EmployeeController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register-employee.php");
    exit();
}

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);
$controller = new EmployeeController($employeeModel, $db);

$result = $controller->registerEmployee($_POST);

if (!$result['success']) {
    header("Location: register-employee.php?error=" . urlencode($result['error']));
    exit();
}

header("Location: manage-employees.php?created=1");
exit();
?>
