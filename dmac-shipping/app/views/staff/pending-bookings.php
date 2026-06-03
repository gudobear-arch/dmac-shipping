<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['bookings_view', 'bookings_approve']);
require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

$db = (new Database())->getConnection();
$model = new Employee($db);
$pendingBookings = $model->getPendingBookings();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateReadable($date) {
    if (empty($date)) {
        return 'No date set';
    }

    $time = strtotime($date);
    return $time ? date('M d, Y', $time) : $date;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Pending Bookings</title>

    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .pending-hero {
            background: linear-gradient(135deg, #064e3b, #15803d);
            color: #fff;
            border-radius: 26px;
            padding: 26px;
            margin-bottom: 22px;
            box-shadow: 0 18px 40px rgba(6, 78, 59, .18);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .pending-hero h2 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .pending-hero p {
            margin: 0;
            color: #dcfce7;
        }

        .pending-count-pill {
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            padding: 14px 18px;
            min-width: 150px;
            text-align: center;
        }

        .pending-count-pill strong {
            display: block;
            font-size: 32px;
            line-height: 1;
        }

        .pending-count-pill span {
            color: #dcfce7;
            font-weight: 700;
            font-size: 13px;
        }

        .pending-table-card {
            background: #fff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 12px 34px rgba(15,23,42,.08);
            border: 1px solid #e3f1e8;
        }

        .booking-id {
            font-weight: 900;
            color: #0b3d2b;
        }

        .muted-small {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 3px;
        }

        .animal-list {
            max-width: 260px;
            white-space: normal;
            line-height: 1.45;
        }

        .drop-address {
            max-width: 260px;
            white-space: normal;
            line-height: 1.45;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .approve-btn,
        .reject-btn {
            border: 0;
            border-radius: 12px;
            padding: 9px 12px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
        }

        .approve-btn {
            background: #137547;
            color: #fff;
        }

        .reject-btn {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending-review {
            background: #fef3c7;
            color: #92400e;
        }

        .empty-panel {
            text-align: center;
            padding: 46px 20px;
            color: #64748b;
        }

        .empty-panel i {
            font-size: 44px;
            color: #94a3b8;
            margin-bottom: 12px;
        }

        @media (max-width: 850px) {
            .pending-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .pending-count-pill {
                width: 100%;
            }
        }
    </style>
</head>

<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Pending Bookings</h1>
            <small>Review client bookings that are waiting for admin approval.</small>
        </div>

        <div class="user-wrapper">
            <i class="fa-solid fa-user-shield"></i>
            <div>
                <h4><?= h($_SESSION['employee_name'] ?? 'Employee') ?></h4>
                <small><?= h($_SESSION['employee_role'] ?? 'Staff') ?></small>
            </div>
        </div>
    </header>

    <main>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert-success <?= $_GET['updated'] === '1' ? '' : 'alert-error' ?>">
                <?= $_GET['updated'] === '1' ? 'Booking review updated successfully.' : 'Unable to update booking review. Please try again.' ?>
            </div>
        <?php endif; ?>

        <section class="pending-hero">
            <div>
                <h2>Bookings for Review</h2>
                <p>These are client bookings with <strong>PENDING REVIEW</strong> status. They are not accepted yet.</p>
            </div>
            <div class="pending-count-pill">
                <strong><?= h(count($pendingBookings)) ?></strong>
                <span>Pending Review</span>
            </div>
        </section>

        <section class="pending-table-card">
            <div class="section-header">
                <div>
                    <h3>Pending Booking Records</h3>
                    <small>Booking ID, client, animal details, drop-off address, request date, and status.</small>
                </div>
            </div>

            <?php if (empty($pendingBookings)): ?>
                <div class="empty-panel">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3>No pending bookings</h3>
                    <p>All client bookings have already been reviewed.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Client Name</th>
                                <th>Animal Type / Quantity</th>
                                <th>Total Quantity</th>
                                <th>Drop-off Address</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <?php if (hasPermission('bookings_approve')): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingBookings as $booking): ?>
                                <?php
                                    $dropOffAddress = trim(
                                        ($booking['receiver_street'] ?? '') . ', ' .
                                        ($booking['receiver_municipality'] ?? '') . ', ' .
                                        ($booking['receiver_province'] ?? '')
                                    );
                                ?>
                                <tr>
                                    <td>
                                        <span class="booking-id">#<?= h($booking['booking_ID']) ?></span>
                                        <span class="muted-small">Submitted: <?= h(formatDateReadable($booking['booking_startdate'] ?? '')) ?></span>
                                    </td>
                                    <td><?= h(trim(($booking['client_firstname'] ?? '') . ' ' . ($booking['client_lastname'] ?? ''))) ?></td>
                                    <td class="animal-list"><?= h($booking['animal_details'] ?? 'No animals listed') ?></td>
                                    <td><?= h((int)($booking['total_quantity'] ?? 0)) ?> head(s)</td>
                                    <td class="drop-address"><?= h($dropOffAddress) ?></td>
                                    <td><?= h(formatDateReadable($booking['booking_requestdate'] ?? '')) ?></td>
                                    <td>
                                        <span class="status-badge status-pending-review">
                                            <?= h($booking['booking_status']) ?>
                                        </span>
                                    </td>

                                    <?php if (hasPermission('bookings_approve')): ?>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" action="process-pending-booking.php" onsubmit="return confirm('Approve this booking and move it to Processing?');">
                                                    <input type="hidden" name="booking_id" value="<?= h($booking['booking_ID']) ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="approve-btn" type="submit">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>

                                                <form method="POST" action="process-pending-booking.php" onsubmit="return confirm('Reject this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?= h($booking['booking_ID']) ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button class="reject-btn" type="submit">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
