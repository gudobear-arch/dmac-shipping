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
$clientId = (int)$_SESSION['client_id'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function feedbackDate($date) {
    $time = strtotime((string)$date);
    return $time ? date('M j, Y g:i A', $time) : 'N/A';
}

function isCompletedShipment($status) {
    $status = strtoupper(trim((string)$status));

    return in_array($status, [
        'DELIVERED/SHIPPED',
        'DELIVERED / SHIPPED',
        'COMPLETED',
        'DELIVERED',
        'SHIPPED'
    ], true);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($bookingId <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        $error = 'Please select a completed shipment, rating, and write your feedback.';
    } else {
        $ok = $model->submitFeedback($clientId, $bookingId, $rating, $comment);

        if ($ok) {
            header('Location: feedback.php?success=1');
            exit();
        }

        $error = 'Feedback can only be submitted for your delivered/shipped bookings.';
    }
}

if (isset($_GET['success'])) {
    $message = 'Feedback submitted successfully.';
}

$shipments = $model->getClientShipments($clientId, 100);
$feedbackHistory = $model->getClientFeedbackHistory($clientId);
$selected = (int)($_GET['booking_id'] ?? 0);
$completedShipments = array_filter($shipments, function ($shipment) {
    return isCompletedShipment($shipment['booking_status'] ?? '');
});
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Feedback - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/feedback.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="dashboard-body">
<div class="sidebar">
    <div class="sidebar-brand">
        <h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2>
    </div>

    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="booking-wizard.php"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
        <li><a href="my-shipments.php"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
        <li><a href="billing.php"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
        <li><a href="feedback.php" class="active"><i class="fa-solid fa-message"></i> Feedback</a></li>
        <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
    </ul>
</div>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Feedback</h1>
            <small>Rate your delivered shipments from 1 to 5.</small>
        </div>
    </header>

    <main class="feedback-page">
        <?php if ($message): ?>
            <div class="alert-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="feedback-grid">
            <section class="feedback-card">
                <div class="feedback-section-head">
                    <div>
                        <h3>Submit Shipment Feedback</h3>
                        <p>Only delivered/shipped bookings can receive feedback.</p>
                    </div>
                    <div class="feedback-icon"><i class="fa-solid fa-star"></i></div>
                </div>

                <?php if (empty($completedShipments)): ?>
                    <div class="empty-feedback">
                        <i class="fa-solid fa-box-open"></i>
                        <p>No delivered shipments available for feedback yet.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" class="feedback-form">
                        <div class="form-group">
                            <label for="booking_id">Completed Shipment</label>
                            <select name="booking_id" id="booking_id" required>
                                <option value="">Select completed shipment</option>
                                <?php foreach ($completedShipments as $shipment): ?>
                                    <option value="<?= h($shipment['booking_ID']) ?>" <?= $selected === (int)$shipment['booking_ID'] ? 'selected' : '' ?>>
                                        #<?= h($shipment['booking_ID']) ?> —
                                        <?= h($shipment['pickup_municipality'] ?? 'Pickup') ?>
                                        to
                                        <?= h($shipment['receiver_municipality'] ?? 'Drop-off') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="rating">Rating</label>
                            <select name="rating" id="rating" required>
                                <option value="">Select rating</option>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Okay</option>
                                <option value="2">2 - Needs improvement</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comment">Write feedback here</label>
                            <textarea name="comment" id="comment" rows="6" maxlength="1000" placeholder="Tell us about your shipment experience..." required></textarea>
                        </div>

                        <button class="btn-primary feedback-submit" type="submit">
                            <i class="fa-solid fa-paper-plane"></i>
                            Submit Feedback
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="feedback-card">
                <div class="feedback-section-head">
                    <div>
                        <h3>My Feedback History</h3>
                        <p>Your latest submitted ratings and comments.</p>
                    </div>
                    <div class="feedback-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                </div>

                <?php if (empty($feedbackHistory)): ?>
                    <div class="empty-feedback">
                        <i class="fa-regular fa-message"></i>
                        <p>You have not submitted feedback yet.</p>
                    </div>
                <?php else: ?>
                    <div class="feedback-history-list">
                        <?php foreach ($feedbackHistory as $item): ?>
                            <article class="feedback-history-item">
                                <div>
                                    <strong>Booking #<?= h($item['booking_ID']) ?></strong>
                                    <span><?= h(feedbackDate($item['feed_submitted'])) ?></span>
                                </div>

                                <div class="client-stars">
                                    <?= str_repeat('★', (int)$item['feed_rate']) ?>
                                    <?= str_repeat('☆', 5 - (int)$item['feed_rate']) ?>
                                </div>

                                <p><?= h($item['feed_comment']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>
