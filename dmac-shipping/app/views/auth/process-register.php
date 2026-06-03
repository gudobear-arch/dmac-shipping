<?php
session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Client.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $clientModel = new Client($db);
    $authController = new AuthController($clientModel);

    $authController->handleRegister();
} catch (Exception $e) {
    header('Location: register.php?error=server');
    exit();
}
?>
