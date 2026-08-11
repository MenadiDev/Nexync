<?php
require_once 'config.php';

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = $_POST['Customer_ID'] ?? null;

    if (!$customerID) {
        die('Customer not selected');
    }

    $sql = "
        SELECT m.Meter_ID, ut.UnitCost
        FROM METER m
        LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
        WHERE m.Customer_ID = ?
    ";
    $meter = $db->fetchOne($sql, [$customerID]);
    if (!$meter) die('Meter not found for customer');

    $sqlLatestReading = "
        SELECT TOP 1 Current_reading, Previous_reading
        FROM METER_READING
        WHERE Meter_ID = ?
        ORDER BY Reading_date DESC, Reading_ID DESC
    ";
    $latestReading = $db->fetchOne($sqlLatestReading, [$meter['Meter_ID']]);

    $previousReading = $latestReading['Current_reading'] ?? 0; 
    $currentReading = $previousReading;
    $unitsConsumed = $currentReading - $previousReading; 

    
    $sqlReading = "INSERT INTO METER_READING (Reading_date, Units_consumed, Current_reading, Previous_reading, Meter_ID)
                   VALUES (GETDATE(), ?, ?, ?, ?)";
    $readingID = $db->insert($sqlReading, [$unitsConsumed, $currentReading, $previousReading, $meter['Meter_ID']]);

    
    $totalAmount = $unitsConsumed * $meter['UnitCost'];

    
    $sqlBill = "INSERT INTO BILL (Customer_ID, Reading_ID, Bill_Date, Billing_Period, Total_amount, Due_date, Status)
                VALUES (?, ?, GETDATE(), ?, ?, DATEADD(DAY, 15, GETDATE()), 'Pending')";
    $billingPeriod = date('Y-m'); 
    $db->insert($sqlBill, [$customerID, $readingID, $billingPeriod, $totalAmount]);

    header("Location: ../billing.php?success=1");
    exit;
}
?>
