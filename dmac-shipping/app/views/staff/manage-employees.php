<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['employees_view','employees_create','employees_edit','employees_permissions','employees_deactivate']);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../helpers/staff_nav.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);
$employees = $employeeModel->getEmployeesWithRoles();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateNice($date) {
    if (empty($date)) {
        return '—';
    }
    return date('Y-m-d', strtotime($date));
}

function employeeStatusLabel($status) {
    $status = strtolower((string)$status);
    if ($status === 'approved') return ['Active', 'status-approved'];
    if ($status === 'rejected') return ['Disabled', 'status-rejected'];
    return [ucfirst($status ?: 'Unknown'), 'status-pending'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Employees List - DMAC Shipping</title>
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
                <h1>Employees List</h1>
                <p>Register employees, manage roles, assign access, and disable accounts.</p>
            </div>

            <div class="employees-actions">
                <?php if (hasPermission('employees_create')): ?>
                    <a href="register-employee.php" class="top-action">
                        <i class="fa-solid fa-user-plus"></i> Register Employees
                    </a>
                    <a href="add-role.php" class="top-action">
                        <i class="fa-solid fa-circle-plus"></i> Add New Role
                    </a>
                <?php endif; ?>
                <?php if (hasAnyPermission(['employees_edit','employees_permissions'])): ?>
                    <a href="assign-position.php" class="top-action">
                        <i class="fa-solid fa-users-gear"></i> Assign Position
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert-success">Employee account created successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert-success">Employee details and access updated successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert-success">Employee account disabled successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['role_added'])): ?>
            <div class="alert-success">New role added successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['position_updated'])): ?>
            <div class="alert-success">Employee position updated successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                <?php
                    $error = $_GET['error'];
                    if ($error === 'unauthorized') echo 'Only admin or super admin can do that action.';
                    elseif ($error === 'self_delete') echo 'You cannot delete or disable your own account.';
                    elseif ($error === 'protected') echo 'The super admin account is protected.';
                    else echo 'Something went wrong. Please try again.';
                ?>
            </div>
        <?php endif; ?>

        <div class="employees-table-card">
            <div class="employees-table-wrap">
                <table class="employees-table" id="employeesTable">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Registered Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">No employees found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <?php
                                    $roles = array_filter(array_map('trim', explode(',', $employee['roles'] ?? '')));
                                    [$statusLabel, $statusClass] = employeeStatusLabel($employee['account_status'] ?? 'approved');
                                ?>
                                <tr>
                                    <td class="emp-id"><?= h($employee['emp_ID']) ?></td>
                                    <td>
                                        <strong class="emp-name"><?= h(trim($employee['emp_firstname'] . ' ' . $employee['emp_lastname'])) ?></strong><br>
                                        <span class="status-badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                                        <?php if ((int)($employee['is_coordinator'] ?? 0) === 1): ?>
                                            <span class="status-badge status-coordinator">Coordinator</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($employee['emp_email']) ?></td>
                                    <td>
                                        <div class="role-badges">
                                            <?php if (empty($roles)): ?>
                                                <span class="role-badge">No Role</span>
                                            <?php else: ?>
                                                <?php foreach ($roles as $role): ?>
                                                    <span class="role-badge"><?= h($role) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= h(formatDateNice($employee['registered_since'] ?? '')) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ((int)$employee['is_super_admin'] === 1): ?>
                                                <span class="protected-text"><i class="fa-solid fa-crown"></i> Protected</span>
                                            <?php else: ?>
                                                <?php if (hasAnyPermission(['employees_edit','employees_permissions'])): ?>
                                                    <a class="btn-edit" href="edit-employee-access.php?id=<?= h($employee['emp_ID']) ?>">Edit</a>
                                                <?php endif; ?>
                                                <?php if (isSuperAdmin() || strtolower((string)($_SESSION['employee_role'] ?? '')) === 'admin'): ?>
                                                    <a class="btn-delete" href="process-delete-employee.php?emp_ID=<?= h($employee['emp_ID']) ?>" onclick="return confirm('Disable this employee account?');">Delete</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
