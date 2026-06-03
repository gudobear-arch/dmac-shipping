<?php
class Payment {
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

    public function getPaymentMethods() {
        $this->ensurePaymentMethods();
        $stmt = $this->db->query("
            SELECT paymethod_ID, pay_method
            FROM paymethod
            WHERE pay_method IN ('Cash on Delivery', 'Online Payment')
            ORDER BY FIELD(pay_method, 'Cash on Delivery', 'Online Payment')
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensurePaymentMethods() {
        // Keep only the two official choices used by the billing UI:
        // LAND = Cash on Delivery or Online Payment
        // AIR  = Online Payment only
        $this->normalizePayMethod('Cash on Delivery', "UPPER(TRIM(pay_method)) IN ('COD', 'CASH', 'CASH ON DELIVERY')");
        $this->normalizePayMethod('Online Payment', "UPPER(TRIM(pay_method)) IN ('ONLINE', 'ONLINE PAYMENT', 'ONLINE BANKING', 'GCASH', 'BANK TRANSFER') OR UPPER(pay_method) LIKE '%ONLINE%'");

        $required = ['Cash on Delivery', 'Online Payment'];
        $check = $this->db->prepare("SELECT paymethod_ID FROM paymethod WHERE pay_method = ? LIMIT 1");
        $insert = $this->db->prepare("INSERT INTO paymethod (pay_method) VALUES (?)");

        foreach ($required as $method) {
            $check->execute([$method]);
            if (!$check->fetchColumn()) {
                $insert->execute([$method]);
            }
        }
    }

    private function normalizePayMethod($canonical, $condition) {
        $check = $this->db->prepare("SELECT paymethod_ID FROM paymethod WHERE pay_method = ? LIMIT 1");
        $check->execute([$canonical]);
        if ($check->fetchColumn()) {
            return;
        }

        $query = "SELECT paymethod_ID FROM paymethod WHERE {$condition} ORDER BY paymethod_ID LIMIT 1";
        $stmt = $this->db->query($query);
        $paymethodId = $stmt->fetchColumn();

        if ($paymethodId) {
            $update = $this->db->prepare("UPDATE paymethod SET pay_method = ? WHERE paymethod_ID = ?");
            $update->execute([$canonical, $paymethodId]);
        }
    }

    private function paymentExtraSelect() {
        $selects = [];
        foreach (['box_fee','pickup_fee','shipping_fee','head_price','number_of_heads','total_amount','payment_status','payment_reference','updated_at'] as $column) {
            if ($this->tableHasColumn('payment', $column)) {
                $selects[] = "p.$column";
            } else {
                if (in_array($column, ['box_fee','pickup_fee','shipping_fee','head_price','number_of_heads'], true)) {
                    $selects[] = "0 AS $column";
                } elseif ($column === 'total_amount') {
                    $selects[] = "p.pay_amount AS total_amount";
                } elseif ($column === 'payment_status') {
                    if ($this->tableHasColumn('payment', 'payment_status')) {
                        $selects[] = "p.payment_status";
                    } elseif ($this->tableHasColumn('payment', 'is_paid')) {
                        $selects[] = "CASE WHEN p.payment_ID IS NULL THEN 'NOT SET' WHEN p.is_paid = 1 THEN 'PAID' ELSE 'PENDING' END AS payment_status";
                    } else {
                        $selects[] = "CASE WHEN p.payment_ID IS NULL THEN 'NOT SET' ELSE 'PENDING' END AS payment_status";
                    }
                } else {
                    $selects[] = "NULL AS $column";
                }
            }
        }
        return ', ' . implode(', ', $selects);
    }

    private function getPaymentIsPaidSelect() {
        if ($this->tableHasColumn('payment', 'is_paid')) {
            return 'p.is_paid';
        }

        if ($this->tableHasColumn('payment', 'payment_status')) {
            return "CASE WHEN UPPER(TRIM(p.payment_status)) = 'PAID' THEN 1 ELSE 0 END";
        }

        return '0';
    }

    private function getPaymentStatusExpression($alias = 'p') {
        if ($this->tableHasColumn('payment', 'payment_status')) {
            return "$alias.payment_status";
        }

        if ($this->tableHasColumn('payment', 'is_paid')) {
            return "CASE WHEN $alias.is_paid = 1 THEN 'PAID' ELSE 'PENDING' END";
        }

        return "CASE WHEN $alias.payment_ID IS NULL THEN 'NOT SET' ELSE 'PENDING' END";
    }

    private function getPaymentDateExpression($alias = 'p') {
        $dateColumns = [];
        if ($this->tableHasColumn('payment', 'paid_at')) {
            $dateColumns[] = "$alias.paid_at";
        }
        if ($this->tableHasColumn('payment', 'pay_date')) {
            $dateColumns[] = "$alias.pay_date";
        }
        if ($this->tableHasColumn('payment', 'updated_at')) {
            $dateColumns[] = "$alias.updated_at";
        }
        if ($this->tableHasColumn('payment', 'created_at')) {
            $dateColumns[] = "$alias.created_at";
        }

        if (empty($dateColumns)) {
            return 'NULL';
        }

        return 'COALESCE(' . implode(', ', $dateColumns) . ')';
    }

    public function getClientBilling($clientId) {
        $shipmentSelect = $this->tableHasColumn('booking', 'shipment_type') ? "COALESCE(b.shipment_type, 'NOT SET') AS shipment_type" : "'NOT SET' AS shipment_type";
        $extraSelect = $this->paymentExtraSelect();
        $paymentIsPaidSelect = $this->getPaymentIsPaidSelect();

        $sql = "SELECT b.booking_ID,
                       b.booking_status,
                       b.booking_requestdate,
                       b.booking_startdate,
                       b.booking_enddate,
                       $shipmentSelect,
                       r.receiver_street,
                       r.receiver_municipality,
                       r.receiver_province,
                       COALESCE(animals.total_animals, 0) AS total_animals,
                       p.payment_ID,
                       p.pay_amount,
                       p.pay_date,
                       $paymentIsPaidSelect AS is_paid,
                       pm.pay_method
                       $extraSelect
                FROM booking b
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN payment p ON b.booking_ID = p.booking_ID
                LEFT JOIN paymethod pm ON p.paymethod_ID = pm.paymethod_ID
                LEFT JOIN (
                    SELECT booking_ID, SUM(animalbatch_quantity) AS total_animals
                    FROM animalbatch
                    GROUP BY booking_ID
                ) animals ON b.booking_ID = animals.booking_ID
                WHERE b.client_ID = :client_id
                ORDER BY b.booking_startdate DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['client_id' => (int)$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllBillingRecords() {
        $shipmentSelect = $this->tableHasColumn('booking', 'shipment_type') ? "COALESCE(b.shipment_type, 'NOT SET') AS shipment_type" : "'NOT SET' AS shipment_type";
        $extraSelect = $this->paymentExtraSelect();
        $paymentIsPaidSelect = $this->getPaymentIsPaidSelect();

        $sql = "SELECT b.booking_ID,
                       b.booking_status,
                       b.booking_requestdate,
                       b.booking_startdate,
                       b.booking_enddate,
                       $shipmentSelect,
                       c.client_firstname,
                       c.client_lastname,
                       r.receiver_municipality,
                       r.receiver_province,
                       COALESCE(animals.total_animals, 0) AS total_animals,
                       p.payment_ID,
                       p.paymethod_ID,
                       p.pay_amount,
                       p.pay_date,
                       $paymentIsPaidSelect AS is_paid,
                       pm.pay_method
                       $extraSelect
                FROM booking b
                JOIN client c ON b.client_ID = c.client_ID
                JOIN receiver r ON b.receiver_ID = r.receiver_ID
                LEFT JOIN payment p ON b.booking_ID = p.booking_ID
                LEFT JOIN paymethod pm ON p.paymethod_ID = pm.paymethod_ID
                LEFT JOIN (
                    SELECT booking_ID, SUM(animalbatch_quantity) AS total_animals
                    FROM animalbatch
                    GROUP BY booking_ID
                ) animals ON b.booking_ID = animals.booking_ID
                ORDER BY b.booking_startdate DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary() {
        $summary = [
            'total' => 0,
            'paid' => 0,
            'unpaid' => 0,
            'overdue' => 0,
            'paid_count' => 0,
            'pending_count' => 0,
            'overdue_count' => 0,
            'not_set_count' => 0
        ];

        $stmt = $this->db->query("SELECT COUNT(*) FROM booking b LEFT JOIN payment p ON b.booking_ID = p.booking_ID WHERE p.payment_ID IS NULL");
        $summary['not_set_count'] = (int)$stmt->fetchColumn();

        if ($this->tableHasColumn('payment', 'payment_status')) {
            $amountExpr = $this->tableHasColumn('payment', 'total_amount') ? 'total_amount' : 'pay_amount';
            $rows = $this->db->query("SELECT payment_status, COUNT(*) total_count, COALESCE(SUM($amountExpr),0) total_amount FROM payment GROUP BY payment_status")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $status = strtoupper((string)$row['payment_status']);
                $amount = (float)$row['total_amount'];
                if ($status === 'PAID') {
                    $summary['paid'] += $amount;
                    $summary['total'] += $amount;
                    $summary['paid_count'] += (int)$row['total_count'];
                } elseif ($status === 'OVERDUE') {
                    $summary['overdue'] += $amount;
                    $summary['overdue_count'] += (int)$row['total_count'];
                } else {
                    $summary['unpaid'] += $amount;
                    $summary['pending_count'] += (int)$row['total_count'];
                }
            }
        } else {
            $rows = $this->db->query("SELECT is_paid, COUNT(*) total_count, COALESCE(SUM(pay_amount),0) total_amount FROM payment GROUP BY is_paid")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $amount = (float)$row['total_amount'];
                if ((int)$row['is_paid'] === 1) {
                    $summary['paid'] += $amount;
                    $summary['total'] += $amount;
                    $summary['paid_count'] += (int)$row['total_count'];
                } else {
                    $summary['unpaid'] += $amount;
                    $summary['pending_count'] += (int)$row['total_count'];
                }
            }
        }

        return $summary;
    }

    private function getBookingInfo($bookingId) {
        $shipmentSelect = $this->tableHasColumn('booking', 'shipment_type') ? "COALESCE(shipment_type, 'NOT SET') AS shipment_type" : "'NOT SET' AS shipment_type";
        $stmt = $this->db->prepare("SELECT booking_ID, $shipmentSelect FROM booking WHERE booking_ID = :booking_id LIMIT 1");
        $stmt->execute(['booking_id' => (int)$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getMethodName($paymethodId) {
        $stmt = $this->db->prepare("SELECT pay_method FROM paymethod WHERE paymethod_ID = :id LIMIT 1");
        $stmt->execute(['id' => (int)$paymethodId]);
        return strtoupper(trim((string)$stmt->fetchColumn()));
    }

    public function savePaymentBreakdown($bookingId, $boxFee, $pickupFee, $shippingFee, $headPrice, $numberOfHeads, $paymethodId, $paymentStatus, $paymentReference = '', $shipmentType = null) {
        $bookingId = (int)$bookingId;
        $paymethodId = (int)$paymethodId;
        $boxFee = max(0, (float)$boxFee);
        $pickupFee = max(0, (float)$pickupFee);
        $shippingFee = max(0, (float)$shippingFee);
        $headPrice = max(0, (float)$headPrice);
        $numberOfHeads = max(0, (int)$numberOfHeads);
        $paymentStatus = strtoupper(trim((string)$paymentStatus));
        $paymentReference = trim((string)$paymentReference);
        $shipmentType = strtoupper(trim((string)$shipmentType));

        if (!in_array($shipmentType, ['LAND', 'AIR'], true)) {
            return false;
        }

        if ($bookingId <= 0 || $paymethodId <= 0 || !in_array($paymentStatus, ['PENDING','PAID','OVERDUE'], true)) {
            return false;
        }

        $booking = $this->getBookingInfo($bookingId);
        if (!$booking) {
            return false;
        }

        $methodName = $this->getMethodName($paymethodId);
        if ($shipmentType === 'AIR' && strpos($methodName, 'ONLINE') === false) {
            return false;
        }

        $totalAmount = $boxFee + $pickupFee + $shippingFee + ($numberOfHeads * $headPrice);
        $isPaid = $paymentStatus === 'PAID' ? 1 : 0;

        try {
            $this->db->beginTransaction();

            if ($this->tableHasColumn('booking', 'shipment_type')) {
                $updateBooking = $this->db->prepare("UPDATE booking SET shipment_type = :shipment_type WHERE booking_ID = :booking_id");
                $updateBooking->execute([
                    'shipment_type' => $shipmentType,
                    'booking_id' => $bookingId
                ]);
            }

            $existing = $this->db->prepare("SELECT payment_ID FROM payment WHERE booking_ID = :booking_id LIMIT 1");
            $existing->execute(['booking_id' => $bookingId]);
            $paymentId = $existing->fetchColumn();

            $fields = [
                'paymethod_ID' => $paymethodId,
                'pay_amount' => $totalAmount,
                'is_paid' => $isPaid,
            ];

            if ($this->tableHasColumn('payment', 'box_fee')) $fields['box_fee'] = $boxFee;
            if ($this->tableHasColumn('payment', 'pickup_fee')) $fields['pickup_fee'] = $pickupFee;
            if ($this->tableHasColumn('payment', 'shipping_fee')) $fields['shipping_fee'] = $shippingFee;
            if ($this->tableHasColumn('payment', 'head_price')) $fields['head_price'] = $headPrice;
            if ($this->tableHasColumn('payment', 'number_of_heads')) $fields['number_of_heads'] = $numberOfHeads;
            if ($this->tableHasColumn('payment', 'total_amount')) $fields['total_amount'] = $totalAmount;
            if ($this->tableHasColumn('payment', 'payment_status')) $fields['payment_status'] = $paymentStatus;
            if ($this->tableHasColumn('payment', 'payment_reference')) $fields['payment_reference'] = $paymentReference;
            if ($this->tableHasColumn('payment', 'updated_at')) $fields['updated_at'] = date('Y-m-d H:i:s');

            if ($paymentStatus === 'PAID') {
                $fields['pay_date'] = date('Y-m-d');
            } else {
                $fields['pay_date'] = null;
            }

            if ($paymentId) {
                $sets = [];
                foreach ($fields as $column => $value) {
                    $sets[] = "$column = :$column";
                }
                $fields['payment_ID'] = $paymentId;
                $sql = "UPDATE payment SET " . implode(', ', $sets) . " WHERE payment_ID = :payment_ID";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($fields);
            } else {
                $fields['booking_ID'] = $bookingId;
                $columns = array_keys($fields);
                $placeholders = array_map(fn($c) => ':' . $c, $columns);
                $sql = "INSERT INTO payment (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($fields);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Payment save error: ' . $e->getMessage());
            return false;
        }
    }

    public function ensureExpenseCategories() {
        $categories = [
            'Airline Expenses',
            'Consignee Per Destination',
            'Farm Expenses',
            'Land Trip Expenses',
            'Fuel Expenses',
            'Meal Expenses',
            'Cebu Pac Expenses',
            'Miscellaneous Fee',
            'Salary',
            'Employee Salary',
            'Insurance Fee'
        ];

        $check = $this->db->prepare("SELECT expensecategory_ID FROM expensecategory WHERE LOWER(categoryname) = LOWER(?) LIMIT 1");
        $insert = $this->db->prepare("INSERT INTO expensecategory (categoryname) VALUES (?)");

        foreach ($categories as $category) {
            $check->execute([$category]);
            if (!$check->fetchColumn()) {
                $insert->execute([$category]);
            }
        }
    }

    public function getExpenseCategories() {
        $this->ensureExpenseCategories();

        $stmt = $this->db->query("
            SELECT expensecategory_ID, categoryname
            FROM expensecategory
            ORDER BY categoryname ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveExpense($expenseCategoryId, $processedByEmpId, $amount, $expenseDate, $description = '') {
        $expenseCategoryId = (int)$expenseCategoryId;
        $processedByEmpId = (int)$processedByEmpId;
        $amount = (float)$amount;
        $expenseDate = trim((string)$expenseDate);
        $description = trim((string)$description);

        if ($expenseCategoryId <= 0 || $processedByEmpId <= 0 || $amount <= 0 || $expenseDate === '') {
            return false;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $expenseDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $expenseDate) {
            return false;
        }

        $checkCategory = $this->db->prepare("SELECT COUNT(*) FROM expensecategory WHERE expensecategory_ID = ?");
        $checkCategory->execute([$expenseCategoryId]);
        if ((int)$checkCategory->fetchColumn() === 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO expense (
                expensecategory_ID,
                processed_by_emp_ID,
                expense_amount,
                expense_description,
                expense_date
            ) VALUES (
                :expensecategory_ID,
                :processed_by_emp_ID,
                :expense_amount,
                :expense_description,
                :expense_date
            )
        ");

        return $stmt->execute([
            'expensecategory_ID' => $expenseCategoryId,
            'processed_by_emp_ID' => $processedByEmpId,
            'expense_amount' => $amount,
            'expense_description' => $description !== '' ? $description : null,
            'expense_date' => $expenseDate
        ]);
    }

    public function getIncomeExpenseSummary($year, $month = null) {
        $year = (int)$year;
        $month = $month !== null && $month !== '' ? (int)$month : null;

        if ($year <= 0) {
            $year = (int)date('Y');
        }

        $paymentDateExpr = $this->getPaymentDateExpression('p');
        $dateWherePayment = "YEAR($paymentDateExpr) = :payment_year";
        $dateWhereExpense = "YEAR(e.expense_date) = :expense_year";
        $paramsPayment = ['payment_year' => $year];
        $paramsExpense = ['expense_year' => $year];

        if ($month !== null && $month >= 1 && $month <= 12) {
            $dateWherePayment .= " AND MONTH($paymentDateExpr) = :payment_month";
            $dateWhereExpense .= " AND MONTH(e.expense_date) = :expense_month";
            $paramsPayment['payment_month'] = $month;
            $paramsExpense['expense_month'] = $month;
        }

        $amountExpr = $this->tableHasColumn('payment', 'total_amount') ? 'p.total_amount' : 'p.pay_amount';
        $paymentStatusExpr = $this->getPaymentStatusExpression('p');

        $grossStmt = $this->db->prepare("
            SELECT COALESCE(SUM($amountExpr), 0)
            FROM payment p
            WHERE UPPER(COALESCE($paymentStatusExpr, 'PENDING')) = 'PAID'
              AND $dateWherePayment
        ");
        $grossStmt->execute($paramsPayment);
        $grossIncome = (float)$grossStmt->fetchColumn();

        $expenseStmt = $this->db->prepare("
            SELECT COALESCE(SUM(e.expense_amount), 0)
            FROM expense e
            WHERE $dateWhereExpense
        ");
        $expenseStmt->execute($paramsExpense);
        $totalExpenses = (float)$expenseStmt->fetchColumn();

        return [
            'gross_income' => $grossIncome,
            'total_expenses' => $totalExpenses,
            'net_income' => $grossIncome - $totalExpenses
        ];
    }

    public function getMonthlyIncomeExpenseReport($year) {
        $year = (int)$year;
        if ($year <= 0) {
            $year = (int)date('Y');
        }

        $amountExpr = $this->tableHasColumn('payment', 'total_amount') ? 'p.total_amount' : 'p.pay_amount';
        $paymentStatusExpr = $this->getPaymentStatusExpression('p');

        $paymentDateExpr = $this->getPaymentDateExpression('p');
        $grossRows = $this->db->prepare("
            SELECT 
                MONTH($paymentDateExpr) AS report_month,
                COALESCE(SUM($amountExpr), 0) AS gross_income
            FROM payment p
            WHERE UPPER(COALESCE($paymentStatusExpr, 'PENDING')) = 'PAID'
              AND YEAR($paymentDateExpr) = :year
            GROUP BY MONTH($paymentDateExpr)
        ");
        $grossRows->execute(['year' => $year]);
        $grossByMonth = [];
        foreach ($grossRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grossByMonth[(int)$row['report_month']] = (float)$row['gross_income'];
        }

        $expenseRows = $this->db->prepare("
            SELECT MONTH(expense_date) AS report_month,
                   COALESCE(SUM(expense_amount), 0) AS total_expenses
            FROM expense
            WHERE YEAR(expense_date) = :year
            GROUP BY MONTH(expense_date)
        ");
        $expenseRows->execute(['year' => $year]);
        $expensesByMonth = [];
        foreach ($expenseRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expensesByMonth[(int)$row['report_month']] = (float)$row['total_expenses'];
        }

        $report = [];
        for ($month = 1; $month <= 12; $month++) {
            $gross = $grossByMonth[$month] ?? 0;
            $expenses = $expensesByMonth[$month] ?? 0;

            $report[] = [
                'month_number' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'gross_income' => $gross,
                'total_expenses' => $expenses,
                'net_income' => $gross - $expenses
            ];
        }

        return $report;
    }

    public function getExpenseDateCategoryRows($year, $month = null) {
        $year = (int)$year;
        $month = $month !== null && $month !== '' ? (int)$month : null;

        if ($year <= 0) {
            $year = (int)date('Y');
        }

        $where = "YEAR(e.expense_date) = :year";
        $params = ['year' => $year];

        if ($month !== null && $month >= 1 && $month <= 12) {
            $where .= " AND MONTH(e.expense_date) = :month";
            $params['month'] = $month;
        }

        $stmt = $this->db->prepare("
            SELECT 
                e.expense_date,
                ec.categoryname,
                COALESCE(SUM(e.expense_amount), 0) AS amount
            FROM expense e
            JOIN expensecategory ec ON e.expensecategory_ID = ec.expensecategory_ID
            WHERE $where
            GROUP BY e.expense_date, ec.categoryname
            ORDER BY e.expense_date DESC, ec.categoryname ASC
        ");
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($rawRows as $row) {
            $date = $row['expense_date'];
            if (!isset($rows[$date])) {
                $rows[$date] = [
                    'expense_date' => $date,
                    'categories' => [],
                    'total' => 0
                ];
            }

            $amount = (float)$row['amount'];
            $rows[$date]['categories'][$row['categoryname']] = $amount;
            $rows[$date]['total'] += $amount;
        }

        return array_values($rows);
    }

    public function getRecentExpenses($limit = 10) {
        $limit = max(1, (int)$limit);

        $stmt = $this->db->prepare("
            SELECT 
                e.expense_ID,
                e.expense_amount,
                e.expense_description,
                e.expense_date,
                e.created_at,
                ec.categoryname,
                emp.emp_firstname,
                emp.emp_lastname
            FROM expense e
            JOIN expensecategory ec ON e.expensecategory_ID = ec.expensecategory_ID
            LEFT JOIN employee emp ON e.processed_by_emp_ID = emp.emp_ID
            ORDER BY e.expense_date DESC, e.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


}
?>
