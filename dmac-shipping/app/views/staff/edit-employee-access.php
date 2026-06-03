<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['employees_edit','employees_permissions']);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../helpers/staff_nav.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

$empId = (int)($_GET['id'] ?? $_GET['emp_ID'] ?? 0);
$employee = $employeeModel->getEmployeeById($empId);

if (!$employee || (int)$employee['is_super_admin'] === 1) {
    header('Location: manage-employees.php?error=notfound');
    exit();
}

$departments = $employeeModel->getDepartments();
$roles = $employeeModel->getRoles();
$selectedRoles = $employeeModel->getRoleIdsForEmployee($empId);
$permissions = $employeeModel->getPermissions();
$selectedPermissions = $employeeModel->getPermissionIdsForEmployee($empId);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isChecked($needle, $haystack) {
    return in_array((int)$needle, array_map('intval', $haystack), true) ? 'checked' : '';
}

function isSelected($value, $selected) {
    return (string)$value === (string)$selected ? 'selected' : '';
}

function groupPermissionsByCategory($permissions) {
    $groups = [];
    foreach ($permissions as $permission) {
        $group = $permission['permission_group'] ?? 'General';
        if ($group === '' || strtolower($group) === 'system') {
            $group = 'General';
        }
        $groups[$group][] = $permission;
    }
    return $groups;
}

$permissionGroups = groupPermissionsByCategory($permissions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Edit Employee Access - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/employee-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <main class="employees-page">
        <div class="employees-header">
            <div class="employees-title">
                <h1>Edit Employee Access</h1>
                <p>Update profile, roles/positions, account status, and page access.</p>
            </div>
            <a href="manage-employees.php" class="top-action"><i class="fa-solid fa-arrow-left"></i> Back to Employees</a>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">Please complete all required fields.</div>
        <?php endif; ?>

        <form action="process-update-employee-access.php" method="POST" class="access-form">
            <input type="hidden" name="emp_id" value="<?= h($employee['emp_ID']) ?>">

            <div class="employee-form-grid">
                <section class="employee-form-card">
                    <h3><i class="fa-solid fa-id-card"></i> Employee Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="emp_firstname" required value="<?= h($employee['emp_firstname']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="emp_lastname" required value="<?= h($employee['emp_lastname']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= h($employee['emp_email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="text" name="emp_contact" required value="<?= h($employee['emp_contact']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="dept_id" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= h($dept['dept_ID']) ?>" <?= isSelected($dept['dept_ID'], $employee['dept_ID']) ?>><?= h($dept['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Account Status *</label>
                            <select name="account_status" required>
                                <option value="approved" <?= isSelected('approved', $employee['account_status']) ?>>Active - can login</option>
                                <option value="rejected" <?= isSelected('rejected', $employee['account_status']) ?>>Disabled - cannot login</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="employee-form-card">
                    <h3><i class="fa-solid fa-user-tag"></i> Roles / Positions</h3>
                    <p class="helper-text">Select one or more roles. Example: Admin + Coordinator + IT.</p>

                    <div class="role-checkbox-grid">
                        <?php foreach ($roles as $role): ?>
                            <label class="role-check-card">
                                <input type="checkbox" name="roles[]" value="<?= h($role['role_ID']) ?>" <?= isChecked($role['role_ID'], $selectedRoles) ?>>
                                <span><?= h($role['role']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="employee-form-card">
                <div class="section-header-flat">
                    <div>
                        <h3><i class="fa-solid fa-key"></i> Assigned Access</h3>
                        <p class="helper-text">Checked items are the pages/features this employee can access.</p>
                    </div>
                    <div class="permission-tools">
                        <button type="button" onclick="togglePermissions(true)">Select All</button>
                        <button type="button" onclick="togglePermissions(false)">Clear All</button>
                        <button type="button" onclick="selectOperationalAccess()">Operations Access</button>
                    </div>
                </div>

                <?php foreach ($permissionGroups as $groupName => $groupPermissions): ?>
                    <div class="permission-group-block">
                        <div class="permission-group-title">
                            <strong><?= h($groupName) ?></strong>
                            <button type="button" onclick="togglePermissionGroup(this, true)">Select group</button>
                        </div>
                        <div class="permission-grid">
                            <?php foreach ($groupPermissions as $permission): ?>
                                <label class="permission-card">
                                    <input type="checkbox" name="permissions[]" value="<?= h($permission['permission_ID']) ?>" data-key="<?= h($permission['permission_key']) ?>" <?= isChecked($permission['permission_ID'], $selectedPermissions) ?>>
                                    <div>
                                        <strong><?= h($permission['permission_name']) ?></strong>
                                        <small><?= h($permission['permission_key']) ?></small>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="form-actions-right">
                <a href="manage-employees.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Employee Access</button>
            </div>
        </form>
    </main>
</div>

<script>
function togglePermissions(value) {
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = value);
}
function togglePermissionGroup(button, value) {
    const group = button.closest('.permission-group-block');
    group.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = value);
}
function selectOperationalAccess() {
    const allowed = ['dashboard_view','bookings_view','bookings_approve','bookings_assign','shipments_view','shipments_update'];
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = allowed.includes(cb.dataset.key));
}
</script>
</body>
</html>
