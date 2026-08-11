<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: login.php');
    exit;
}

require_once 'PHP/config.php';
$db = new Database();

$customer_id = $_SESSION['customer_id'];
$user_name = $_SESSION['name'];

$utilityMap = [
    1 => 'electricity', 2 => 'electricity', 3 => 'electricity', 4 => 'electricity', 5 => 'electricity',
    6 => 'electricity', 7 => 'electricity', 8 => 'electricity', 9 => 'electricity', 10 => 'electricity',
    11 => 'water', 12 => 'water', 13 => 'water', 14 => 'water', 15 => 'water',
    16 => 'water', 17 => 'water', 18 => 'water', 19 => 'water', 20 => 'water',
    21 => 'gas', 22 => 'gas', 23 => 'gas', 24 => 'gas', 25 => 'gas',
    26 => 'gas', 27 => 'gas', 28 => 'gas', 29 => 'gas', 30 => 'gas'
];

$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json');
    
    if ($action === 'get_unpaid_bills') {
        $sql = "
            SELECT 
                b.Bill_ID,
                b.Bill_date,
                b.Due_date,
                b.Total_amount,
                b.Billing_Period,
                m.Utility_ID,
                ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0) as Amount_Paid,
                (b.Total_amount - ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0)) as Balance
            FROM BILL b
            LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
            LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
            WHERE b.Customer_ID = ? 
            AND b.Status IN ('Pending', 'Overdue')
            AND (b.Total_amount - ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0)) > 0
            ORDER BY b.Due_date ASC
        ";
        
        $bills = $db->fetchAll($sql, [$customer_id]);
        foreach ($bills as &$bill) {
            $bill['utility_type'] = $utilityMap[$bill['Utility_ID']] ?? 'unknown';
        }
        echo json_encode(['success' => true, 'bills' => $bills]);
        exit;
    }
    
    if ($action === 'make_payment') {
        $data = json_decode(file_get_contents('php://input'), true);
        $bill_id = $data['bill_id'] ?? null;
        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        $method = $data['payment_method'] ?? 'Cash';
        $reference = $data['reference'] ?? null;
        
        if (!$bill_id || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment data']);
            exit;
        }
        
        $billCheck = $db->fetchOne("SELECT Total_amount, Customer_ID, ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = ?), 0) as Paid FROM BILL WHERE Bill_ID = ?", [$bill_id, $bill_id]);
        
        if (!$billCheck || $billCheck['Customer_ID'] != $customer_id) {
            echo json_encode(['success' => false, 'message' => 'Bill not found']);
            exit;
        }
        
        $balance = $billCheck['Total_amount'] - $billCheck['Paid'];
        if ($amount > $balance) {
            echo json_encode(['success' => false, 'message' => 'Amount exceeds bill balance']);
            exit;
        }
        
        $sql = "INSERT INTO PAYMENT (Payment_date, Payment_Method, Amount_paid, Bill_ID, Transaction_Reference, Notes)
                VALUES (GETDATE(), ?, ?, ?, ?, 'Customer self-service payment')";
        
        $payment_id = $db->insert($sql, [$method, $amount, $bill_id, $reference]);
        
        if ($payment_id) {
            $newPaid = $billCheck['Paid'] + $amount;
            if ($newPaid >= $billCheck['Total_amount']) {
                $db->update("UPDATE BILL SET Status = 'Paid' WHERE Bill_ID = ?", [$bill_id]);
            }
            echo json_encode(['success' => true, 'message' => 'Payment successful', 'payment_id' => $payment_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Payment failed']);
        }
        exit;
    }
}

$stats = [
    'total_bills' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Customer_ID = ?", [$customer_id])['cnt'] ?? 0,
    'pending_bills' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Customer_ID = ? AND Status IN ('Pending', 'Overdue')", [$customer_id])['cnt'] ?? 0,
    'total_amount_due' => $db->fetchOne("SELECT ISNULL(SUM(b.Total_amount - ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0)), 0) as total FROM BILL b WHERE b.Customer_ID = ? AND b.Status IN ('Pending', 'Overdue')", [$customer_id])['total'] ?? 0,
    'paid_this_month' => $db->fetchOne("SELECT ISNULL(SUM(Amount_paid), 0) as total FROM PAYMENT p JOIN BILL b ON p.Bill_ID = b.Bill_ID WHERE b.Customer_ID = ? AND MONTH(p.Payment_date) = MONTH(GETDATE()) AND YEAR(p.Payment_date) = YEAR(GETDATE())", [$customer_id])['total'] ?? 0
];

$recentBills = $db->fetchAll("
    SELECT TOP 5
        b.Bill_ID,
        b.Bill_date,
        b.Due_date,
        b.Total_amount,
        b.Status,
        b.Billing_Period,
        m.Utility_ID,
        ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0) as Amount_Paid
    FROM BILL b
    LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
    LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
    WHERE b.Customer_ID = ?
    ORDER BY b.Bill_date DESC
", [$customer_id]);

foreach ($recentBills as &$bill) {
    $bill['utility_type'] = $utilityMap[$bill['Utility_ID']] ?? 'unknown';
}

$recentPayments = $db->fetchAll("
    SELECT TOP 5
        p.Payment_ID,
        p.Payment_date,
        p.Payment_Method,
        p.Amount_paid,
        p.Transaction_Reference,
        b.Bill_ID,
        m.Utility_ID
    FROM PAYMENT p
    JOIN BILL b ON p.Bill_ID = b.Bill_ID
    LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
    LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
    WHERE b.Customer_ID = ?
    ORDER BY p.Payment_date DESC
", [$customer_id]);

foreach ($recentPayments as &$payment) {
    $payment['utility_type'] = $utilityMap[$payment['Utility_ID']] ?? 'unknown';
}

$activeMeters = $db->fetchAll("
    SELECT 
        m.Meter_ID,
        m.Meter_Serial,
        m.Status,
        m.Installation_date,
        m.Utility_ID,
        ut.Utility_Type
    FROM METER m
    JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
    WHERE m.Customer_ID = ?
    ORDER BY ut.Utility_Type
", [$customer_id]);

foreach ($activeMeters as &$meter) {
    $meter['utility_type'] = $utilityMap[$meter['Utility_ID']] ?? 'unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Nexsync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #040d34ff 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #445498ff 0%, #764ba2 100%);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 1rem 0;
        }

        .navbar-custom .navbar-brand {
            font-size: 1.5rem;
            color: white !important;
            font-weight: 700;
            letter-spacing: -0.5px;
            transition: transform 0.3s ease;
        }

        .navbar-custom .navbar-brand:hover {
            transform: scale(1.05);
        }

        .navbar-custom .navbar-brand i {
            font-size: 1.8rem;
            margin-right: 0.5rem;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 4rem;
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-info i {
            font-size: 1.8rem;
        }

        .user-info strong {
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-logout {
            background: linear-gradient(90deg, #185941ff 0%, #05281cff 100%);
            border: none;
            color: white !important;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
            font-weight: 700;
            box-shadow: 0 8px 22px rgba(16,185,129,0.12);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout i { color: white; }

        .btn-logout:hover {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 14px 36px rgba(16,185,129,0.18);
        }

        .btn-logout:focus { outline: none; box-shadow: 0 0 0 6px rgba(16,185,129,0.10); }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-card {
            background: linear-gradient(135deg, #29325aff 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .utility-badge-electricity { background: #4e73df; color: white; }
        .utility-badge-water { background: #36b9cc; color: white; }
        .utility-badge-gas { background: #f6c23e; color: #000; }

        .btn-pay {
            background: linear-gradient(135deg, #050506ff 0%, #08070aff 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            font-size: 1rem;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(118,75,162,0.12);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .btn-pay:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 14px 36px rgba(118,75,162,0.16);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-lightning-charge-fill"></i> Nexsync
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="me-3">
                    <i class="bi bi-person-circle"></i> 
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </span>
                <a href="logout.php" class="btn btn-logout btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="welcome-card">
            <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p class="mb-0 opacity-75">Manage your utility bills and payments easily</p>
            <div class="mt-4 d-flex justify-content-end">
                <button class="btn btn-pay btn-lg" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="bi bi-cash-coin me-2"></i> Pay Now
                </button>
            </div>
        </div>

        <div id="alertArea"></div>

        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Bills</div>
                            <h4 class="mb-0"><?php echo $stats['total_bills']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Pending Bills</div>
                            <h4 class="mb-0"><?php echo $stats['pending_bills']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Amount Due</div>
                            <h4 class="mb-0">$<?php echo number_format($stats['total_amount_due'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Paid This Month</div>
                            <h4 class="mb-0">$<?php echo number_format($stats['paid_this_month'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="content-card">
                    <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>Recent Bills</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Utility</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentBills)): ?>
                                    <?php foreach ($recentBills as $bill): ?>
                                    <tr>
                                        <td><strong>BL-<?php echo str_pad($bill['Bill_ID'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <span class="badge utility-badge-<?php echo $bill['utility_type']; ?>">
                                                <?php echo ucfirst($bill['utility_type']); ?>
                                            </span>
                                        </td>
                                        <td><strong>$<?php echo number_format($bill['Total_amount'], 2); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($bill['Due_date'])); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = $bill['Status'] === 'Paid' ? 'success' : ($bill['Status'] === 'Overdue' ? 'danger' : 'warning');
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo $bill['Status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No bills found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="content-card">
                    <h5 class="mb-3"><i class="bi bi-check-circle me-2"></i>Recent Payments</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Utility</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentPayments)): ?>
                                    <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><strong>PAY-<?php echo str_pad($payment['Payment_ID'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <span class="badge utility-badge-<?php echo $payment['utility_type']; ?>">
                                                <?php echo ucfirst($payment['utility_type']); ?>
                                            </span>
                                        </td>
                                        <td><strong>$<?php echo number_format($payment['Amount_paid'], 2); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($payment['Payment_date'])); ?></td>
                                        <td><?php echo $payment['Payment_Method']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No payments found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="mb-3"><i class="bi bi-speedometer2 me-2"></i>Your Meters</h5>
            <div class="row">
                <?php if (!empty($activeMeters)): ?>
                    <?php foreach ($activeMeters as $meter): ?>
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge utility-badge-<?php echo $meter['utility_type']; ?>">
                                        <?php echo $meter['Utility_Type']; ?>
                                    </span>
                                    <span class="badge bg-success"><?php echo $meter['Status']; ?></span>
                                </div>
                                <h6>Meter #<?php echo $meter['Meter_Serial']; ?></h6>
                                <p class="text-muted small mb-0">
                                    Installed: <?php echo date('M d, Y', strtotime($meter['Installation_date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-muted">No active meters found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Make a Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label">Select Bill to Pay *</label>
                            <div id="billsContainer" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted text-center">Loading bills...</p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount to Pay *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="paymentAmount" name="amount" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit Card">Credit/Debit Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Mobile Payment">Mobile Payment</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transaction Reference (Optional)</label>
                            <input type="text" class="form-control" name="reference" placeholder="e.g., Check number, transaction ID">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-pay btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Process Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentForm = document.getElementById('paymentForm');
        const alertArea = document.getElementById('alertArea');
        const paymentModal = document.getElementById('paymentModal');
        
        if (!paymentForm || !alertArea || !paymentModal) {
            console.error('Payment modal elements not found');
            return;
        }

        function showAlert(message, type = 'success') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `${message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            alertArea.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        paymentModal.addEventListener('show.bs.modal', async function() {
            await loadUnpaidBills();
        });

        async function loadUnpaidBills() {
            const container = document.getElementById('billsContainer');
            container.innerHTML = '<p class="text-muted text-center">Loading bills...</p>';

            try {
                const res = await fetch('customer_dashboard.php?action=get_unpaid_bills');
                const data = await res.json();

                if (!data.success || !data.bills || data.bills.length === 0) {
                    container.innerHTML = '<p class="text-success text-center"><i class="bi bi-check-circle me-2"></i>No unpaid bills</p>';
                    return;
                }

                let html = '';
                data.bills.forEach(bill => {
                    html += `
                        <div class="form-check p-3 mb-2 border rounded">
                            <input class="form-check-input bill-radio" type="radio" name="bill_id" 
                                   value="${bill.Bill_ID}" data-amount="${bill.Balance}" id="bill${bill.Bill_ID}" required>
                            <label class="form-check-label w-100" for="bill${bill.Bill_ID}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>BL-${String(bill.Bill_ID).padStart(3, '0')}</strong>
                                        <span class="badge utility-badge-${bill.utility_type} ms-2">${capitalize(bill.utility_type)}</span>
                                        <div class="small text-muted mt-1">
                                            Period: ${bill.Billing_Period}<br>
                                            Due: ${formatDate(bill.Due_date)}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-danger fw-bold">$${parseFloat(bill.Balance).toFixed(2)}</div>
                                        <small class="text-muted">Balance</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    `;
                });

                container.innerHTML = html;

                document.querySelectorAll('.bill-radio').forEach(radio => {
                    radio.addEventListener('change', function() {
                        document.getElementById('paymentAmount').value = parseFloat(this.dataset.amount).toFixed(2);
                    });
                });
            } catch (error) {
                container.innerHTML = '<p class="text-danger text-center">Error loading bills</p>';
                console.error(error);
            }
        }

        paymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            const formData = new FormData(paymentForm);
            const data = {
                bill_id: parseInt(formData.get('bill_id')),
                amount: parseFloat(formData.get('amount')),
                payment_method: formData.get('payment_method'),
                reference: formData.get('reference') || null
            };

            try {
                const res = await fetch('customer_dashboard.php?action=make_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    showAlert('Payment processed successfully!', 'success');
                    const modalInstance = bootstrap.Modal.getInstance(paymentModal);
                    modalInstance.hide();
                    paymentForm.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('Payment failed: ' + (result.message || 'Unknown error'), 'danger');
                }
            } catch (error) {
                showAlert('Error processing payment', 'danger');
                console.error(error);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    });
    </script>
</body>
</html>