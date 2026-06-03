<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['bookings_view','bookings_approve','bookings_assign','shipments_view','shipments_update']);
require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

$db = (new Database())->getConnection();
$model = new Employee($db);
$bookings = $model->getBookingsForCoordinatorAssignments();
$employees = $model->getAssignableEmployees();
$bookingIds = array_column($bookings, 'booking_ID');
$assignmentHistory = $model->getAssignmentsForBookings($bookingIds);
$currentAssignments = $model->getCurrentStageAssignments($bookingIds);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stageLabel($stage) {
    return [
        'PICKUP' => 'Pickup',
        'PROCESSING' => 'Processing',
        'IN_TRANSIT' => 'In-Transit',
        'ARRIVAL' => 'Arrival',
        'DELIVERED' => 'Delivered'
    ][$stage] ?? $stage;
}

function statusClass($status) {
    $status = strtoupper((string)$status);
    if (strpos($status, 'PENDING') !== false) return 'status-pending';
    if (strpos($status, 'PROCESS') !== false) return 'status-processing';
    if (strpos($status, 'TRANSIT') !== false) return 'status-transit';
    if (strpos($status, 'DELIVER') !== false || strpos($status, 'ARRIVAL') !== false) return 'status-done';
    return 'status-default';
}

function assignmentShipmentTypeLabel($booking) {
    $id = !empty($booking['transport_ID']) ? (int)$booking['transport_ID'] : 0;
    $type = strtoupper(trim((string)($booking['shipment_type'] ?? '')));

    if ($id === 1 || $type === 'AIR') return 'Air Travel';
    if ($id === 2 || $type === 'LAND') return 'Land Travel';
    return 'Not set';
}

function assignmentCourierDetails($booking) {
    $id = !empty($booking['transport_ID']) ? (int)$booking['transport_ID'] : 0;
    $type = strtoupper(trim((string)($booking['shipment_type'] ?? '')));
    $items = [];

    if ($id === 1 || $type === 'AIR') {
        if (!empty($booking['shipper_agent'])) {
            $items[] = '<strong>Shipper Agent:</strong> ' . h($booking['shipper_agent']);
        }
        if (!empty($booking['airline_name'])) {
            $items[] = '<strong>Airline:</strong> ' . h($booking['airline_name']);
        }
        if (!empty($booking['flight_reference_number'])) {
            $items[] = '<strong>Flight Ref:</strong> ' . h($booking['flight_reference_number']);
        }
    } elseif ($id === 2 || $type === 'LAND') {
        $driverName = trim(($booking['driver_firstname'] ?? '') . ' ' . ($booking['driver_lastname'] ?? ''));
        if ($driverName !== '') {
            $items[] = '<strong>Driver:</strong> ' . h($driverName);
        }
        if (!empty($booking['vehicle_type'])) {
            $items[] = '<strong>Vehicle:</strong> ' . h($booking['vehicle_type']);
        }
        if (!empty($booking['vehicle_plate_number'])) {
            $items[] = '<strong>Plate:</strong> ' . h($booking['vehicle_plate_number']);
        }
        if (!empty($booking['vehicle_license_permit'])) {
            $items[] = '<strong>Permit:</strong> ' . h($booking['vehicle_license_permit']);
        }
    }

    if (!$items) {
        return '<span class="muted">Travel details not set</span>';
    }

    return implode('<br>', $items);
}

