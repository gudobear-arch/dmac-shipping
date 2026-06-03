<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['shipments_view','bookings_view']);
require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

$db = (new Database())->getConnection();
$model = new Employee($db);
$records = $model->getBookingRecords();

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function recordTransportLabel($record) {
    $id = (int)($record['transport_ID'] ?? 0);
    $type = strtoupper(trim((string)($record['shipment_type'] ?? '')));
    if ($id === 1 || $type === 'AIR') return 'Air Travel';
    if ($id === 2 || $type === 'LAND') return 'Land Travel';
    return 'Not set';
}

function recordCourierDetails($record) {
    $id = (int)($record['transport_ID'] ?? 0);
    $type = strtoupper(trim((string)($record['shipment_type'] ?? '')));
    $items = [];

    if ($id === 1 || $type === 'AIR') {
        if (!empty($record['shipper_agent'])) $items[] = '<strong>Agent:</strong> ' . h($record['shipper_agent']);
        if (!empty($record['airline_name'])) $items[] = '<strong>Airline:</strong> ' . h($record['airline_name']);
        if (!empty($record['flight_reference_number'])) $items[] = '<strong>Flight Ref:</strong> ' . h($record['flight_reference_number']);
    } elseif ($id === 2 || $type === 'LAND') {
        $driver = trim(($record['driver_firstname'] ?? '') . ' ' . ($record['driver_lastname'] ?? ''));
        if ($driver !== '') $items[] = '<strong>Driver:</strong> ' . h($driver);
        if (!empty($record['vehicle_plate_number'])) $items[] = '<strong>Plate:</strong> ' . h($record['vehicle_plate_number']);
        if (!empty($record['vehicle_license_permit'])) $items[] = '<strong>Permit:</strong> ' . h($record['vehicle_license_permit']);
    }

    return $items ? implode('<br>', $items) : '<span class="muted">Courier details not set</span>';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Booking Records</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .records-hero{background:linear-gradient(135deg,#083d2c,#157347);color:#fff;border-radius:24px;padding:26px;margin-bottom:22px;box-shadow:0 16px 36px rgba(8,61,44,.18)}
        .records-hero h1{margin:0;font-size:28px}.records-hero p{margin:7px 0 0;color:#dff8e8}
        .records-panel{background:#fff;border:1px solid #e4eee9;border-radius:22px;box-shadow:0 12px 32px rgba(15,61,45,.07);overflow:hidden}
        .panel-head{padding:22px 24px;border-bottom:1px solid #eef5f1}.panel-head h2{margin:0;color:#07382a}.panel-head p{margin:6px 0 0;color:#60736c}
        .records-table-wrap{overflow-x:auto}.records-table{width:100%;border-collapse:collapse;min-width:1100px}.records-table th{background:#eef8f2;color:#07382a;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.04em;padding:14px 16px}.records-table td{padding:16px;border-bottom:1px solid #eef5f1;vertical-align:top}.records-table tr:hover{background:#fbfefc}
        .booking-title{font-size:18px;font-weight:900;color:#082f24}.muted{color:#64746f;font-size:13px}.route-text{font-weight:700;color:#315247;font-size:13px;margin-top:4px}.meta-line{color:#64746f;font-size:12px;margin-top:6px}
        .status-badge{display:inline-flex;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:900;text-transform:uppercase;background:#dcfce7;color:#166534}.transport-details{line-height:1.5;background:#f8fbf9;border:1px solid #e1ece6;border-radius:10px;padding:8px 10px;font-size:12px;color:#52665e}.empty-state{padding:46px;text-align:center;color:#64746f}.empty-state i{font-size:38px;color:#9bb5aa;margin-bottom:12px}
    </style>
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>
<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Booking Records</h1>
            <small>Completed and delivered/shipped bookings.</small>
        </div>
    </header>

    <main>
        <section class="records-hero">
            <h1>Delivered / Shipped Records</h1>
            <p>Bookings appear here after admin or coordinator updates the status to Delivered / Shipped.</p>
        </section>

        <section class="records-panel">
            <div class="panel-head">
                <h2>Completed Booking Records</h2>
                <p>Total records: <?= h(count($records)) ?></p>
            </div>

            <?php if (empty($records)): ?>
                <div class="empty-state"><i class="fa-regular fa-folder-open"></i><br>No delivered/shipped records yet.</div>
            <?php else: ?>
                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Client</th>
                                <th>Animal / Quantity</th>
                                <th>Pickup Address</th>
                                <th>Drop-off Address</th>
                                <th>Transport / Courier</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <div class="booking-title">#<?= h($record['booking_ID']) ?></div>
                                        <div class="meta-line">Request: <?= h($record['booking_requestdate']) ?></div>
                                    </td>
                                    <td><?= h($record['client_firstname'] . ' ' . $record['client_lastname']) ?></td>
                                    <td>
                                        <?= h($record['animal_details']) ?><br>
                                        <span class="muted">Total heads: <?= h($record['total_heads']) ?></span>
                                    </td>
                                    <td><?= h(trim(($record['pickup_street'] ?? '') . ', ' . ($record['pickup_municipality'] ?? '') . ', ' . ($record['pickup_province'] ?? ''), ', ')) ?></td>
                                    <td><?= h(trim(($record['receiver_street'] ?? '') . ', ' . ($record['receiver_municipality'] ?? '') . ', ' . ($record['receiver_province'] ?? ''), ', ')) ?></td>
                                    <td>
                                        <strong><?= h(recordTransportLabel($record)) ?></strong>
                                        <div class="transport-details"><?= recordCourierDetails($record) ?></div>
                                    </td>
                                    <td><?= h($record['booking_enddate'] ?: 'Not set') ?></td>
                                    <td><span class="status-badge"><?= h($record['booking_status']) ?></span></td>
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
