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

function getUtilityIDFromType($type) {
    $typeMap = ['electricity' => 1, 'water' => 11, 'gas' => 21];
    return $typeMap[strtolower($type)] ?? null;
}

$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($action) {
            case 'get_bills':
                echo json_encode(api_get_bills($db, $utilityMap));
                break;
            case 'get_bill':
                echo json_encode(api_get_bill($db, $utilityMap));
                break;
            case 'generate_bill':
                echo json_encode(api_generate_bill($db));
                break;
            case 'update_bill':
                echo json_encode(api_update_bill($db));
                break;
            case 'update_reading':
                echo json_encode(api_update_reading($db));
            break;
            case 'get_customers_with_meters':
                echo json_encode(api_get_customers_with_meters($db, $utilityMap));
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


function api_get_bills($db, $utilityMap) {
    $sql = "
        SELECT 
            b.Bill_ID,
            b.Bill_Date,
            b.Billing_Period,
            b.Total_amount,
            b.Due_date,
            b.Status,
            c.Customer_ID,
            u.Name AS Full_Name,
            m.Meter_ID,
            m.Utility_ID,
            ut.UnitCost,
            mr.Units_consumed,
            mr.Current_reading,
            mr.Previous_reading
        FROM BILL b
        LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN [USER] u ON c.User_ID = u.User_ID
        LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
        LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
        LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
        ORDER BY b.Bill_ID DESC
    ";

    $rows = $db->fetchAll($sql);
    if ($rows === false) return ['success' => false, 'message' => 'DB error while fetching bills'];

    foreach ($rows as &$r) {
        $r['utility_type'] = $utilityMap[$r['Utility_ID']] ?? 'unknown';
    }
    unset($r);

    return ['success' => true, 'bills' => $rows];
}

function api_get_bill($db, $utilityMap) {
    $bill_id = $_GET['bill_id'] ?? '';
    if (empty($bill_id)) {
        return ['success' => false, 'message' => 'Bill ID required'];
    }

    $sql = "
        SELECT 
            b.Bill_ID,
            b.Bill_Date,
            b.Billing_Period,
            b.Total_amount,
            b.Due_date,
            b.Status,
            b.Reading_ID,
            c.Customer_ID,
            u.Name AS Full_Name,
            m.Meter_ID,
            m.Utility_ID,
            ut.UnitCost,
            mr.Units_consumed,
            mr.Current_reading,
            mr.Previous_reading
        FROM BILL b
        LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN [USER] u ON c.User_ID = u.User_ID
        LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
        LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
        LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
        WHERE b.Bill_ID = ?
    ";

    $bill = $db->fetchOne($sql, [$bill_id]);
    if (!$bill) {
        return ['success' => false, 'message' => 'Bill not found'];
    }

    $bill['utility_type'] = $utilityMap[$bill['Utility_ID']] ?? 'unknown';
    return ['success' => true, 'bill' => $bill];
}

function api_get_customers_with_meters($db, $utilityMap) {
    $sql = "
        SELECT 
            c.Customer_ID,
            u.Name,
            m.Meter_ID,
            m.Utility_ID,
            ut.UnitCost,
            (SELECT TOP 1 mr.Current_reading FROM METER_READING mr WHERE mr.Meter_ID = m.Meter_ID ORDER BY mr.Reading_date DESC, mr.Reading_ID DESC) AS Last_Reading
        FROM CUSTOMER c
        LEFT JOIN [USER] u ON c.User_ID = u.User_ID
        LEFT JOIN METER m ON c.Customer_ID = m.Customer_ID
        LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
        WHERE c.Status = 'Active' AND m.Status = 'Active'
    ";

    $rows = $db->fetchAll($sql);
    if ($rows === false) return ['success' => false, 'message' => 'DB error'];

    foreach ($rows as &$r) {
        $r['utility_type'] = $utilityMap[$r['Utility_ID']] ?? 'unknown';
    }
    unset($r);

    return ['success' => true, 'customers' => $rows];
}

function api_generate_bill($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) return ['success' => false, 'message' => 'Invalid input'];

    $customer_id = $data['customer_id'] ?? null;
    $meter_id = $data['meter_id'] ?? null;
    $billing_period = $data['billing_period'] ?? date('Y-m');
    $due_date = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

    if (!$customer_id || !$meter_id) {
        return ['success' => false, 'message' => 'customer_id and meter_id required'];
    }

    try {
        $reading = $db->fetchOne(
            "SELECT TOP 1 Reading_ID, Current_reading, Previous_reading
             FROM METER_READING 
             WHERE Meter_ID = ?
             ORDER BY Reading_ID DESC",
            [$meter_id]
        );

        if (!$reading) {
            return ['success' => false, 'message' => 'No meter readings found'];
        }

        $exists = $db->fetchOne(
            "SELECT Bill_ID FROM BILL WHERE Reading_ID = ?",
            [$reading['Reading_ID']]
        );

        if ($exists) {
            return ['success' => false, 'message' => 'Bill already generated for this reading'];
        }

        $units = floatval($reading['Current_reading']) - floatval($reading['Previous_reading']);
        if ($units < 0) {
            return ['success' => false, 'message' => 'Invalid meter reading values'];
        }

        $meter = $db->fetchOne(
            "SELECT ut.UnitCost 
             FROM METER m 
             JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID 
             WHERE m.Meter_ID = ?",
            [$meter_id]
        );

        if (!$meter) return ['success' => false, 'message' => 'Meter not found'];

        $total = $units * floatval($meter['UnitCost']);

        $bill_id = $db->insert(
            "INSERT INTO BILL 
             (Bill_Date, Billing_Period, Total_amount, Due_date, Status, Customer_ID, Reading_ID)
             VALUES (?, ?, ?, ?, 'Pending', ?, ?)",
            [
                date('Y-m-d'),
                $billing_period,
                $total,
                $due_date,
                $customer_id,
                $reading['Reading_ID']
            ]
        );

        return ['success' => true, 'bill_id' => $bill_id, 'total' => $total];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function api_update_bill($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) return ['success' => false, 'message' => 'Invalid input'];
    
    $bill_id = $data['bill_id'] ?? null;
    if (!$bill_id) return ['success' => false, 'message' => 'bill_id required'];

    $fields = [];
    $params = [];

    if (isset($data['billing_period'])) { $fields[] = "Billing_Period = ?"; $params[] = $data['billing_period']; }
    if (isset($data['total_amount'])) { $fields[] = "Total_amount = ?"; $params[] = $data['total_amount']; }
    if (isset($data['due_date'])) { $fields[] = "Due_date = ?"; $params[] = $data['due_date']; }
    if (isset($data['status'])) { $fields[] = "Status = ?"; $params[] = $data['status']; }

    if (empty($fields)) return ['success' => false, 'message' => 'No updatable fields provided'];

    $params[] = $bill_id;
    $sql = "UPDATE BILL SET " . implode(', ', $fields) . " WHERE Bill_ID = ?";
    $ok = $db->update($sql, $params);

    if ($ok === false) return ['success' => false, 'message' => 'Failed to update bill'];
    return ['success' => true, 'message' => 'Bill updated successfully'];
}
function api_update_reading($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => 'Invalid input'];
    }

    $reading_id = $data['reading_id'] ?? null;
    $units_consumed = isset($data['units_consumed']) ? floatval($data['units_consumed']) : null;

    if (!$reading_id || $units_consumed === null) {
        return ['success' => false, 'message' => 'reading_id and units_consumed required'];
    }

    try {
        $reading = $db->fetchOne(
            "SELECT Previous_reading FROM METER_READING WHERE Reading_ID = ?",
            [$reading_id]
        );

        if (!$reading) {
            return ['success' => false, 'message' => 'Reading not found'];
        }

        $previous = floatval($reading['Previous_reading']);
        $new_current = $previous + $units_consumed;

        $sql = "
            UPDATE METER_READING 
            SET Units_consumed = ?, Current_reading = ?
            WHERE Reading_ID = ?
        ";

        $ok = $db->update($sql, [$units_consumed, $new_current, $reading_id]);

        if ($ok === false) {
            return ['success' => false, 'message' => 'Failed to update reading'];
        }

        return ['success' => true, 'message' => 'Reading updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}


$stats = [
    'total_revenue' => $db->fetchOne("SELECT ISNULL(SUM(Total_amount), 0) as total FROM BILL")['total'] ?? 0,
    'paid' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Status='Paid'")['cnt'] ?? 0,
    'pending' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Status='Pending'")['cnt'] ?? 0,
    'overdue' => $db->fetchOne("SELECT COUNT(*) as cnt FROM BILL WHERE Status='Overdue'")['cnt'] ?? 0
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - Nexsync</title>
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
                    <i class="bi bi-receipt me-2"></i>Billing Management
                </h4>
                <div>
                    <button class="btn btn-primary" id="btnGenerateBill">
                        <i class="bi bi-plus-circle me-1"></i> Generate Bill
                    </button>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($stats['total_revenue'],2) ?></div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid Bills</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['paid'] ?></div>
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
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending'] ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card utility-card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Bills</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['overdue'] ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="billsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Customer</th>
                                    <th>Utility Type</th>
                                    <th>Units</th>
                                    <th>Billing Period</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Bill Modal -->
<div class="modal fade" id="generateBillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="generateBillForm">
            <div class="modal-header">
                <h5 class="modal-title">Generate Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Customer *</label>
                    <select class="form-select" name="customer_id" id="gen_customer_select" required>
                        <option value="">-- Select Customer --</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Meter ID</label>
                        <input type="text" id="gen_meter_id" class="form-control" readonly>
                        <input type="hidden" name="meter_id" id="gen_meter_id_hidden">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Utility Type</label>
                        <input type="text" id="gen_utility_type" class="form-control" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Unit Rate ($)</label>
                        <input type="text" id="gen_unit_rate" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Reading</label>
                        <input type="text" id="gen_last_reading" class="form-control" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Billing Period *</label>
                        <input type="month" class="form-control" name="billing_period" value="<?= date('Y-m') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Bill will be generated based on the latest meter reading.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Bill</button>
            </div>
        </form>
    </div>
</div>

<!-- View Bill Modal -->
<div class="modal fade" id="viewBillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>View Bill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Bill ID:</strong>
                        <p id="viewBillID"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Customer:</strong>
                        <p id="viewCustomer"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Utility:</strong>
                        <p id="viewUtility"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Billing Period:</strong>
                        <p id="viewPeriod"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Units Consumed:</strong>
                        <p id="viewUnits"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Total Amount:</strong>
                        <p id="viewTotal"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Due Date:</strong>
                        <p id="viewDue"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong>
                        <p id="viewStatus"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bill Modal -->
<div class="modal fade" id="editBillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="editBillForm">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="bill_id" id="edit_bill_id">
                <input type="hidden" id="edit_reading_id">
                <input type="hidden" id="edit_unit_cost">

                <div class="mb-3">
                    <label class="form-label">Customer</label>
                    <input type="text" id="edit_customer" class="form-control" readonly>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Utility Type</label>
                        <input type="text" id="edit_utility_display" class="form-control" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Unit Rate ($)</label>
                        <input type="text" id="edit_unit_rate_display" class="form-control" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Units Consumed *</label>
                        <input type="number" step="0.01" class="form-control" name="units_consumed" id="edit_units_consumed" required>
                        <small class="text-muted">Changing units will recalculate the total amount</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Total Amount ($)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_total_amount_display" readonly>
                        <input type="hidden" name="total_amount" id="edit_total_amount">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Billing Period *</label>
                        <input type="text" class="form-control" name="billing_period" id="edit_billing_period" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="form-control" name="due_date" id="edit_due_date" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status *</label>
                    <select class="form-select" name="status" id="edit_status" required>
                        <option value="Pending">Pending</option>
                        <option value="Paid">Paid</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Note:</strong> Changing units consumed will automatically update the meter reading and recalculate the total amount.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const apiBase = 'billing.php?action=';

    function showAlert(message, type='success') {
        const area = document.getElementById('alertsArea');
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `${message} <button class="btn-close" data-bs-dismiss="alert"></button>`;
        area.appendChild(div);
        setTimeout(()=> { if(div.parentNode) div.remove(); }, 6000);
    }

    const tableBody = document.querySelector('#billsTable tbody');
    const generateBillModal = new bootstrap.Modal(document.getElementById('generateBillModal'));
    const viewBillModal = new bootstrap.Modal(document.getElementById('viewBillModal'));
    const editBillModal = new bootstrap.Modal(document.getElementById('editBillModal'));

    const generateBillForm = document.getElementById('generateBillForm');
    const editBillForm = document.getElementById('editBillForm');

    document.getElementById('btnGenerateBill').addEventListener('click', async () => {
        await loadCustomersWithMeters();
        generateBillModal.show();
    });

    document.addEventListener('DOMContentLoaded', () => loadBills());

    async function loadCustomersWithMeters() {
        try {
            const res = await fetch(apiBase + 'get_customers_with_meters');
            const json = await res.json();
            if (!json.success) return;

            const select = document.getElementById('gen_customer_select');
            select.innerHTML = '<option value="">-- Select Customer --</option>';
            
            const customers = json.customers || [];
            customers.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Customer_ID;
                opt.dataset.meterInfo = JSON.stringify(c);
                opt.textContent = `${c.Name} (${c.Customer_ID}) - ${capitalize(c.utility_type)}`;
                select.appendChild(opt);
            });
        } catch (err) {
            console.error(err);
        }
    }

    document.getElementById('gen_customer_select').addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.dataset.meterInfo) {
            document.getElementById('gen_meter_id').value = '';
            document.getElementById('gen_meter_id_hidden').value = '';
            document.getElementById('gen_utility_type').value = '';
            document.getElementById('gen_unit_rate').value = '';
            document.getElementById('gen_last_reading').value = '';
            return;
        }

        const info = JSON.parse(opt.dataset.meterInfo);
        document.getElementById('gen_meter_id').value = info.Meter_ID || '';
        document.getElementById('gen_meter_id_hidden').value = info.Meter_ID || '';
        document.getElementById('gen_utility_type').value = capitalize(info.utility_type) || '';
        document.getElementById('gen_unit_rate').value = parseFloat(info.UnitCost || 0).toFixed(2);
        document.getElementById('gen_last_reading').value = parseFloat(info.Last_Reading || 0).toFixed(2);
    });

    function renderBills(bills) {
        tableBody.innerHTML = '';
        if (!bills || bills.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No bills found</td></tr>';
            return;
        }

        bills.forEach(b => {
            const tr = document.createElement('tr');
            const units = b.Units_consumed ? parseFloat(b.Units_consumed).toFixed(2) : '0.00';
            tr.innerHTML = `
                <td><strong>BL-${String(b.Bill_ID).padStart(3,'0')}</strong></td>
                <td>${escapeHtml(b.Full_Name || 'N/A')} (${b.Customer_ID || ''})</td>
                <td><span class="badge ${getUtilityBadge(b.utility_type)}">${capitalize(b.utility_type)}</span></td>
                <td>${units}</td>
                <td>${escapeHtml(b.Billing_Period || '')}</td>
                <td><strong>$${parseFloat(b.Total_amount).toFixed(2)}</strong></td>
                <td>${b.Due_date || ''}</td>
                <td><span class="badge ${getStatusBadge(b.Status)}">${escapeHtml(b.Status)}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Bill" onclick="window.viewBill(${b.Bill_ID})"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-warning" title="Edit Bill" onclick="window.editBill(${b.Bill_ID})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-info" title="Print Bill" onclick="window.printBill(${b.Bill_ID})"><i class="bi bi-printer"></i></button>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    async function loadBills() {
        try {
            const res = await fetch(apiBase + 'get_bills');
            const json = await res.json();
            if (!json.success) {
                showAlert('Error loading bills: ' + (json.message || ''), 'danger');
                return;
            }
            window.billsData = json.bills || [];
            renderBills(window.billsData);
        } catch (err) {
            showAlert('Error loading bills', 'danger');
            console.error(err);
        }
    }

    generateBillForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = generateBillForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

        const raw = Object.fromEntries(new FormData(generateBillForm).entries());
        const data = {
            customer_id: parseInt(raw.customer_id),
            meter_id: parseInt(raw.meter_id),
            billing_period: raw.billing_period,
            due_date: raw.due_date
        };

        try {
            const res = await fetch(apiBase + 'generate_bill', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            
            if (json.success) {
                showAlert(`Bill generated successfully! Total: $${json.total.toFixed(2)}`, 'success');
                generateBillModal.hide();
                generateBillForm.reset();
                loadBills();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('Failed to generate bill: ' + (json.message || ''), 'danger');
            }
        } catch (err) {
            showAlert('Error generating bill', 'danger');
            console.error(err);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    window.viewBill = async function(billId) {
        try {
            const res = await fetch(apiBase + 'get_bill&bill_id=' + billId);
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Could not load bill details', 'danger');
                return;
            }

            const b = json.bill;
            
            const viewBillIdEl = document.getElementById('viewBillID');
            const viewCustomerEl = document.getElementById('viewCustomer');
            const viewUtilityEl = document.getElementById('viewUtility');
            const viewPeriodEl = document.getElementById('viewPeriod');
            const viewUnitsEl = document.getElementById('viewUnits');
            const viewTotalEl = document.getElementById('viewTotal');
            const viewDueEl = document.getElementById('viewDue');
            const viewStatusEl = document.getElementById('viewStatus');

            if (viewBillIdEl) viewBillIdEl.textContent = 'BL-' + String(b.Bill_ID).padStart(3,'0');
            if (viewCustomerEl) viewCustomerEl.textContent = `${b.Full_Name} (${b.Customer_ID})`;
            if (viewUtilityEl) viewUtilityEl.innerHTML = `<span class="badge ${getUtilityBadge(b.utility_type)}">${capitalize(b.utility_type)}</span>`;
            if (viewPeriodEl) viewPeriodEl.textContent = b.Billing_Period || 'N/A';
            if (viewUnitsEl) viewUnitsEl.textContent = b.Units_consumed ? parseFloat(b.Units_consumed).toFixed(2) : '0.00';
            if (viewTotalEl) viewTotalEl.textContent = '$' + parseFloat(b.Total_amount).toFixed(2);
            if (viewDueEl) viewDueEl.textContent = b.Due_date || 'N/A';
            if (viewStatusEl) viewStatusEl.innerHTML = `<span class="badge ${getStatusBadge(b.Status)}">${b.Status}</span>`;

            viewBillModal.show();
        } catch (err) {
            showAlert('Error fetching bill', 'danger');
            console.error(err);
        }
    };

    window.editBill = async function(billId) {
        try {
            const res = await fetch(apiBase + 'get_bill&bill_id=' + billId);
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Could not load bill details', 'danger');
                return;
            }

            const b = json.bill;
            const editBillIdEl = document.getElementById('edit_bill_id');
            const editCustomerEl = document.getElementById('edit_customer');
            const editBillingPeriodEl = document.getElementById('edit_billing_period');
            const editTotalAmountEl = document.getElementById('edit_total_amount');
            const editDueDateEl = document.getElementById('edit_due_date');
            const editStatusEl = document.getElementById('edit_status');
            const billInfoDisplayEl = document.getElementById('billInfoDisplay');

            if (editBillIdEl) editBillIdEl.value = b.Bill_ID;
            if (editCustomerEl) editCustomerEl.value = `${b.Full_Name} (${b.Customer_ID})`;
            if (editBillingPeriodEl) editBillingPeriodEl.value = b.Billing_Period || '';
            if (editTotalAmountEl) editTotalAmountEl.value = parseFloat(b.Total_amount).toFixed(2);
            if (editDueDateEl) editDueDateEl.value = b.Due_date || '';
            if (editStatusEl) editStatusEl.value = b.Status || 'Pending';

            const units = b.Units_consumed ? parseFloat(b.Units_consumed).toFixed(2) : '0.00';
            if (billInfoDisplayEl) {
                billInfoDisplayEl.innerHTML = `
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="bi bi-speedometer me-1"></i> Units Consumed:</strong> ${units}
                            </div>
                            <div class="col-md-6">
                                <strong><i class="bi bi-lightning me-1"></i> Utility:</strong> ${capitalize(b.utility_type)}
                            </div>
                        </div>
                    </div>
                `;
            }

            editBillModal.show();
        } catch (err) {
            showAlert('Error fetching bill', 'danger');
            console.error(err);
        }
    };

    editBillForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = editBillForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        const raw = Object.fromEntries(new FormData(editBillForm).entries());
        const data = {
            bill_id: parseInt(raw.bill_id),
            billing_period: raw.billing_period,
            total_amount: parseFloat(raw.total_amount),
            due_date: raw.due_date,
            status: raw.status
        };

        try {
            const res = await fetch(apiBase + 'update_bill', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            
            if (json.success) {
                showAlert('Bill updated successfully!', 'success');
                editBillModal.hide();
                editBillForm.reset();
                loadBills();
            } else {
                showAlert('Failed to update bill: ' + (json.message || ''), 'danger');
            }
        } catch (err) {
            showAlert('Error updating bill', 'danger');
            console.error(err);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    window.printBill = async function(billId) {
        try {
            const res = await fetch(apiBase + 'get_bill&bill_id=' + billId);
            const json = await res.json();
            
            if (!json.success) {
                showAlert('Could not load bill for printing', 'danger');
                return;
            }

            const b = json.bill;
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Bill ${String(b.Bill_ID).padStart(3,'0')}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h2 { color: #333; }
                        .info { margin: 10px 0; }
                        .label { font-weight: bold; }
                    </style>
                </head>
                <body>
                    <h2>NEXSYNC - Utility Bill</h2>
                    <hr>
                    <div class="info"><span class="label">Bill ID:</span> BL-${String(b.Bill_ID).padStart(3,'0')}</div>
                    <div class="info"><span class="label">Customer:</span> ${b.Full_Name} (${b.Customer_ID})</div>
                    <div class="info"><span class="label">Utility Type:</span> ${capitalize(b.utility_type)}</div>
                    <div class="info"><span class="label">Billing Period:</span> ${b.Billing_Period || 'N/A'}</div>
                    <div class="info"><span class="label">Units Consumed:</span> ${b.Units_consumed ? parseFloat(b.Units_consumed).toFixed(2) : '0.00'}</div>
                    <div class="info"><span class="label">Total Amount:</span> $${parseFloat(b.Total_amount).toFixed(2)}</div>
                    <div class="info"><span class="label">Due Date:</span> ${b.Due_date || 'N/A'}</div>
                    <div class="info"><span class="label">Status:</span> ${b.Status || 'N/A'}</div>
                    <hr>
                    <p><em>Thank you for your business!</em></p>
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
            showAlert('Error printing bill', 'danger');
            console.error(err);
        }
    };

    document.getElementById('editBillModal').addEventListener('hidden.bs.modal', function() {
        editBillForm.reset();
        document.getElementById('billInfoDisplay').innerHTML = '';
    });

    document.getElementById('generateBillModal').addEventListener('hidden.bs.modal', function() {
        generateBillForm.reset();
    });

    function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function capitalize(s){ return (s||'').charAt(0).toUpperCase() + (s||'').slice(1); }
    function getUtilityBadge(type) {
        if(type==='electricity') return 'bg-primary';
        if(type==='water') return 'bg-info';
        if(type==='gas') return 'bg-warning';
        return 'bg-secondary';
    }
    function getStatusBadge(status){
        if(!status) return 'bg-secondary';
        const s = status.toLowerCase();
        if(s==='paid') return 'bg-success';
        if(s==='pending') return 'bg-warning';
        if(s==='overdue') return 'bg-danger';
        return 'bg-secondary';
    }
})();
</script>
</body>
</html>