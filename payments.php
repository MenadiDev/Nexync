<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Administrator' && $_SESSION['role'] !== 'Billing Officer')) {
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Nexsync</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-shield-lock text-danger" style="font-size: 4rem;"></i>
                            <h3 class="text-danger mt-3">Access Denied</h3>
                            <p class="text-muted">This page is restricted to Administrators and Billing Officers only.</p>
                            <p class="text-muted">Your role: <strong>' . htmlspecialchars($_SESSION['role'] ?? 'Unknown') . '</strong></p>
                            <a href="dashboard.php" class="btn btn-primary mt-3">
                                <i class="bi bi-house-door"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ');
}

require_once 'PHP/config.php';
$db = new Database();

$currentUser = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['name'] ?? 'User',
    'role' => $_SESSION['role']
];

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
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($action) {
            case 'get_payments':
                echo json_encode(api_get_payments($db, $utilityMap));
                break;
            case 'get_payment':
                echo json_encode(api_get_payment($db, $utilityMap));
                break;
            case 'get_unpaid_bills':
                echo json_encode(api_get_unpaid_bills($db, $utilityMap));
                break;
            case 'record_payment':
                echo json_encode(api_record_payment($db));
                break;
            case 'get_customers':
                echo json_encode(api_get_customers($db));
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


function api_get_payments($db, $utilityMap) {
    $search = $_GET['search'] ?? '';
    $payment_method = $_GET['payment_method'] ?? '';

    $sql = "
        SELECT 
            p.Payment_ID,
            p.Payment_date,
            p.Payment_Method,
            p.Amount_paid,
            p.Transaction_Reference,
            p.Notes,
            b.Bill_ID,
            b.Total_amount as Bill_Amount,
            b.Status as Bill_Status,
            c.Customer_ID,
            u.Name AS Customer_Name,
            m.Utility_ID
        FROM PAYMENT p
        LEFT JOIN BILL b ON p.Bill_ID = b.Bill_ID
        LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN [USER] u ON c.User_ID = u.User_ID
        LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
        LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search !== '') {
        $term = "%$search%";
        $sql .= " AND (CAST(p.Payment_ID AS NVARCHAR) LIKE ? OR u.Name LIKE ? OR p.Transaction_Reference LIKE ?)";
        $params[] = $term; $params[] = $term; $params[] = $term;
    }
    
    if ($payment_method !== '') {
        $sql .= " AND p.Payment_Method = ?";
        $params[] = $payment_method;
    }
    
    $sql .= " ORDER BY p.Payment_date DESC";
    
    $rows = $db->fetchAll($sql, $params);
    if ($rows === false) return ['success' => false, 'message' => 'DB error while fetching payments'];

    foreach ($rows as &$r) {
        $r['utility_type'] = $utilityMap[$r['Utility_ID']] ?? 'unknown';
    }
    unset($r);

    return ['success' => true, 'payments' => $rows];
}

function api_get_payment($db, $utilityMap) {
    $payment_id = $_GET['payment_id'] ?? '';
    if (empty($payment_id)) return ['success' => false, 'message' => 'Payment ID required'];

    $sql = "
        SELECT 
            p.Payment_ID,
            p.Payment_date,
            p.Payment_Method,
            p.Amount_paid,
            p.Transaction_Reference,
            p.Notes,
            b.Bill_ID,
            b.Total_amount as Bill_Amount,
            b.Billing_Period,
            b.Status as Bill_Status,
            c.Customer_ID,
            u.Name AS Customer_Name,
            m.Utility_ID
        FROM PAYMENT p
        LEFT JOIN BILL b ON p.Bill_ID = b.Bill_ID
        LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN [USER] u ON c.User_ID = u.User_ID
        LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
        LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
        WHERE p.Payment_ID = ?
    ";

    $payment = $db->fetchOne($sql, [$payment_id]);
    if (!$payment) return ['success' => false, 'message' => 'Payment not found'];

    $payment['utility_type'] = $utilityMap[$payment['Utility_ID']] ?? 'unknown';
    return ['success' => true, 'payment' => $payment];
}

function api_get_unpaid_bills($db, $utilityMap) {
    $customer_id = $_GET['customer_id'] ?? '';
    if (empty($customer_id)) return ['success' => false, 'message' => 'Customer ID required'];

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
    
    $rows = $db->fetchAll($sql, [$customer_id]);
    if ($rows === false) return ['success' => false, 'message' => 'DB error'];

    foreach ($rows as &$r) {
        $r['utility_type'] = $utilityMap[$r['Utility_ID']] ?? 'unknown';
    }
    unset($r);

    return ['success' => true, 'bills' => $rows];
}

