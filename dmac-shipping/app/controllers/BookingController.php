<?php

class BookingController
{
    private $bookingModel;

    public function __construct($bookingModel)
    {
        $this->bookingModel = $bookingModel;
    }

    public function handleCreateBooking()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: booking-wizard.php");
            exit();
        }

        if (!isset($_SESSION['client_id'])) {
            header("Location: ../auth/login.php");
            exit();
        }

        $clientID = $_SESSION['client_id'];

        $pickupFirstname = trim($_POST['pickup_firstname'] ?? '');
        $pickupLastname  = trim($_POST['pickup_lastname'] ?? '');
        $pickupContact   = trim($_POST['pickup_contact'] ?? '');
        $pickupAddress   = trim($_POST['pickup_address'] ?? '');

        $receiverFirstname = trim($_POST['receiver_firstname'] ?? '');
        $receiverLastname  = trim($_POST['receiver_lastname'] ?? '');
        $receiverContact   = trim($_POST['receiver_contact'] ?? '');
        $receiverAddress   = trim($_POST['receiver_address'] ?? '');

        $receiverMunicipality = trim($_POST['receiver_municipality'] ?? '');
        $receiverProvince     = trim($_POST['receiver_province'] ?? '');

        $animalID = $_POST['animal_ID'] ?? '';
        $quantity = $_POST['quantity'] ?? '';
        $requestShipDate = trim($_POST['booking_requestdate'] ?? '');

        $termsAccepted = isset($_POST['terms_accepted']) ? 1 : 0;
        $insuranceAccepted = isset($_POST['insurance_accepted']) ? 1 : 0;

        if (
            $pickupFirstname === '' ||
            $pickupLastname === '' ||
            $pickupContact === '' ||
            $pickupAddress === '' ||
            $receiverFirstname === '' ||
            $receiverLastname === '' ||
            $receiverContact === '' ||
            $receiverAddress === '' ||
            $receiverMunicipality === '' ||
            $receiverProvince === '' ||
            $animalID === '' ||
            $quantity === '' ||
            $requestShipDate === ''
        ) {
            header("Location: booking-wizard.php?error=missing");
            exit();
        }

        if (!preg_match('/^[0-9]{10,15}$/', $pickupContact)) {
            header("Location: booking-wizard.php?error=pickup_contact");
            exit();
        }

        if (!preg_match('/^[0-9]{10,15}$/', $receiverContact)) {
            header("Location: booking-wizard.php?error=receiver_contact");
            exit();
        }

        if (!is_numeric($quantity) || (int)$quantity <= 0) {
            header("Location: booking-wizard.php?error=quantity");
            exit();
        }

        if (strtotime($requestShipDate) < strtotime(date('Y-m-d'))) {
            header("Location: booking-wizard.php?error=date");
            exit();
        }

        if (!$termsAccepted || !$insuranceAccepted) {
            header("Location: booking-wizard.php?error=agreement");
            exit();
        }

        $bookingData = [
            'client_ID' => $clientID,

            'pickup_firstname' => htmlspecialchars($pickupFirstname, ENT_QUOTES, 'UTF-8'),
            'pickup_lastname'  => htmlspecialchars($pickupLastname, ENT_QUOTES, 'UTF-8'),
            'pickup_contact'   => htmlspecialchars($pickupContact, ENT_QUOTES, 'UTF-8'),
            'pickup_address'   => htmlspecialchars($pickupAddress, ENT_QUOTES, 'UTF-8'),

            'receiver_firstname'    => htmlspecialchars($receiverFirstname, ENT_QUOTES, 'UTF-8'),
            'receiver_lastname'     => htmlspecialchars($receiverLastname, ENT_QUOTES, 'UTF-8'),
            'receiver_contact'      => htmlspecialchars($receiverContact, ENT_QUOTES, 'UTF-8'),
            'receiver_address'      => htmlspecialchars($receiverAddress, ENT_QUOTES, 'UTF-8'),
            'receiver_municipality' => htmlspecialchars($receiverMunicipality, ENT_QUOTES, 'UTF-8'),
            'receiver_province'     => htmlspecialchars($receiverProvince, ENT_QUOTES, 'UTF-8'),

            'animal_ID' => (int)$animalID,
            'quantity'  => (int)$quantity,

            'booking_requestdate' => $requestShipDate,
            'booking_status' => 'PENDING REVIEW',

            'terms_accepted' => $termsAccepted,
            'insurance_accepted' => $insuranceAccepted
        ];

        $created = $this->bookingModel->createBooking($bookingData);

        if ($created) {
            header("Location: my-shipments.php?success=booking_created");
            exit();
        }

        header("Location: booking-wizard.php?error=server");
        exit();
    }
}
?>