<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isEmployeeLoggedIn() {
    return isset($_SESSION['employee_id']);
}

function isClientLoggedIn() {
    return isset($_SESSION['client_id']);
}

function isSuperAdmin() {
    return isset($_SESSION['is_super_admin']) && (int)$_SESSION['is_super_admin'] === 1;
}

function requireEmployeeLogin() {
    if (!isEmployeeLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function requireClientLogin() {
    if (!isClientLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function employeePermissions() {
    return $_SESSION['employee_permissions'] ?? [];
}

function hasPermission($permissionKey) {
    if (isSuperAdmin()) {
        return true;
    }

    $permissions = employeePermissions();
    return in_array($permissionKey, $permissions, true);
}

function hasAnyPermission($permissionKeys) {
    if (isSuperAdmin()) {
        return true;
    }

    foreach ($permissionKeys as $key) {
        if (hasPermission($key)) {
            return true;
        }
    }

    return false;
}

function requirePermission($permissionKey) {
    requireEmployeeLogin();

    if (!hasPermission($permissionKey)) {
        header('Location: dashboard.php?unauthorized=1');
        exit();
    }
}

function requireAnyPermission($permissionKeys) {
    requireEmployeeLogin();

    if (!hasAnyPermission($permissionKeys)) {
        header('Location: dashboard.php?unauthorized=1');
        exit();
    }
}

function requireSuperAdmin() {
    requireEmployeeLogin();

    if (!isSuperAdmin()) {
        header('Location: dashboard.php?unauthorized=1');
        exit();
    }
}
?>
