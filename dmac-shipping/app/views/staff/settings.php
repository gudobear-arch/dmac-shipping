<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireEmployeeLogin();

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../helpers/logger.php';

$db = (new Database())->getConnection();
$empID = (int)($_SESSION['employee_id'] ?? 0);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectSettings($query) {
    header("Location: settings.php?" . $query);
    exit();
}

function settingsErrorMessage($error) {
    switch ($error) {
        case 'missing':
            return 'Please fill in all profile fields.';
        case 'email':
            return 'Please enter a valid email address.';
        case 'email_taken':
            return 'That email is already used by another employee.';
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

function saveProfileImage($file, $oldPath = null) {
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

    $newName = 'employee_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('upload_failed');
    }

    return 'public/uploads/profile/' . $newName;
}

$stmt = $db->prepare("SELECT * FROM employee WHERE emp_ID = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$empID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header("Location: logout.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'profile') {
            $firstname = trim($_POST['emp_firstname'] ?? '');
            $lastname = trim($_POST['emp_lastname'] ?? '');
            $contact = trim($_POST['emp_contact'] ?? '');
            $email = trim($_POST['emp_email'] ?? '');

            if ($firstname === '' || $lastname === '' || $contact === '' || $email === '') {
                redirectSettings('error=missing');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirectSettings('error=email');
            }

            $check = $db->prepare("SELECT COUNT(*) FROM employee WHERE emp_email = ? AND emp_ID <> ? AND deleted_at IS NULL");
            $check->execute([$email, $empID]);
            if ((int)$check->fetchColumn() > 0) {
                redirectSettings('error=email_taken');
            }

            $profileImage = saveProfileImage($_FILES['profile_image'] ?? null, $employee['profile_image'] ?? null);

            $update = $db->prepare("
                UPDATE employee
                SET emp_firstname = ?, emp_lastname = ?, emp_contact = ?, emp_email = ?, profile_image = ?
                WHERE emp_ID = ?
            ");
            $update->execute([$firstname, $lastname, $contact, $email, $profileImage, $empID]);

            $_SESSION['employee_name'] = $firstname . ' ' . $lastname;
            $_SESSION['employee_email'] = $email;

            logActivity($db, 'employee', $empID, 'Updated profile', 'Employee/Admin updated account settings.');

            redirectSettings('success=profile');
        }

        if ($action === 'password') {
            $oldPassword = trim($_POST['old_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
                redirectSettings('error=missing_password');
            }

            if (!password_verify($oldPassword, $employee['emp_password'])) {
                redirectSettings('error=old_password');
            }

            if (strlen($newPassword) < 8) {
                redirectSettings('error=weak_password');
            }

            if ($newPassword !== $confirmPassword) {
                redirectSettings('error=password_match');
            }

            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $update = $db->prepare("UPDATE employee SET emp_password = ?, force_password_change = 0 WHERE emp_ID = ?");
            $update->execute([$hashed, $empID]);

            logActivity($db, 'employee', $empID, 'Changed password', 'Employee/Admin changed account password.');

            redirectSettings('success=password');
        }

        redirectSettings('error=invalid');

    } catch (Exception $e) {
        error_log('Staff settings error: ' . $e->getMessage());
        redirectSettings('error=' . urlencode($e->getMessage()));
    }
}

$fullName = trim(($employee['emp_firstname'] ?? '') . ' ' . ($employee['emp_lastname'] ?? ''));
$profileImage = $employee['profile_image'] ?? '';
$profileImagePath = $profileImage ? '../../../' . $profileImage : '';
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

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
                <?php echo h(settingsErrorMessage($_GET['error'])); ?>
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
                        <small><?= h($employee['emp_email'] ?? '') ?></small>
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
                        <input type="text" name="emp_firstname" value="<?= h($employee['emp_firstname'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="emp_lastname" value="<?= h($employee['emp_lastname'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="emp_contact" value="<?= h($employee['emp_contact'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="emp_email" value="<?= h($employee['emp_email'] ?? '') ?>" required>
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
                    <p>End your current admin/employee session safely.</p>
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
