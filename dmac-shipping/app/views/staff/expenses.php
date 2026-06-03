<?php
require_once __DIR__ . '/../../helpers/auth.php';
requireEmployeeLogin();

require_once __DIR__ . '/../../helpers/staff_nav.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Payment.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value) {
    return '₱' . number_format((float)$value, 2);
}

function isAdminAccountingUser() {
    if (isSuperAdmin()) {
        return true;
    }

    $role = strtolower((string)($_SESSION['employee_role'] ?? ''));
    return $role === 'admin' || strpos($role, 'admin') !== false;
}

if (!isAdminAccountingUser()) {
    header('Location: dashboard.php?unauthorized=1');
    exit();
}

$db = (new Database())->getConnection();
$paymentModel = new Payment($db);

$year = (int)($_GET['year'] ?? date('Y'));
$monthInput = $_GET['month'] ?? date('n');
$month = $monthInput === 'all' ? null : (int)$monthInput;

$categories = $paymentModel->getExpenseCategories();
$summary = $paymentModel->getIncomeExpenseSummary($year, $month);
$monthlyReport = $paymentModel->getMonthlyIncomeExpenseReport($year);
$expenseRows = $paymentModel->getExpenseDateCategoryRows($year, $month);
$recentExpenses = $paymentModel->getRecentExpenses(8);
$categoryNames = array_map(fn($row) => $row['categoryname'], $categories);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses / Income Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../../../public/css/dashboard.css">
    <link rel="stylesheet" href="../../../public/css/expenses.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body class="dashboard-body">
<?php showStaffSidebar(); ?>

<div class="main-content">
    <main class="expenses-page">
        <div class="expenses-hero">
            <div>
                <span class="page-tag"><i class="fa-solid fa-chart-line"></i> Admin Accounting</span>
                <h1>Expenses & Income Report</h1>
                <p>Track daily shipping expenses and compare them with paid client revenue.</p>
            </div>

            <a class="back-link" href="finances.php">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Billing
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">Expense saved successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                <?php
                    $error = $_GET['error'];
                    if ($error === 'missing') {
                        echo 'Please complete all required fields.';
                    } elseif ($error === 'amount') {
                        echo 'Expense amount must be greater than zero.';
                    } elseif ($error === 'date') {
                        echo 'Please enter a valid expense date.';
                    } else {
                        echo 'Something went wrong. Please try again.';
                    }
                ?>
            </div>
        <?php endif; ?>

        <form class="filter-bar" method="GET">
            <div>
                <label>Month</label>
                <select name="month">
                    <option value="all" <?= $month === null ? 'selected' : '' ?>>All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                            <?= h(date('F', mktime(0, 0, 0, $m, 1))) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label>Year</label>
                <input type="number" name="year" min="2000" max="2100" value="<?= h($year) ?>">
            </div>

            <button type="submit">
                <i class="fa-solid fa-filter"></i>
                Apply Filter
            </button>
        </form>

        <section class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                <div>
                    <strong><?= money($summary['gross_income']) ?></strong>
                    <span><?= $month === null ? 'Yearly Gross Income' : 'Monthly Gross Income' ?></span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon danger"><i class="fa-solid fa-receipt"></i></div>
                <div>
                    <strong><?= money($summary['total_expenses']) ?></strong>
                    <span><?= $month === null ? 'Yearly Expenses' : 'Monthly Expenses' ?></span>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon net"><i class="fa-solid fa-scale-balanced"></i></div>
                <div>
                    <strong><?= money($summary['net_income']) ?></strong>
                    <span><?= $month === null ? 'Yearly Net Income' : 'Monthly Net Income' ?></span>
                </div>
            </div>
        </section>

        <section class="expenses-layout">
            <div class="panel">
                <div class="panel-title">
                    <h2>Add Expense</h2>
                    <p>Input daily expenses used for shipping operations.</p>
                </div>

                <form action="process-expense.php" method="POST" class="expense-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Expense Date</label>
                            <input type="date" name="expense_date" value="<?= h(date('Y-m-d')) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Expense Category</label>
                            <select name="expensecategory_ID" required>
                                <option value="">Choose category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= h($category['expensecategory_ID']) ?>">
                                        <?= h($category['categoryname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="expense_amount" min="0.01" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description / Notes</label>
                        <textarea name="expense_description" rows="3" placeholder="Example: fuel for Batangas delivery, airline cargo fee, employee meal allowance"></textarea>
                    </div>

                    <button class="save-expense-btn" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Expense
                    </button>
                </form>
            </div>

            <div class="panel">
                <div class="panel-title">
                    <h2>Recent Expenses</h2>
                    <p>Latest expense entries recorded by admin.</p>
                </div>

                <div class="recent-list">
                    <?php if (empty($recentExpenses)): ?>
                        <div class="empty-state">No expense records yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentExpenses as $expense): ?>
                            <div class="recent-item">
                                <div>
                                    <strong><?= h($expense['categoryname']) ?></strong>
                                    <span><?= h($expense['expense_date']) ?> • <?= h(trim(($expense['emp_firstname'] ?? '') . ' ' . ($expense['emp_lastname'] ?? '')) ?: 'Admin') ?></span>
                                    <?php if (!empty($expense['expense_description'])): ?>
                                        <small><?= h($expense['expense_description']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <b><?= money($expense['expense_amount']) ?></b>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">
                <h2>Expense Category Breakdown</h2>
                <p>Date-based output: expense date, category amounts, and daily total.</p>
            </div>

            <div class="table-wrap">
                <table class="expense-breakdown-table">
                    <thead>
                        <tr>
                            <th>Expense Date</th>
                            <?php foreach ($categoryNames as $categoryName): ?>
                                <th><?= h($categoryName) ?></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($expenseRows)): ?>
                            <tr>
                                <td colspan="<?= count($categoryNames) + 2 ?>" class="empty-cell">No expenses found for this filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenseRows as $row): ?>
                                <tr>
                                    <td class="date-cell"><?= h($row['expense_date']) ?></td>
                                    <?php foreach ($categoryNames as $categoryName): ?>
                                        <td><?= money($row['categories'][$categoryName] ?? 0) ?></td>
                                    <?php endforeach; ?>
                                    <td class="total-cell"><?= money($row['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-title">
                <h2>Monthly Gross / Expenses / Net Income</h2>
                <p>Gross income only counts payments marked as PAID.</p>
            </div>

            <div class="table-wrap">
                <table class="monthly-report-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Gross Income</th>
                            <th>Expenses</th>
                            <th>Net Income</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($monthlyReport as $row): ?>
                            <tr>
                                <td><?= h($row['month_name']) ?> <?= h($year) ?></td>
                                <td><?= money($row['gross_income']) ?></td>
                                <td><?= money($row['total_expenses']) ?></td>
                                <td class="<?= (float)$row['net_income'] < 0 ? 'negative-net' : 'positive-net' ?>">
                                    <?= money($row['net_income']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
