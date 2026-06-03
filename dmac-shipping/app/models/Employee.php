<?php
class Employee {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }


    private function tableExists($table) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1"
        );
        $stmt->execute(['table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static $columnExistsCache = [];

    private function tableHasColumn($table, $column) {
        $cacheKey = "$table.$column";
        if (array_key_exists($cacheKey, self::$columnExistsCache)) {
            return self::$columnExistsCache[$cacheKey];
        }

        $hasColumn = false;
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$table` LIKE :column_name");
            $stmt->execute(['column_name' => $column]);
            $hasColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name
                 LIMIT 1"
            );
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            $hasColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        }

        self::$columnExistsCache[$cacheKey] = $hasColumn;
        return $hasColumn;
    }

    private function getBookingTransportShipmentSelects() {
        $hasShipmentType = $this->tableHasColumn('booking', 'shipment_type');
        $hasTransportId = $this->tableHasColumn('booking', 'transport_ID');

        if ($hasTransportId && $hasShipmentType) {
            $transportSelect = "COALESCE(b.transport_ID, CASE UPPER(COALESCE(b.shipment_type,'')) WHEN 'AIR' THEN 1 WHEN 'LAND' THEN 2 ELSE NULL END) AS transport_ID";
            $shipmentSelect = "COALESCE(b.shipment_type, CASE b.transport_ID WHEN 1 THEN 'AIR' WHEN 2 THEN 'LAND' ELSE 'NOT SET' END) AS shipment_type";
        } elseif ($hasTransportId) {
            $transportSelect = "b.transport_ID AS transport_ID";
            $shipmentSelect = "CASE b.transport_ID WHEN 1 THEN 'AIR' WHEN 2 THEN 'LAND' ELSE 'NOT SET' END AS shipment_type";
        } elseif ($hasShipmentType) {
            $transportSelect = "CASE UPPER(COALESCE(b.shipment_type,'')) WHEN 'AIR' THEN 1 WHEN 'LAND' THEN 2 ELSE NULL END AS transport_ID";
            $shipmentSelect = "COALESCE(b.shipment_type, 'NOT SET') AS shipment_type";
        } else {
            $transportSelect = "NULL AS transport_ID";
            $shipmentSelect = "'NOT SET' AS shipment_type";
        }

        return compact('transportSelect', 'shipmentSelect');
    }

    public function findEmployeeByEmail($email) {
        $sql = "SELECT e.*, r.role
                FROM employee e
                LEFT JOIN emprole er ON e.emp_ID = er.emp_ID
                LEFT JOIN roles r ON er.role_ID = r.role_ID
                WHERE e.emp_email = :email AND e.deleted_at IS NULL
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists($email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM employee WHERE emp_email = :email AND deleted_at IS NULL");
        $stmt->execute(['email' => $email]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function login($email, $password) {
        $emp = $this->findEmployeeByEmail($email);
        return ($emp && password_verify($password, $emp['emp_password'])) ? $emp : false;
    }

    public function getDepartments() {
        return $this->db->query("SELECT dept_ID, dept_name FROM department ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoles() {
        return $this->db->query("SELECT role_ID, role FROM roles ORDER BY role")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoleIdsForEmployee($empId) {
        $stmt = $this->db->prepare("SELECT role_ID FROM emprole WHERE emp_ID = :emp_id ORDER BY role_ID");
        $stmt->execute(['emp_id' => (int)$empId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function setEmployeeRoles($empId, $roleIds) {
        $empId = (int)$empId;
        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)$roleIds))));

        $this->db->prepare("DELETE FROM emprole WHERE emp_ID = :emp_id")->execute(['emp_id' => $empId]);

        if (empty($roleIds)) {
            return true;
        }

        $roleNameStmt = $this->db->prepare("SELECT role FROM roles WHERE role_ID = :role_id LIMIT 1");
        $insertStmt = $this->db->prepare("INSERT INTO emprole (emp_ID, role_ID, is_coordinator) VALUES (:emp_id, :role_id, :is_coordinator)");

        foreach ($roleIds as $roleId) {
            if ($roleId <= 0) {
                continue;
            }

            $roleNameStmt->execute(['role_id' => $roleId]);
            $roleName = strtolower((string)$roleNameStmt->fetchColumn());
            $isCoordinator = strpos($roleName, 'coordinator') !== false ? 1 : 0;

            $insertStmt->execute([
                'emp_id' => $empId,
                'role_id' => $roleId,
                'is_coordinator' => $isCoordinator
            ]);
        }

        return true;
    }

    public function getPermissions() {
        // Uses the newer grouped permissions when the database has these columns.
        // Falls back safely for older databases.
        try {
            $stmt = $this->db->query("SELECT permission_ID, permission_key, permission_name, permission_group, permission_description FROM permissions WHERE permission_key <> 'system_access' ORDER BY permission_group, permission_ID");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $stmt = $this->db->query("SELECT permission_ID, permission_key, permission_name FROM permissions WHERE permission_key <> 'system_access' ORDER BY permission_ID");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $key = $row['permission_key'] ?? '';
                $row['permission_group'] = ucfirst(str_replace('_', ' ', explode('_', $key)[0] ?? 'General'));
                $row['permission_description'] = '';
            }
            return $rows;
        }
    }

    public function getPermissionKeysForEmployee($empId) {
        if ($this->isSuperAdminById($empId)) {
            $stmt = $this->db->query("SELECT permission_key FROM permissions");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $sql = "SELECT p.permission_key
                FROM employee_permissions ep
                JOIN permissions p ON ep.permission_ID = p.permission_ID
                WHERE ep.emp_ID = :emp_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['emp_id' => $empId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function isSuperAdminById($empId) {
        $stmt = $this->db->prepare("SELECT is_super_admin FROM employee WHERE emp_ID = :emp_id LIMIT 1");
        $stmt->execute(['emp_id' => $empId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    public function registerEmployee($data) {
        try {
            $this->db->beginTransaction();

            $status = $data['account_status'] ?? 'approved';
            if (!in_array($status, ['approved','rejected'], true)) {
                $status = 'approved';
            }

            $stmt = $this->db->prepare("INSERT INTO employee
                (dept_ID, emp_firstname, emp_lastname, emp_contact, emp_email, emp_password, account_status, is_super_admin)
                VALUES
                (:dept_id, :firstname, :lastname, :contact, :email, :password, :status, 0)");

            $stmt->execute([
                'dept_id' => $data['dept_id'],
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'contact' => $data['contact'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $status
            ]);

            $empId = (int)$this->db->lastInsertId();

            $roleIds = $data['role_ids'] ?? [$data['role_id'] ?? 0];
            $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)$roleIds))));
            if (empty($roleIds)) {
                $roleIds = [(int)($data['role_id'] ?? 0)];
            }
            $this->setEmployeeRoles($empId, $roleIds);

            if (!empty($data['is_coordinator'])) {
                $firstRoleId = (int)($roleIds[0] ?? 0);
                if ($firstRoleId > 0) {
                    $this->db->prepare("UPDATE emprole SET is_coordinator = 1 WHERE emp_ID = :emp_id AND role_ID = :role_id")
                             ->execute(['emp_id' => $empId, 'role_id' => $firstRoleId]);
                }
            }

            $permissionIds = $data['permission_ids'] ?? [];
            $this->setEmployeePermissions($empId, $permissionIds);

            $this->db->commit();
            return $empId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            return false;
        }
    }

    public function setEmployeePermissions($empId, $permissionIds) {
        $empId = (int)$empId;
        $this->db->prepare("DELETE FROM employee_permissions WHERE emp_ID = :emp_id")->execute(['emp_id' => $empId]);

        if (empty($permissionIds)) {
            return true;
        }

        $stmt = $this->db->prepare("INSERT INTO employee_permissions (emp_ID, permission_ID) VALUES (:emp_id, :permission_id)");
        foreach ($permissionIds as $permissionId) {
            $permissionId = (int)$permissionId;
            if ($permissionId > 0) {
                $stmt->execute(['emp_id' => $empId, 'permission_id' => $permissionId]);
            }
        }

        return true;
    }

    public function getEmployeesWithRoles() {
        $sql = "SELECT 
                    e.emp_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    e.emp_contact,
                    e.emp_email,
                    e.account_status,
                    e.is_super_admin,
                    e.registered_since,
                    d.dept_ID,
                    d.dept_name,
                    GROUP_CONCAT(DISTINCT r.role ORDER BY r.role SEPARATOR ', ') AS roles,
                    GROUP_CONCAT(DISTINCT r.role_ID ORDER BY r.role_ID SEPARATOR ',') AS role_ids,
                    MAX(CASE WHEN COALESCE(er.is_coordinator, 0) = 1 OR LOWER(r.role) LIKE '%coordinator%' THEN 1 ELSE 0 END) AS is_coordinator,
                    GROUP_CONCAT(DISTINCT p.permission_name ORDER BY p.permission_ID SEPARATOR ', ') AS permission_names,
                    GROUP_CONCAT(DISTINCT p.permission_key ORDER BY p.permission_ID SEPARATOR ',') AS permission_keys
                FROM employee e
                LEFT JOIN emprole er ON e.emp_ID = er.emp_ID
                LEFT JOIN roles r ON er.role_ID = r.role_ID
                LEFT JOIN department d ON e.dept_ID = d.dept_ID
                LEFT JOIN employee_permissions ep ON e.emp_ID = ep.emp_ID
                LEFT JOIN permissions p ON ep.permission_ID = p.permission_ID
                WHERE e.deleted_at IS NULL
                GROUP BY 
                    e.emp_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    e.emp_contact,
                    e.emp_email,
                    e.account_status,
                    e.is_super_admin,
                    e.registered_since,
                    d.dept_ID,
                    d.dept_name
                ORDER BY e.is_super_admin DESC, e.emp_ID ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeeById($empId) {
        $sql = "SELECT 
                    e.emp_ID,
                    e.dept_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    e.emp_contact,
                    e.emp_email,
                    e.account_status,
                    e.is_super_admin,
                    e.registered_since,
                    d.dept_name,
                    GROUP_CONCAT(DISTINCT r.role ORDER BY r.role SEPARATOR ', ') AS roles,
                    GROUP_CONCAT(DISTINCT r.role_ID ORDER BY r.role_ID SEPARATOR ',') AS role_ids,
                    MAX(CASE WHEN COALESCE(er.is_coordinator, 0) = 1 OR LOWER(r.role) LIKE '%coordinator%' THEN 1 ELSE 0 END) AS is_coordinator
                FROM employee e
                LEFT JOIN emprole er ON e.emp_ID = er.emp_ID
                LEFT JOIN roles r ON er.role_ID = r.role_ID
                LEFT JOIN department d ON e.dept_ID = d.dept_ID
                WHERE e.emp_ID = :emp_id AND e.deleted_at IS NULL
                GROUP BY 
                    e.emp_ID,
                    e.dept_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    e.emp_contact,
                    e.emp_email,
                    e.account_status,
                    e.is_super_admin,
                    e.registered_since,
                    d.dept_name
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['emp_id' => (int)$empId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPermissionIdsForEmployee($empId) {
        $stmt = $this->db->prepare("SELECT permission_ID FROM employee_permissions WHERE emp_ID = :emp_id");
        $stmt->execute(['emp_id' => (int)$empId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function updateEmployeeAccess($empId, $data) {
        $empId = (int)$empId;
        if ($empId <= 0) return false;

        $current = $this->getEmployeeById($empId);
        if (!$current) return false;

        // Super Admin account should not be downgraded from this page.
        if ((int)$current['is_super_admin'] === 1) {
            return false;
        }

        $status = $data['account_status'] ?? 'approved';
        if (!in_array($status, ['approved','rejected'], true)) {
            $status = 'approved';
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE employee
                SET dept_ID = :dept_id,
                    emp_firstname = :firstname,
                    emp_lastname = :lastname,
                    emp_contact = :contact,
                    account_status = :status
                WHERE emp_ID = :emp_id AND is_super_admin = 0");
            $stmt->execute([
                'dept_id' => (int)$data['dept_id'],
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'contact' => $data['contact'],
                'status' => $status,
                'emp_id' => $empId
            ]);

            $roleIds = $data['role_ids'] ?? [];
            if (!empty($roleIds)) {
                $this->setEmployeeRoles($empId, $roleIds);
            }

            $this->setEmployeePermissions($empId, $data['permission_ids'] ?? []);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            return false;
        }
    }

    public function getDashboardStats() {
        $stats = [];

        $queries = [
            'pending_bookings' => "SELECT COUNT(*) FROM booking WHERE booking_status = 'PENDING REVIEW'",
            'for_pickup' => "SELECT COUNT(*) FROM booking WHERE booking_status = 'FOR PICK-UP'",
            'processing' => "SELECT COUNT(*) FROM booking WHERE booking_status = 'PROCESSING'",
            'preparing_for_transit' => "SELECT COUNT(*) FROM booking WHERE booking_status = 'PREPARING FOR TRANSIT'",
            'in_transit' => "SELECT COUNT(*) FROM booking WHERE booking_status = 'IN TRANSIT'",
            'delivered' => "SELECT COUNT(*) FROM booking WHERE booking_status IN ('DELIVERED/SHIPPED', 'COMPLETED', 'DELIVERED', 'SHIPPED')",
            'feedback_count' => "SELECT COUNT(*) FROM feedback"
        ];

        foreach ($queries as $key => $sql) {
            $stats[$key] = (int)$this->db->query($sql)->fetchColumn();
        }

        $stats['average_rating'] = number_format(
            (float)$this->db->query("SELECT COALESCE(AVG(feed_rate), 0) FROM feedback")->fetchColumn(),
            1
        );

        // Backward-compatible keys for older dashboard cards/pages.
        $stats['total'] = (int)$this->db->query("SELECT COUNT(*) FROM booking")->fetchColumn();
        $stats['pending'] = $stats['pending_bookings'];
        $stats['active'] = $stats['for_pickup'] + $stats['processing'] + $stats['preparing_for_transit'] + $stats['in_transit'];
        $stats['completed'] = $stats['delivered'];
        $stats['feedback'] = $stats['feedback_count'];
        $stats['rating'] = $stats['average_rating'];

        return $stats;
    }

    public function getPendingBookings() {
        $sql = "SELECT 
                    b.booking_ID,
                    b.booking_requestdate,
                    b.booking_startdate,
                    b.booking_status,
                    c.client_firstname,
                    c.client_lastname,
                    p.pickup_municipality,
                    p.pickup_province,
                    r.receiver_street,
                    r.receiver_municipality,
                    r.receiver_province,
                    COALESCE(SUM(ab.animalbatch_quantity), 0) AS total_quantity,
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT CONCAT(a.animal_type, ' - ', ab.animalbatch_quantity, ' heads')
                            ORDER BY a.animal_type SEPARATOR ', '
                        ),
                        'No animals listed'
                    ) AS animal_details
                FROM booking b
                JOIN client c ON b.client_ID = c.client_ID
                JOIN pickup p ON b.pickup_ID = p.pickup_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN animalbatch ab ON b.booking_ID = ab.booking_ID
                LEFT JOIN animal a ON ab.animal_ID = a.animal_ID
                WHERE b.booking_status = 'PENDING REVIEW'
                GROUP BY 
                    b.booking_ID, b.booking_requestdate, b.booking_startdate, b.booking_status,
                    c.client_firstname, c.client_lastname,
                    p.pickup_municipality, p.pickup_province,
                    r.receiver_street, r.receiver_municipality, r.receiver_province
                ORDER BY b.booking_startdate ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableDrivers() {
        $sql = "SELECT e.emp_ID,e.emp_firstname,e.emp_lastname FROM employee e JOIN emprole er ON e.emp_ID=er.emp_ID JOIN roles r ON er.role_ID=r.role_ID
                WHERE UPPER(r.role)='DRIVER' AND e.deleted_at IS NULL AND e.account_status='approved' ORDER BY e.emp_firstname";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssignableEmployees() {
        $sql = "SELECT e.emp_ID, e.emp_firstname, e.emp_lastname, COALESCE(r.role, 'Employee') AS role
                FROM employee e
                LEFT JOIN emprole er ON e.emp_ID = er.emp_ID
                LEFT JOIN roles r ON er.role_ID = r.role_ID
                WHERE e.deleted_at IS NULL
                  AND e.account_status = 'approved'
                  AND e.is_super_admin = 0
                  AND COALESCE(er.is_coordinator, 0) = 1
                ORDER BY r.role, e.emp_firstname, e.emp_lastname";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function getBookingsForCoordinatorAssignments() {
        extract($this->getBookingTransportShipmentSelects());

        $airSelect = ",
                       NULL AS air_emp_ID,
                       NULL AS shipper_agent,
                       NULL AS airline_name,
                       NULL AS flight_reference_number";
        $airJoin = "";
        $airGroup = "";

        if ($this->tableExists('airdetails') && $this->tableHasColumn('booking', 'airdetails_ID')) {
            $airJoin = " LEFT JOIN airdetails ad ON b.airdetails_ID = ad.airdetails_ID ";
            if ($this->tableHasColumn('airdetails', 'emp_ID')) {
                $airJoin .= " LEFT JOIN employee air_emp ON ad.emp_ID = air_emp.emp_ID ";
            }

            $airSelect = ",
                       " . ($this->tableHasColumn('airdetails', 'emp_ID') ? "ad.emp_ID" : "NULL") . " AS air_emp_ID,
                       " . ($this->tableHasColumn('airdetails', 'emp_ID') ? "CONCAT(air_emp.emp_firstname, ' ', air_emp.emp_lastname)" : "NULL") . " AS shipper_agent,
                       " . ($this->tableHasColumn('airdetails', 'airlines') ? "ad.airlines" : "NULL") . " AS airline_name,
                       " . ($this->tableHasColumn('airdetails', 'airdetails_reference') ? "ad.airdetails_reference" : "NULL") . " AS flight_reference_number";

            $airGroup = "";
            if ($this->tableHasColumn('airdetails', 'emp_ID')) {
                $airGroup .= ", ad.emp_ID, air_emp.emp_firstname, air_emp.emp_lastname";
            }
            if ($this->tableHasColumn('airdetails', 'airlines')) {
                $airGroup .= ", ad.airlines";
            }
            if ($this->tableHasColumn('airdetails', 'airdetails_reference')) {
                $airGroup .= ", ad.airdetails_reference";
            }
        }

        $landSelect = ",
                       NULL AS driver_emp_ID,
                       NULL AS driver_firstname,
                       NULL AS driver_lastname,
                       NULL AS vehicle_ID,
                       NULL AS vehicle_type,
                       NULL AS vehicle_plate_number,
                       NULL AS vehicle_license_permit";
        $landJoin = "";
        $landGroup = "";

        if ($this->tableExists('landdetails') && $this->tableHasColumn('booking', 'landdetails_ID')) {
            $landJoin = " LEFT JOIN landdetails ld ON b.landdetails_ID = ld.landdetails_ID ";

            if ($this->tableHasColumn('landdetails', 'emp_ID')) {
                $landJoin .= " LEFT JOIN employee drv ON ld.emp_ID = drv.emp_ID ";
            }

            if ($this->tableHasColumn('landdetails', 'vehicle_ID') && $this->tableExists('vehicle')) {
                $landJoin .= " LEFT JOIN vehicle v ON ld.vehicle_ID = v.vehicle_ID ";
            }

            $plateExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_platenumber') ? "v.vehicle_platenumber" :
                ($this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_plate_number') ? "v.vehicle_plate_number" : "NULL");

            $typeExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_type') ? "v.vehicle_type" : "NULL";
            $permitExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_licensepermit') ? "v.vehicle_licensepermit" :
                ($this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_license_permit') ? "v.vehicle_license_permit" : "NULL");

            $landSelect = ",
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "ld.emp_ID" : "NULL") . " AS driver_emp_ID,
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "drv.emp_firstname" : "NULL") . " AS driver_firstname,
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "drv.emp_lastname" : "NULL") . " AS driver_lastname,
                       " . ($this->tableHasColumn('landdetails', 'vehicle_ID') ? "ld.vehicle_ID" : "NULL") . " AS vehicle_ID,
                       $typeExpr AS vehicle_type,
                       $plateExpr AS vehicle_plate_number,
                       $permitExpr AS vehicle_license_permit";

            if ($this->tableHasColumn('landdetails', 'emp_ID')) {
                $landGroup .= ", ld.emp_ID, drv.emp_firstname, drv.emp_lastname";
            }
            if ($this->tableHasColumn('landdetails', 'vehicle_ID')) {
                $landGroup .= ", ld.vehicle_ID";
            }
            if ($plateExpr !== "NULL") $landGroup .= ", $plateExpr";
            if ($typeExpr !== "NULL") $landGroup .= ", $typeExpr";
            if ($permitExpr !== "NULL") $landGroup .= ", $permitExpr";
        }

        $sql = "SELECT b.booking_ID, b.booking_status, b.booking_requestdate, b.booking_startdate,
                       $transportSelect,
                       $shipmentSelect,
                       c.client_firstname, c.client_lastname,
                       p.pickup_municipality, p.pickup_province,
                       r.receiver_municipality, r.receiver_province,
                       COALESCE(SUM(ab.animalbatch_quantity), 0) AS total_heads
                       $airSelect
                       $landSelect
                FROM booking b
                JOIN client c ON b.client_ID = c.client_ID
                JOIN pickup p ON b.pickup_ID = p.pickup_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN animalbatch ab ON b.booking_ID = ab.booking_ID
                $airJoin
                $landJoin
                WHERE b.booking_status NOT IN ('CANCELLED','DELIVERED/SHIPPED','DELIVERED','SHIPPED')
                GROUP BY b.booking_ID, b.booking_status, b.booking_requestdate, b.booking_startdate,
                         c.client_firstname, c.client_lastname,
                         p.pickup_municipality, p.pickup_province, r.receiver_municipality, r.receiver_province
                         $airGroup
                         $landGroup
                ORDER BY b.booking_startdate DESC, b.booking_ID DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }




    public function getAssignmentsForBookings($bookingIds = []) {
        if (empty($bookingIds)) {
            return [];
        }

        $bookingIds = array_values(array_unique(array_map('intval', $bookingIds)));
        $bookingIds = array_filter($bookingIds, fn($id) => $id > 0);
        if (empty($bookingIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $sql = "SELECT ba.bookingassignment_ID, ba.booking_ID, ba.emp_ID, ba.process_stage, ba.assigned_at,
                       ba.assigned_by_emp_ID, ba.notes,
                       e.emp_firstname, e.emp_lastname,
                       assigner.emp_firstname AS assigned_by_firstname,
                       assigner.emp_lastname AS assigned_by_lastname
                FROM bookingassignment ba
                JOIN employee e ON ba.emp_ID = e.emp_ID
                LEFT JOIN employee assigner ON ba.assigned_by_emp_ID = assigner.emp_ID
                WHERE ba.booking_ID IN ($placeholders)
                ORDER BY ba.booking_ID DESC, FIELD(ba.process_stage, 'PICKUP','PROCESSING','IN_TRANSIT','ARRIVAL','DELIVERED'), ba.assigned_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bookingIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['booking_ID']][] = $row;
        }
        return $grouped;
    }

    public function getCurrentStageAssignments($bookingIds = []) {
        $history = $this->getAssignmentsForBookings($bookingIds);
        $current = [];
        foreach ($history as $bookingId => $rows) {
            foreach ($rows as $row) {
                $stage = $row['process_stage'];
                if (!isset($current[$bookingId][$stage])) {
                    $current[$bookingId][$stage] = $row;
                }
            }
        }
        return $current;
    }

    public function assignCoordinatorToStage($bookingId, $stage, $empId, $assignedByEmpId, $notes = '') {
        $bookingId = (int)$bookingId;
        $empId = (int)$empId;
        $assignedByEmpId = (int)$assignedByEmpId;
        $stage = strtoupper(trim((string)$stage));
        $notes = trim((string)$notes);

        $allowedStages = ['PICKUP','PROCESSING','IN_TRANSIT','ARRIVAL','DELIVERED'];
        if ($bookingId <= 0 || $empId <= 0 || !in_array($stage, $allowedStages, true)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $bookingCheck = $this->db->prepare("SELECT COUNT(*) FROM booking WHERE booking_ID = ?");
            $bookingCheck->execute([$bookingId]);
            if ((int)$bookingCheck->fetchColumn() === 0) {
                $this->db->rollBack();
                return false;
            }

            $employeeCheck = $this->db->prepare("
                SELECT COUNT(*)
                FROM employee e
                JOIN emprole er ON e.emp_ID = er.emp_ID
                WHERE e.emp_ID = ?
                  AND e.deleted_at IS NULL
                  AND e.account_status = 'approved'
                  AND COALESCE(er.is_coordinator, 0) = 1
            ");
            $employeeCheck->execute([$empId]);
            if ((int)$employeeCheck->fetchColumn() === 0) {
                $this->db->rollBack();
                return false;
            }

            $stmt = $this->db->prepare("INSERT INTO bookingassignment
                (booking_ID, emp_ID, assigned_by_emp_ID, process_stage, assigned_at, notes)
                VALUES
                (:booking_id, :emp_id, :assigned_by, :stage, NOW(), :notes)");
            $stmt->execute([
                'booking_id' => $bookingId,
                'emp_id' => $empId,
                'assigned_by' => $assignedByEmpId > 0 ? $assignedByEmpId : null,
                'stage' => $stage,
                'notes' => $notes !== '' ? $notes : null
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            return false;
        }
    }

    

    public function getActiveShipments() {
        extract($this->getBookingTransportShipmentSelects());

        $hasTransportId = $this->tableHasColumn('booking', 'transport_ID');
        $hasAirdetailsId = $this->tableHasColumn('booking', 'airdetails_ID');
        $hasLanddetailsId = $this->tableHasColumn('booking', 'landdetails_ID');

        $detailStatusConditions = [];
        if ($hasTransportId) {
            $detailStatusConditions[] = 'b.transport_ID IS NULL';
            $detailStatusConditions[] = 'b.transport_ID = 0';
        }
        if ($hasAirdetailsId && $hasTransportId) {
            $detailStatusConditions[] = '(b.transport_ID = 1 AND (b.airdetails_ID IS NULL OR b.airdetails_ID = 0))';
        } elseif ($hasAirdetailsId) {
            $detailStatusConditions[] = 'b.airdetails_ID IS NULL OR b.airdetails_ID = 0';
        }
        if ($hasLanddetailsId && $hasTransportId) {
            $detailStatusConditions[] = '(b.transport_ID = 2 AND (b.landdetails_ID IS NULL OR b.landdetails_ID = 0))';
        } elseif ($hasLanddetailsId) {
            $detailStatusConditions[] = 'b.landdetails_ID IS NULL OR b.landdetails_ID = 0';
        }

        $activeShipmentWhere = empty($detailStatusConditions)
            ? '1 = 1'
            : implode("\n                        OR ", $detailStatusConditions);

        $airSelect = ",
                       NULL AS air_emp_ID,
                       NULL AS shipper_agent,
                       NULL AS airline_name,
                       NULL AS flight_reference_number";
        $airJoin = "";
        $airGroup = "";

        if ($this->tableExists('airdetails') && $this->tableHasColumn('booking', 'airdetails_ID')) {
            $airJoin = " LEFT JOIN airdetails ad ON b.airdetails_ID = ad.airdetails_ID ";
            if ($this->tableHasColumn('airdetails', 'emp_ID')) {
                $airJoin .= " LEFT JOIN employee air_emp ON ad.emp_ID = air_emp.emp_ID ";
            }

            $airSelect = ",
                       " . ($this->tableHasColumn('airdetails', 'emp_ID') ? "ad.emp_ID" : "NULL") . " AS air_emp_ID,
                       " . ($this->tableHasColumn('airdetails', 'emp_ID') ? "CONCAT(air_emp.emp_firstname, ' ', air_emp.emp_lastname)" : "NULL") . " AS shipper_agent,
                       " . ($this->tableHasColumn('airdetails', 'airlines') ? "ad.airlines" : "NULL") . " AS airline_name,
                       " . ($this->tableHasColumn('airdetails', 'airdetails_reference') ? "ad.airdetails_reference" : "NULL") . " AS flight_reference_number";

            if ($this->tableHasColumn('airdetails', 'emp_ID')) {
                $airGroup .= ", ad.emp_ID, air_emp.emp_firstname, air_emp.emp_lastname";
            }
            if ($this->tableHasColumn('airdetails', 'airlines')) {
                $airGroup .= ", ad.airlines";
            }
            if ($this->tableHasColumn('airdetails', 'airdetails_reference')) {
                $airGroup .= ", ad.airdetails_reference";
            }
        }

        $landSelect = ",
                       NULL AS driver_emp_ID,
                       NULL AS driver_firstname,
                       NULL AS driver_lastname,
                       NULL AS vehicle_ID,
                       NULL AS vehicle_type,
                       NULL AS vehicle_plate_number,
                       NULL AS vehicle_license_permit";
        $landJoin = "";
        $landGroup = "";

        if ($this->tableExists('landdetails') && $this->tableHasColumn('booking', 'landdetails_ID')) {
            $landJoin = " LEFT JOIN landdetails ld ON b.landdetails_ID = ld.landdetails_ID ";

            if ($this->tableHasColumn('landdetails', 'emp_ID')) {
                $landJoin .= " LEFT JOIN employee drv ON ld.emp_ID = drv.emp_ID ";
            }

            if ($this->tableHasColumn('landdetails', 'vehicle_ID') && $this->tableExists('vehicle')) {
                $landJoin .= " LEFT JOIN vehicle v ON ld.vehicle_ID = v.vehicle_ID ";
            }

            $plateExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_platenumber') ? "v.vehicle_platenumber" :
                ($this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_plate_number') ? "v.vehicle_plate_number" : "NULL");

            $typeExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_type') ? "v.vehicle_type" : "NULL";
            $permitExpr = $this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_licensepermit') ? "v.vehicle_licensepermit" :
                ($this->tableExists('vehicle') && $this->tableHasColumn('vehicle', 'vehicle_license_permit') ? "v.vehicle_license_permit" : "NULL");

            $landSelect = ",
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "ld.emp_ID" : "NULL") . " AS driver_emp_ID,
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "drv.emp_firstname" : "NULL") . " AS driver_firstname,
                       " . ($this->tableHasColumn('landdetails', 'emp_ID') ? "drv.emp_lastname" : "NULL") . " AS driver_lastname,
                       " . ($this->tableHasColumn('landdetails', 'vehicle_ID') ? "ld.vehicle_ID" : "NULL") . " AS vehicle_ID,
                       $typeExpr AS vehicle_type,
                       $plateExpr AS vehicle_plate_number,
                       $permitExpr AS vehicle_license_permit";

            if ($this->tableHasColumn('landdetails', 'emp_ID')) {
                $landGroup .= ", ld.emp_ID, drv.emp_firstname, drv.emp_lastname";
            }
            if ($this->tableHasColumn('landdetails', 'vehicle_ID')) {
                $landGroup .= ", ld.vehicle_ID";
            }
            if ($plateExpr !== "NULL") $landGroup .= ", $plateExpr";
            if ($typeExpr !== "NULL") $landGroup .= ", $typeExpr";
            if ($permitExpr !== "NULL") $landGroup .= ", $permitExpr";
        }

        $sql = "SELECT b.booking_ID,
                       b.booking_status,
                       b.booking_requestdate,
                       b.booking_startdate,
                       b.booking_enddate,
                       $transportSelect,
                       $shipmentSelect,
                       c.client_firstname,
                       c.client_lastname,
                       p.pickup_municipality,
                       p.pickup_province,
                       r.receiver_street,
                       r.receiver_municipality,
                       r.receiver_province,
                       COALESCE(SUM(ab.animalbatch_quantity), 0) AS total_heads
                       $airSelect
                       $landSelect
                FROM booking b
                JOIN client c ON b.client_ID = c.client_ID
                JOIN pickup p ON b.pickup_ID = p.pickup_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN animalbatch ab ON b.booking_ID = ab.booking_ID
                $airJoin
                $landJoin
                WHERE b.booking_status = 'FOR PICK-UP'
                  AND (
                        $activeShipmentWhere
                      )
                GROUP BY b.booking_ID,
                         b.booking_status,
                         b.booking_requestdate,
                         b.booking_startdate,
                         b.booking_enddate,
                         c.client_firstname,
                         c.client_lastname,
                         p.pickup_municipality,
                         p.pickup_province,
                         r.receiver_street,
                         r.receiver_municipality,
                         r.receiver_province
                         $airGroup
                         $landGroup
                ORDER BY b.booking_startdate DESC, b.booking_ID DESC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }




    public function updateBookingStatus($bookingId, $status) {
        $allowed = ['FOR PICK-UP','PROCESSING','PREPARING FOR TRANSIT','IN TRANSIT','DELIVERED/SHIPPED','CANCELLED'];
        if (!in_array($status, $allowed, true)) return false;

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "UPDATE booking
                 SET booking_status = :status,
                     booking_enddate = IF(:is_done = 1, CURDATE(), booking_enddate)
                 WHERE booking_ID = :booking_id"
            )->execute([
                'status' => $status,
                'is_done' => $status === 'DELIVERED/SHIPPED' ? 1 : 0,
                'booking_id' => $bookingId
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    

    public function getTransportCoordinators() {
        $hasCoordinatorFlag = $this->tableHasColumn('emprole', 'is_coordinator');

        $whereCoordinator = $hasCoordinatorFlag
            ? "AND COALESCE(er.is_coordinator, 0) = 1"
            : "";

        $sql = "SELECT DISTINCT e.emp_ID, e.emp_firstname, e.emp_lastname, COALESCE(r.role, 'Employee') AS role
                FROM employee e
                LEFT JOIN emprole er ON e.emp_ID = er.emp_ID
                LEFT JOIN roles r ON er.role_ID = r.role_ID
                WHERE e.deleted_at IS NULL
                  AND e.account_status = 'approved'
                  AND e.is_super_admin = 0
                  $whereCoordinator
                ORDER BY e.emp_firstname, e.emp_lastname";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAirShipperAgents() {
        // Air travel must only show employees/companies with a Shipper Agent role.
        $sql = "SELECT DISTINCT 
                    e.emp_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    COALESCE(r.role, 'Shipper Agent') AS role
                FROM employee e
                JOIN emprole er ON e.emp_ID = er.emp_ID
                JOIN roles r ON er.role_ID = r.role_ID
                WHERE e.deleted_at IS NULL
                  AND e.account_status = 'approved'
                  AND LOWER(r.role) = 'shipper agent'
                ORDER BY e.emp_firstname, e.emp_lastname";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLandDrivers() {
        // Land travel must only show employees with a Driver role.
        // The assignment dropdown should not show all coordinators here.
        $sql = "SELECT DISTINCT 
                    e.emp_ID,
                    e.emp_firstname,
                    e.emp_lastname,
                    COALESCE(r.role, 'Driver') AS role
                FROM employee e
                JOIN emprole er ON e.emp_ID = er.emp_ID
                JOIN roles r ON er.role_ID = r.role_ID
                WHERE e.deleted_at IS NULL
                  AND e.account_status = 'approved'
                  AND LOWER(r.role) = 'driver'
                ORDER BY e.emp_firstname, e.emp_lastname";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVehicles() {
        if (!$this->tableExists('vehicle')) {
            return [];
        }

        $plateCol = $this->tableHasColumn('vehicle', 'vehicle_platenumber') ? 'vehicle_platenumber' :
            ($this->tableHasColumn('vehicle', 'vehicle_plate_number') ? 'vehicle_plate_number' : null);

        $typeCol = $this->tableHasColumn('vehicle', 'vehicle_type') ? 'vehicle_type' : null;
        $permitCol = $this->tableHasColumn('vehicle', 'vehicle_licensepermit') ? 'vehicle_licensepermit' :
            ($this->tableHasColumn('vehicle', 'vehicle_license_permit') ? 'vehicle_license_permit' : null);

        $select = "vehicle_ID";
        $select .= $plateCol ? ", $plateCol AS vehicle_plate_number" : ", NULL AS vehicle_plate_number";
        $select .= $typeCol ? ", $typeCol AS vehicle_type" : ", NULL AS vehicle_type";
        $select .= $permitCol ? ", $permitCol AS vehicle_license_permit" : ", NULL AS vehicle_license_permit";

        $sql = "SELECT $select FROM vehicle ORDER BY vehicle_plate_number, vehicle_ID";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }



    public function getCouriers() {
        if (!$this->tableExists('couriers')) {
            return [];
        }

        $columns = [
            'courier_ID' => 'courier_ID',
            'courier_type' => 'courier_type',
            'courier_name' => 'courier_name',
            'agent_name' => 'agent_name',
            'airline_name' => 'airline_name',
            'vehicle_plate_number' => 'vehicle_plate_number',
            'vehicle_license_permit' => 'vehicle_license_permit',
            'permit_expiry_date' => 'permit_expiry_date'
        ];

        $select = [];
        foreach ($columns as $alias => $column) {
            $select[] = $this->tableHasColumn('couriers', $column)
                ? "$column AS $alias"
                : "NULL AS $alias";
        }

        $sql = "SELECT " . implode(", ", $select) . " FROM couriers ORDER BY courier_type, courier_name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function updateShipmentManagement($bookingId, $status, $transportId, array $details = []) {
        $allowedStatuses = ['FOR PICK-UP','PROCESSING','PREPARING FOR TRANSIT','IN TRANSIT','DELIVERED/SHIPPED','CANCELLED'];
        $transportId = (int)$transportId;
        $shipmentType = $transportId === 1 ? 'AIR' : ($transportId === 2 ? 'LAND' : '');

        if ($bookingId <= 0 || !in_array($status, $allowedStatuses, true) || !in_array($transportId, [1, 2], true)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $airdetailsId = null;
            $landdetailsId = null;

            if ($transportId === 1) {
                if (!$this->tableExists('airdetails') || !$this->tableHasColumn('booking', 'airdetails_ID')) {
                    $this->db->rollBack();
                    return false;
                }

                $airEmpId = (int)($details['air_emp_ID'] ?? 0);
                $airline = trim((string)($details['airline'] ?? ''));
                $flightReference = trim((string)($details['flight_reference_number'] ?? ''));

                if ($airEmpId <= 0 || $airline === '' || $flightReference === '') {
                    $this->db->rollBack();
                    return false;
                }

                $employeeCheck = $this->db->prepare("SELECT COUNT(*)
                    FROM employee e
                    JOIN emprole er ON e.emp_ID = er.emp_ID
                    JOIN roles r ON er.role_ID = r.role_ID
                    WHERE e.emp_ID = :emp_id
                      AND e.deleted_at IS NULL
                      AND e.account_status = 'approved'
                      AND LOWER(r.role) = 'shipper agent'");
                $employeeCheck->execute(['emp_id' => $airEmpId]);
                if ((int)$employeeCheck->fetchColumn() === 0) {
                    $this->db->rollBack();
                    return false;
                }

                $existingStmt = $this->db->prepare("SELECT airdetails_ID FROM booking WHERE booking_ID = :booking_id LIMIT 1");
                $existingStmt->execute(['booking_id' => $bookingId]);
                $airdetailsId = $existingStmt->fetchColumn();

                if ($airdetailsId) {
                    $checkStmt = $this->db->prepare("SELECT 1 FROM airdetails WHERE airdetails_ID = :id LIMIT 1");
                    $checkStmt->execute(['id' => $airdetailsId]);
                    if ($checkStmt->fetchColumn()) {
                        $stmt = $this->db->prepare("UPDATE airdetails
                            SET transport_ID = 1,
                                emp_ID = :emp_id,
                                airlines = :airline,
                                airdetails_reference = :reference
                            WHERE airdetails_ID = :id");
                        $stmt->execute([
                            'emp_id' => $airEmpId,
                            'airline' => $airline,
                            'reference' => $flightReference,
                            'id' => $airdetailsId
                        ]);
                    } else {
                        $airdetailsId = null;
                    }
                }

                if (!$airdetailsId) {
                    $stmt = $this->db->prepare("INSERT INTO airdetails (transport_ID, emp_ID, airlines, airdetails_reference)
                        VALUES (1, :emp_id, :airline, :reference)");
                    $stmt->execute([
                        'emp_id' => $airEmpId,
                        'airline' => $airline,
                        'reference' => $flightReference
                    ]);
                    $airdetailsId = (int)$this->db->lastInsertId();
                }
            }

            if ($transportId === 2) {
                if (!$this->tableExists('landdetails') || !$this->tableHasColumn('booking', 'landdetails_ID')) {
                    $this->db->rollBack();
                    return false;
                }

                $driverEmpId = (int)($details['driver_emp_ID'] ?? 0);
                $vehicleId = (int)($details['vehicle_ID'] ?? 0);

                if ($driverEmpId <= 0 || $vehicleId <= 0) {
                    $this->db->rollBack();
                    return false;
                }

                $driverStmt = $this->db->prepare("SELECT COUNT(*) FROM employee WHERE emp_ID = :emp_id AND deleted_at IS NULL AND account_status = 'approved'");
                $driverStmt->execute(['emp_id' => $driverEmpId]);
                if ((int)$driverStmt->fetchColumn() === 0) {
                    $this->db->rollBack();
                    return false;
                }

                $vehicleStmt = $this->db->prepare("SELECT COUNT(*) FROM vehicle WHERE vehicle_ID = :vehicle_id");
                $vehicleStmt->execute(['vehicle_id' => $vehicleId]);
                if ((int)$vehicleStmt->fetchColumn() === 0) {
                    $this->db->rollBack();
                    return false;
                }

                $existingStmt = $this->db->prepare("SELECT landdetails_ID FROM booking WHERE booking_ID = :booking_id LIMIT 1");
                $existingStmt->execute(['booking_id' => $bookingId]);
                $landdetailsId = $existingStmt->fetchColumn();

                if ($landdetailsId) {
                    $checkStmt = $this->db->prepare("SELECT 1 FROM landdetails WHERE landdetails_ID = :id LIMIT 1");
                    $checkStmt->execute(['id' => $landdetailsId]);
                    if ($checkStmt->fetchColumn()) {
                        $stmt = $this->db->prepare("UPDATE landdetails
                            SET transport_ID = 2,
                                vehicle_ID = :vehicle_id,
                                emp_ID = :emp_id
                            WHERE landdetails_ID = :id");
                        $stmt->execute([
                            'vehicle_id' => $vehicleId,
                            'emp_id' => $driverEmpId,
                            'id' => $landdetailsId
                        ]);
                    } else {
                        $landdetailsId = null;
                    }
                }

                if (!$landdetailsId) {
                    $stmt = $this->db->prepare("INSERT INTO landdetails (transport_ID, vehicle_ID, emp_ID)
                        VALUES (2, :vehicle_id, :emp_id)");
                    $stmt->execute([
                        'vehicle_id' => $vehicleId,
                        'emp_id' => $driverEmpId
                    ]);
                    $landdetailsId = (int)$this->db->lastInsertId();
                }
            }

            $sets = [
                "booking_status = :status",
                "booking_enddate = IF(:is_done = 1, CURDATE(), booking_enddate)"
            ];

            $params = [
                'status' => $status,
                'is_done' => $status === 'DELIVERED/SHIPPED' ? 1 : 0,
                'booking_id' => $bookingId
            ];

            if ($this->tableHasColumn('booking', 'transport_ID')) {
                $sets[] = "transport_ID = :transport_id";
                $params['transport_id'] = $transportId;
            }

            if ($this->tableHasColumn('booking', 'shipment_type')) {
                $sets[] = "shipment_type = :shipment_type";
                $params['shipment_type'] = $shipmentType;
            }

            if ($this->tableHasColumn('booking', 'airdetails_ID')) {
                $sets[] = "airdetails_ID = :airdetails_id";
                $params['airdetails_id'] = $transportId === 1 ? $airdetailsId : null;
            }

            if ($this->tableHasColumn('booking', 'landdetails_ID')) {
                $sets[] = "landdetails_ID = :landdetails_id";
                $params['landdetails_id'] = $transportId === 2 ? $landdetailsId : null;
            }

            $sql = "UPDATE booking SET " . implode(", ", $sets) . " WHERE booking_ID = :booking_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }


    public function getBookingRecords() {
        extract($this->getBookingTransportShipmentSelects());

        $airSelect = ", NULL AS shipper_agent, NULL AS airline_name, NULL AS flight_reference_number";
        $airJoin = "";
        $airGroup = "";
        if ($this->tableExists('airdetails') && $this->tableHasColumn('booking', 'airdetails_ID')) {
            $airJoin = " LEFT JOIN airdetails ad ON b.airdetails_ID = ad.airdetails_ID LEFT JOIN employee air_emp ON ad.emp_ID = air_emp.emp_ID ";
            $airSelect = ", CONCAT(air_emp.emp_firstname, ' ', air_emp.emp_lastname) AS shipper_agent, ad.airlines AS airline_name, ad.airdetails_reference AS flight_reference_number";
            $airGroup = ", air_emp.emp_firstname, air_emp.emp_lastname, ad.airlines, ad.airdetails_reference";
        }

        $landSelect = ", NULL AS driver_firstname, NULL AS driver_lastname, NULL AS vehicle_type, NULL AS vehicle_plate_number, NULL AS vehicle_license_permit";
        $landJoin = "";
        $landGroup = "";
        if ($this->tableExists('landdetails') && $this->tableHasColumn('booking', 'landdetails_ID')) {
            $landJoin = " LEFT JOIN landdetails ld ON b.landdetails_ID = ld.landdetails_ID LEFT JOIN employee drv ON ld.emp_ID = drv.emp_ID LEFT JOIN vehicle v ON ld.vehicle_ID = v.vehicle_ID ";
            $plateExpr = $this->tableHasColumn('vehicle', 'vehicle_platenumber') ? "v.vehicle_platenumber" : ($this->tableHasColumn('vehicle', 'vehicle_plate_number') ? "v.vehicle_plate_number" : "NULL");
            $permitExpr = $this->tableHasColumn('vehicle', 'vehicle_licensepermit') ? "v.vehicle_licensepermit" : ($this->tableHasColumn('vehicle', 'vehicle_license_permit') ? "v.vehicle_license_permit" : "NULL");
            $typeExpr = $this->tableHasColumn('vehicle', 'vehicle_type') ? "v.vehicle_type" : "NULL";
            $landSelect = ", drv.emp_firstname AS driver_firstname, drv.emp_lastname AS driver_lastname, $typeExpr AS vehicle_type, $plateExpr AS vehicle_plate_number, $permitExpr AS vehicle_license_permit";
            $landGroup = ", drv.emp_firstname, drv.emp_lastname, $typeExpr, $plateExpr, $permitExpr";
        }

        $sql = "SELECT b.booking_ID,
                       b.booking_status,
                       b.booking_requestdate,
                       b.booking_startdate,
                       b.booking_enddate,
                       $transportSelect,
                       $shipmentSelect,
                       c.client_firstname,
                       c.client_lastname,
                       p.pickup_street,
                       p.pickup_municipality,
                       p.pickup_province,
                       r.receiver_street,
                       r.receiver_municipality,
                       r.receiver_province,
                       COALESCE(SUM(ab.animalbatch_quantity), 0) AS total_heads,
                       COALESCE(GROUP_CONCAT(DISTINCT CONCAT(a.animal_type, ' - ', ab.animalbatch_quantity, ' heads') ORDER BY a.animal_type SEPARATOR ', '), 'No animals listed') AS animal_details
                       $airSelect
                       $landSelect
                FROM booking b
                JOIN client c ON b.client_ID = c.client_ID
                JOIN pickup p ON b.pickup_ID = p.pickup_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN animalbatch ab ON b.booking_ID = ab.booking_ID
                LEFT JOIN animal a ON ab.animal_ID = a.animal_ID
                $airJoin
                $landJoin
                WHERE b.booking_status IN ('DELIVERED/SHIPPED','DELIVERED','SHIPPED')
                GROUP BY b.booking_ID,
                         b.booking_status,
                         b.booking_requestdate,
                         b.booking_startdate,
                         b.booking_enddate,
                         c.client_firstname,
                         c.client_lastname,
                         p.pickup_street,
                         p.pickup_municipality,
                         p.pickup_province,
                         r.receiver_street,
                         r.receiver_municipality,
                         r.receiver_province
                         $airGroup
                         $landGroup
                ORDER BY b.booking_enddate DESC, b.booking_ID DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllShipments($limit=100) {
        $stmt = $this->db->prepare("SELECT b.booking_ID,b.booking_status,b.booking_requestdate,c.client_firstname,c.client_lastname,p.pickup_municipality,r.receiver_municipality
                                   FROM booking b JOIN client c ON b.client_ID=c.client_ID JOIN pickup p ON b.pickup_ID=p.pickup_ID JOIN receiver r ON b.receiver_ID=r.receiver_ID
                                   ORDER BY b.booking_startdate DESC LIMIT :limit");
        $stmt->bindValue(':limit',(int)$limit,PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeedback($limit = 50, $rating = null, $search = '') {
        $sql = "SELECT
                    f.feedback_ID AS feed_ID,
                    f.feed_rate,
                    f.feed_comment,
                    f.feed_submitted,
                    b.booking_ID,
                    b.booking_status,
                    c.client_firstname,
                    c.client_lastname,
                    c.client_email
                FROM feedback f
                JOIN booking b ON f.booking_ID = b.booking_ID
                JOIN client c ON b.client_ID = c.client_ID
                WHERE 1 = 1";

        $params = [];

        if ($rating !== null && $rating !== '') {
            $sql .= " AND f.feed_rate = :rating";
            $params['rating'] = (int)$rating;
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $sql .= " AND (
                        b.booking_ID LIKE :search
                        OR c.client_firstname LIKE :search
                        OR c.client_lastname LIKE :search
                        OR c.client_email LIKE :search
                        OR f.feed_comment LIKE :search
                    )";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY f.feed_submitted DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === 'rating') {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeedbackSummary() {
        $summary = [
            'total_feedback' => 0,
            'average_rating' => '0.0',
            'five_star' => 0,
            'latest_date' => null
        ];

        $row = $this->db->query("
            SELECT
                COUNT(*) AS total_feedback,
                COALESCE(AVG(feed_rate), 0) AS average_rating,
                SUM(CASE WHEN feed_rate = 5 THEN 1 ELSE 0 END) AS five_star,
                MAX(feed_submitted) AS latest_date
            FROM feedback
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $summary['total_feedback'] = (int)$row['total_feedback'];
            $summary['average_rating'] = number_format((float)$row['average_rating'], 1);
            $summary['five_star'] = (int)$row['five_star'];
            $summary['latest_date'] = $row['latest_date'];
        }

        return $summary;
    }

    public function getFinanceSummary() {
        $summary = ['paid'=>0,'unpaid'=>0,'total'=>0];
        $rows = $this->db->query("SELECT is_paid, COALESCE(SUM(pay_amount),0) amount FROM payment GROUP BY is_paid")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) { $summary[$r['is_paid'] ? 'paid':'unpaid'] = (float)$r['amount']; $summary['total'] += (float)$r['amount']; }
        return $summary;
    }

}
?>
