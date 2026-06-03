<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['shipments_view','shipments_update']);
require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

$db = (new Database())->getConnection();
$model = new Employee($db);
$activeShipments = $model->getActiveShipments();
$drivers = method_exists($model, 'getLandDrivers') ? $model->getLandDrivers() : [];
$airAgents = method_exists($model, 'getAirShipperAgents') ? $model->getAirShipperAgents() : [];
$vehicles = method_exists($model, 'getVehicles') ? $model->getVehicles() : [];

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function transportIdFromShipment($shipment) {
    if (!empty($shipment['transport_ID'])) {
        return (int)$shipment['transport_ID'];
    }

    $type = strtoupper(trim((string)($shipment['shipment_type'] ?? '')));
    if ($type === 'AIR') return 1;
    if ($type === 'LAND') return 2;
    return 0;
}

function transportLabel($shipment) {
    $id = transportIdFromShipment($shipment);
    if ($id === 1) return 'Air Travel';
    if ($id === 2) return 'Land Travel';
    return 'Not set';
}

function transportClass($shipment) {
    $id = transportIdFromShipment($shipment);
    if ($id === 1) return 'air';
    if ($id === 2) return 'land';
    return 'notset';
}

function statusClass($status) {
    $status = strtoupper(trim((string)$status));
    switch ($status) {
        case 'FOR PICK-UP':
            return 'status-pickup';
        case 'PROCESSING':
            return 'status-processing';
        case 'PREPARING FOR TRANSIT':
            return 'status-preparing';
        case 'IN TRANSIT':
            return 'status-transit';
        case 'DELIVERED/SHIPPED':
        case 'DELIVERED':
        case 'SHIPPED':
            return 'status-delivered';
        case 'CANCELLED':
            return 'status-cancelled';
        default:
            return 'status-default';
    }
}

function travelDetails($shipment) {
    $transportId = transportIdFromShipment($shipment);
    $items = [];

    if ($transportId === 1) {
        if (!empty($shipment['shipper_agent'])) {
            $items[] = '<strong>Shipper Agent:</strong> ' . h($shipment['shipper_agent']);
        }
        if (!empty($shipment['airline_name'])) {
            $items[] = '<strong>Airline:</strong> ' . h($shipment['airline_name']);
        }
        if (!empty($shipment['flight_reference_number'])) {
            $items[] = '<strong>Flight Ref:</strong> ' . h($shipment['flight_reference_number']);
        }
    } elseif ($transportId === 2) {
        $driverName = trim(($shipment['driver_firstname'] ?? '') . ' ' . ($shipment['driver_lastname'] ?? ''));
        if ($driverName !== '') {
            $items[] = '<strong>Driver:</strong> ' . h($driverName);
        }
        if (!empty($shipment['vehicle_type'])) {
            $items[] = '<strong>Vehicle:</strong> ' . h($shipment['vehicle_type']);
        }
        if (!empty($shipment['vehicle_plate_number'])) {
            $items[] = '<strong>Plate:</strong> ' . h($shipment['vehicle_plate_number']);
        }
        if (!empty($shipment['vehicle_license_permit'])) {
            $items[] = '<strong>Permit:</strong> ' . h($shipment['vehicle_license_permit']);
        }
    }

    if (!$items) {
        return '<span class="muted-text">Travel details not set</span>';
    }

    return implode('<br>', $items);
}

$statuses = [
    'FOR PICK-UP' => 'For Pick Up',
    'PROCESSING' => 'Processing',
    'PREPARING FOR TRANSIT' => 'Preparing for Transit',
    'IN TRANSIT' => 'In-Transit',
    'DELIVERED/SHIPPED' => 'Delivered / Shipped',
    'CANCELLED' => 'Cancelled'
];

