<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['bookings_assign','shipments_update']);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assignments.php');
    exit();
}

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

$action = $_POST['action'] ?? 'assign_stage';
$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($action === 'update_status') {
    $status = trim($_POST['booking_status'] ?? '');
    $ok = $employeeModel->updateBookingStatus($bookingId, $status);
    header('Location: assignments.php?status=' . ($ok ? 'status_updated' : 'status_error'));
    exit();
}

$empId = (int)($_POST['emp_id'] ?? ($_POST['driver_emp_id'] ?? 0));
$stage = trim($_POST['process_stage'] ?? 'PICKUP');
$notes = trim($_POST['notes'] ?? '');
$assignedBy = (int)($_SESSION['employee_id'] ?? 0);

$ok = $employeeModel->assignCoordinatorToStage($bookingId, $stage, $empId, $assignedBy, $notes);

header('Location: assignments.php?status=' . ($ok ? 'assigned' : 'error'));
exit();
?>
