<?php
class Client {
    private $db;
    public function __construct($dbConnection) { $this->db = $dbConnection; }

    public function findClientByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM client WHERE client_email = :email AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($clientId) {
        $stmt = $this->db->prepare("SELECT * FROM client WHERE client_ID = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function register($data) {
        $sql = "INSERT INTO client (client_firstname, client_lastname, client_contact, client_email, client_password)
                VALUES (:firstname, :lastname, :contact, :email, :password)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'],
            'contact'   => $data['contact'],
            'email'     => $data['email'],
            'password'  => $data['password']
        ]);
    }

    public function updateProfile($clientId, $data) {
        $sql = "UPDATE client SET client_firstname=:firstname, client_lastname=:lastname, client_contact=:contact, client_email=:email WHERE client_ID=:id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'firstname'=>$data['firstname'], 'lastname'=>$data['lastname'], 'contact'=>$data['contact'], 'email'=>$data['email'], 'id'=>$clientId
        ]);
    }

    public function updatePassword($clientId, $hashedPassword) {
        $stmt = $this->db->prepare("UPDATE client SET client_password=:password WHERE client_ID=:id");
        return $stmt->execute(['password'=>$hashedPassword, 'id'=>$clientId]);
    }
}
?>