$airlineOptions = [
    'Cebu Pacific',
    'Philippine Airlines'
];
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>For Pick-up Bookings</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .shipments-hero{background:linear-gradient(135deg,#083d2c,#157347);color:#fff;border-radius:24px;padding:26px;margin-bottom:22px;display:flex;justify-content:space-between;gap:18px;align-items:center;box-shadow:0 16px 36px rgba(8,61,44,.18)}
        .shipments-hero h1{margin:0;font-size:28px}.shipments-hero p{margin:7px 0 0;color:#dff8e8}.hero-chip{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);border-radius:16px;padding:12px 16px;font-weight:900;white-space:nowrap}
        .clean-panel{background:#fff;border:1px solid #e4eee9;border-radius:22px;box-shadow:0 12px 32px rgba(15,61,45,.07);overflow:hidden}
        .panel-head{padding:22px 24px;border-bottom:1px solid #eef5f1;display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}
        .panel-head h2{margin:0;color:#07382a;font-size:22px}.panel-head p{margin:6px 0 0;color:#60736c;font-size:14px}
        .ship-table-wrap{overflow-x:auto}.ship-table{width:100%;border-collapse:collapse;min-width:1050px}.ship-table th{background:#eef8f2;color:#07382a;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.04em;padding:14px 16px}.ship-table td{padding:16px;border-bottom:1px solid #eef5f1;vertical-align:middle}.ship-table tr:hover{background:#fbfefc}
        .booking-title{font-size:18px;font-weight:900;color:#082f24}.muted{color:#64746f;font-size:13px}.route-text{font-weight:800;color:#315247;font-size:13px;margin-top:4px}.meta-line{color:#64746f;font-size:12px;margin-top:6px}
        .transport-box{display:grid;gap:8px;font-size:13px;color:#52665e;min-width:230px}
        .transport-pill{display:inline-flex;width:max-content;align-items:center;gap:6px;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:900;background:#e7f7ee;color:#0f6b45}
        .transport-pill.air{background:#e0f2fe;color:#075985}.transport-pill.land{background:#dcfce7;color:#166534}.transport-pill.notset{background:#f1f5f9;color:#475569}
        .courier-details{line-height:1.55;background:#f8fbf9;border:1px solid #e1ece6;border-radius:12px;padding:9px 11px}
        .muted-text{color:#7a8b84;font-style:italic}
        .status-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:900;white-space:nowrap}
        .status-pickup{background:#dff6ff;color:#075985}.status-processing{background:#e6f4ea;color:#166534}.status-preparing{background:#ede9fe;color:#5b21b6}.status-transit{background:#dbeafe;color:#1d4ed8}.status-delivered{background:#dcfce7;color:#15803d}.status-cancelled{background:#fee2e2;color:#b91c1c}.status-default{background:#eef2f7;color:#475569}
        .btn-edit{border:0;border-radius:12px;background:#157347;color:#fff;font-weight:900;padding:10px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
        .btn-edit:hover{background:#0f5a38}
        .modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}
        .modal:target{display:flex}
        .modal-card{background:#fff;border-radius:22px;max-width:820px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 24px 80px rgba(0,0,0,.25)}
        .modal-head{padding:22px 26px;border-bottom:1px solid #eaf2ee;display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.modal-head h2{margin:0;color:#07382a}.modal-head p{margin:5px 0 0;color:#64746f}
        .modal-body{padding:24px 26px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.form-group{display:grid;gap:7px}.form-group label{font-weight:900;color:#07382a;font-size:14px}.form-group select,.form-group input{width:100%;border:1px solid #d5e4dd;border-radius:12px;padding:12px 13px;font:inherit;background:#fff}
        .full{grid-column:1/-1}.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}.close-x{border:1px solid #bdd9ca;background:#fff;color:#11623f;border-radius:12px;padding:10px 13px;font-weight:900;text-decoration:none}.btn-save{border:0;border-radius:12px;background:#145c43;color:#fff;font-weight:900;padding:11px 16px;cursor:pointer}.btn-save:hover{background:#0d4432}
        .detail-section{grid-column:1/-1;border:1px solid #e2eee7;border-radius:18px;padding:18px;background:#fbfefc}.detail-section h3{margin:0 0 12px;color:#07382a;font-size:17px;display:flex;gap:8px;align-items:center}
        .detail-section.air-box{background:#f0f9ff;border-color:#bae6fd}.detail-section.land-box{background:#f0fdf4;border-color:#bbf7d0}
        .empty-state{text-align:center;padding:42px;color:#60736c;font-weight:800}
        @media(max-width:760px){.shipments-hero{display:block}.hero-chip{margin-top:14px;display:inline-block}.main-content{padding:16px}.ship-table{min-width:860px}.form-grid{grid-template-columns:1fr}.modal-head,.modal-body{padding:18px}}
    </style>
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>For Pick-up Bookings</h1>
            <small>Set transportation mode and travel details for approved bookings.</small>
        </div>
    </header>

    <main>
        <section class="shipments-hero">
            <div>
                <h1>For Pick-up Booking Setup</h1>
                <p>Choose Air or Land travel, then fill the proper travel details. After saving, the booking moves to Coordinator Assignments.</p>
            </div>
            <div class="hero-chip"><i class="fa-solid fa-truck-fast"></i> <?= h(count($activeShipments)) ?> For Pick-up Bookings</div>
        </section>

        <?php if(isset($_GET['updated'])): ?>
            <?php if ($_GET['updated'] === '1'): ?>
                <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Shipment details updated successfully.</div>
            <?php else: ?>
                <div class="alert-success alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Shipment update failed. Please check transport mode, required travel details, and status.</div>
            <?php endif; ?>
        <?php endif; ?>

        <section class="clean-panel">
            <div class="panel-head">
                <div>
                    <h2>For Pick-up Booking Records</h2>
                    <p>Air requires shipper agent, airline, and flight reference number. Land requires driver and vehicle plate number.</p>
                </div>
                <?php if (hasAnyPermission(['bookings_assign','shipments_update'])): ?>
                    <a class="close-x" href="assignments.php"><i class="fa-solid fa-user-pen"></i> Coordinator Assignments</a>
                <?php endif; ?>
            </div>

            <?php if(empty($activeShipments)): ?>
                <div class="empty-state"><i class="fa-regular fa-folder-open"></i><br>No for pick-up bookings need shipment setup.</div>
            <?php else: ?>
                <div class="ship-table-wrap">
                    <table class="ship-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Client / Route</th>
                                <th>Delivery Date</th>
                                <th>Transport / Travel Details</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($activeShipments as $shipment): ?>
                                <?php $bookingId = (int)$shipment['booking_ID']; ?>
                                <tr>
                                    <td>
                                        <div class="booking-title">#<?= h($bookingId) ?></div>
                                        <div class="meta-line">Heads: <?= h($shipment['total_heads']) ?></div>
                                    </td>
                                    <td>
                                        <div><strong><?= h($shipment['client_firstname'].' '.$shipment['client_lastname']) ?></strong></div>
                                        <div class="route-text"><?= h($shipment['pickup_municipality'].' → '.$shipment['receiver_municipality']) ?></div>
                                        <div class="meta-line"><?= h(trim(($shipment['receiver_street'] ?? '') . ', ' . ($shipment['receiver_province'] ?? ''), ', ')) ?></div>
                                    </td>
                                    <td><?= h($shipment['booking_enddate'] ?: 'Not set') ?></td>
                                    <td>
                                        <div class="transport-box">
                                            <span class="transport-pill <?= h(transportClass($shipment)) ?>"><i class="fa-solid fa-route"></i> <?= h(transportLabel($shipment)) ?></span>
                                            <div class="courier-details"><?= travelDetails($shipment) ?></div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge <?= h(statusClass($shipment['booking_status'])) ?>"><?= h($shipment['booking_status']) ?></span></td>
                                    <td>
                                        <?php if (hasPermission('shipments_update')): ?>
                                            <a class="btn-edit" href="#edit-shipment-<?= h($bookingId) ?>"><i class="fa-solid fa-pen-to-square"></i> Set Transport</a>
                                        <?php else: ?>
                                            <span class="muted">View only</span>
                                        <?php endif; ?>
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

<?php foreach($activeShipments as $shipment): ?>
    <?php
        $bookingId = (int)$shipment['booking_ID'];
        $currentTransportId = transportIdFromShipment($shipment);
        if (!in_array($currentTransportId, [1,2], true)) $currentTransportId = 2;
    ?>
    <div class="modal" id="edit-shipment-<?= h($bookingId) ?>">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <h2>Set Transport for Booking #<?= h($bookingId) ?></h2>
                    <p><?= h($shipment['client_firstname'].' '.$shipment['client_lastname']) ?> · <?= h($shipment['pickup_municipality'].' → '.$shipment['receiver_municipality']) ?></p>
                </div>
                <a href="#" class="close-x"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>
            <div class="modal-body">
                <form method="POST" action="process-status-update.php">
                    <input type="hidden" name="booking_id" value="<?= h($bookingId) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Transportation Mode</label>
                            <select name="transport_id" class="transport-mode-select" data-booking="<?= h($bookingId) ?>" required>
                                <option value="1" <?= $currentTransportId === 1 ? 'selected' : '' ?>>Air Travel</option>
                                <option value="2" <?= $currentTransportId === 2 ? 'selected' : '' ?>>Land Travel</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Shipment Status</label>
                            <select name="booking_status" required>
                                <option value="FOR PICK-UP" selected>For Pick Up</option>
                            </select>
                        </div>

                        <section class="detail-section air-box travel-section" data-type="air" data-booking="<?= h($bookingId) ?>">
                            <h3><i class="fa-solid fa-plane"></i> Air Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Shipper Agent</label>
                                    <select name="air_emp_ID" required>
                                        <option value="">Select shipper agent</option>
                                        <?php foreach ($airAgents as $agent): ?>
                                            <option value="<?= h($agent['emp_ID']) ?>" <?= (int)($shipment['air_emp_ID'] ?? 0) === (int)$agent['emp_ID'] ? 'selected' : '' ?>>
                                                <?= h($agent['emp_firstname'].' '.$agent['emp_lastname'].' · '.$agent['role']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($airAgents)): ?>
                                        <small class="muted-text">No shipper agents found. Add employees with the Shipper Agent role first.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Airline</label>
                                    <select name="airline" required>
                                        <option value="">Select airline</option>
                                        <?php foreach ($airlineOptions as $airline): ?>
                                            <option value="<?= h($airline) ?>" <?= ($shipment['airline_name'] ?? '') === $airline ? 'selected' : '' ?>>
                                                <?= h($airline) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group full">
                                    <label>Flight Reference Number</label>
                                    <input type="text" name="flight_reference_number" value="<?= h($shipment['flight_reference_number'] ?? '') ?>" placeholder="Example: PR-1234 / AWB-00001" required>
                                </div>
                            </div>
                        </section>

                        <section class="detail-section land-box travel-section" data-type="land" data-booking="<?= h($bookingId) ?>">
                            <h3><i class="fa-solid fa-truck"></i> Land Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Driver</label>
                                    <select name="driver_emp_ID" required>
                                        <option value="">Select driver</option>
                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?= h($driver['emp_ID']) ?>" <?= (int)($shipment['driver_emp_ID'] ?? 0) === (int)$driver['emp_ID'] ? 'selected' : '' ?>>
                                                <?= h($driver['emp_firstname'].' '.$driver['emp_lastname'].' · '.$driver['role']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Vehicle Plate Number</label>
                                    <select name="vehicle_ID" required>
                                        <option value="">Select a vehicle plate number</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?= h($vehicle['vehicle_ID']) ?>" <?= (int)($shipment['vehicle_ID'] ?? 0) === (int)$vehicle['vehicle_ID'] ? 'selected' : '' ?>>
                                                <?= h(($vehicle['vehicle_plate_number'] ? $vehicle['vehicle_plate_number'] : 'Vehicle #'.$vehicle['vehicle_ID'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($vehicles)): ?>
                                        <small class="muted-text">No vehicles are currently available. Add vehicles to the vehicle table before saving land shipment details.</small>
                                    <?php else: ?>
                                        <small>Vehicle plate number is loaded from the vehicle table.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="form-actions">
                        <a href="#" class="close-x">Cancel</a>
                        <button class="btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Transport Setup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.querySelectorAll('.transport-mode-select').forEach(function(select) {
    function toggleSections() {
        const booking = select.dataset.booking;
        const selected = select.value === '1' ? 'air' : 'land';
        document.querySelectorAll('.travel-section[data-booking="' + booking + '"]').forEach(function(section) {
            section.style.display = section.dataset.type === selected ? 'block' : 'none';
            section.querySelectorAll('input, select').forEach(function(field) {
                if (section.dataset.type === selected) {
                    field.removeAttribute('disabled');
                } else {
                    field.setAttribute('disabled', 'disabled');
                }
            });
        });
    }

    select.addEventListener('change', toggleSections);
    toggleSections();
});
</script>
</body>
</html>
