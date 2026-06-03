<?php
/**
 * Backend logging helpers for DMAC.
 * Uses existing tables:
 * - login_attempts(email, user_type, success, ip_address, user_agent, attempted_at)
 * - activity_logs(user_type, user_id, action, details, ip_address, created_at)
 */

function dmacClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function dmacUserAgent() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    return mb_substr($ua, 0, 255);
}

function logLoginAttempt($db, $email, $userType = 'unknown', $success = false) {
    try {
        if (!in_array($userType, ['client', 'employee', 'unknown'], true)) {
            $userType = 'unknown';
        }

        $stmt = $db->prepare("INSERT INTO login_attempts (user_type, email, success, ip_address, user_agent, attempted_at)
                              VALUES (:user_type, :email, :success, :ip_address, :user_agent, NOW())");
        $stmt->execute([
            'user_type' => $userType,
            'email' => (string)$email,
            'success' => $success ? 1 : 0,
            'ip_address' => dmacClientIp(),
            'user_agent' => dmacUserAgent()
        ]);
    } catch (Exception $e) {
        error_log('Login attempt log error: ' . $e->getMessage());
    }
}

function logActivity($db, $userType, $userId, $action, $details = null) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_type, user_id, action, details, ip_address, created_at)
                              VALUES (:user_type, :user_id, :action, :details, :ip_address, NOW())");
        $stmt->execute([
            'user_type' => (string)$userType,
            'user_id' => $userId !== null ? (int)$userId : null,
            'action' => (string)$action,
            'details' => $details,
            'ip_address' => dmacClientIp()
        ]);
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

function recordFailedClientLogin($db, $clientId) {
    try {
        $stmt = $db->prepare("UPDATE client
                              SET failed_login_count = COALESCE(failed_login_count, 0) + 1,
                                  locked_until = CASE
                                      WHEN COALESCE(failed_login_count, 0) + 1 >= 3 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                      ELSE locked_until
                                  END
                              WHERE client_ID = :id");
        $stmt->execute(['id' => (int)$clientId]);
    } catch (Exception $e) {
        error_log('Client failed login update error: ' . $e->getMessage());
    }
}

function recordSuccessfulClientLogin($db, $clientId) {
    try {
        $stmt = $db->prepare("UPDATE client
                              SET failed_login_count = 0,
                                  locked_until = NULL,
                                  last_login_at = NOW()
                              WHERE client_ID = :id");
        $stmt->execute(['id' => (int)$clientId]);
    } catch (Exception $e) {
        error_log('Client successful login update error: ' . $e->getMessage());
    }
}

function recordFailedEmployeeLogin($db, $empId) {
    try {
        $stmt = $db->prepare("UPDATE employee
                              SET failed_login_count = COALESCE(failed_login_count, 0) + 1,
                                  locked_until = CASE
                                      WHEN COALESCE(failed_login_count, 0) + 1 >= 3 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                      ELSE locked_until
                                  END
                              WHERE emp_ID = :id");
        $stmt->execute(['id' => (int)$empId]);
    } catch (Exception $e) {
        error_log('Employee failed login update error: ' . $e->getMessage());
    }
}

function recordSuccessfulEmployeeLogin($db, $empId) {
    try {
        $stmt = $db->prepare("UPDATE employee
                              SET failed_login_count = 0,
                                  locked_until = NULL,
                                  last_login_at = NOW()
                              WHERE emp_ID = :id");
        $stmt->execute(['id' => (int)$empId]);
    } catch (Exception $e) {
        error_log('Employee successful login update error: ' . $e->getMessage());
    }
}

function isAccountLocked($lockedUntil) {
    if (empty($lockedUntil)) {
        return false;
    }

    return strtotime($lockedUntil) > time();
}
?>
