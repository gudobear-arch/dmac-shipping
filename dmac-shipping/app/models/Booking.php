<?php
class Booking {
    private $db;
    private $columnCache = [];

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    private function tableHasColumn($table, $column) {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

                $stmt = $this->db->prepare(
                        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = :table_name
                             AND COLUMN_NAME = :column_name
                         LIMIT 1"
                );
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        $this->columnCache[$key] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        return $this->columnCache[$key];
    }

    public function createBooking($clientId, $data) {
        try {
            $this->db->beginTransaction();

            $pickupSql = "INSERT INTO pickup (
                            contact_firstname,
                            contact_lastname,
                            contact_number,
                            pickup_street,
                            pickup_municipality,
                            pickup_province
                          ) VALUES (
                            :fn,
                            :ln,
                            :num,
                            :street,
                            :mun,
                            :prov
                          )";
            $this->db->prepare($pickupSql)->execute([
                'fn'     => $data['pickup_firstname'],
                'ln'     => $data['pickup_lastname'],
                'num'    => $data['pickup_number'],
                'street' => $data['pickup_street'],
                'mun'    => $data['pickup_municipality'],
                'prov'   => $data['pickup_province']
            ]);
            $pickupId = $this->db->lastInsertId();

            $receiverSql = "INSERT INTO receiver (
                                receiver_firstname,
                                receiver_lastname,
                                receiver_contact,
                                receiver_street,
                                receiver_municipality,
                                receiver_province
                            ) VALUES (
                                :fn,
                                :ln,
                                :num,
                                :street,
                                :mun,
                                :prov
                            )";
            $this->db->prepare($receiverSql)->execute([
                'fn'     => $data['receiver_firstname'],
                'ln'     => $data['receiver_lastname'],
                'num'    => $data['receiver_contact'],
                'street' => $data['receiver_street'],
                'mun'    => $data['receiver_municipality'],
                'prov'   => $data['receiver_province']
            ]);
            $receiverId = $this->db->lastInsertId();

            $bookingColumns = ['client_ID', 'pickup_ID', 'receiver_ID', 'booking_requestdate', 'booking_status'];
            $bookingValues = [':client_id', ':pickup_id', ':receiver_id', ':req_date', ':status'];
            $bookingParams = [
                'client_id'   => $clientId,
                'pickup_id'   => $pickupId,
                'receiver_id' => $receiverId,
                'req_date'    => $data['booking_requestdate'],
                'status'      => 'PENDING REVIEW'
            ];

            // Transportation mode is intentionally NOT set by the client.
            // Admin/authorized staff will set booking.shipment_type later in Billing/Accounting.
            if ($this->tableHasColumn('booking', 'shipment_type')) {
                $bookingColumns[] = 'shipment_type';
                $bookingValues[] = 'NULL';
            }

            if ($this->tableHasColumn('booking', 'terms_accepted')) {
                $bookingColumns[] = 'terms_accepted';
                $bookingValues[] = ':terms_accepted';
                $bookingParams['terms_accepted'] = (int)($data['terms_accepted'] ?? 0);
            }

            if ($this->tableHasColumn('booking', 'insurance_accepted')) {
                $bookingColumns[] = 'insurance_accepted';
                $bookingValues[] = ':insurance_accepted';
                $bookingParams['insurance_accepted'] = (int)($data['insurance_accepted'] ?? 0);
            }

            if ($this->tableHasColumn('booking', 'agreement_accepted_at')) {
                $bookingColumns[] = 'agreement_accepted_at';
                $bookingValues[] = 'CURRENT_TIMESTAMP';
            }

            $bookingSql = "INSERT INTO booking (" . implode(', ', $bookingColumns) . ") VALUES (" . implode(', ', $bookingValues) . ")";
            $this->db->prepare($bookingSql)->execute($bookingParams);
            $bookingId = $this->db->lastInsertId();

            $batchStmt = $this->db->prepare("INSERT INTO animalbatch (booking_ID, animal_ID, animalbatch_quantity) VALUES (:booking_id, :animal_id, :qty)");
            $types = $data['animal_types'] ?? [];
            $qtys  = $data['animal_quantities'] ?? [];

            for ($i = 0; $i < count($types); $i++) {
                $animalId = (int)$types[$i];
                $qty = max(1, (int)($qtys[$i] ?? 1));
                if ($animalId <= 0) {
                    throw new Exception('Invalid animal type.');
                }
                $batchStmt->execute([
                    'booking_id' => $bookingId,
                    'animal_id'  => $animalId,
                    'qty'        => $qty
                ]);
            }

