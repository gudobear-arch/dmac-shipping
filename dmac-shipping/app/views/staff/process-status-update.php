<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('shipments_update');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: active-shipments.php');
    exit();
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$status = trim($_POST['booking_status'] ?? '');
$transportId = (int)($_POST['transport_id'] ?? 0);

$details = [
    'air_emp_ID' => (int)($_POST['air_emp_ID'] ?? 0),
    'airline' => trim($_POST['airline'] ?? ''),
    'flight_reference_number' => trim($_POST['flight_reference_number'] ?? ''),
    'driver_emp_ID' => (int)($_POST['driver_emp_ID'] ?? 0),
    'vehicle_ID' => (int)($_POST['vehicle_ID'] ?? 0)
];

try {
    $db = (new Database())->getConnection();
    $model = new Employee($db);

    $ok = $model->updateShipmentManagement($bookingId, $status, $transportId, $details);

    header('Location: active-shipments.php?updated=' . ($ok ? '1' : '0'));
    exit();
} catch (Exception $e) {
    error_log('Process Shipment Management Error: ' . $e->getMessage());
    header('Location: active-shipments.php?updated=0');
    exit();
}
?>
