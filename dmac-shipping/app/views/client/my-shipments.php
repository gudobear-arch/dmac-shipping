<?php
session_start();
if (!isset($_SESSION['client_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../../../config/database.php';
require_once '../../models/Booking.php';

$db = (new Database())->getConnection();
$model = new Booking($db);
$orders = $model->getClientShipments($_SESSION['client_id'], 100);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function badgeClass($status) {
    return 'badge-' . strtolower(str_replace(' ', '-', (string)$status));
}

function formatDateOnly($date) {
    $ts = strtotime((string)$date);
    return $ts ? date('M j, Y', $ts) : 'N/A';
}

function fullAddress($street, $municipality, $province) {
    $parts = array_filter([trim((string)$street), trim((string)$municipality), trim((string)$province)]);
    return $parts ? implode(', ', $parts) : 'N/A';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Active Orders - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
<div class="sidebar">
    <div class="sidebar-brand"><h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2></div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="booking-wizard.php"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
        <li><a href="my-shipments.php" class="active"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
        <li><a href="billing.php"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
        <li><a href="feedback.php"><i class="fa-solid fa-message"></i> Feedback</a></li>
        <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
    </ul>
</div>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Active Orders</h1>
            <small>Complete details of every booking request you submitted</small>
        </div>
    </header>

    <main>
        <div class="dashboard-card">
            <div class="section-header">
                <div>
                    <h3>Booking Details</h3>
                    <small>All pickup, receiver, drop-off, animal, date, and agreement details are shown here.</small>
                </div>
                <a href="booking-wizard.php" class="btn-primary-sm"><i class="fa-solid fa-plus"></i> New Booking</a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <p>No active orders yet.</p>
                    <a href="booking-wizard.php" class="btn-secondary-outline">Create your first booking</a>
                </div>
            <?php else: ?>
                <div class="active-orders-grid">
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $pickupName = trim(($order['contact_firstname'] ?? '') . ' ' . ($order['contact_lastname'] ?? ''));
                            $receiverName = trim(($order['receiver_firstname'] ?? '') . ' ' . ($order['receiver_lastname'] ?? ''));
                            $pickupAddress = fullAddress($order['pickup_street'] ?? '', $order['pickup_municipality'] ?? '', $order['pickup_province'] ?? '');
                            $receiverAddress = fullAddress($order['receiver_street'] ?? '', $order['receiver_municipality'] ?? '', $order['receiver_province'] ?? '');
                            $dropOff = fullAddress('', $order['receiver_municipality'] ?? '', $order['receiver_province'] ?? '');
                            $termsAccepted = (int)($order['terms_accepted'] ?? 0) === 1;
                            $insuranceAccepted = (int)($order['insurance_accepted'] ?? 0) === 1;
                        ?>
                        <article class="order-detail-card">
                            <div class="order-detail-header">
                                <div>
                                    <h3>Booking #<?= h($order['booking_ID']) ?></h3>
                                    <small>Requested ship date: <?= h(formatDateOnly($order['booking_requestdate'])) ?></small>
                                </div>
                                <span class="status-badge <?= h(badgeClass($order['booking_status'])) ?>"><?= h($order['booking_status']) ?></span>
                            </div>

                            <div class="order-detail-sections">
                                <section class="order-info-box">
                                    <h4><i class="fa-solid fa-location-dot"></i> Pickup Details</h4>
                                    <p><strong>Contact Name:</strong> <?= h($pickupName ?: 'N/A') ?></p>
                                    <p><strong>Contact Number:</strong> <?= h($order['contact_number'] ?? 'N/A') ?></p>
                                    <p><strong>Pickup Address:</strong> <?= h($pickupAddress) ?></p>
                                </section>

                                <section class="order-info-box">
                                    <h4><i class="fa-solid fa-user-check"></i> Receiver Details</h4>
                                    <p><strong>Receiver Name:</strong> <?= h($receiverName ?: 'N/A') ?></p>
                                    <p><strong>Receiver Contact:</strong> <?= h($order['receiver_contact'] ?? 'N/A') ?></p>
                                    <p><strong>Receiver Address:</strong> <?= h($receiverAddress) ?></p>
                                </section>

                                <section class="order-info-box">
                                    <h4><i class="fa-solid fa-map-location-dot"></i> Drop-off Location</h4>
                                    <p><strong>Municipality:</strong> <?= h($order['receiver_municipality'] ?? 'N/A') ?></p>
                                    <p><strong>Province:</strong> <?= h($order['receiver_province'] ?? 'N/A') ?></p>
                                    <p><strong>Full Drop-off:</strong> <?= h($dropOff) ?></p>
                                </section>

                                <section class="order-info-box">
                                    <h4><i class="fa-solid fa-paw"></i> Shipment Details</h4>
                                    <p><strong>Animal Type & Quantity:</strong> <?= h($order['animal_summary'] ?? 'No animals listed') ?></p>
                                    <p><strong>Total Quantity:</strong> <?= h($order['total_animals'] ?? 0) ?> head(s)</p>
                                    <p><strong>Date Submitted:</strong> <?= h(formatDateOnly($order['booking_startdate'])) ?></p>
                                </section>

                                <section class="order-info-box agreement-box">
                                    <h4><i class="fa-solid fa-shield-heart"></i> Agreement</h4>
                                    <p><strong>Terms and Agreement:</strong> <?= $termsAccepted ? '<span class="ok-text">Accepted</span>' : '<span class="warn-text">Not recorded</span>' ?></p>
                                    <p><strong>Insurance Policy:</strong> <?= $insuranceAccepted ? '<span class="ok-text">Accepted</span>' : '<span class="warn-text">Not recorded</span>' ?></p>
                                    <p><strong>Accepted At:</strong> <?= h(!empty($order['agreement_accepted_at']) ? date('M j, Y g:i A', strtotime($order['agreement_accepted_at'])) : 'N/A') ?></p>
                                </section>
                            </div>

                            <div class="order-detail-footer">
                                <?php if (in_array($order['booking_status'], ['DELIVERED/SHIPPED','COMPLETED','DELIVERED','SHIPPED'], true)): ?>
                                    <a class="btn-secondary-outline mini" href="feedback.php?booking_id=<?= h($order['booking_ID']) ?>"><i class="fa-solid fa-star"></i> Rate Booking</a>
                                <?php else: ?>
                                    <small><i class="fa-solid fa-circle-info"></i> Feedback becomes available after completion.</small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