function api_record_payment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) return ['success' => false, 'message' => 'Invalid input'];

    $bill_id = $data['bill_id'] ?? null;
    $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
    $method = $data['payment_method'] ?? 'Cash';
    $reference = $data['reference'] ?? null;
    $notes = $data['notes'] ?? null;

    if (!$bill_id || $amount <= 0) {
        return ['success' => false, 'message' => 'bill_id and valid amount required'];
    }

    try {
        // Check if bill exists and get balance
        $bill = $db->fetchOne("SELECT Total_amount, ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = ?), 0) as Paid FROM BILL WHERE Bill_ID = ?", [$bill_id, $bill_id]);
        
        if (!$bill) return ['success' => false, 'message' => 'Bill not found'];
        
        $balance = $bill['Total_amount'] - $bill['Paid'];
        if ($amount > $balance) {
            return ['success' => false, 'message' => 'Amount exceeds bill balance ($' . number_format($balance, 2) . ')'];
        }

        // Record payment
        $sql = "INSERT INTO PAYMENT (Payment_date, Payment_Method, Amount_paid, Bill_ID, Transaction_Reference, Notes)
                VALUES (GETDATE(), ?, ?, ?, ?, ?)";
        
        $payment_id = $db->insert($sql, [$method, $amount, $bill_id, $reference, $notes]);
        
        if (!$payment_id) return ['success' => false, 'message' => 'Failed to record payment'];

        // Update bill status if fully paid
        $newPaid = $bill['Paid'] + $amount;
        if ($newPaid >= $bill['Total_amount']) {
            $db->update("UPDATE BILL SET Status = 'Paid' WHERE Bill_ID = ?", [$bill_id]);
        }

        return ['success' => true, 'message' => 'Payment recorded successfully', 'payment_id' => $payment_id];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function api_get_customers($db) {
    $sql = "SELECT c.Customer_ID, u.Name FROM CUSTOMER c LEFT JOIN [USER] u ON c.User_ID = u.User_ID WHERE c.Status = 'Active' ORDER BY u.Name";
    $rows = $db->fetchAll($sql);
    if ($rows === false) return ['success' => false, 'message' => 'DB error'];
    return ['success' => true, 'customers' => $rows];
}

$stats = [
    'today_collection' => $db->fetchOne("SELECT ISNULL(SUM(Amount_paid), 0) as total FROM PAYMENT WHERE CAST(Payment_date AS DATE) = CAST(GETDATE() AS DATE)")['total'] ?? 0,
    'monthly_collection' => $db->fetchOne("SELECT ISNULL(SUM(Amount_paid), 0) as total FROM PAYMENT WHERE MONTH(Payment_date) = MONTH(GETDATE()) AND YEAR(Payment_date) = YEAR(GETDATE())")['total'] ?? 0,
    'successful_payments' => $db->fetchOne("SELECT COUNT(*) as cnt FROM PAYMENT")['cnt'] ?? 0,
    'pending_verification' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Status IN ('Pending', 'Overdue')")['cnt'] ?? 0
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - Nexsync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>.alerts-area { margin-bottom: 1rem; }</style>
    <?php include 'includes/sidebar_styles.php'; ?>
</head>
<body class="bg-light">
<?php include 'includes/sidebar.php'; ?>

            <div class="main-content">
            <div class="alerts-area" id="alertsArea"></div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-primary">
                    <i class="bi bi-credit-card me-2"></i>Payments Management
                </h4>
                <button class="btn btn-primary" id="btnRecordPayment">
                    <i class="bi bi-cash-coin me-1"></i> Record Payment
                </button>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Collection</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($stats['today_collection'], 2) ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-currency-dollar fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Monthly Collection</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($stats['monthly_collection'], 2) ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-graph-up fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Successful Payments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['successful_payments'] ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-check-circle fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Bills</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending_verification'] ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search payment ID or customer...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterPaymentMethod">
                                <option value="">All Payment Methods</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Payment">Mobile Payment</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" id="btnFilter"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-danger w-100" id="btnClear"><i class="bi bi-x-circle"></i> Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="paymentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Customer</th>
                                    <th>Bill Reference</th>
                                    <th>Utility</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Payment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- JS will populate -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="recordPaymentForm">
            <div class="modal-header">
                <h5 class="modal-title">Record New Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Customer *</label>
                        <select class="form-select" id="pay_customer_select" required>
                            <option value="">Select Customer</option>
                        </select>
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
                    <label class="form-label">Select Bill to Pay *</label>
                    <div id="billsContainer" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                        <p class="text-muted">Select a customer to view their unpaid bills</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Amount to Pay *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="pay_amount_input" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" name="reference" placeholder="Optional">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Process Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Payment ID:</strong>
                        <p id="viewPaymentID"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Customer:</strong>
                        <p id="viewCustomer"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Bill Reference:</strong>
                        <p id="viewBillRef"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Utility Type:</strong>
                        <p id="viewUtilityType"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Amount Paid:</strong>
                        <p id="viewAmount"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Payment Method:</strong>
                        <p id="viewMethod"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Payment Date:</strong>
                        <p id="viewDate"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Transaction Reference:</strong>
                        <p id="viewReference"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Notes:</strong>
                    <p id="viewNotes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const apiBase = 'payments.php?action=';

    function showAlert(message, type='success') {
        const area = document.getElementById('alertsArea');
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `${message} <button class="btn-close" data-bs-dismiss="alert"></button>`;
        area.appendChild(div);
        setTimeout(()=> { if(div.parentNode) div.remove(); }, 6000);
    }

    const tableBody = document.querySelector('#paymentsTable tbody');
    const searchInput = document.getElementById('searchInput');
    const filterPaymentMethod = document.getElementById('filterPaymentMethod');
    const btnFilter = document.getElementById('btnFilter');
    const btnClear = document.getElementById('btnClear');

    const recordPaymentModal = new bootstrap.Modal(document.getElementById('recordPaymentModal'));
    const viewPaymentModal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));

    const recordPaymentForm = document.getElementById('recordPaymentForm');

    document.getElementById('btnRecordPayment').addEventListener('click', async () => {
        await loadCustomers();
        recordPaymentModal.show();
    });

    document.addEventListener('DOMContentLoaded', () => loadPayments());

    btnFilter.addEventListener('click', (e) => { e.preventDefault(); loadPayments(); });
    btnClear.addEventListener('click', (e) => {
        e.preventDefault();
        searchInput.value = '';
        filterPaymentMethod.value = '';
        loadPayments();
    });

    function debounce(fn, wait=300) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(()=> fn(...args), wait); };
    }
    
    searchInput.addEventListener('input', debounce(()=> loadPayments(), 400));
    filterPaymentMethod.addEventListener('change', () => loadPayments());

    async function loadCustomers() {
        try {
            const res = await fetch(apiBase + 'get_customers');
            const json = await res.json();
            if (!json.success) return;

            const select = document.getElementById('pay_customer_select');
            select.innerHTML = '<option value="">Select Customer</option>';
            
            const customers = json.customers || [];
            customers.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Customer_ID;
                opt.textContent = `${c.Name} (${c.Customer_ID})`;
                select.appendChild(opt);
            });
        } catch (err) {
            console.error(err);
        }
    }

    document.getElementById('pay_customer_select').addEventListener('change', async function() {
        const customerId = this.value;
        const container = document.getElementById('billsContainer');
        
        if (!customerId) {
            container.innerHTML = '<p class="text-muted">Select a customer to view their unpaid bills</p>';
            return;
        }
        
        container.innerHTML = '<p class="text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading bills...</p>';
        
        try {
            const res = await fetch(apiBase + 'get_unpaid_bills&customer_id=' + customerId);
            const json = await res.json();
            
            if (!json.success) {
                container.innerHTML = '<p class="text-danger">Error loading bills</p>';
                return;
            }

            const bills = json.bills || [];
            
            if (bills.length === 0) {
                container.innerHTML = '<p class="text-success"><i class="bi bi-check-circle me-2"></i>No unpaid bills for this customer</p>';
                return;
            }
            
            let html = '';
            bills.forEach(bill => {
                html += `
                    <div class="form-check mb-2 p-2 border-bottom">
                        <input class="form-check-input bill-radio" type="radio" name="bill_id" id="bill${bill.Bill_ID}" 
                               value="${bill.Bill_ID}" data-amount="${bill.Balance}" required>
                        <label class="form-check-label w-100" for="bill${bill.Bill_ID}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>BL-${String(bill.Bill_ID).padStart(3,'0')}</strong> - 
                                    <span class="badge ${getUtilityBadge(bill.utility_type)}">${capitalize(bill.utility_type)}</span>
                                </div>
                                <div>
                                    <strong class="text-danger">$${parseFloat(bill.Balance).toFixed(2)}</strong>
                                </div>
                            </div>
                            <small class="text-muted">Due: ${bill.Due_date} | Period: ${bill.Billing_Period}</small>
                        </label>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            document.querySelectorAll('.bill-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const amountInput = document.getElementById('pay_amount_input');
                    if (amountInput) {
                        amountInput.value = parseFloat(this.dataset.amount).toFixed(2);
                    }
                });
            });
            
        } catch (error) {
            container.innerHTML = '<p class="text-danger">Error loading bills</p>';
            console.error(error);
        }
    });

    function renderPayments(payments) {
        tableBody.innerHTML = '';
        if (!payments || payments.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No payments found</td></tr>';
            return;
        }

        payments.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>PAY-${String(p.Payment_ID).padStart(4,'0')}</strong></td>
                <td>${escapeHtml(p.Customer_Name || 'N/A')} (${p.Customer_ID || ''})</td>
                <td>BL-${String(p.Bill_ID).padStart(3,'0')}</td>
                <td><span class="badge ${getUtilityBadge(p.utility_type)}">${capitalize(p.utility_type)}</span></td>
                <td><strong>$${parseFloat(p.Amount_paid).toFixed(2)}</strong></td>
                <td><span class="badge ${getMethodBadge(p.Payment_Method)}">${escapeHtml(p.Payment_Method)}</span></td>
                <td>${formatDateTime(p.Payment_date)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Details" onclick="window.viewPayment(${p.Payment_ID})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-info" title="Print Receipt" onclick="window.printReceipt(${p.Payment_ID})">
                            <i class="bi bi-receipt"></i>
                        </button>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    async function loadPayments() {
        try {
            const search = encodeURIComponent(searchInput.value || '');
            const method = encodeURIComponent(filterPaymentMethod.value || '');
            const qs = [];
            if (search) qs.push('search=' + search);
            if (method) qs.push('payment_method=' + method);

            const res = await fetch(apiBase + 'get_payments' + (qs.length ? '&' + qs.join('&') : ''));
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Error loading payments: ' + (json.message || ''), 'danger');
                return;
            }
            
            window.paymentsData = json.payments || [];
            renderPayments(window.paymentsData);
        } catch (err) {
            showAlert('Error loading payments', 'danger');
            console.error(err);
        }
    }

    recordPaymentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = recordPaymentForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        const raw = Object.fromEntries(new FormData(recordPaymentForm).entries());
        
        if (!raw.bill_id) {
            showAlert('Please select a bill to pay', 'warning');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        const data = {
            bill_id: parseInt(raw.bill_id),
            amount: parseFloat(raw.amount),
            payment_method: raw.payment_method,
            reference: raw.reference || null,
            notes: raw.notes || null
        };

        try {
            const res = await fetch(apiBase + 'record_payment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            
            if (json.success) {
                showAlert('Payment recorded successfully!', 'success');
                recordPaymentModal.hide();
                recordPaymentForm.reset();
                document.getElementById('billsContainer').innerHTML = '<p class="text-muted">Select a customer to view their unpaid bills</p>';
                loadPayments();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('Failed to record payment: ' + (json.message || ''), 'danger');
            }
        } catch (err) {
            showAlert('Error recording payment', 'danger');
            console.error(err);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    window.viewPayment = async function(paymentId) {
        try {
            const res = await fetch(apiBase + 'get_payment&payment_id=' + paymentId);
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Could not load payment details', 'danger');
                return;
            }

            const p = json.payment;
            
            const viewPaymentIdEl = document.getElementById('viewPaymentID');
            const viewCustomerEl = document.getElementById('viewCustomer');
            const viewBillRefEl = document.getElementById('viewBillRef');
            const viewUtilityTypeEl = document.getElementById('viewUtilityType');
            const viewAmountEl = document.getElementById('viewAmount');
            const viewMethodEl = document.getElementById('viewMethod');
            const viewDateEl = document.getElementById('viewDate');
            const viewReferenceEl = document.getElementById('viewReference');
            const viewNotesEl = document.getElementById('viewNotes');

            if (viewPaymentIdEl) viewPaymentIdEl.textContent = 'PAY-' + String(p.Payment_ID).padStart(4,'0');
            if (viewCustomerEl) viewCustomerEl.textContent = `${p.Customer_Name} (${p.Customer_ID})`;
            if (viewBillRefEl) viewBillRefEl.textContent = 'BL-' + String(p.Bill_ID).padStart(3,'0');
            if (viewUtilityTypeEl) viewUtilityTypeEl.innerHTML = `<span class="badge ${getUtilityBadge(p.utility_type)}">${capitalize(p.utility_type)}</span>`;
            if (viewAmountEl) viewAmountEl.innerHTML = `<strong class="text-success">$${parseFloat(p.Amount_paid).toFixed(2)}</strong>`;
            if (viewMethodEl) viewMethodEl.innerHTML = `<span class="badge ${getMethodBadge(p.Payment_Method)}">${p.Payment_Method}</span>`;
            if (viewDateEl) viewDateEl.textContent = formatDateTime(p.Payment_date);
            if (viewReferenceEl) viewReferenceEl.textContent = p.Transaction_Reference || 'N/A';
            if (viewNotesEl) viewNotesEl.textContent = p.Notes || 'No notes';

            viewPaymentModal.show();
        } catch (err) {
            showAlert('Error fetching payment', 'danger');
            console.error(err);
        }
    };

    window.printReceipt = async function(paymentId) {
        try {
            const res = await fetch(apiBase + 'get_payment&payment_id=' + paymentId);
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Could not load payment for printing', 'danger');
                return;
            }

            const p = json.payment;
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Payment Receipt ${String(p.Payment_ID).padStart(4,'0')}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
                        h2 { color: #333; text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .info { margin: 15px 0; display: flex; justify-content: space-between; }
                        .label { font-weight: bold; color: #555; }
                        .value { color: #000; }
                        .total { font-size: 1.3em; margin-top: 20px; padding-top: 20px; border-top: 2px solid #333; }
                        .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #666; }
                    </style>
                </head>
                <body>
                    <h2>NEXSYNC - Payment Receipt</h2>
                    <div class="info">
                        <span class="label">Receipt No:</span>
                        <span class="value">PAY-${String(p.Payment_ID).padStart(4,'0')}</span>
                    </div>
                    <div class="info">
                        <span class="label">Date:</span>
                        <span class="value">${formatDateTime(p.Payment_date)}</span>
                    </div>
                    <div class="info">
                        <span class="label">Customer:</span>
                        <span class="value">${p.Customer_Name} (${p.Customer_ID})</span>
                    </div>
                    <div class="info">
                        <span class="label">Bill Reference:</span>
                        <span class="value">BL-${String(p.Bill_ID).padStart(3,'0')}</span>
                    </div>
                    <div class="info">
                        <span class="label">Utility Type:</span>
                        <span class="value">${capitalize(p.utility_type)}</span>
                    </div>
                    <div class="info">
                        <span class="label">Payment Method:</span>
                        <span class="value">${p.Payment_Method}</span>
                    </div>
                    ${p.Transaction_Reference ? `<div class="info">
                        <span class="label">Transaction Ref:</span>
                        <span class="value">${p.Transaction_Reference}</span>
                    </div>` : ''}
                    <div class="info total">
                        <span class="label">Amount Paid:</span>
                        <span class="value"><strong>$${parseFloat(p.Amount_paid).toFixed(2)}</strong></span>
                    </div>
                    <div class="footer">
                        <p>Thank you for your payment!</p>
                        <p><em>This is a computer-generated receipt.</em></p>
                    </div>
                </body>
                </html>
            `;

            const newWin = window.open('', '_blank');
            newWin.document.write(printContent);
            newWin.document.close();
            setTimeout(() => {
                newWin.print();
            }, 250);
        } catch (err) {
            showAlert('Error printing receipt', 'danger');
            console.error(err);
        }
    };

    document.getElementById('recordPaymentModal').addEventListener('hidden.bs.modal', function() {
        recordPaymentForm.reset();
        document.getElementById('billsContainer').innerHTML = '<p class="text-muted">Select a customer to view their unpaid bills</p>';
    });

    function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function capitalize(s){ return (s||'').charAt(0).toUpperCase() + (s||'').slice(1); }
    
    function getUtilityBadge(type) {
        if(type==='electricity') return 'bg-primary';
        if(type==='water') return 'bg-info';
        if(type==='gas') return 'bg-warning';
        return 'bg-secondary';
    }
    
    function getMethodBadge(method){
        if(!method) return 'bg-secondary';
        const m = method.toLowerCase();
        if(m.includes('cash')) return 'bg-success';
        if(m.includes('card') || m.includes('credit')) return 'bg-primary';
        if(m.includes('bank') || m.includes('transfer')) return 'bg-info';
        if(m.includes('mobile')) return 'bg-warning';
        return 'bg-secondary';
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
})();
</script>
</body>
</html>