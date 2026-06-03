<?php
session_start();

require_once __DIR__ . '/../../helpers/auth.php';
requireEmployeeLogin();

require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Payment.php';
require_once __DIR__ . '/../../controllers/FinanceController.php';

function isAdminAccountingUserForExpense() {
    if (isSuperAdmin()) {
        return true;
    }

    $role = strtolower((string)($_SESSION['employee_role'] ?? ''));
    return $role === 'admin' || strpos($role, 'admin') !== false;
}

if (!isAdminAccountingUserForExpense()) {
    header('Location: dashboard.php?unauthorized=1');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: expenses.php');
    exit();
}

$expenseCategoryId = (int)($_POST['expensecategory_ID'] ?? 0);
$expenseAmount = (float)($_POST['expense_amount'] ?? 0);
$expenseDate = trim($_POST['expense_date'] ?? '');
$expenseDescription = trim($_POST['expense_description'] ?? '');

if ($expenseCategoryId <= 0 || $expenseDate === '') {
    header('Location: expenses.php?error=missing');
    exit();
}

if ($expenseAmount <= 0) {
    header('Location: expenses.php?error=amount');
    exit();
}

$dateObj = DateTime::createFromFormat('Y-m-d', $expenseDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $expenseDate) {
    header('Location: expenses.php?error=date');
    exit();
}

try {
    $db = (new Database())->getConnection();
    $paymentModel = new Payment($db);
    $controller = new FinanceController($paymentModel);

    $result = $controller->saveExpense($_POST, (int)($_SESSION['employee_id'] ?? 0));
    if (!$result['success']) {
        header('Location: expenses.php?error=' . urlencode($result['error'] === 'date' ? 'date' : 'server'));
        exit();
    }

    logActivity(
        $db,
        'employee',
        (int)($_SESSION['employee_id'] ?? 0),
        'Added expense',
        'Added expense amount ₱' . number_format($expenseAmount, 2) . ' on ' . $expenseDate
    );

    header('Location: expenses.php?success=1&month=' . (int)date('n', strtotime($expenseDate)) . '&year=' . (int)date('Y', strtotime($expenseDate)));
    exit();

} catch (Exception $e) {
    error_log('Process expense error: ' . $e->getMessage());
    header('Location: expenses.php?error=server');
    exit();
}
?>
