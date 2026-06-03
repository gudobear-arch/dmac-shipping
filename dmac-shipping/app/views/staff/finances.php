<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['accounting_view','accounting_manage']);
require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once '../../../config/database.php';
require_once '../../models/Payment.php';

$db = (new Database())->getConnection();
$paymentModel = new Payment($db);
$summary = $paymentModel->getSummary();
$records = $paymentModel->getAllBillingRecords();
$methods = $paymentModel->getPaymentMethods();
$canManage = hasPermission('accounting_manage');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n) { return '₱' . number_format((float)$n, 2); }

function statusBadge($status) {
    $status = strtoupper((string)$status);
    if ($status === 'PAID') return 'badge-paid';
    if ($status === 'OVERDUE') return 'badge-overdue';
    if ($status === 'NOT SET') return 'badge-not-set';
    return 'badge-pending';
}

function paymentStatusLabel($record) {
    if (empty($record['payment_ID'])) return 'NOT SET';
    return strtoupper((string)($record['payment_status'] ?? ((int)($record['is_paid'] ?? 0) === 1 ? 'PAID' : 'PENDING')));
}

function normalizedMethod($name) {
    $name = strtoupper(trim((string)$name));
    return strpos($name, 'ONLINE') !== false ? 'ONLINE' : $name;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Billing / Accounting</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .billing-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px}
        .billing-header h1{margin:0 0 6px;font-size:28px;color:#002f22}
        .billing-header p{margin:0;color:#506174}
        .billing-badge{display:inline-flex;align-items:center;gap:8px;background:#dcfce7;color:#006b43;border:1px solid #86efac;padding:8px 12px;border-radius:999px;font-weight:800;font-size:13px}
        .alert{padding:13px 16px;border-radius:12px;margin:0 0 16px;font-weight:700}
        .alert-success{background:#dcfce7;color:#166534;border:1px solid #86efac}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
        .finance-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:16px;margin-bottom:20px}
        .finance-card{background:#fff;border:1px solid #e4ece8;border-radius:18px;padding:20px;box-shadow:0 12px 30px rgba(0,40,25,.08);display:flex;align-items:center;gap:14px}
        .finance-icon{width:48px;height:48px;border-radius:14px;background:#d9ffe7;color:#087a4d;display:grid;place-items:center;font-size:20px}
        .finance-card strong{display:block;font-size:26px;color:#003f2f;line-height:1}
        .finance-card span{display:block;margin-top:8px;color:#5a6b7d;font-weight:700}
        .panel{background:#fff;border:1px solid #e3ece7;border-radius:20px;padding:22px;box-shadow:0 14px 35px rgba(0,40,25,.08)}
        .panel-title{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:16px}
        .panel-title h2{margin:0;color:#003b2c;font-size:20px}
        .panel-title p{margin:5px 0 0;color:#5b6f7f}
        .table-wrap{overflow-x:auto;border-radius:16px;border:1px solid #e2ede7}
        .billing-table{width:100%;border-collapse:collapse;min-width:980px;background:#fff}
        .billing-table th{background:#e9f6ef;color:#004632;text-transform:uppercase;font-size:12px;letter-spacing:.02em;text-align:left;padding:14px}
        .billing-table td{padding:16px 14px;border-top:1px solid #edf3f0;vertical-align:middle}
        .booking-no{font-weight:900;color:#003b2c;font-size:18px}
        .mini{font-size:12px;color:#66788a;margin-top:4px}
        .ship-pill{display:inline-flex;align-items:center;gap:6px;background:#e3fcec;color:#006b43;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:900}
        .amount-text{font-weight:900;color:#003b2c;font-size:17px}
        .status-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:900;text-transform:uppercase}
        .badge-paid{background:#dcfce7;color:#166534}
        .badge-pending{background:#fef9c3;color:#854d0e}
        .badge-overdue{background:#fee2e2;color:#991b1b}
        .badge-not-set{background:#e5e7eb;color:#374151}
        .btn-edit{border:0;background:#087f4f;color:#fff;border-radius:12px;padding:10px 14px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
        .btn-edit:hover{background:#06643f}
        .btn-secondary{border:1px solid #cbded3;background:#fff;color:#064e3b;border-radius:12px;padding:10px 14px;font-weight:900;cursor:pointer}
        .btn-save{border:0;background:#087f4f;color:#fff;border-radius:12px;padding:12px 16px;font-weight:900;cursor:pointer;width:100%}
        .btn-save:hover{background:#06643f}
        .empty-state{text-align:center;padding:42px;color:#607080}
        .modal-backdrop{position:fixed;inset:0;background:rgba(0,25,15,.55);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
        .modal-backdrop.show{display:flex}
        .billing-modal{width:min(780px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:22px;box-shadow:0 30px 90px rgba(0,0,0,.25);padding:24px}
        .modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px}
        .modal-head h2{margin:0;color:#003b2c;font-size:24px}
        .modal-head p{margin:5px 0 0;color:#5d6f7e}
        .close-btn{border:1px solid #cbded3;background:#fff;color:#064e3b;border-radius:999px;width:38px;height:38px;font-size:18px;cursor:pointer}
        .billing-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .billing-form label{font-weight:900;color:#003b2c}
        .billing-form input,.billing-form select{width:100%;margin-top:6px;padding:12px;border:1px solid #cbded3;border-radius:12px;font-weight:700;color:#102033;background:#fff}
        .form-full{grid-column:1/-1}
        .total-box{grid-column:1/-1;background:#e9f6ef;border:1px dashed #8fd3ad;border-radius:14px;padding:14px;display:flex;justify-content:space-between;align-items:center;gap:14px;font-weight:900;color:#003b2c}
        .total-box strong{font-size:22px}
        .air-note{grid-column:1/-1;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:12px;padding:10px 12px;font-weight:800}
        .modal-actions{grid-column:1/-1;display:grid;grid-template-columns:140px 1fr;gap:10px;margin-top:6px}
        @media(max-width:1000px){.finance-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:700px){.billing-header,.panel-title{flex-direction:column}.finance-grid{grid-template-columns:1fr}.billing-form{grid-template-columns:1fr}.modal-actions{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php showStaffSidebar('finances.php'); ?>

    <main class="main-content">
        <div class="billing-header">
            <div>
                <h1>Billing / Accounting</h1>
                <p>Set the client billing breakdown here. Clients only see total amount, method, and payment status.</p>
            </div>
            <div class="billing-badge"><i class="fa-solid fa-calculator"></i> Auto-calculated Billing</div>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Billing information updated successfully.</div>
        <?php elseif (isset($_GET['air_online'])): ?>
            <div class="alert alert-error">Air travel requires Online Payment only.</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-error">Unable to save billing information. Please check the values and try again.</div>
        <?php endif; ?>

        <div class="finance-grid">
            <div class="finance-card">
                <div class="finance-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div><strong><?= h(money($summary['paid'] ?? 0)) ?> / <?= h(money($summary['total'] ?? 0)) ?></strong><span>Paid / Total Gross</span></div>
            </div>
            <div class="finance-card">
                <div class="finance-icon"><i class="fa-solid fa-clock"></i></div>
                <div><strong><?= h(money($summary['unpaid'] ?? 0)) ?></strong><span>Pending Amount</span></div>
            </div>
            <div class="finance-card">
                <div class="finance-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><strong><?= h(money($summary['overdue'] ?? 0)) ?></strong><span>Overdue Amount</span></div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-title">
                <div>
                    <h2>Booking Billing Records</h2>
                    <p>Use the action button to set or edit billing for each booking.</p>
                </div>
                <?php if (!$canManage): ?>
                    <span class="billing-badge"><i class="fa-solid fa-eye"></i> View Only</span>
                <?php endif; ?>
            </div>

            <div class="table-wrap">
                <table class="billing-table">
                    <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Client / Drop-off</th>
                        <th>Shipment</th>
                        <th>Total Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <?php if ($canManage): ?><th>Action</th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="<?= $canManage ? 7 : 6 ?>" class="empty-state">No booking records found yet.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($records as $record): ?>
                        <?php
                            $shipmentType = strtoupper((string)($record['shipment_type'] ?? 'NOT SET'));
                            if ($shipmentType === '') $shipmentType = 'NOT SET';
                            $heads = (int)($record['number_of_heads'] ?? 0);
                            if ($heads <= 0) $heads = (int)($record['total_animals'] ?? 0);
                            $status = paymentStatusLabel($record);
                            $totalAmount = (float)($record['total_amount'] ?? $record['pay_amount'] ?? 0);
                            $clientName = trim(($record['client_firstname'] ?? '') . ' ' . ($record['client_lastname'] ?? ''));
                            $dropOff = trim(($record['receiver_municipality'] ?? '') . ', ' . ($record['receiver_province'] ?? ''), ', ');
                            $payMethod = $record['pay_method'] ?: 'Not set';
                            $modalData = [
                                'bookingId' => (int)$record['booking_ID'],
                                'clientName' => $clientName,
                                'shipmentType' => $shipmentType,
                                'boxFee' => (float)($record['box_fee'] ?? 0),
                                'pickupFee' => (float)($record['pickup_fee'] ?? 0),
                                'shippingFee' => (float)($record['shipping_fee'] ?? 0),
                                'headPrice' => (float)($record['head_price'] ?? 0),
                                'numberOfHeads' => $heads,
                                'paymethodId' => (int)($record['paymethod_ID'] ?? 0),
                                'paymentStatus' => $status === 'NOT SET' ? 'PENDING' : $status,
                                'paymentReference' => (string)($record['payment_reference'] ?? ''),
                                'totalAmount' => $totalAmount,
                            ];
                        ?>
                        <tr>
                            <td>
                                <div class="booking-no">#<?= h($record['booking_ID']) ?></div>
                                <div class="mini"><?= h($record['booking_status'] ?? '') ?></div>
                            </td>
                            <td>
                                <strong><?= h($clientName ?: 'Client') ?></strong>
                                <div class="mini"><?= h($dropOff ?: 'No drop-off set') ?></div>
                            </td>
                            <td>
                                <span class="ship-pill"><i class="fa-solid <?= $shipmentType === 'AIR' ? 'fa-plane' : ($shipmentType === 'LAND' ? 'fa-truck' : 'fa-circle-question') ?>"></i> <?= h($shipmentType === 'NOT SET' ? 'NOT SET' : $shipmentType) ?></span>
                                <div class="mini"><?= h($heads) ?> head(s)</div>
                            </td>
                            <td><span class="amount-text"><?= h(money($totalAmount)) ?></span></td>
                            <td><?= h($payMethod) ?></td>
                            <td><span class="status-badge <?= h(statusBadge($status)) ?>"><?= h($status) ?></span></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <button
                                        type="button"
                                        class="btn-edit"
                                        onclick='openBillingModal(<?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <?= empty($record['payment_ID']) ? 'Set Billing' : 'Edit Billing' ?>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php if ($canManage): ?>
<div id="billingModal" class="modal-backdrop" onclick="closeBillingModal(event)">
    <div class="billing-modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div>
                <h2 id="modalTitle">Edit Billing</h2>
                <p id="modalSubtitle">Set the breakdown. The client only sees the total.</p>
            </div>
            <button type="button" class="close-btn" onclick="hideBillingModal()">&times;</button>
        </div>

        <form class="billing-form" method="POST" action="process-payment.php" oninput="calcBillingTotal(this)">
            <input type="hidden" name="booking_id" id="booking_id">

            <label class="form-full">Transportation Mode
                <select name="shipment_type" id="shipment_type" required onchange="setMethodRules(this.value, document.getElementById('paymethod_id').value)">
                    <option value="">Select transportation mode</option>
                    <option value="LAND">Land Transport</option>
                    <option value="AIR">Air Transport</option>
                </select>
            </label>

            <label>Box Fee
                <input type="number" name="box_fee" id="box_fee" min="0" step="0.01" required>
            </label>

            <label>Pickup Fee
                <input type="number" name="pickup_fee" id="pickup_fee" min="0" step="0.01" required>
            </label>

            <label>Shipping Fee
                <input type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" required>
            </label>

            <label>Head Price
                <input type="number" name="head_price" id="head_price" min="0" step="0.01" required>
            </label>

            <label>No. of Heads
                <input type="number" name="number_of_heads" id="number_of_heads" min="0" step="1" required>
            </label>

            <label>Payment Method
                <select name="paymethod_id" id="paymethod_id" required>
                    <option value="">Select method</option>
                    <?php foreach ($methods as $method): ?>
                        <?php $normalized = normalizedMethod($method['pay_method']); ?>
                        <option value="<?= h($method['paymethod_ID']) ?>" data-method="<?= h($normalized) ?>">
                            <?= h($method['pay_method']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Payment Status
                <select name="payment_status" id="payment_status" required>
                    <option value="PENDING">Pending</option>
                    <option value="PAID">Paid</option>
                    <option value="OVERDUE">Overdue</option>
                </select>
            </label>

            <label class="form-full">Reference / Note
                <input type="text" name="payment_reference" id="payment_reference" placeholder="Online payment reference or Cash on Delivery note">
            </label>

            <div id="airNote" class="air-note" style="display:none;">
                <i class="fa-solid fa-plane"></i> Air travel requires Online Payment only.
            </div>

            <div class="total-box">
                <span>Calculated Total</span>
                <strong id="totalPreview">₱0.00</strong>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="hideBillingModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Billing</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function peso(value) {
    const amount = Number(value) || 0;
    return '₱' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function calcBillingTotal(form) {
    const box = parseFloat(form.box_fee.value) || 0;
    const pickup = parseFloat(form.pickup_fee.value) || 0;
    const shipping = parseFloat(form.shipping_fee.value) || 0;
    const headPrice = parseFloat(form.head_price.value) || 0;
    const heads = parseInt(form.number_of_heads.value) || 0;
    const total = box + pickup + shipping + (heads * headPrice);
    const preview = document.getElementById('totalPreview');
    if (preview) preview.textContent = peso(total);
}

function hideBillingModal() {
    const modal = document.getElementById('billingModal');
    if (modal) modal.classList.remove('show');
}

function closeBillingModal(event) {
    if (event.target && event.target.id === 'billingModal') {
        hideBillingModal();
    }
}

function setMethodRules(shipmentType, selectedMethodId) {
    const select = document.getElementById('paymethod_id');
    const airNote = document.getElementById('airNote');
    if (!select) return;

    shipmentType = (shipmentType || '').toUpperCase();
    let firstOnline = null;

    Array.from(select.options).forEach(option => {
        if (!option.value) return;
        const method = (option.dataset.method || '').toUpperCase();
        const isOnline = method.includes('ONLINE');
        if (isOnline && !firstOnline) firstOnline = option.value;
        option.disabled = shipmentType === 'AIR' && !isOnline;
    });

    if (shipmentType === 'AIR') {
        if (airNote) {
            airNote.style.display = 'block';
            airNote.innerHTML = '<i class="fa-solid fa-plane"></i> Air travel requires Online Payment only.';
        }
        const current = select.options[select.selectedIndex];
        const currentIsOnline = current && (current.dataset.method || '').toUpperCase().includes('ONLINE');
        if (!selectedMethodId || !currentIsOnline) {
            select.value = firstOnline || '';
        }
    } else if (shipmentType === 'LAND') {
        if (airNote) {
            airNote.style.display = 'block';
            airNote.innerHTML = '<i class="fa-solid fa-truck"></i> Land transport allows Cash on Delivery or Online Payment.';
        }
        if (selectedMethodId) select.value = String(selectedMethodId);
    } else {
        if (airNote) {
            airNote.style.display = 'block';
            airNote.innerHTML = '<i class="fa-solid fa-circle-info"></i> Select Land or Air before saving billing.';
        }
        select.value = selectedMethodId || '';
    }
}

function openBillingModal(data) {
    const modal = document.getElementById('billingModal');
    if (!modal) return;

    document.getElementById('modalTitle').textContent = 'Billing Setup for Booking #' + data.bookingId;
    document.getElementById('modalSubtitle').textContent = (data.clientName || 'Client') + ' • Transportation mode set by admin/staff';

    document.getElementById('booking_id').value = data.bookingId || '';
    document.getElementById('shipment_type').value = (data.shipmentType && data.shipmentType !== 'NOT SET') ? data.shipmentType : '';
    document.getElementById('box_fee').value = Number(data.boxFee || 0).toFixed(2);
    document.getElementById('pickup_fee').value = Number(data.pickupFee || 0).toFixed(2);
    document.getElementById('shipping_fee').value = Number(data.shippingFee || 0).toFixed(2);
    document.getElementById('head_price').value = Number(data.headPrice || 0).toFixed(2);
    document.getElementById('number_of_heads').value = parseInt(data.numberOfHeads || 0);
    document.getElementById('payment_status').value = data.paymentStatus || 'PENDING';
    document.getElementById('payment_reference').value = data.paymentReference || '';

    const methodSelect = document.getElementById('paymethod_id');
    methodSelect.value = data.paymethodId ? String(data.paymethodId) : '';

    setMethodRules((data.shipmentType && data.shipmentType !== 'NOT SET') ? data.shipmentType : '', data.paymethodId ? String(data.paymethodId) : '');
    calcBillingTotal(document.querySelector('.billing-form'));

    modal.classList.add('show');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') hideBillingModal();
});
</script>
</body>
</html>
