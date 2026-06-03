<?php
session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Client.php';
require_once __DIR__ . '/../../models/Employee.php';
require_once __DIR__ . '/../../helpers/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header("Location: login.php?error=missing");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: login.php?error=email");
    exit();
}

try {
    $db = (new Database())->getConnection();

    /*
     * Unified login process:
     * 1. Try client account first.
     * 2. If not a client success, try employee/admin account.
     * 3. Every success/failure is recorded in login_attempts.
     * 4. Successful login/logout is recorded in activity_logs.
     * 5. 3 failed attempts locks the account for 15 minutes.
     */

    $clientModel = new Client($db);
    $client = $clientModel->findClientByEmail($email);

    if ($client) {
        if (isAccountLocked($client['locked_until'] ?? null)) {
            logLoginAttempt($db, $email, 'client', false);
            header("Location: login.php?error=locked");
            exit();
        }

        if (password_verify($password, $client['client_password'])) {
            recordSuccessfulClientLogin($db, (int)$client['client_ID']);
            logLoginAttempt($db, $email, 'client', true);
            logActivity($db, 'client', (int)$client['client_ID'], 'Logged in', 'Client logged in successfully.');

            session_regenerate_id(true);

            $_SESSION['client_id'] = $client['client_ID'];
            $_SESSION['client_name'] = $client['client_firstname'] . ' ' . $client['client_lastname'];
            $_SESSION['client_email'] = $client['client_email'];
            $_SESSION['user_type'] = 'client';
            $_SESSION['last_activity'] = time();

            header("Location: ../client/dashboard.php");
            exit();
        }

        recordFailedClientLogin($db, (int)$client['client_ID']);
    }

    $employeeModel = new Employee($db);
    $employeeByEmail = $employeeModel->findEmployeeByEmail($email);

    if ($employeeByEmail) {
        if (isAccountLocked($employeeByEmail['locked_until'] ?? null)) {
            logLoginAttempt($db, $email, 'employee', false);
            header("Location: login.php?error=locked");
            exit();
        }

        if (!password_verify($password, $employeeByEmail['emp_password'])) {
            recordFailedEmployeeLogin($db, (int)$employeeByEmail['emp_ID']);
            logLoginAttempt($db, $email, 'employee', false);
            header("Location: login.php?error=invalid");
            exit();
        }

        $employee = $employeeByEmail;
        $accountStatus = $employee['account_status'] ?? 'approved';

        if ($accountStatus === 'pending') {
            logLoginAttempt($db, $email, 'employee', false);
            header("Location: login.php?pending=1");
            exit();
        }

        if ($accountStatus === 'rejected') {
            logLoginAttempt($db, $email, 'employee', false);
            header("Location: login.php?rejected=1");
            exit();
        }

        if ($accountStatus !== 'approved') {
            logLoginAttempt($db, $email, 'employee', false);
            header("Location: login.php?error=access");
            exit();
        }

        recordSuccessfulEmployeeLogin($db, (int)$employee['emp_ID']);
        logLoginAttempt($db, $email, 'employee', true);
        logActivity($db, 'employee', (int)$employee['emp_ID'], 'Logged in', 'Employee/Admin logged in successfully.');

        session_regenerate_id(true);

        $_SESSION['employee_id'] = $employee['emp_ID'];
        $_SESSION['employee_name'] = $employee['emp_firstname'] . ' ' . $employee['emp_lastname'];
        $_SESSION['employee_email'] = $employee['emp_email'];
        $_SESSION['employee_role'] = $employee['role'] ?? 'Employee';
        $_SESSION['is_super_admin'] = (int)($employee['is_super_admin'] ?? 0);
        $_SESSION['employee_permissions'] = $employeeModel->getPermissionKeysForEmployee((int)$employee['emp_ID']);
        $_SESSION['user_type'] = 'employee';
        $_SESSION['last_activity'] = time();

        header("Location: ../staff/dashboard.php");
        exit();
    }

    logLoginAttempt($db, $email, 'unknown', false);
    header("Location: login.php?error=invalid");
    exit();

} catch (Exception $e) {
    error_log('Unified login error: ' . $e->getMessage());
    header("Location: login.php?error=server");
    exit();
}
?>
