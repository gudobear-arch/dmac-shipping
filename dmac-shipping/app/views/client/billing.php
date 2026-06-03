<?php
session_start();
if (!isset($_SESSION['client_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../../../config/database.php';
require_once '../../models/Payment.php';

$db = (new Database())->getConnection();
$paymentModel = new Payment($db);
$billings = $paymentModel->getClientBilling($_SESSION['client_id']);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n) { return $n === null || $n === '' ? 'Not set' : '₱' . number_format((float)$n, 2); }
function dateOnly($date) { $ts = strtotime((string)$date); return $ts ? date('M j, Y', $ts) : 'N/A'; }
function fullDropOff($row) {
    $parts = array_filter([
        trim((string)($row['receiver_street'] ?? '')),
        trim((string)($row['receiver_municipality'] ?? '')),
        trim((string)($row['receiver_province'] ?? ''))
    ]);
    return $parts ? implode(', ', $parts) : 'N/A';
}
function payStatus($row) {
    if (empty($row['payment_ID'])) return 'Not Set';
    return strtoupper((string)($row['payment_status'] ?? ((int)($row['is_paid'] ?? 0) === 1 ? 'PAID' : 'PENDING')));
}
function payBadge($row) {
    $status = payStatus($row);
    if ($status === 'PAID') return 'badge-completed';
    if ($status === 'OVERDUE') return 'badge-cancelled';
    if ($status === 'Not Set') return 'badge-processing';
    return 'badge-pending-review';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Billing - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
<div class="sidebar">
    <div class="sidebar-brand"><h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2></div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="booking-wizard.php"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
        <li><a href="my-shipments.php"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
        <li><a href="billing.php" class="active"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
        <li><a href="feedback.php"><i class="fa-solid fa-message"></i> Feedback</a></li>
        <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
    </ul>
</div>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Billing</h1>
            <small>View your booking payment amount, method, and status</small>
        </div>
    </header>

    <main>
        <div class="dashboard-card">
            <div class="section-header">
                <div>
                    <h3>Payment Details</h3>
                    <small>Payment amount, method, and status are set by admin or authorized employees.</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Drop-off Address</th>
                            <th>Delivery Date</th>
                            <th>Pay Amount</th>
                            <th>Pay Method</th>
                            <th>Pay Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($billings)): ?>
                        <tr><td colspan="6" class="table-empty">No billing records yet. Create a booking first.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($billings as $billing): ?>
                        <tr>
                            <td>#<?= h($billing['booking_ID']) ?></td>
                            <td><?= h(fullDropOff($billing)) ?></td>
                            <td><?= !empty($billing['booking_enddate']) ? h(dateOnly($billing['booking_enddate'])) : '<span class="muted-text">Not set by admin</span>' ?></td>
                            <td><strong><?= h(money($billing['total_amount'] ?? $billing['pay_amount'])) ?></strong></td>
                            <td><?= h($billing['pay_method'] ?: 'Not set') ?></td>
                            <td><span class="status-badge <?= h(payBadge($billing)) ?>"><?= h(payStatus($billing)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
