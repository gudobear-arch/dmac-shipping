<?php
session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Booking.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: booking-wizard.php");
    exit();
}

function redirectBooking($errorCode) {
    header("Location: booking-wizard.php?error=" . urlencode($errorCode));
    exit();
}

function cleanText($value) {
    return trim((string)($value ?? ''));
}

function validPhone($value) {
    $value = cleanText($value);
    return preg_match('/^(09\d{9}|\+639\d{9})$/', $value) === 1;
}

function validDateNotPast($date) {
    if (empty($date)) {
        return false;
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        return false;
    }

    $today = new DateTime(date('Y-m-d'));
    return $dateObj >= $today;
}

function animalExists($db, $animalId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM animal WHERE animal_ID = ?");
    $stmt->execute([$animalId]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $requiredFields = [
        'pickup_firstname',
        'pickup_lastname',
        'pickup_number',
        'pickup_street',
        'pickup_municipality',
        'pickup_province',

        'receiver_firstname',
        'receiver_lastname',
        'receiver_contact',
        'receiver_street',
        'receiver_municipality',
        'receiver_province',

        'booking_requestdate'
    ];

    foreach ($requiredFields as $field) {
        if (cleanText($_POST[$field] ?? '') === '') {
            redirectBooking('missing');
        }
    }

    if (($_POST['booking_confirmed'] ?? '0') !== '1') {
        redirectBooking('missing');
    }

    if (empty($_POST['terms_accepted']) || empty($_POST['insurance_accepted'])) {
        redirectBooking('agreement');
    }

    $pickupNumber = cleanText($_POST['pickup_number']);
    $receiverContact = cleanText($_POST['receiver_contact']);

    if (!validPhone($pickupNumber)) {
        redirectBooking('pickup_phone');
    }

    if (!validPhone($receiverContact)) {
        redirectBooking('receiver_phone');
    }


    $requestDate = cleanText($_POST['booking_requestdate']);

    if (!validDateNotPast($requestDate)) {
        redirectBooking('date');
    }

    $animalTypes = $_POST['animal_types'] ?? [];
    $animalQuantities = $_POST['animal_quantities'] ?? [];

    if (!is_array($animalTypes) || !is_array($animalQuantities)) {
        redirectBooking('animal');
    }

    if (count($animalTypes) === 0 || count($animalTypes) !== count($animalQuantities)) {
        redirectBooking('animal');
    }

    $database = new Database();
    $db = $database->getConnection();
    $bookingModel = new Booking($db);

    $cleanAnimalTypes = [];
    $cleanAnimalQuantities = [];

    foreach ($animalTypes as $index => $animalId) {
        $animalId = (int)$animalId;
        $qty = (int)($animalQuantities[$index] ?? 0);

        if ($animalId <= 0 || $qty <= 0) {
            redirectBooking('animal');
        }

        if (!animalExists($db, $animalId)) {
            redirectBooking('animal');
        }

        $cleanAnimalTypes[] = $animalId;
        $cleanAnimalQuantities[] = $qty;
    }

    $formData = [
        'pickup_firstname'    => cleanText($_POST['pickup_firstname']),
        'pickup_lastname'     => cleanText($_POST['pickup_lastname']),
        'pickup_number'       => $pickupNumber,
        'pickup_street'       => cleanText($_POST['pickup_street']),
        'pickup_municipality' => cleanText($_POST['pickup_municipality']),
        'pickup_province'     => cleanText($_POST['pickup_province']),

        'receiver_firstname'    => cleanText($_POST['receiver_firstname']),
        'receiver_lastname'     => cleanText($_POST['receiver_lastname']),
        'receiver_contact'      => $receiverContact,
        'receiver_street'       => cleanText($_POST['receiver_street']),
        'receiver_municipality' => cleanText($_POST['receiver_municipality']),
        'receiver_province'     => cleanText($_POST['receiver_province']),

        'booking_requestdate'   => $requestDate,
        'booking_status'        => 'PENDING REVIEW',

        'terms_accepted'        => 1,
        'insurance_accepted'    => 1,

        'animal_types'          => $cleanAnimalTypes,
        'animal_quantities'     => $cleanAnimalQuantities
    ];

    $bookingId = $bookingModel->createBooking((int)$_SESSION['client_id'], $formData);

    if ($bookingId) {
        header("Location: my-shipments.php?booking=success");
        exit();
    }

    redirectBooking('server');

} catch (Exception $e) {
    error_log('Process Booking Error: ' . $e->getMessage());
    redirectBooking('server');
}
?>