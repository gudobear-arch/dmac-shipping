<?php
require_once __DIR__ . '/../../helpers/auth.php';
requirePermission('accounting_manage');
require_once '../../../config/database.php';
require_once '../../models/Payment.php';
require_once '../../controllers/FinanceController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: finances.php');
    exit();
}

try {
    $db = (new Database())->getConnection();
    $paymentModel = new Payment($db);
    $controller = new FinanceController($paymentModel);
    $result = $controller->savePayment($_POST);

    if (!$result['success']) {
        if ($result['error'] === 'air_online') {
            header('Location: finances.php?air_online=1');
            exit();
        }
        header('Location: finances.php?error=1');
        exit();
    }

    header('Location: finances.php?updated=1');
    exit();
} catch (Exception $e) {
    error_log('Process Payment Error: ' . $e->getMessage());
    header('Location: finances.php?error=1');
    exit();
}
?>
