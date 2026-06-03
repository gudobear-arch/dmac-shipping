<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$errorMessages = [
    'missing' => 'Please complete all required fields before previewing or confirming your booking.',
    'date' => 'Requested ship date cannot be in the past.',
    'animal' => 'Please add at least one animal type with a valid quantity.',
    'agreement' => 'You must accept the Terms and Agreement and Insurance Policy before confirming.',
    'phone' => 'Please enter valid contact numbers.',
    'pickup_phone' => 'Please enter a valid pickup contact number.',
    'receiver_phone' => 'Please enter a valid receiver contact number.',
    'server' => 'Something went wrong while saving your booking. Please try again.'
];
$successMessages = [
    'saved' => 'Booking request submitted successfully.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Shipment - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .help-text{font-size:13px;color:#64748b;margin-top:6px}.terms-box{background:#f8fcfa;border:1px solid #e2e8f0;border-radius:16px;padding:16px;margin-bottom:14px}.check-row{display:flex;gap:10px;align-items:flex-start;margin-top:10px}.check-row input{width:auto;margin-top:4px}.check-row label{margin:0}.btn-disabled,.btn-disabled:hover{opacity:.55;cursor:not-allowed}.preview-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999}.preview-modal.show{display:flex}.preview-box{background:#fff;border-radius:24px;max-width:900px;width:100%;max-height:90vh;overflow:auto;padding:24px;box-shadow:0 25px 80px rgba(0,0,0,.25)}.preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}.preview-section{border:1px solid #e2e8f0;background:#f8fcfa;border-radius:16px;padding:16px}.preview-section h4{color:#0b3d2b;margin-bottom:10px}.preview-line{display:flex;justify-content:space-between;gap:12px;border-bottom:1px dashed #d7e5dc;padding:8px 0}.preview-line span:first-child{font-weight:700;color:#475569}.preview-line span:last-child{text-align:right;color:#0f172a}.modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:18px}.location-note{padding:10px 12px;border-radius:12px;background:#e8f6ee;color:#0b3d2b;font-size:13px;margin-bottom:14px}.input-error{border-color:#dc2626!important;outline:3px solid rgba(220,38,38,.12)!important}@media(max-width:700px){.preview-grid{grid-template-columns:1fr}.modal-actions{flex-direction:column}.preview-line{display:block}.preview-line span:last-child{text-align:left;display:block;margin-top:3px}}
    </style>
</head>
<body class="dashboard-body">

    <div class="sidebar">
        <div class="sidebar-brand">
            <h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="booking-wizard.php" class="active"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
            <li><a href="my-shipments.php"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
            <li><a href="billing.php"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <div class="header-title">
                <h1>Create Shipment Booking</h1>
                <small>Complete the details, review the preview, then confirm your booking.</small>
            </div>
        </header>

        <main>
            <?php if (isset($_GET['error']) && isset($errorMessages[$_GET['error']])): ?>
                <div class="alert-error" style="padding:12px 14px;border-radius:13px;margin-bottom:16px;font-weight:700;border:1px solid #fecaca;">
                    <?php echo htmlspecialchars($errorMessages[$_GET['error']]); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success']) && isset($successMessages[$_GET['success']])): ?>
                <div class="alert-success"><?php echo htmlspecialchars($successMessages[$_GET['success']]); ?></div>
            <?php endif; ?>

            <div class="wizard-progress">
                <div class="step active" id="step-indicator-1">
                    <span class="step-number">1</span>
                    <span class="step-label">Pickup Details</span>
                </div>
                <div class="step" id="step-indicator-2">
                    <span class="step-number">2</span>
                    <span class="step-label">Receiver Details</span>
                </div>
                <div class="step" id="step-indicator-3">
                    <span class="step-number">3</span>
                    <span class="step-label">Shipment Details</span>
                </div>
                <div class="step" id="step-indicator-4">
                    <span class="step-number">4</span>
                    <span class="step-label">Agreement & Preview</span>
                </div>
            </div>

            <div class="wizard-card">
                <form id="shippingWizardForm" action="process-booking.php" method="POST" novalidate>
                    <input type="hidden" name="booking_confirmed" id="booking_confirmed" value="0">

                    <div class="wizard-step step-visible" id="wizard-step-1">
                        <h3><i class="fa-solid fa-location-dot"></i> Pickup Details</h3>
                        <p class="step-subtitle">Input the contact person and pickup address.</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact First Name *</label>
                                <input type="text" name="pickup_firstname" data-preview="Pickup First Name" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Last Name *</label>
                                <input type="text" name="pickup_lastname" data-preview="Pickup Last Name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="tel" name="pickup_number" data-preview="Pickup Contact" required placeholder="09XXXXXXXXX" pattern="^(09|\+639)\d{9}$">
                            <div class="help-text">Example: 09123456789 or +639123456789</div>
                        </div>
                        <div class="form-group">
                            <label>Pickup Address *</label>
                            <input type="text" name="pickup_street" data-preview="Pickup Address" required placeholder="House no., street, barangay, landmark">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Pickup Municipality / City *</label>
                                <input type="text" name="pickup_municipality" data-preview="Pickup Municipality" required>
                            </div>
                            <div class="form-group">
                                <label>Pickup Province *</label>
                                <input type="text" name="pickup_province" data-preview="Pickup Province" required>
                            </div>
                        </div>

                        <div class="wizard-buttons">
                            <button type="button" class="btn-primary" onclick="changeStep(1, 2)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="wizard-step" id="wizard-step-2">
                        <h3><i class="fa-solid fa-user-location"></i> Receiver Details</h3>
                        <p class="step-subtitle">Input receiver information and choose the fixed drop-off location.</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Receiver First Name *</label>
                                <input type="text" name="receiver_firstname" data-preview="Receiver First Name" required>
                            </div>
                            <div class="form-group">
                                <label>Receiver Last Name *</label>
                                <input type="text" name="receiver_lastname" data-preview="Receiver Last Name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Receiver Contact Number *</label>
                            <input type="tel" name="receiver_contact" data-preview="Receiver Contact" required placeholder="09XXXXXXXXX" pattern="^(09|\+639)\d{9}$">
                        </div>
                        <div class="form-group">
                            <label>Receiver Address *</label>
                            <input type="text" name="receiver_street" data-preview="Receiver Address" required placeholder="House no., street, barangay, landmark">
                        </div>

                        <div class="location-note">
                            <i class="fa-solid fa-circle-info"></i> Choose the drop-off location from the approved municipality/province list.
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Drop-off Province *</label>
                                <select name="receiver_province" id="receiver_province" data-preview="Drop-off Province" required onchange="loadMunicipalities()">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Drop-off Municipality / City *</label>
                                <select name="receiver_municipality" id="receiver_municipality" data-preview="Drop-off Municipality" required>
                                    <option value="">Select Municipality</option>
                                </select>
                            </div>
                        </div>

                        <div class="wizard-buttons">
                            <button type="button" class="btn-secondary-outline" onclick="changeStep(2, 1)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn-primary" onclick="changeStep(2, 3)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="wizard-step" id="wizard-step-3">
                        <h3><i class="fa-solid fa-egg"></i> Other Shipment Details</h3>
                        <p class="step-subtitle">Input animal type, quantity, and requested ship date. Transportation mode will be set by admin or authorized staff after review.</p>

                        <div id="animalBatchContainer">
                            <div class="animal-batch-row">
                                <div class="form-group max-width-select">
                                    <label>Animal Type *</label>
                                    <select name="animal_types[]" class="form-control animal-type" required>
                                        <option value="">Select Animal</option>
                                        <option value="1">Gamefowl</option>
                                        <option value="2">Chicks</option>
                                        <option value="3">Dog</option>
                                    </select>
                                </div>
                                <div class="form-group max-width-input">
                                    <label>Quantity *</label>
                                    <input type="number" name="animal_quantities[]" min="1" value="1" class="form-control animal-qty" required>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-add-row" onclick="addAnimalRow()">
                            <i class="fa-solid fa-plus-circle"></i> Add Another Animal Type
                        </button>


                        <div class="form-group margin-top-lg">
                            <label>Request Ship Date *</label>
                            <input type="date" name="booking_requestdate" data-preview="Request Ship Date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="wizard-buttons">
                            <button type="button" class="btn-secondary-outline" onclick="changeStep(3, 2)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn-primary" onclick="changeStep(3, 4)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="wizard-step" id="wizard-step-4">
                        <h3><i class="fa-solid fa-file-signature"></i> Terms, Insurance & Preview</h3>
                        <p class="step-subtitle">You must check both agreements before previewing your booking.</p>

                        <div class="terms-box">
                            <h4>Terms and Agreement</h4>
                            <p class="help-text">I confirm that all shipment information is correct, the animals are ready for transport, and I agree to follow DMAC Shipping policies.</p>
                            <div class="check-row">
                                <input type="checkbox" name="terms_accepted" id="terms_accepted" value="1" onchange="togglePreviewButton()" required>
                                <label for="terms_accepted">I have read and agree to the Terms and Agreement.</label>
                            </div>
                        </div>

                        <div class="terms-box">
                            <h4>Insurance Policy</h4>
                            <p class="help-text">I understand the declared shipment details and agree to the insurance policy terms for livestock transport.</p>
                            <div class="check-row">
                                <input type="checkbox" name="insurance_accepted" id="insurance_accepted" value="1" onchange="togglePreviewButton()" required>
                                <label for="insurance_accepted">I have read and agree to the Insurance Policy.</label>
                            </div>
                        </div>

                        <div class="wizard-buttons">
                            <button type="button" class="btn-secondary-outline" onclick="changeStep(4, 3)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" id="previewBtn" class="btn-success-submit btn-disabled" onclick="showPreview()" disabled>
                                <i class="fa-solid fa-eye"></i> Preview Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <div class="preview-modal" id="previewModal" aria-hidden="true">
        <div class="preview-box">
            <div class="section-header">
                <div>
                    <h2>Preview Booking</h2>
                    <small>Please review all details before confirming.</small>
                </div>
                <button type="button" class="btn-secondary-outline mini" onclick="closePreview()"><i class="fa-solid fa-xmark"></i> Close</button>
            </div>
            <div class="preview-grid" id="previewContent"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary-outline" onclick="closePreview()">Edit Details</button>
                <button type="button" class="btn-success-submit" onclick="confirmBooking()"><i class="fa-solid fa-circle-check"></i> Confirm Booking</button>
            </div>
        </div>
    </div>

    <script>
        const dropOffLocations = {
            "Batangas": ["Batangas City", "Lipa City", "Tanauan City", "Santo Tomas", "Malvar", "Balete", "Taal", "Lemery", "San Jose", "Rosario"],
            "Laguna": ["Calamba", "Santa Rosa", "Biñan", "San Pedro", "Los Baños", "San Pablo", "Cabuyao"],
            "Cavite": ["Bacoor", "Dasmariñas", "Imus", "General Trias", "Tagaytay", "Silang", "Trece Martires"],
            "Quezon": ["Lucena", "Candelaria", "Sariaya", "Tiaong", "Pagbilao", "Tayabas"]
        };

        const animalNames = {"1": "Gamefowl", "2": "Chicks", "3": "Dog"};

        function initLocations() {
            const provinceSelect = document.getElementById('receiver_province');
            Object.keys(dropOffLocations).forEach(province => {
                const option = document.createElement('option');
                option.value = province;
                option.textContent = province;
                provinceSelect.appendChild(option);
            });
        }

        function loadMunicipalities() {
            const province = document.getElementById('receiver_province').value;
            const municipalitySelect = document.getElementById('receiver_municipality');
            municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
            (dropOffLocations[province] || []).forEach(municipality => {
                const option = document.createElement('option');
                option.value = municipality;
                option.textContent = municipality;
                municipalitySelect.appendChild(option);
            });
        }

        function changeStep(current, next) {
            if (next > current && !validateStep(current)) {
                alert("Please complete all required fields in this section before continuing.");
                return;
            }

            document.getElementById(`wizard-step-${current}`).classList.remove('step-visible');
            document.getElementById(`wizard-step-${next}`).classList.add('step-visible');
            document.getElementById(`step-indicator-${current}`).classList.remove('active');
            document.getElementById(`step-indicator-${next}`).classList.add('active');
        }

        function validateStep(stepNumber) {
            const currentStepEl = document.getElementById(`wizard-step-${stepNumber}`);
            const fields = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
            let valid = true;
            fields.forEach(field => {
                const fieldValid = field.type === 'checkbox' ? field.checked : field.value.trim() !== '';
                if (!fieldValid || (field.pattern && field.value && !(new RegExp(field.pattern).test(field.value)))) {
                    valid = false;
                    field.classList.add('input-error');
                } else {
                    field.classList.remove('input-error');
                }
            });
            return valid;
        }

        function addAnimalRow() {
            const container = document.getElementById('animalBatchContainer');
            const newRow = document.createElement('div');
            newRow.className = 'animal-batch-row row-animated';
            newRow.innerHTML = `
                <div class="form-group max-width-select">
                    <label>Animal Type *</label>
                    <select name="animal_types[]" class="form-control animal-type" required>
                        <option value="">Select Animal</option>
                        <option value="1">Gamefowl</option>
                        <option value="2">Chicks</option>
                        <option value="3">Dog</option>
                    </select>
                </div>
                <div class="form-group max-width-input">
                    <label>Quantity *</label>
                    <input type="number" name="animal_quantities[]" min="1" value="1" class="form-control animal-qty" required>
                </div>
                <button type="button" class="btn-remove-row" onclick="this.parentElement.remove(); togglePreviewButton();"><i class="fa-solid fa-trash-can"></i></button>
            `;
            container.appendChild(newRow);
        }

        function togglePreviewButton() {
            const previewBtn = document.getElementById('previewBtn');
            const termsAccepted = document.getElementById('terms_accepted').checked;
            const insuranceAccepted = document.getElementById('insurance_accepted').checked;
            const enabled = termsAccepted && insuranceAccepted;
            previewBtn.disabled = !enabled;
            previewBtn.classList.toggle('btn-disabled', !enabled);
        }

        function valueOf(name) {
            const field = document.querySelector(`[name="${name}"]`);
            return field ? field.value.trim() : '';
        }

        function makeLine(label, value) {
            return `<div class="preview-line"><span>${label}</span><span>${value || '-'}</span></div>`;
        }

        function getAnimalPreview() {
            const types = document.querySelectorAll('.animal-type');
            const qtys = document.querySelectorAll('.animal-qty');
            let lines = '';
            types.forEach((type, index) => {
                if (type.value) {
                    lines += makeLine(animalNames[type.value] || type.value, qtys[index]?.value || '1');
                }
            });
            return lines || makeLine('Animals', '-');
        }

        function showPreview() {
            if (!validateStep(1) || !validateStep(2) || !validateStep(3) || !validateStep(4)) {
                alert('Please complete all required fields and agreements before previewing.');
                return;
            }

            const previewContent = document.getElementById('previewContent');
            previewContent.innerHTML = `
                <div class="preview-section">
                    <h4>Pickup Details</h4>
                    ${makeLine('Contact Name', `${valueOf('pickup_firstname')} ${valueOf('pickup_lastname')}`)}
                    ${makeLine('Contact Number', valueOf('pickup_number'))}
                    ${makeLine('Pickup Address', valueOf('pickup_street'))}
                    ${makeLine('Pickup Location', `${valueOf('pickup_municipality')}, ${valueOf('pickup_province')}`)}
                </div>
                <div class="preview-section">
                    <h4>Receiver Details</h4>
                    ${makeLine('Receiver Name', `${valueOf('receiver_firstname')} ${valueOf('receiver_lastname')}`)}
                    ${makeLine('Receiver Contact', valueOf('receiver_contact'))}
                    ${makeLine('Receiver Address', valueOf('receiver_street'))}
                    ${makeLine('Drop-off Location', `${valueOf('receiver_municipality')}, ${valueOf('receiver_province')}`)}
                </div>
                <div class="preview-section">
                    <h4>Animal Details</h4>
                    ${getAnimalPreview()}
                    ${makeLine('Request Ship Date', valueOf('booking_requestdate'))}
                </div>
                <div class="preview-section">
                    <h4>Agreement</h4>
                    ${makeLine('Terms and Agreement', 'Accepted')}
                    ${makeLine('Insurance Policy', 'Accepted')}
                    ${makeLine('Initial Status', 'PENDING REVIEW')}
                </div>
            `;
            document.getElementById('previewModal').classList.add('show');
            document.getElementById('previewModal').setAttribute('aria-hidden', 'false');
        }

        function closePreview() {
            document.getElementById('previewModal').classList.remove('show');
            document.getElementById('previewModal').setAttribute('aria-hidden', 'true');
        }

        function confirmBooking() {
            document.getElementById('booking_confirmed').value = '1';
            document.getElementById('shippingWizardForm').submit();
        }

        initLocations();
        togglePreviewButton();
    </script>
</body>
</html>
