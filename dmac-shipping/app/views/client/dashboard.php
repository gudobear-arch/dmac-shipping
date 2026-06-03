<?php
// Start session to access logged-in user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: If user is not logged in, boot them back to the login page
if (!isset($_SESSION['client_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$clientId = $_SESSION['client_id'];
$clientName = isset($_SESSION['client_name']) ? $_SESSION['client_name'] : 'Client';

require_once '../../../config/database.php';
require_once '../../models/Booking.php';

$database = new Database();
$db = $database->getConnection();
$bookingModel = new Booking($db);
$stats = $bookingModel->getStatusCounts($clientId);
$recentShipments = $bookingModel->getRecentShipments($clientId, 5);

function badgeClassForStatus($status) {
    switch ($status) {
        case 'PENDING REVIEW':
            return 'badge-pending-review';
        case 'PROCESSING':
            return 'badge-processing';
        case 'IN TRANSIT':
            return 'badge-in-transit';
        case 'DELIVERED/SHIPPED':
        case 'COMPLETED':
        case 'DELIVERED':
        case 'SHIPPED':
            return 'badge-completed';
        default:
            return 'badge-processing';
    }
}

function formatShipmentDate($dateString) {
    $timestamp = strtotime($dateString);
    return $timestamp ? date('M j, Y', $timestamp) : 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">

    <div class="sidebar">
        <div class="sidebar-brand">
            <h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="booking-wizard.php"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
            <li><a href="my-shipments.php"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
            <li><a href="billing.php"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <div class="header-title">
                <h1>Overview</h1>
            </div>
            <div class="user-wrapper">
                <i class="fa-solid fa-circle-user"></i>
                <div>
                    <h4><?php echo htmlspecialchars($clientName); ?></h4>
                    <small>Client Account</small>
                </div>
            </div>
        </header>

        <main>
            <div class="welcome-banner">
                <h2>Hello, <?php echo htmlspecialchars(explode(' ', $clientName)[0]); ?>! 👋</h2>
                <p>Welcome to your logistics panel. Track your animal shipments, view payment receipts, or create a new booking request below.</p>
            </div>

            <div class="cards-container">
                <div class="card card-pending">
                    <div class="card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($stats['PENDING REVIEW']); ?></h3>
                        <span>Pending Review</span>
                    </div>
                </div>
                <div class="card card-processing">
                    <div class="card-icon"><i class="fa-solid fa-gears"></i></div>
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($stats['PROCESSING']); ?></h3>
                        <span>In Processing</span>
                    </div>
                </div>
                <div class="card card-transit">
                    <div class="card-icon"><i class="fa-solid fa-truck-moving"></i></div>
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($stats['IN TRANSIT']); ?></h3>
                        <span>In Transit</span>
                    </div>
                </div>
                <div class="card card-completed">
                    <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($stats['DELIVERED/SHIPPED'] ?? $stats['COMPLETED'] ?? 0); ?></h3>
                        <span>Delivered / Shipped</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-table-section">
                <div class="section-header">
                    <h3>Recent Shipments</h3>
                    <a href="booking-wizard.php" class="btn-primary-sm"><i class="fa-solid fa-plus"></i> New Booking</a>
                </div>
                <?php if (empty($recentShipments)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <p>You haven't requested any livestock shipments yet.</p>
                        <a href="booking-wizard.php" class="btn-secondary-outline">Get Started</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Drop-off Address</th>
                                    <th>Delivery Date</th>
                                    <th>Booking Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentShipments as $shipment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($shipment['booking_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['receiver_street'] . ', ' . $shipment['receiver_municipality'] . ', ' . $shipment['receiver_province']); ?></td>
                                        <td><?php echo htmlspecialchars(formatShipmentDate($shipment['booking_requestdate'])); ?></td>
                                        <td><span class="status-badge <?php echo badgeClassForStatus($shipment['booking_status']); ?>"><?php echo htmlspecialchars($shipment['booking_status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

</body>
</html>