$stages = ['PICKUP', 'PROCESSING', 'IN_TRANSIT', 'ARRIVAL', 'DELIVERED'];
$displayStages = ['PICKUP', 'PROCESSING', 'IN_TRANSIT', 'ARRIVAL'];
$canAssign = hasAnyPermission(['bookings_assign','shipments_update']);
$totalBookings = count($bookings);
$totalAssignedStages = 0;
foreach ($currentAssignments as $rows) {
    $totalAssignedStages += count($rows);
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Coordinator Assignments</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:18px}
        .summary-card{background:#fff;border:1px solid #e4eee9;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,61,45,.06);display:flex;gap:14px;align-items:center}
        .summary-icon{width:46px;height:46px;border-radius:15px;background:#dcfce7;color:#11623f;display:grid;place-items:center;font-size:20px}
        .summary-card h3{margin:0;color:#082f24;font-size:24px}.summary-card p{margin:3px 0 0;color:#60736c;font-weight:700;font-size:13px}
        .assignment-panel{background:#fff;border:1px solid #e4eee9;border-radius:22px;box-shadow:0 12px 32px rgba(15,61,45,.07);overflow:hidden}
        .panel-head{padding:22px 24px;border-bottom:1px solid #eef5f1;display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}
        .panel-head h2{margin:0;color:#07382a;font-size:22px}.panel-head p{margin:6px 0 0;color:#60736c;font-size:14px}
        .assign-table-wrap{overflow-x:auto}.assign-table{width:100%;border-collapse:collapse;min-width:980px}.assign-table th{background:#eef8f2;color:#07382a;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.04em;padding:14px 16px}.assign-table td{padding:16px;border-bottom:1px solid #eef5f1;vertical-align:middle}.assign-table tr:hover{background:#fbfefc}
        .booking-title{font-size:18px;font-weight:900;color:#082f24}.muted{color:#64746f;font-size:13px}.route-text{font-weight:700;color:#315247;font-size:13px;margin-top:4px}.meta-line{color:#64746f;font-size:12px;margin-top:6px}
        .status-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:900;text-transform:uppercase;white-space:nowrap}.status-pending{background:#e8f5ee;color:#11623f}.status-processing{background:#fff7db;color:#8a5b00}.status-transit{background:#e8f1ff;color:#174ea6}.status-done{background:#dcfce7;color:#166534}.status-default{background:#f1f5f9;color:#475569}
        .stage-chips{display:flex;gap:8px;flex-wrap:wrap;max-width:430px}.stage-chip{border:1px solid #dbeae3;background:#f8fcfa;border-radius:999px;padding:7px 10px;font-size:12px;color:#244b3c}.stage-chip strong{color:#07382a}.stage-chip.assigned{background:#eaf7ef;border-color:#bce7cd;color:#0f5132}
        .btn-primary-soft,.btn-green{border:0;border-radius:12px;padding:10px 14px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;white-space:nowrap}.btn-green{background:#147a4f;color:white}.btn-green:hover{background:#0f5e3d}.btn-primary-soft{background:#eaf7ef;color:#11623f}.btn-primary-soft:hover{background:#d8f0e2}.btn-outline{border:1px solid #bdd9ca;background:#fff;color:#11623f;border-radius:12px;padding:10px 14px;font-weight:800;text-decoration:none;display:inline-flex;gap:8px;align-items:center}
        .alert-success,.alert-error{padding:13px 16px;border-radius:14px;margin:0 0 18px;font-weight:800}.alert-success{background:#dcfce7;color:#11623f;border:1px solid #86efac}.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.empty-state{padding:46px;text-align:center;color:#64746f}.empty-state i{font-size:38px;color:#9bb5aa;margin-bottom:12px}
        .modal{position:fixed;inset:0;background:rgba(2,31,22,.58);display:none;align-items:center;justify-content:center;padding:24px;z-index:9999}.modal:target{display:flex}.modal-card{width:min(1080px,96vw);max-height:92vh;overflow:auto;background:#fff;border-radius:24px;box-shadow:0 30px 90px rgba(0,0,0,.28)}.modal-head{position:sticky;top:0;background:#fff;z-index:1;padding:22px 26px;border-bottom:1px solid #eef5f1;display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.modal-head h2{margin:0;color:#07382a}.modal-head p{margin:5px 0 0;color:#64746f}.modal-body{padding:24px 26px}.stage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.stage-card{background:#fbfefc;border:1px solid #dfece6;border-radius:18px;padding:16px}.stage-card-title{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px}.stage-card-title h3{margin:0;font-size:17px;color:#07382a}.current-small{font-size:12px;color:#64746f;text-align:right}.assign-form{display:grid;gap:10px}.assign-form select,.assign-form textarea{width:100%;border:1px solid #d5e4dd;border-radius:12px;padding:11px 12px;font:inherit;background:#fff}.assign-form textarea{min-height:72px;resize:vertical}.btn-save{border:0;border-radius:12px;background:#145c43;color:#fff;font-weight:900;padding:11px 13px;cursor:pointer}.btn-save:hover{background:#0d4432}.history-section{margin-top:24px;border-top:1px solid #eef5f1;padding-top:20px}.history-section h3{margin:0 0 12px;color:#07382a}.history-list{display:grid;gap:10px}.history-item{border:1px solid #e3eee8;background:#fbfefc;border-radius:14px;padding:12px 14px;font-size:13px;color:#42564f}.history-item strong{color:#07382a}.history-note{margin-top:5px;color:#64746f}.close-x{border:1px solid #bdd9ca;background:#fff;color:#11623f;border-radius:12px;padding:9px 12px;font-weight:900;text-decoration:none}.close-x:hover{background:#eaf7ef}
        .status-update-form{border:1px solid #dfece6;background:#f8fcfa;border-radius:18px;padding:16px;margin-bottom:18px;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end}.status-update-form .form-group{display:grid;gap:7px}.status-update-form label{font-weight:900;color:#07382a}.status-update-form select{border:1px solid #d5e4dd;border-radius:12px;padding:11px 12px;font:inherit;background:#fff}
        .transport-mini{display:grid;gap:7px;min-width:190px}.transport-mini .pill{display:inline-flex;width:max-content;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:900;background:#e7f7ee;color:#0f6b45}.transport-mini .pill.air{background:#e0f2fe;color:#075985}.transport-mini .pill.notset{background:#f1f5f9;color:#475569}.transport-details{line-height:1.5;background:#f8fbf9;border:1px solid #e1ece6;border-radius:10px;padding:8px 10px;font-size:12px;color:#52665e}
        @media(max-width:760px){.main-content{padding:16px}.panel-head{padding:18px}.assign-table{min-width:760px}.modal{padding:10px}.modal-head,.modal-body{padding:18px}.stage-grid{grid-template-columns:1fr}}
    </style>
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Coordinator Assignments</h1>
            <small>Assign employees per booking stage without losing past assignment records.</small>
        </div>
        <div class="page-actions">
            <a href="active-shipments.php" class="btn-primary-soft"><i class="fa-solid fa-truck-ramp-box"></i> For Pick-up Bookings</a>
            <a href="booking-records.php" class="btn-outline"><i class="fa-solid fa-box-archive"></i> Booking Records</a>
        </div>
    </header>

    <main>

        <?php if ($canAssign && empty($employees)): ?>
            <div class="alert-success alert-error" style="background:#fff7ed;color:#9a3412;border-color:#fed7aa;">
                No coordinators found. Go to <b>Manage Employees</b>, edit an employee, and check <b>Set this employee as Coordinator</b>.
            </div>
        <?php endif; ?>

        <?php if (($_GET['status'] ?? '') === 'assigned'): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Coordinator assignment saved successfully.</div>
        <?php elseif (($_GET['status'] ?? '') === 'status_updated'): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Booking status updated successfully.</div>
        <?php elseif (isset($_GET['status'])): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Assignment failed. Please check the booking, employee, and stage.</div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-card"><div class="summary-icon"><i class="fa-solid fa-boxes-stacked"></i></div><div><h3><?= h($totalBookings) ?></h3><p>Bookings available</p></div></div>
            <div class="summary-card"><div class="summary-icon"><i class="fa-solid fa-user-check"></i></div><div><h3><?= h($totalAssignedStages) ?></h3><p>Assigned stages</p></div></div>
            <div class="summary-card"><div class="summary-icon"><i class="fa-solid fa-layer-group"></i></div><div><h3><?= h(count($stages)) ?></h3><p>Tracked stages</p></div></div>
        </div>

        <section class="assignment-panel">
            <div class="panel-head">
                <div>
                    <h2>Booking Assignment Records</h2>
                    <p>Use Manage to assign coordinators and update the booking status. Delivered/Shipped bookings move to Booking Records.</p>
                </div>
                <span class="status-badge status-default">History Enabled</span>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="empty-state"><i class="fa-regular fa-folder-open"></i><br>No bookings available for assignment.</div>
            <?php else: ?>
                <div class="assign-table-wrap">
                    <table class="assign-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Client / Route</th>
                                <th>Status</th>
                                <th>Transport / Courier</th>
                                <th>Current Coordinators</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                    $bookingId = (int)$booking['booking_ID'];
                                    $historyRows = $assignmentHistory[$bookingId] ?? [];
                                    $currentRows = $currentAssignments[$bookingId] ?? [];
                                ?>
                                <tr>
                                    <td>
                                        <div class="booking-title">#<?= h($bookingId) ?></div>
                                        <div class="meta-line">Request: <?= h($booking['booking_requestdate']) ?></div>
                                        <div class="meta-line">Heads: <?= h($booking['total_heads']) ?></div>
                                    </td>
                                    <td>
                                        <div><strong><?= h($booking['client_firstname'] . ' ' . $booking['client_lastname']) ?></strong></div>
                                        <div class="route-text"><?= h($booking['pickup_municipality'] . ' → ' . $booking['receiver_municipality']) ?></div>
                                    </td>
                                    <td><span class="status-badge <?= h(statusClass($booking['booking_status'])) ?>"><?= h($booking['booking_status']) ?></span></td>
                                    <td>
                                        <?php
                                            $type = strtoupper(trim((string)($booking['shipment_type'] ?? '')));
                                            $typeClass = $type === 'AIR' ? 'air' : ($type === 'LAND' ? '' : 'notset');
                                        ?>
                                        <div class="transport-mini">
                                            <span class="pill <?= h($typeClass) ?>"><i class="fa-solid fa-route"></i> <?= h(assignmentShipmentTypeLabel($booking)) ?></span>
                                            <div class="transport-details"><?= assignmentCourierDetails($booking) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="stage-chips">
                                            <?php foreach ($displayStages as $stage): ?>
                                                <?php $current = $currentRows[$stage] ?? null; ?>
                                                <span class="stage-chip <?= $current ? 'assigned' : '' ?>">
                                                    <strong><?= h(stageLabel($stage)) ?>:</strong>
                                                    <?= $current ? h($current['emp_firstname'] . ' ' . $current['emp_lastname']) : 'None' ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a class="btn-green" href="#assign-<?= h($bookingId) ?>"><i class="fa-solid fa-user-pen"></i> Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php foreach ($bookings as $booking): ?>
    <?php
        $bookingId = (int)$booking['booking_ID'];
        $historyRows = $assignmentHistory[$bookingId] ?? [];
        $currentRows = $currentAssignments[$bookingId] ?? [];
    ?>
    <div class="modal" id="assign-<?= h($bookingId) ?>">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <h2>Manage Booking #<?= h($bookingId) ?></h2>
                    <p><?= h($booking['client_firstname'] . ' ' . $booking['client_lastname']) ?> · <?= h($booking['pickup_municipality'] . ' → ' . $booking['receiver_municipality']) ?></p>
                </div>
                <a href="#" class="close-x"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>
            <div class="modal-body">
                <div class="transport-details" style="margin-bottom:18px;">
                    <strong>Travel Details:</strong> <?= h(assignmentShipmentTypeLabel($booking)) ?><br>
                    <?= assignmentCourierDetails($booking) ?>
                </div>

                <?php if ($canAssign): ?>
                    <form class="status-update-form" method="POST" action="process-assignment.php">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="booking_id" value="<?= h($bookingId) ?>">
                        <div class="form-group">
                            <label>Update Booking Status</label>
                            <select name="booking_status" required>
                                <?php
                                    $statusOptions = [
                                        'FOR PICK-UP' => 'For Pick Up',
                                        'PROCESSING' => 'Processing',
                                        'PREPARING FOR TRANSIT' => 'Preparing for Transit',
                                        'IN TRANSIT' => 'In-Transit',
                                        'DELIVERED/SHIPPED' => 'Delivered / Shipped'
                                    ];
                                ?>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= h($value) ?>" <?= $booking['booking_status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-save" type="submit"><i class="fa-solid fa-arrows-rotate"></i> Save Status</button>
                    </form>
                <?php endif; ?>

                <div class="stage-grid">
                    <?php foreach ($stages as $stage): ?>
                        <?php $current = $currentRows[$stage] ?? null; ?>
                        <div class="stage-card">
                            <div class="stage-card-title">
                                <h3><?= h(stageLabel($stage)) ?></h3>
                                <div class="current-small">
                                    Current:<br>
                                    <strong><?= $current ? h($current['emp_firstname'] . ' ' . $current['emp_lastname']) : 'Not assigned' ?></strong>
                                </div>
                            </div>
                            <?php if ($canAssign): ?>
                                <form class="assign-form" method="POST" action="process-assignment.php">
                                    <input type="hidden" name="booking_id" value="<?= h($bookingId) ?>">
                                    <input type="hidden" name="process_stage" value="<?= h($stage) ?>">
                                    <select name="emp_id" required>
                                        <option value="">Choose coordinator</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?= h($employee['emp_ID']) ?>" <?= $current && (int)$current['emp_ID'] === (int)$employee['emp_ID'] ? 'selected' : '' ?>>
                                                <?= h($employee['emp_firstname'] . ' ' . $employee['emp_lastname'] . ' — Coordinator') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="notes" placeholder="Optional notes for this assignment"></textarea>
                                    <button class="btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save <?= h(stageLabel($stage)) ?></button>
                                </form>
                            <?php else: ?>
                                <p class="muted">You can view assignments, but you do not have permission to update them.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="history-section">
                    <h3>Assignment History</h3>
                    <?php if (empty($historyRows)): ?>
                        <div class="history-item">No assignment history yet.</div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($historyRows as $row): ?>
                                <div class="history-item">
                                    <strong><?= h(stageLabel($row['process_stage'])) ?>:</strong>
                                    <?= h($row['emp_firstname'] . ' ' . $row['emp_lastname']) ?>
                                    <br>
                                    Assigned by <?= h(trim(($row['assigned_by_firstname'] ?? '') . ' ' . ($row['assigned_by_lastname'] ?? '')) ?: 'System') ?>
                                    · <?= h($row['assigned_at']) ?>
                                    <?php if (!empty($row['notes'])): ?>
                                        <div class="history-note">Note: <?= h($row['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</body>
</html>
