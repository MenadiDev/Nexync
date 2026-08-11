<?php
require_once 'config.php';

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billID = $_POST['Bill_ID'] ?? null;
    $billingPeriod = $_POST['Billing_Period'] ?? null;
    $unitsConsumed = $_POST['Units_Consumed'] ?? 0;
    $totalAmount = $_POST['Total_amount'] ?? 0;
    $dueDate = $_POST['Due_date'] ?? null;
    $status = $_POST['Status'] ?? 'Pending';

    if (!$billID) die('Bill ID missing');

    $sql = "UPDATE BILL
            SET Billing_Period = ?, Total_amount = ?, Due_date = ?, Status = ?
            WHERE Bill_ID = ?";
    $db->update($sql, [$billingPeriod, $totalAmount, $dueDate, $status, $billID]);

    $sqlReading = "UPDATE METER_READING
                   SET Units_consumed = ?
                   WHERE Reading_ID = (SELECT Reading_ID FROM BILL WHERE Bill_ID = ?)";
    $db->update($sqlReading, [$unitsConsumed, $billID]);

    header("Location: ../billing.php?updated=1");
    exit;
}
?>
