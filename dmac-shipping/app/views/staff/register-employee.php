<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('employees_create');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../helpers/staff_nav.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);

$departments = $employeeModel->getDepartments();
$roles = $employeeModel->getRoles();
$permissions = $employeeModel->getPermissions();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/employee-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <header>
        <div class="header-title">
            <h1>Add Employee/Admin</h1>
            <small>Create staff accounts inside the admin system and assign allowed access.</small>
        </div>
        <div class="user-wrapper">
            <i class="fa-solid fa-user-shield"></i>
            <div><h4><?=h($_SESSION['employee_name'])?></h4><small><?=h($_SESSION['employee_role'])?></small></div>
        </div>
    </header>

    <main>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert-success alert-error">
                <?php
                    $error = $_GET['error'];
                    if ($error === 'missing') echo 'Please complete all required fields.';
                    elseif ($error === 'email') echo 'Invalid email format.';
                    elseif ($error === 'taken') echo 'Email is already used by another employee.';
                    elseif ($error === 'weak') echo 'Password must be at least 8 characters.';
                    else echo 'Unable to create employee account.';
                ?>
            </div>
        <?php endif; ?>

        <div class="wizard-card" style="max-width: 850px; margin: 20px auto;">
            <h3><i class="fa-solid fa-user-plus"></i> Employee Account Details</h3>
            <p class="step-subtitle">Only the Super Admin can create employees/admins. Employees cannot register outside.</p>

            <form action="process-staff-reg.php" method="POST" style="margin-top: 20px;">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="emp_firstname" required placeholder="Juan">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="emp_lastname" required placeholder="Dela Cruz">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="emp_email" required placeholder="employee@dmacshipping.com">
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="emp_contact" required placeholder="09123456789">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="dept_id" required>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?=h($dept['dept_ID'])?>"><?=h($dept['dept_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Roles / Positions *</label>
                        <div class="role-checkbox-grid compact">
                            <?php foreach($roles as $role): ?>
                                <label class="role-check-card">
                                    <input type="checkbox" name="roles[]" value="<?=h($role['role_ID'])?>">
                                    <span><?=h($role['role'])?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="step-subtitle">You can select more than one role, like Coordinator + IT.</small>
                    </div>
                </div>

                <div class="dashboard-card" style="box-shadow:none;border:1px solid #bbf7d0;background:#f0fdf4;padding:16px;margin:16px 0;">
                    <label style="display:flex;gap:12px;align-items:flex-start;cursor:pointer;margin:0;">
                        <input type="checkbox" name="is_coordinator" value="1" style="width:auto;margin-top:4px;">
                        <span>
                            <strong>Set this employee as Coordinator</strong><br>
                            <small class="step-subtitle">Only employees marked as coordinators will appear in Managing Booking / Coordinator Assignments dropdowns.</small>
                        </span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="emp_password" required minlength="8" placeholder="Minimum 8 characters">
                </div>

                <div class="dashboard-card" style="box-shadow:none;border:1px solid #e2e8f0;padding:18px;margin-top:18px;">
                    <div class="section-header">
                        <h3>Assign Employee Access</h3>
                        <span class="status-badge">Permission Checkboxes</span>
                    </div>
                    <p class="step-subtitle">Check only the pages/features this employee can access. Super Admin always has full access.</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;">
                        <button type="button" class="btn-secondary-outline mini" onclick="togglePermissions(true)"><i class="fa-solid fa-check-double"></i> Select All</button>
                        <button type="button" class="btn-secondary-outline mini" onclick="togglePermissions(false)"><i class="fa-solid fa-xmark"></i> Clear All</button>
                        <button type="button" class="btn-secondary-outline mini" onclick="selectOperationalAccess()"><i class="fa-solid fa-truck"></i> Operations Access</button>
                    </div>

                    <?php foreach($permissionGroups as $groupName => $groupPermissions): ?>
                        <div class="permission-group-block">
                            <div class="permission-group-title">
                                <strong><?=h($groupName)?></strong>
                                <button type="button" class="permission-group-select" onclick="togglePermissionGroup(this, true)">Select group</button>
                            </div>
                            <div class="permission-grid">
                                <?php foreach($groupPermissions as $permission): ?>
                                    <label class="permission-card">
                                        <input type="checkbox" name="permissions[]" value="<?=h($permission['permission_ID'])?>" data-key="<?=h($permission['permission_key'])?>">
                                        <span><?=h($permission['permission_name'])?></span>
                                        <small><?=h($permission['permission_key'])?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="manage-employees.php" class="btn-secondary-outline">Cancel</a>
                    <button type="submit" class="btn-primary" style="width:auto;">Create Employee Account</button>
                </div>
            </form>
        </div>
    </main>
</div>
<script>
function togglePermissions(value){
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = value);
}
function togglePermissionGroup(button, value){
    const group = button.closest('.permission-group-block');
    group.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = value);
}
function selectOperationalAccess(){
    const allowed = ['dashboard_view','bookings_view','bookings_approve','bookings_assign','shipments_view','shipments_update'];
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = allowed.includes(cb.dataset.key));
}
</script>
</body>
</html>
