<?php
class AuthController {
    private $clientModel;

    public function __construct($clientModel) {
        $this->clientModel = $clientModel;
    }

    public function handleRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: register.php");
            exit();
        }

        $firstname = trim($_POST['client_firstname'] ?? '');
        $lastname  = trim($_POST['client_lastname'] ?? '');
        $contact   = trim($_POST['client_contact'] ?? '');
        $email     = filter_var(trim($_POST['client_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password  = trim($_POST['client_password'] ?? '');

        if ($firstname === '' || $lastname === '' || $contact === '' || $email === '' || $password === '') {
            header("Location: register.php?error=missing");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: register.php?error=email");
            exit();
        }

        if (strlen($password) < 8) {
            header("Location: register.php?error=weak");
            exit();
        }

        if ($this->clientModel->findClientByEmail($email)) {
            header("Location: register.php?error=taken");
            exit();
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $ok = $this->clientModel->register([
            'firstname' => htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'),
            'lastname'  => htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8'),
            'contact'   => htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'),
            'email'     => $email,
            'password'  => $hashedPassword
        ]);

        header("Location: login.php?" . ($ok ? "msg=registered" : "error=server"));
        exit();
    }

    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: login.php");
            exit();
        }

        $email = filter_var(trim($_POST['client_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['client_password'] ?? '');

        if ($email === '' || $password === '') {
            header("Location: login.php?error=missing");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: login.php?error=email");
            exit();
        }

        $client = $this->clientModel->findClientByEmail($email);

        if (!$client || !password_verify($password, $client['client_password'])) {
            header("Location: login.php?error=invalid");
            exit();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['client_id'] = $client['client_ID'];
        $_SESSION['client_name'] = $client['client_firstname'] . ' ' . $client['client_lastname'];
        $_SESSION['client_email'] = $client['client_email'];
        $_SESSION['user_type'] = 'client';
        $_SESSION['last_activity'] = time();

        header("Location: ../client/dashboard.php");
        exit();
    }
}
?>
