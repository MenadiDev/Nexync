<?php
require_once 'config.php';

header('Content-Type: application/json');

$db = new Database();

$totalCustomers = $db->fetchOne("SELECT COUNT(*) as count FROM customers")['count'] ?? 0;
$monthlyRevenue = $db->fetchOne("SELECT ISNULL(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(GETDATE()) AND YEAR(payment_date) = YEAR(GETDATE())")['total'] ?? 0;
$pendingBills = $db->fetchOne("SELECT COUNT(*) as count FROM bills WHERE status = 'pending'")['count'] ?? 0;
$overdueBills = $db->fetchOne("SELECT COUNT(*) as count FROM bills WHERE due_date < GETDATE() AND status = 'pending'")['count'] ?? 0;

echo json_encode([
    'totalCustomers' => $totalCustomers,
    'monthlyRevenue' => floatval($monthlyRevenue),
    'pendingBills' => $pendingBills,
    'overdueBills' => $overdueBills
]);
?>