<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('bookings_approve');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pending-bookings.php');
    exit();
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

if ($bookingId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: pending-bookings.php?updated=0');
    exit();
}

$db = (new Database())->getConnection();
$model = new Employee($db);

$newStatus = $action === 'approve' ? 'FOR PICK-UP' : 'CANCELLED';
$ok = $model->updateBookingStatus($bookingId, $newStatus);

header('Location: pending-bookings.php?updated=' . ($ok ? '1' : '0'));
exit();
?>
