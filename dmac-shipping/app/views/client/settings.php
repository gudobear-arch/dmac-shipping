<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Client.php';
require_once __DIR__ . '/../../helpers/logger.php';

$db = (new Database())->getConnection();
$clientModel = new Client($db);
$clientID = (int)$_SESSION['client_id'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectClientSettings($query) {
    header("Location: settings.php?" . $query);
    exit();
}

function clientSettingsErrorMessage($error) {
    switch ($error) {
        case 'missing':
            return 'Please fill in all profile fields.';
        case 'email':
            return 'Please enter a valid email address.';
        case 'email_taken':
            return 'That email is already used by another client.';
        case 'missing_password':
            return 'Please fill in all password fields.';
        case 'old_password':
            return 'Old password is incorrect.';
        case 'weak_password':
            return 'New password must be at least 8 characters.';
        case 'password_match':
            return 'New password and confirm password do not match.';
        case 'invalid_image':
            return 'Only JPG, JPEG, and PNG profile pictures are allowed.';
        case 'image_too_large':
            return 'Profile picture must be 5MB or less.';
        default:
            return 'Something went wrong. Please try again.';
    }
}

function saveClientProfileImage($file, $oldPath = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('upload_error');
    }

    $allowed = ['jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        throw new Exception('invalid_image');
    }

    if ((int)$file['size'] > $maxSize) {
        throw new Exception('image_too_large');
    }

    $uploadDir = __DIR__ . '/../../../public/uploads/profile/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $newName = 'client_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('upload_failed');
    }

    return 'public/uploads/profile/' . $newName;
}

$client = $clientModel->findById($clientID);

if (!$client) {
    header("Location: logout.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'profile') {
            $firstname = trim($_POST['client_firstname'] ?? '');
            $lastname = trim($_POST['client_lastname'] ?? '');
            $contact = trim($_POST['client_contact'] ?? '');
            $email = trim($_POST['client_email'] ?? '');

            if ($firstname === '' || $lastname === '' || $contact === '' || $email === '') {
                redirectClientSettings('error=missing');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirectClientSettings('error=email');
            }

            $check = $db->prepare("SELECT COUNT(*) FROM client WHERE client_email = ? AND client_ID <> ? AND deleted_at IS NULL");
            $check->execute([$email, $clientID]);
            if ((int)$check->fetchColumn() > 0) {
                redirectClientSettings('error=email_taken');
            }

            $profileImage = saveClientProfileImage($_FILES['profile_image'] ?? null, $client['profile_image'] ?? null);

            $stmt = $db->prepare("
                UPDATE client
                SET client_firstname = ?, client_lastname = ?, client_contact = ?, client_email = ?, profile_image = ?
                WHERE client_ID = ?
            ");
            $stmt->execute([$firstname, $lastname, $contact, $email, $profileImage, $clientID]);

            $_SESSION['client_name'] = $firstname . ' ' . $lastname;
            $_SESSION['client_email'] = $email;

            logActivity($db, 'client', $clientID, 'Updated profile', 'Client updated account settings.');

            redirectClientSettings('success=profile');
        }

        if ($action === 'password') {
            $oldPassword = trim($_POST['old_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
                redirectClientSettings('error=missing_password');
            }

            if (!password_verify($oldPassword, $client['client_password'])) {
                redirectClientSettings('error=old_password');
            }

            if (strlen($newPassword) < 8) {
                redirectClientSettings('error=weak_password');
            }

            if ($newPassword !== $confirmPassword) {
                redirectClientSettings('error=password_match');
            }

            $clientModel->updatePassword($clientID, password_hash($newPassword, PASSWORD_BCRYPT));

            logActivity($db, 'client', $clientID, 'Changed password', 'Client changed account password.');

            redirectClientSettings('success=password');
        }

        redirectClientSettings('error=invalid');

    } catch (Exception $e) {
        error_log('Client settings error: ' . $e->getMessage());
        redirectClientSettings('error=' . urlencode($e->getMessage()));
    }
}

$client = $clientModel->findById($clientID);
$fullName = trim(($client['client_firstname'] ?? '') . ' ' . ($client['client_lastname'] ?? ''));
$profileImage = $client['profile_image'] ?? '';
$profileImagePath = $profileImage ? '../../../' . $profileImage : '';

function activeClient($file) {
    return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Client Settings</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
<div class="sidebar">
    <div class="sidebar-brand">
        <h2><i class="fa-solid fa-truck-fast"></i> DMAC Shipping</h2>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?= activeClient('dashboard.php') ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="booking-wizard.php" class="<?= activeClient('booking-wizard.php') ?>"><i class="fa-solid fa-square-plus"></i> Book a Shipment</a></li>
        <li><a href="my-shipments.php" class="<?= activeClient('my-shipments.php') ?>"><i class="fa-solid fa-boxes-stacked"></i> Active Orders</a></li>
        <li><a href="billing.php" class="<?= activeClient('billing.php') ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a></li>
        <li><a href="feedback.php" class="<?= activeClient('feedback.php') ?>"><i class="fa-solid fa-message"></i> Feedback</a></li>
        <li><a href="settings.php" class="<?= activeClient('settings.php') ?>"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
    </ul>
</div>

<div class="main-content">
    <main class="settings-page">
        <div class="settings-header">
            <div>
                <h1>Settings</h1>
                <p>Update your profile, password, and account session.</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">
                <?= $_GET['success'] === 'password' ? 'Password changed successfully.' : 'Profile updated successfully.' ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                <?php echo h(clientSettingsErrorMessage($_GET['error'])); ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <div class="settings-card">
                <h3>Profile Settings</h3>

                <div class="profile-preview">
                    <?php if ($profileImagePath): ?>
                        <img src="<?= h($profileImagePath) ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-avatar-fallback"><i class="fa-solid fa-circle-user"></i></div>
                    <?php endif; ?>

                    <div>
                        <strong><?= h($fullName) ?></strong>
                        <small><?= h($client['client_email'] ?? '') ?></small>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="profile">

                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <div class="form-hint">Allowed: JPG, JPEG, PNG. Max size: 5MB.</div>
                    </div>

                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="client_firstname" value="<?= h($client['client_firstname'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="client_lastname" value="<?= h($client['client_lastname'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="client_contact" value="<?= h($client['client_contact'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="client_email" value="<?= h($client['client_email'] ?? '') ?>" required>
                    </div>

                    <button type="submit" class="submit-btn"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
                </form>
            </div>

            <div class="settings-card">
                <h3>Change Password</h3>

                <form method="POST">
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label>Old Password</label>
                        <input type="password" name="old_password" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="8" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8" required>
                    </div>

                    <button type="submit" class="submit-btn"><i class="fa-solid fa-key"></i> Change Password</button>
                </form>
            </div>

            <div class="settings-card settings-logout-card">
                <div>
                    <h3>Logout Account</h3>
                    <p>End your current client session safely.</p>
                </div>

                <a href="logout.php" class="logout-settings-btn">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