            $this->db->commit();
            return $bookingId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Booking Error: " . $e->getMessage());
            return false;
        }
    }

    public function getStatusCounts($clientId) {
        $stmt = $this->db->prepare("SELECT booking_status, COUNT(*) total FROM booking WHERE client_ID = :client_id GROUP BY booking_status");
        $stmt->execute(['client_id' => $clientId]);

        $counts = [
            'PENDING REVIEW' => 0,
            'PROCESSING' => 0,
            'FOR PICK-UP' => 0,
            'IN TRANSIT' => 0,
            'PREPARING FOR TRANSIT' => 0,
            'DELIVERED/SHIPPED' => 0,
            'CANCELLED' => 0
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($counts[$row['booking_status']])) {
                $counts[$row['booking_status']] = (int)$row['total'];
            }
        }
        return $counts;
    }

    public function getRecentShipments($clientId, $limit = 5) {
        return $this->getClientShipments($clientId, $limit);
    }

    public function getClientShipments($clientId, $limit = 100) {
        $agreementSelect = '';
        if ($this->tableHasColumn('booking', 'terms_accepted')) {
            $agreementSelect .= ", b.terms_accepted";
        } else {
            $agreementSelect .= ", 0 AS terms_accepted";
        }
        if ($this->tableHasColumn('booking', 'insurance_accepted')) {
            $agreementSelect .= ", b.insurance_accepted";
        } else {
            $agreementSelect .= ", 0 AS insurance_accepted";
        }
        if ($this->tableHasColumn('booking', 'agreement_accepted_at')) {
            $agreementSelect .= ", b.agreement_accepted_at";
        } else {
            $agreementSelect .= ", NULL AS agreement_accepted_at";
        }
        if ($this->tableHasColumn('booking', 'shipment_type')) {
            $agreementSelect .= ", COALESCE(b.shipment_type, 'NOT SET') AS shipment_type";
        } else {
            $agreementSelect .= ", 'NOT SET' AS shipment_type";
        }

        $sql = "SELECT b.booking_ID,
                       b.booking_status,
                       b.booking_requestdate,
                       b.booking_startdate,
                       b.booking_enddate,
                       p.contact_firstname,
                       p.contact_lastname,
                       p.contact_number,
                       p.pickup_street,
                       p.pickup_municipality,
                       p.pickup_province,
                       r.receiver_firstname,
                       r.receiver_lastname,
                       r.receiver_contact,
                       r.receiver_street,
                       r.receiver_municipality,
                       r.receiver_province,
                       COALESCE(animals.total_animals, 0) AS total_animals,
                       COALESCE(animals.animal_summary, 'No animals listed') AS animal_summary,
                       f.feedback_ID,
                       f.feed_rate,
                       f.feed_comment
                       $agreementSelect
                FROM booking b
                JOIN pickup p ON b.pickup_ID = p.pickup_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN (
                    SELECT ab.booking_ID,
                           SUM(ab.animalbatch_quantity) AS total_animals,
                           GROUP_CONCAT(CONCAT(a.animal_type, ' - ', ab.animalbatch_quantity, ' head(s)') ORDER BY a.animal_type SEPARATOR ', ') AS animal_summary
                    FROM animalbatch ab
                    JOIN animal a ON ab.animal_ID = a.animal_ID
                    GROUP BY ab.booking_ID
                ) animals ON b.booking_ID = animals.booking_ID
                LEFT JOIN feedback f ON b.booking_ID = f.booking_ID
                WHERE b.client_ID = :client_id
                ORDER BY b.booking_startdate DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submitFeedback($clientId, $bookingId, $rating, $comment) {
        $clientId = (int)$clientId;
        $bookingId = (int)$bookingId;
        $rating = max(1, min(5, (int)$rating));
        $comment = trim((string)$comment);

        if ($clientId <= 0 || $bookingId <= 0 || $comment === '') {
            return false;
        }

        $check = $this->db->prepare("
            SELECT booking_ID
            FROM booking
            WHERE booking_ID = :booking_id
              AND client_ID = :client_id
              AND booking_status IN (
                    'DELIVERED/SHIPPED',
                    'DELIVERED / SHIPPED',
                    'COMPLETED',
                    'DELIVERED',
                    'SHIPPED'
              )
            LIMIT 1
        ");

        $check->execute([
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        $existing = $this->db->prepare("
            SELECT feedback_ID
            FROM feedback
            WHERE booking_ID = :booking_id
            LIMIT 1
        ");
        $existing->execute(['booking_id' => $bookingId]);
        $feedbackId = $existing->fetchColumn();

        if ($feedbackId) {
            $stmt = $this->db->prepare("
                UPDATE feedback
                SET feed_rate = :rate,
                    feed_comment = :comment,
                    feed_submitted = CURRENT_TIMESTAMP
                WHERE feedback_ID = :feedback_id
            ");

            return $stmt->execute([
                'rate' => $rating,
                'comment' => $comment,
                'feedback_id' => (int)$feedbackId
            ]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO feedback (booking_ID, feed_rate, feed_comment, feed_submitted)
            VALUES (:booking_id, :rate, :comment, CURRENT_TIMESTAMP)
        ");

        return $stmt->execute([
            'booking_id' => $bookingId,
            'rate' => $rating,
            'comment' => $comment
        ]);
    }

    public function getClientFeedbackHistory($clientId) {
        $stmt = $this->db->prepare("
            SELECT
                f.feedback_ID,
                f.feed_rate,
                f.feed_comment,
                f.feed_submitted,
                b.booking_ID,
                b.booking_status,
                b.booking_requestdate,
                p.pickup_municipality,
                r.receiver_municipality,
                r.receiver_province
            FROM feedback f
            JOIN booking b ON f.booking_ID = b.booking_ID
            LEFT JOIN pickup p ON b.pickup_ID = p.pickup_ID
            LEFT JOIN receiver r ON b.receiver_ID = r.receiver_ID
            WHERE b.client_ID = :client_id
            ORDER BY f.feed_submitted DESC
        ");

        $stmt->execute([
            'client_id' => (int)$clientId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>