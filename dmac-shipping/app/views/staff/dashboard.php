<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireEmployeeLogin();

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once '../../../config/database.php';
require_once '../../models/Employee.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

$stats = $employeeModel->getDashboardStats();
$shipments = $employeeModel->getAllShipments(6);
$feedbackList = $employeeModel->getFeedback(8);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashboardStatusClass($status) {
    $status = strtoupper(trim((string)$status));

    switch ($status) {
        case 'PENDING REVIEW':
            return 'status-pending';
        case 'FOR PICK-UP':
            return 'status-pickup';
        case 'PROCESSING':
            return 'status-processing';
        case 'PREPARING FOR TRANSIT':
            return 'status-preparing';
        case 'IN TRANSIT':
            return 'status-transit';
        case 'COMPLETED':
        case 'DELIVERED':
        case 'SHIPPED':
            return 'status-delivered';
        default:
            return 'status-default';
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Employee Dashboard</title>

    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --dash-green: #0f5132;
            --dash-green-dark: #073b25;
            --dash-soft: #eef8f2;
            --dash-border: #dfece5;
            --dash-muted: #64748b;
            --dash-text: #073b25;
            --dash-shadow: 0 14px 30px rgba(15, 81, 50, .08);
        }

        .dashboard-body {
            background: #f4fbf7;
        }

        .main-content main {
            padding-bottom: 34px;
        }

        .dash-hero {
            background: linear-gradient(135deg, #073b25, #168052);
            border-radius: 24px;
            color: #fff;
            padding: 24px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            box-shadow: var(--dash-shadow);
        }

        .dash-hero h2 {
            margin: 0 0 6px;
            font-size: 28px;
            line-height: 1.1;
        }

        .dash-hero p {
            margin: 0;
            color: rgba(255,255,255,.78);
            max-width: 720px;
        }

        .dash-hero-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dash-pill {
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 999px;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(145px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .status-card {
            background: #fff;
            border-radius: 18px;
            padding: 16px;
            border: 1px solid var(--dash-border);
            box-shadow: 0 10px 24px rgba(15, 81, 50, .05);
            min-height: 126px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .status-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .status-card .icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: var(--dash-soft);
            color: var(--dash-green);
            font-size: 17px;
            flex: 0 0 auto;
        }

        .status-card h3 {
            margin: 14px 0 2px;
            font-size: 30px;
            color: var(--dash-text);
            line-height: 1;
        }

        .status-card span {
            display: block;
            color: var(--dash-muted);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
            max-width: 760px;
        }

        .insight-card {
            background: #fff;
            border: 1px solid var(--dash-border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(15, 81, 50, .05);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .insight-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: var(--dash-green);
            background: #dcfce7;
            font-size: 20px;
        }

        .insight-card h3 {
            margin: 0;
            font-size: 27px;
            color: var(--dash-text);
        }

        .insight-card span {
            color: var(--dash-muted);
            font-weight: 700;
            font-size: 14px;
        }

        .dashboard-panels {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
            gap: 20px;
            align-items: start;
        }

        .dashboard-card {
            background: #fff;
            border-radius: 22px;
            border: 1px solid var(--dash-border);
            padding: 22px;
            box-shadow: var(--dash-shadow);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-header h3 {
            margin: 0;
            color: var(--dash-text);
            font-size: 21px;
        }

        .section-header small {
            display: block;
            color: var(--dash-muted);
            margin-top: 4px;
        }

        .btn-primary-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: var(--dash-green);
            color: #fff !important;
            text-decoration: none;
            border-radius: 12px;
            padding: 10px 13px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid #e7efe9;
        }

        .modern-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            background: #fff;
        }

        .feedback-table {
            min-width: 600px;
        }

        .modern-table thead {
            background: #e8f5ee;
        }

        .modern-table th {
            text-align: left;
            padding: 14px 14px;
            color: var(--dash-green-dark);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .modern-table td {
            padding: 15px 14px;
            border-top: 1px solid #e7efe9;
            color: #233244;
            vertical-align: top;
        }

        .route-text {
            font-weight: 700;
            color: #213547;
        }

        .muted-small {
            color: var(--dash-muted);
            font-size: 12px;
            margin-top: 3px;
        }

        .feedback-comment {
            max-width: 330px;
            white-space: normal;
            line-height: 1.45;
        }

        .rating-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fff8df;
            color: #7a5a00;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 800;
            white-space: nowrap;
        }

        .empty-state {
            padding: 28px 16px !important;
            color: var(--dash-muted) !important;
            text-align: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-badge.status-pending { background: #fff3cd; color: #7a5200; }
        .status-badge.status-pickup { background: #dff6ff; color: #075985; }
        .status-badge.status-processing { background: #e6f4ea; color: #166534; }
        .status-badge.status-preparing { background: #ede9fe; color: #5b21b6; }
        .status-badge.status-transit { background: #dbeafe; color: #1d4ed8; }
        .status-badge.status-delivered { background: #dcfce7; color: #15803d; }
        .status-badge.status-default { background: #eef2f7; color: #475569; }

        @media (max-width: 1400px) {
            .status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1050px) {
            .dashboard-panels {
                grid-template-columns: 1fr;
            }

            .dash-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .dash-hero-badges {
                justify-content: flex-start;
            }
        }

        @media (max-width: 760px) {
            .status-grid,
            .insight-grid {
                grid-template-columns: 1fr;
                max-width: none;
            }

            .dash-hero {
                border-radius: 18px;
                padding: 18px;
            }

            .dash-hero h2 {
                font-size: 23px;
            }

            .dashboard-card {
                padding: 16px;
                border-radius: 18px;
            }
        }
    </style>
</head>

<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Employee Dashboard</h1>
            <small>Booking status overview and client feedback summary</small>
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
        <?php if (isset($_GET['unauthorized'])): ?>
            <div class="alert-success alert-error">You do not have permission to open that page.</div>
        <?php endif; ?>

        <section class="dash-hero">
            <div>
                <h2>Shipment Operations Overview</h2>
                <p>Track booking progress by status and monitor recent client feedback in one clean dashboard.</p>
            </div>
            <div class="dash-hero-badges">
                <span class="dash-pill"><i class="fa-solid fa-calendar-day"></i> Today</span>
                <span class="dash-pill"><i class="fa-solid fa-location-dot"></i> DMAC Shipping</span>
            </div>
        </section>

        <section class="status-grid">
            <div class="status-card">
                <div class="status-card-top">
                    <span>Pending Bookings</span>
                    <div class="icon"><i class="fa-solid fa-clock"></i></div>
                </div>
                <h3><?= h($stats['pending_bookings'] ?? 0) ?></h3>
            </div>

            <div class="status-card">
                <div class="status-card-top">
                    <span>For Pick Up</span>
                    <div class="icon"><i class="fa-solid fa-box-open"></i></div>
                </div>
                <h3><?= h($stats['for_pickup'] ?? 0) ?></h3>
            </div>

            <div class="status-card">
                <div class="status-card-top">
                    <span>Processing</span>
                    <div class="icon"><i class="fa-solid fa-gears"></i></div>
                </div>
                <h3><?= h($stats['processing'] ?? 0) ?></h3>
            </div>

            <div class="status-card">
                <div class="status-card-top">
                    <span>Preparing for Transit</span>
                    <div class="icon"><i class="fa-solid fa-boxes-packing"></i></div>
                </div>
                <h3><?= h($stats['preparing_for_transit'] ?? 0) ?></h3>
            </div>

            <div class="status-card">
                <div class="status-card-top">
                    <span>In-Transit</span>
                    <div class="icon"><i class="fa-solid fa-truck-fast"></i></div>
                </div>
                <h3><?= h($stats['in_transit'] ?? 0) ?></h3>
            </div>

            <div class="status-card">
                <div class="status-card-top">
                    <span>Delivered / Shipped</span>
                    <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <h3><?= h($stats['delivered'] ?? 0) ?></h3>
            </div>
        </section>

        <section class="insight-grid">
            <div class="insight-card">
                <div class="icon"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <h3><?= h($stats['feedback_count'] ?? 0) ?></h3>
                    <span>Total Client Feedback</span>
                </div>
            </div>

            <div class="insight-card">
                <div class="icon"><i class="fa-solid fa-star"></i></div>
                <div>
                    <h3><?= h($stats['average_rating'] ?? '0.0') ?></h3>
                    <span>Average Rating</span>
                </div>
            </div>
        </section>

        <section class="dashboard-panels">
            <div class="dashboard-card">
                <div class="section-header">
                    <div>
                        <h3>Recent Shipments</h3>
                        <small>Latest bookings and shipment status updates</small>
                    </div>
                    <a class="btn-primary-sm" href="active-shipments.php">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> View Active
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($shipments)): ?>
                                <tr>
                                    <td class="empty-state" colspan="5">No shipments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($shipments as $shipment): ?>
                                    <tr>
                                        <td><strong>#<?= h($shipment['booking_ID']) ?></strong></td>
                                        <td>
                                            <strong><?= h($shipment['client_firstname'] . ' ' . $shipment['client_lastname']) ?></strong>
                                            <div class="muted-small">Client booking</div>
                                        </td>
                                        <td>
                                            <div class="route-text"><?= h($shipment['pickup_municipality']) ?> → <?= h($shipment['receiver_municipality']) ?></div>
                                        </td>
                                        <td><?= h($shipment['booking_requestdate']) ?></td>
                                        <td>
                                            <span class="status-badge <?= h(dashboardStatusClass($shipment['booking_status'])) ?>">
                                                <?= h($shipment['booking_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="section-header">
                    <div>
                        <h3>Client Feedback Summary</h3>
                        <small>Recent booking comments and ratings</small>
                    </div>
                    <a class="btn-primary-sm" href="feedback.php">
                        <i class="fa-solid fa-list"></i> View All
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="modern-table feedback-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Client</th>
                                <th>Comment</th>
                                <th>Rate</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($feedbackList)): ?>
                                <tr>
                                    <td class="empty-state" colspan="4">No client feedback yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedbackList as $feedback): ?>
                                    <tr>
                                        <td><strong>#<?= h($feedback['booking_ID']) ?></strong></td>
                                        <td><?= h($feedback['client_firstname'] . ' ' . $feedback['client_lastname']) ?></td>
                                        <td class="feedback-comment"><?= h($feedback['feed_comment']) ?></td>
                                        <td>
                                            <span class="rating-pill">
                                                <i class="fa-solid fa-star"></i>
                                                <?= h($feedback['feed_rate']) ?>/5
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
