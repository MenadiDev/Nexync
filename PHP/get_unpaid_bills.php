<?php
require_once 'config.php';

header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? null;

if (!$customer_id) {
    echo json_encode([]);
    exit;
}

$db = new Database();

$sql = "
    SELECT 
        b.Bill_ID,
        b.Bill_date,
        b.Due_date,
        b.Total_amount,
        b.Billing_Period,
        ut.Utility_Type,
        ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0) as Amount_Paid,
        (b.Total_amount - ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0)) as Balance
    FROM BILL b
    LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
    LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
    LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
    WHERE b.Customer_ID = ? 
    AND b.Status IN ('Pending', 'Overdue')
    AND (b.Total_amount - ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0)) > 0
    ORDER BY b.Due_date ASC
";

$bills = $db->fetchAll($sql, [$customer_id]);

echo json_encode($bills);
?>