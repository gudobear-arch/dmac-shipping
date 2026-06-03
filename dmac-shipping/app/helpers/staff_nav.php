<?php
require_once __DIR__ . '/auth.php';

function navActive($file) {
    return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}

function showStaffSidebar($title = 'DMAC Admin') {
    $canEmployees = hasAnyPermission(['employees_view','employees_create','employees_edit','employees_permissions','employees_deactivate']);
?>
<div class="sidebar staff-sidebar">
  <div class="sidebar-brand">
    <h2><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
  <ul class="sidebar-menu">
    <?php if (hasPermission('dashboard_view')): ?>
      <li><a href="dashboard.php" class="<?= navActive('dashboard.php') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
    <?php endif; ?>

    <?php if ($canEmployees): ?>
      <li><a href="manage-employees.php" class="<?= navActive('manage-employees.php') ?>"><i class="fa-solid fa-users-gear"></i> Manage Employees</a></li>
    <?php endif; ?>
<?php if (hasAnyPermission(['bookings_view','bookings_approve'])): ?>
      <li><a href="pending-bookings.php" class="<?= navActive('pending-bookings.php') ?>"><i class="fa-solid fa-clipboard-list"></i> Pending Bookings</a></li>
    <?php endif; ?>

    <?php if (hasAnyPermission(['bookings_assign'])): ?>
      <li><a href="assignments.php" class="<?= navActive('assignments.php') ?>"><i class="fa-solid fa-user-check"></i> Coordinator Assignments</a></li>
    <?php endif; ?>

    <?php if (hasAnyPermission(['shipments_view','shipments_update'])): ?>
      <li><a href="active-shipments.php" class="<?= navActive('active-shipments.php') ?>"><i class="fa-solid fa-truck-ramp-box"></i> For Pick-up Bookings</a></li>
    <?php endif; ?>

    <?php if (hasAnyPermission(['shipments_view','bookings_view'])): ?>
      <li><a href="booking-records.php" class="<?= navActive('booking-records.php') ?>"><i class="fa-solid fa-box-archive"></i> Booking Records</a></li>
    <?php endif; ?>

    <?php if (hasAnyPermission(['accounting_view','accounting_manage'])): ?>
      <li><a href="finances.php" class="<?= navActive('finances.php') ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Accounting</a></li>
    <?php endif; ?>

    <?php if (isSuperAdmin() || strtolower((string)($_SESSION['employee_role'] ?? '')) === 'admin' || strpos(strtolower((string)($_SESSION['employee_role'] ?? '')), 'admin') !== false): ?>
      <li><a href="expenses.php" class="<?= navActive('expenses.php') ?>"><i class="fa-solid fa-chart-line"></i> Expenses</a></li>
    <?php endif; ?>

    <?php if (hasPermission('feedback_view')): ?>
      <li><a href="feedback.php" class="<?= navActive('feedback.php') ?>"><i class="fa-solid fa-comments"></i> Feedback</a></li>
    <?php endif; ?>

    <?php if (hasPermission('activity_logs_view')): ?>
      <li><a href="activity-logs.php" class="<?= navActive('activity-logs.php') ?>"><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</a></li>
    <?php endif; ?>

    <li><a href="settings.php" class="<?= navActive('settings.php') ?>"><i class="fa-solid fa-user-gear"></i> Settings</a></li>
  </ul>
</div>
<?php
}
?>
