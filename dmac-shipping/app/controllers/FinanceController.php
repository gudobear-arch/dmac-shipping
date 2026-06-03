<?php
require_once __DIR__ . '/../../models/Payment.php';

class FinanceController
{
    private Payment $paymentModel;

    public function __construct(Payment $paymentModel)
    {
        $this->paymentModel = $paymentModel;
    }

    private function normalizeAmount($value): float
    {
        return is_numeric($value) ? (float)$value : -1.0;
    }

    public function savePayment(array $input): array
    {
        $bookingId = (int)($input['booking_id'] ?? 0);
        $boxFee = $this->normalizeAmount($input['box_fee'] ?? 0);
        $pickupFee = $this->normalizeAmount($input['pickup_fee'] ?? 0);
        $shippingFee = $this->normalizeAmount($input['shipping_fee'] ?? 0);
        $headPrice = $this->normalizeAmount($input['head_price'] ?? 0);
        $numberOfHeads = $this->normalizeAmount($input['number_of_heads'] ?? 0);
        $paymethodId = (int)($input['paymethod_id'] ?? 0);
        $paymentStatus = strtoupper(trim($input['payment_status'] ?? 'PENDING'));
        $paymentReference = trim((string)($input['payment_reference'] ?? ''));
        $shipmentType = strtoupper(trim((string)($input['shipment_type'] ?? '')));

        $allowedStatuses = ['PENDING', 'PAID', 'OVERDUE'];
        $allowedShipments = ['LAND', 'AIR'];

        if ($bookingId <= 0 || $paymethodId <= 0 || !in_array($paymentStatus, $allowedStatuses, true) || !in_array($shipmentType, $allowedShipments, true)) {
            return ['success' => false, 'error' => 'invalid'];
        }

        foreach ([$boxFee, $pickupFee, $shippingFee, $headPrice, $numberOfHeads] as $value) {
            if ($value < 0) {
                return ['success' => false, 'error' => 'invalid'];
            }
        }

        $paymentMethods = $this->paymentModel->getPaymentMethods();
        $selectedMethod = null;
        foreach ($paymentMethods as $method) {
            if ((int)$method['paymethod_ID'] === $paymethodId) {
                $selectedMethod = $method['pay_method'];
                break;
            }
        }

        if ($selectedMethod === null) {
            return ['success' => false, 'error' => 'method'];
        }

        if ($shipmentType === 'AIR' && stripos($selectedMethod, 'online') === false) {
            return ['success' => false, 'error' => 'air_online'];
        }

        $saved = $this->paymentModel->savePaymentBreakdown(
            $bookingId,
            $boxFee,
            $pickupFee,
            $shippingFee,
            $headPrice,
            $numberOfHeads,
            $paymethodId,
            $paymentStatus,
            $paymentReference,
            $shipmentType
        );

        if (!$saved) {
            return ['success' => false, 'error' => 'save'];
        }

        return ['success' => true];
    }

    public function saveExpense(array $input, int $processedByEmpId): array
    {
        $expenseCategoryId = (int)($input['expensecategory_ID'] ?? 0);
        $expenseAmount = $this->normalizeAmount($input['expense_amount'] ?? 0);
        $expenseDate = trim((string)($input['expense_date'] ?? ''));
        $expenseDescription = trim((string)($input['expense_description'] ?? ''));

        if ($expenseCategoryId <= 0 || $expenseAmount <= 0 || $expenseDate === '') {
            return ['success' => false, 'error' => 'missing'];
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $expenseDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $expenseDate) {
            return ['success' => false, 'error' => 'date'];
        }

        $saved = $this->paymentModel->saveExpense(
            $expenseCategoryId,
            $processedByEmpId,
            $expenseAmount,
            $expenseDate,
            $expenseDescription
        );

        if (!$saved) {
            return ['success' => false, 'error' => 'save'];
        }

        return ['success' => true];
    }
}
