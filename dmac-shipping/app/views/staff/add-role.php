<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('employees_create');

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->query("SELECT role_ID, role FROM roles ORDER BY role ASC");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Add New Role - DMAC Shipping</title>
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
                <h1>Add New Role</h1>
                <p>Create new employee positions like Coordinator, IT, Driver, or Accounting Staff.</p>
            </div>
            <a href="manage-employees.php" class="top-action"><i class="fa-solid fa-arrow-left"></i> Back to Employees</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">Role added successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                <?php
                    if ($_GET['error'] === 'missing') echo 'Role name is required.';
                    elseif ($_GET['error'] === 'exists') echo 'That role already exists.';
                    else echo 'Something went wrong. Please try again.';
                ?>
            </div>
        <?php endif; ?>

        <div class="role-grid">
            <section class="role-card">
                <h3><i class="fa-solid fa-circle-plus"></i> Create Role</h3>
                <form action="process-add-role.php" method="POST">
                    <div class="form-group">
                        <label>Role Name</label>
                        <input type="text" name="role" required placeholder="Example: Coordinator, IT, Driver">
                    </div>
                    <button type="submit" class="btn-save">Save Role</button>
                </form>
            </section>

            <section class="role-card">
                <h3>Existing Roles</h3>
                <table class="roles-table">
                    <thead>
                        <tr>
                            <th>Role ID</th>
                            <th>Role Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr><td colspan="2">No roles found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?= h($role['role_ID']) ?></td>
                                    <td><span class="role-badge"><?= h($role['role']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
</div>
</body>
</html>
