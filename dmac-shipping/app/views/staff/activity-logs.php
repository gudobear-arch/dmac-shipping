<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('activity_logs_view');

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../../config/database.php';

$db = (new Database())->getConnection();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function niceDateTime($value) {
    if (empty($value)) {
        return '—';
    }
    return date('M d, Y h:i A', strtotime($value));
}

$totalAttempts = (int)$db->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
$successfulAttempts = (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE success = 1")->fetchColumn();
$failedAttempts = (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0")->fetchColumn();
$totalActivities = (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

$attemptStmt = $db->prepare("SELECT attempt_ID, user_type, email, success, ip_address, user_agent, attempted_at
                             FROM login_attempts
                             ORDER BY attempted_at DESC, attempt_ID DESC
                             LIMIT 25");
$attemptStmt->execute();
$loginAttempts = $attemptStmt->fetchAll(PDO::FETCH_ASSOC);

$activityStmt = $db->prepare("SELECT log_id, user_type, user_id, action, details, ip_address, created_at
                              FROM activity_logs
                              ORDER BY created_at DESC, log_id DESC
                              LIMIT 25");
$activityStmt->execute();
$activityLogs = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/audit-logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <main class="audit-page">
        <div class="audit-header">
            <div>
                <h1>Activity Logs</h1>
                <p>Track login attempts, successful logins, logouts, and important system activity.</p>
            </div>
        </div>

        <div class="audit-grid">
            <div class="audit-card">
                <span>Total Login Attempts</span>
                <strong><?= h($totalAttempts) ?></strong>
            </div>
            <div class="audit-card">
                <span>Successful Logins</span>
                <strong><?= h($successfulAttempts) ?></strong>
            </div>
            <div class="audit-card">
                <span>Failed Logins</span>
                <strong><?= h($failedAttempts) ?></strong>
            </div>
            <div class="audit-card">
                <span>Activity Records</span>
                <strong><?= h($totalActivities) ?></strong>
            </div>
        </div>

        <section class="audit-panel">
            <h2><i class="fa-solid fa-right-to-bracket"></i> Recent Login Attempts</h2>
            <div class="audit-table-wrap">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Email</th>
                            <th>User Type</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loginAttempts)): ?>
                            <tr><td colspan="6">No login attempts yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($loginAttempts as $attempt): ?>
                                <tr>
                                    <td><?= h(niceDateTime($attempt['attempted_at'])) ?></td>
                                    <td><?= h($attempt['email']) ?></td>
                                    <td><span class="badge-type"><?= h(ucfirst($attempt['user_type'])) ?></span></td>
                                    <td>
                                        <?php if ((int)$attempt['success'] === 1): ?>
                                            <span class="badge-success">Success</span>
                                        <?php else: ?>
                                            <span class="badge-failed">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($attempt['ip_address']) ?></td>
                                    <td><?= h($attempt['user_agent']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="audit-panel">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h2>
            <div class="audit-table-wrap">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>User Type</th>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activityLogs)): ?>
                            <tr><td colspan="6">No activity logs yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <tr>
                                    <td><?= h(niceDateTime($log['created_at'])) ?></td>
                                    <td><span class="badge-type"><?= h(ucfirst($log['user_type'])) ?></span></td>
                                    <td><?= h($log['user_id']) ?></td>
                                    <td><?= h($log['action']) ?></td>
                                    <td><?= h($log['details']) ?></td>
                                    <td><?= h($log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
