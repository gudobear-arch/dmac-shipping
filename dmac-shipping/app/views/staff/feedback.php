<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('feedback_view');

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once '../../../config/database.php';
require_once '../../models/Employee.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function feedbackDate($date) {
    $time = strtotime((string)$date);
    return $time ? date('M j, Y g:i A', $time) : 'N/A';
}

$ratingFilter = $_GET['rating'] ?? '';
$search = trim($_GET['search'] ?? '');

$feedback = $employeeModel->getFeedback(100, $ratingFilter, $search);
$summary = $employeeModel->getFeedbackSummary();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Client Feedback - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/feedback.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Client Feedback</h1>
            <small>Monitor shipment ratings, comments, and customer experience.</small>
        </div>
    </header>

    <main class="feedback-page">
        <div class="feedback-summary-grid">
            <div class="feedback-summary-card">
                <span>Total Feedback</span>
                <strong><?= h($summary['total_feedback'] ?? 0) ?></strong>
            </div>

            <div class="feedback-summary-card">
                <span>Average Rating</span>
                <strong><?= h($summary['average_rating'] ?? '0.0') ?>/5</strong>
            </div>

            <div class="feedback-summary-card">
                <span>Five Star Reviews</span>
                <strong><?= h($summary['five_star'] ?? 0) ?></strong>
            </div>

            <div class="feedback-summary-card">
                <span>Latest Feedback</span>
                <strong class="small-strong"><?= h(feedbackDate($summary['latest_date'] ?? null)) ?></strong>
            </div>
        </div>

        <section class="feedback-card">
            <div class="feedback-section-head">
                <div>
                    <h3>Recent Feedback</h3>
                    <p>Booking ID, client name, feedback comment, and feedback rate.</p>
                </div>
            </div>

            <form method="GET" class="feedback-filter">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= h($search) ?>"
                        placeholder="Booking ID, client name, or comment"
                    >
                </div>

                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating">
                        <option value="">All Ratings</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>" <?= (string)$ratingFilter === (string)$i ? 'selected' : '' ?>>
                                <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary filter-btn">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>

                <a href="feedback.php" class="btn-secondary-outline clear-filter">Clear</a>
            </form>

            <div class="table-responsive">
                <table class="modern-table feedback-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Client Name</th>
                            <th>Feedback Comment</th>
                            <th>Feedback Rate</th>
                            <th>Date Submitted</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($feedback)): ?>
                            <tr>
                                <td colspan="5" class="table-empty">No feedback found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedback as $item): ?>
                                <tr>
                                    <td>#<?= h($item['booking_ID']) ?></td>
                                    <td>
                                        <strong><?= h(trim(($item['client_firstname'] ?? '') . ' ' . ($item['client_lastname'] ?? ''))) ?></strong>
                                        <?php if (!empty($item['client_email'])): ?>
                                            <small><?= h($item['client_email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="feedback-comment-cell"><?= h($item['feed_comment']) ?></td>
                                    <td>
                                        <span class="rating-pill">
                                            <i class="fa-solid fa-star"></i>
                                            <?= h($item['feed_rate']) ?>/5
                                        </span>
                                    </td>
                                    <td><?= h(feedbackDate($item['feed_submitted'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
