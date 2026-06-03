<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireAnyPermission(['employees_edit','employees_permissions']);

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Employee.php';

$db = (new Database())->getConnection();
$employeeModel = new Employee($db);
$employees = $employeeModel->getEmployeesWithRoles();
$roles = $employeeModel->getRoles();
$selectedEmpId = (int)($_GET['emp_ID'] ?? 0);
$selectedRoles = $selectedEmpId > 0 ? $employeeModel->getRoleIdsForEmployee($selectedEmpId) : [];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function isChecked($needle, $haystack) {
    return in_array((int)$needle, array_map('intval', $haystack), true) ? 'checked' : '';
}
function isSelected($value, $selected) {
    return (string)$value === (string)$selected ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Assign Position - DMAC Shipping</title>
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
                <h1>Assign Position</h1>
                <p>Assign one or more roles to an employee.</p>
            </div>
            <a href="manage-employees.php" class="top-action"><i class="fa-solid fa-arrow-left"></i> Back to Employees</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">Position updated successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">Please select an employee and at least one role.</div>
        <?php endif; ?>

        <section class="employee-form-card">
            <form action="assign-position.php" method="GET" class="form-group">
                <label>Choose Employee</label>
                <select name="emp_ID" onchange="this.form.submit()">
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <?php if ((int)$employee['is_super_admin'] === 1) continue; ?>
                        <option value="<?= h($employee['emp_ID']) ?>" <?= isSelected($employee['emp_ID'], $selectedEmpId) ?>>
                            <?= h($employee['emp_firstname'] . ' ' . $employee['emp_lastname'] . ' - ' . $employee['emp_email']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selectedEmpId > 0): ?>
                <form action="process-assign-position.php" method="POST">
                    <input type="hidden" name="emp_ID" value="<?= h($selectedEmpId) ?>">

                    <h3><i class="fa-solid fa-user-tag"></i> Select Roles</h3>
                    <div class="role-checkbox-grid">
                        <?php foreach ($roles as $role): ?>
                            <label class="role-check-card">
                                <input type="checkbox" name="roles[]" value="<?= h($role['role_ID']) ?>" <?= isChecked($role['role_ID'], $selectedRoles) ?>>
                                <span><?= h($role['role']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions-right">
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Position</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
