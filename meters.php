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

// utility map 
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
            case 'get_meters':
                echo json_encode(api_get_meters($db));
                break;
            case 'get_meter':
                echo json_encode(api_get_meter($db));
                break;
            case 'add_meter':
                echo json_encode(api_add_meter($db));
                break;
            case 'update_meter':
                echo json_encode(api_update_meter($db));
                break;
            case 'record_reading':
                echo json_encode(api_record_reading($db));
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

function api_get_meters($db) {
    global $utilityMap;
    $search = $_GET['search'] ?? '';
    $utility_id = $_GET['utility_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';

    $sql = "SELECT 
                m.Meter_ID, m.Meter_Serial, m.Installation_date, m.Status, m.Last_reading_date, m.Location, m.Utility_ID, m.Customer_ID,
                u.Name AS customer_name,
                (SELECT TOP 1 mr.Current_reading FROM METER_READING mr WHERE mr.Meter_ID = m.Meter_ID ORDER BY mr.Reading_date DESC, mr.Reading_ID DESC) AS Current_reading
            FROM METER m
            LEFT JOIN CUSTOMER c ON m.Customer_ID = c.Customer_ID
            LEFT JOIN [USER] u ON c.User_ID = u.User_ID
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $term = "%$search%";
        $sql .= " AND (CAST(m.Meter_ID AS NVARCHAR) LIKE ? OR m.Meter_Serial LIKE ? OR u.Name LIKE ?)";
        $params[] = $term; $params[] = $term; $params[] = $term;
    }
    
    if ($utility_id !== '') { 
        $util_id = is_numeric($utility_id) ? $utility_id : getUtilityIDFromType($utility_id);
        if ($util_id) {
            $sql .= " AND m.Utility_ID = ?"; 
            $params[] = $util_id;
        }
    }
    
    if ($status !== '') { $sql .= " AND m.Status = ?"; $params[] = $status; }
    if ($customer_id !== '') { $sql .= " AND m.Customer_ID = ?"; $params[] = $customer_id; }

    $sql .= " ORDER BY m.Meter_ID";

    $rows = $db->fetchAll($sql, $params);
    if ($rows === false) return ['success' => false, 'message' => 'DB error while fetching meters'];

    foreach ($rows as &$r) {
        $r['utility_type'] = $utilityMap[$r['Utility_ID']] ?? 'unknown';
        if (!isset($r['Current_reading'])) $r['Current_reading'] = null;
    }
    unset($r);

    return ['success' => true, 'meters' => $rows];
}

function api_get_meter($db) {
    global $utilityMap;
    $meter_id = $_GET['meter_id'] ?? '';
    if (empty($meter_id)) return ['success' => false, 'message' => 'Meter ID required'];

    $sql = "SELECT 
                m.Meter_ID, m.Meter_Serial, m.Installation_date, m.Status, m.Last_reading_date, m.Location, m.Utility_ID, m.Customer_ID,
                u.Name AS customer_name,
                (SELECT TOP 1 mr.Current_reading FROM METER_READING mr WHERE mr.Meter_ID = m.Meter_ID ORDER BY mr.Reading_date DESC, mr.Reading_ID DESC) AS Current_reading
            FROM METER m
            LEFT JOIN CUSTOMER c ON m.Customer_ID = c.Customer_ID
            LEFT JOIN [USER] u ON c.User_ID = u.User_ID
            WHERE m.Meter_ID = ?";
    $meter = $db->fetchOne($sql, [$meter_id]);
    if (!$meter) return ['success' => false, 'message' => 'Meter not found'];

    $meter['utility_type'] = $utilityMap[$meter['Utility_ID']] ?? 'unknown';
    return ['success' => true, 'meter' => $meter];
}

function api_add_meter($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) return ['success' => false, 'message' => 'Invalid input'];

    $customer_id = $data['customer_id'] ?? null;
    $utility_id_input = $data['utility_id'] ?? null;
    $serial = $data['serial_number'] ?? ($data['meter_serial'] ?? null);
    $installation_date = $data['installation_date'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'Active';
    $initial_reading = isset($data['initial_reading']) ? floatval($data['initial_reading']) : 0.0;
    $location = $data['location'] ?? null;

    $utility_id = is_numeric($utility_id_input) ? $utility_id_input : getUtilityIDFromType($utility_id_input);

    if (!$customer_id || !$utility_id || !$serial) {
        return ['success' => false, 'message' => 'customer_id, utility_id and serial_number are required'];
    }

    try {
        $sql = "INSERT INTO METER (Meter_Serial, Installation_date, Status, Location, Utility_ID, Customer_ID)
                VALUES (?, ?, ?, ?, ?, ?)";
        $insertId = $db->insert($sql, [$serial, $installation_date, $status, $location, $utility_id, $customer_id]);

        $meter_id = null;
        if ($insertId && is_numeric($insertId)) {
            $meter_id = (int)$insertId;
        } else {
            $row = $db->fetchOne("SELECT Meter_ID FROM METER WHERE Meter_Serial = ?", [$serial]);
            $meter_id = $row['Meter_ID'] ?? null;
        }

        if (!$meter_id) return ['success' => false, 'message' => 'Failed to create meter'];

        if ($initial_reading > 0) {
            $reading_sql = "INSERT INTO METER_READING (Reading_date, Units_consumed, Current_reading, Previous_reading, Meter_ID)
                            VALUES (?, ?, ?, ?, ?)";
            $db->insert($reading_sql, [$installation_date, $initial_reading, $initial_reading, 0.0, $meter_id]);
            $db->update("UPDATE METER SET Last_reading_date = ? WHERE Meter_ID = ?", [$installation_date, $meter_id]);
        }

        return ['success' => true, 'message' => 'Meter registered successfully', 'meter_id' => $meter_id];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function api_update_meter($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) return ['success' => false, 'message' => 'Invalid input'];
    $meter_id = $data['meter_id'] ?? null;
    if (!$meter_id) return ['success' => false, 'message' => 'meter_id required'];

    $fields = [];
    $params = [];

    if (isset($data['serial_number'])) { $fields[] = "Meter_Serial = ?"; $params[] = $data['serial_number']; }
    if (isset($data['installation_date'])) { $fields[] = "Installation_date = ?"; $params[] = $data['installation_date']; }
    if (isset($data['status'])) { $fields[] = "Status = ?"; $params[] = $data['status']; }
    if (isset($data['location'])) { $fields[] = "Location = ?"; $params[] = $data['location']; }
    
    if (isset($data['utility_id'])) { 
        $fields[] = "Utility_ID = ?"; 
        $util_id = is_numeric($data['utility_id']) ? $data['utility_id'] : getUtilityIDFromType($data['utility_id']);
        $params[] = $util_id; 
    }
    
    if (isset($data['customer_id'])) { $fields[] = "Customer_ID = ?"; $params[] = $data['customer_id']; }

    if (empty($fields)) return ['success' => false, 'message' => 'No updatable fields provided'];

    $params[] = $meter_id;
    $sql = "UPDATE METER SET " . implode(', ', $fields) . " WHERE Meter_ID = ?";
    $ok = $db->update($sql, $params);

    if ($ok === false) return ['success' => false, 'message' => 'Failed to update meter'];

    return ['success' => true, 'message' => 'Meter updated successfully'];
}

function api_record_reading($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => 'Invalid input'];
    }

    $meter_id = $data['meter_id'] ?? null;
    $current_reading = isset($data['current_reading']) ? floatval($data['current_reading']) : null;
    $reading_date = $data['reading_date'] ?? date('Y-m-d');

    if (!$meter_id || $current_reading === null) {
        return ['success' => false, 'message' => 'meter_id and current_reading required'];
    }

    try {
        $prev = $db->fetchOne(
            "SELECT TOP 1 Current_reading 
             FROM METER_READING 
             WHERE Meter_ID = ?
             ORDER BY Reading_ID DESC",
            [$meter_id]
        );

        $previous = $prev ? floatval($prev['Current_reading']) : 0.0;
        $units = $current_reading - $previous;

        if ($units < 0) {
            return [
                'success' => false,
                'message' => 'Current reading cannot be less than previous reading (' . $previous . ')'
            ];
        }

        $sql = "
            INSERT INTO METER_READING
            (Reading_date, Units_consumed, Current_reading, Previous_reading, Meter_ID)
            VALUES (?, ?, ?, ?, ?)
        ";

        $insert = $db->insert($sql, [
            $reading_date,
            $units,
            $current_reading,
            $previous,
            $meter_id
        ]);

        if (!$insert) {
            return ['success' => false, 'message' => 'Failed to record reading'];
        }

        $db->update(
            "UPDATE METER SET Last_reading_date = ? WHERE Meter_ID = ?",
            [$reading_date, $meter_id]
        );

        return [
            'success' => true,
            'message' => 'Reading recorded successfully',
            'consumption' => $units
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

$customers = $db->fetchAll("SELECT c.Customer_ID, u.Name AS customer_name FROM CUSTOMER c LEFT JOIN [USER] u ON c.User_ID = u.User_ID WHERE c.Status = 'Active' ORDER BY u.Name");
$totalMeters = $db->fetchOne("SELECT COUNT(*) AS count FROM METER")['count'] ?? 0;
$activeMeters = $db->fetchOne("SELECT COUNT(*) AS count FROM METER WHERE Status = 'Active'")['count'] ?? 0;
$faultyMeters = $db->fetchOne("SELECT COUNT(*) AS count FROM METER WHERE Status = 'Faulty'")['count'] ?? 0;
$readingsThisMonth = $db->fetchOne("SELECT COUNT(*) AS count FROM METER_READING WHERE MONTH(Reading_date) = MONTH(GETDATE()) AND YEAR(Reading_date) = YEAR(GETDATE())")['count'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Meter Management - Nexsync</title>
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
                    <h4 class="text-primary"><i class="bi bi-speedometer me-2"></i>Meter Management</h4>
                    <div>
                        <button class="btn btn-outline-primary me-2" id="btnRecordReading">
                            <i class="bi bi-pencil-square me-1"></i> Record Reading
                        </button>
                        <button class="btn btn-primary" id="btnAddMeter">
                            <i class="bi bi-plus-circle me-1"></i> Add Meter
                        </button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <?php 
                    $stats = [
                        ['label'=>'Total Meters','value'=>$totalMeters,'icon'=>'bi-speedometer2','color'=>'primary'],
                        ['label'=>'Active Meters','value'=>$activeMeters,'icon'=>'bi-check-circle','color'=>'success'],
                        ['label'=>'Readings This Month','value'=>$readingsThisMonth,'icon'=>'bi-clipboard-data','color'=>'warning'],
                        ['label'=>'Faulty Meters','value'=>$faultyMeters,'icon'=>'bi-exclamation-triangle','color'=>'danger']
                    ];
                    foreach ($stats as $stat): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card utility-card border-left-<?= $stat['color'] ?> shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-<?= $stat['color'] ?> text-uppercase mb-1"><?= $stat['label'] ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stat['value'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi <?= $stat['icon'] ?> fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Search and Filter -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search meter ID or customer...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="filterUtility">
                                    <option value="">All Utility Types</option>
                                    <option value="electricity">Electricity</option>
                                    <option value="water">Water</option>
                                    <option value="gas">Gas</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="filterStatus">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Faulty">Faulty</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="filterCustomer">
                                    <option value="">All Customers</option>
                                    <?php foreach($customers as $customer): ?>
                                        <option value="<?= $customer['Customer_ID'] ?>"><?= htmlspecialchars($customer['customer_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" id="btnFilter"><i class="bi bi-funnel"></i> Filter</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meters Table -->
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="metersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Meter ID</th>
                                        <th>Customer</th>
                                        <th>Utility Type</th>
                                        <th>Current Reading</th>
                                        <th>Last Reading Date</th>
                                        <th>Status</th>
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

<!-- Add Meter Modal -->
<div class="modal fade" id="addMeterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="addMeterForm">
            <div class="modal-header">
                <h5 class="modal-title">Register New Meter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Customer *</label>
                        <select class="form-select" name="customer_id" required>
                            <option value="">Select Customer</option>
                            <?php foreach($customers as $customer): ?>
                                <option value="<?= $customer['Customer_ID'] ?>"><?= htmlspecialchars($customer['customer_name'].' ('.$customer['Customer_ID'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Utility *</label>
                        <select class="form-select" name="utility_id" required>
                            <option value="">Select Utility</option>
                            <option value="electricity">Electricity</option>
                            <option value="water">Water</option>
                            <option value="gas">Gas</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Meter Serial *</label>
                        <input class="form-control" name="serial_number" required placeholder="e.g. MTR-12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Installation Date *</label>
                        <input type="date" class="form-control" name="installation_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Initial Reading (optional)</label>
                        <input type="number" step="0.01" class="form-control" name="initial_reading" placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Faulty">Faulty</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Location (optional)</label>
                        <input class="form-control" name="location" placeholder="e.g. Main Building - Floor 2">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Register Meter</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Reading Modal -->
<div class="modal fade" id="recordReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="recordReadingForm">
            <div class="modal-header">
                <h5 class="modal-title">Record Meter Reading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="meter_id" id="reading_meter_id">
                <div class="mb-3">
                    <label class="form-label">Meter ID</label>
                    <input type="text" id="reading_meter_display" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Reading *</label>
                    <input type="number" step="0.01" class="form-control" name="current_reading" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reading Date *</label>
                    <input type="date" class="form-control" name="reading_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Record Reading</button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Meter Modal -->
<div class="modal fade" id="editMeterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="editMeterForm">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Meter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="meter_id" id="edit_meter_id">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Meter ID</label>
                        <input type="text" id="edit_meter_id_display" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer *</label>
                        <select class="form-select" name="customer_id" id="edit_customer_id" required>
                            <option value="">Select Customer</option>
                            <?php foreach($customers as $customer): ?>
                                <option value="<?= $customer['Customer_ID'] ?>"><?= htmlspecialchars($customer['customer_name'].' ('.$customer['Customer_ID'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Utility *</label>
                        <select class="form-select" name="utility_id" id="edit_utility_id" required>
                            <option value="">Select Utility</option>
                            <option value="electricity">Electricity</option>
                            <option value="water">Water</option>
                            <option value="gas">Gas</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Meter Serial *</label>
                        <input class="form-control" name="serial_number" id="edit_serial_number" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Installation Date *</label>
                        <input type="date" class="form-control" name="installation_date" id="edit_installation_date" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Faulty">Faulty</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Location</label>
                        <input class="form-control" name="location" id="edit_location" placeholder="e.g. Main Building - Floor 2">
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> Changing the utility type or customer may affect existing readings and bills.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark">
                    <i class="bi bi-save me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const apiBase = 'meters.php?action=';

    /*  ALERT  */
    function showAlert(message, type='success') {
        const area = document.getElementById('alertsArea');
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `${message} <button class="btn-close" data-bs-dismiss="alert"></button>`;
        area.appendChild(div);
        setTimeout(()=> { if(div.parentNode) div.remove(); }, 6000);
    }

    /*  DOM REFERENCES  */
    const tableBody = document.querySelector('#metersTable tbody');

    const addMeterModal = new bootstrap.Modal(document.getElementById('addMeterModal'));
    const recordReadingModal = new bootstrap.Modal(document.getElementById('recordReadingModal'));
    const editMeterModal = new bootstrap.Modal(document.getElementById('editMeterModal'));

    const addMeterForm = document.getElementById('addMeterForm');
    const recordReadingForm = document.getElementById('recordReadingForm');
    const editMeterForm = document.getElementById('editMeterForm');

    /*  BUTTON EVENTS */
    document.getElementById('btnAddMeter').addEventListener('click', () => addMeterModal.show());
    document.getElementById('btnRecordReading').addEventListener('click', () => {
        showAlert('Please select a meter from the table to record a reading', 'info');
    });

    document.addEventListener('DOMContentLoaded', loadMeters);
    document.getElementById('btnFilter').addEventListener('click', loadMeters);

    /*  RENDER TABLE  */
    function renderMeters(meters) {
        tableBody.innerHTML = '';

        if (!meters || meters.length === 0) {
            tableBody.innerHTML =
                '<tr><td colspan="7" class="text-center text-muted">No meters found</td></tr>';
            return;
        }

        meters.forEach(m => {
            const reading = m.Current_reading ? parseFloat(m.Current_reading).toFixed(2) : 'N/A';
            const lastDate = m.Last_reading_date || 'Never';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${m.Meter_ID}</strong></td>
                <td>${escapeHtml(m.customer_name || 'N/A')}</td>
                <td><span class="badge ${getUtilityBadge(m.utility_type)}">${capitalize(m.utility_type)}</span></td>
                <td>${reading}</td>
                <td>${lastDate}</td>
                <td><span class="badge ${getStatusBadge(m.Status)}">${escapeHtml(m.Status)}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewMeter(${m.Meter_ID})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="recordReading(${m.Meter_ID})">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="editMeter(${m.Meter_ID})">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    /*  LOAD METERS */
    async function loadMeters() {
        try {
            const params = [];
            const search = document.getElementById('searchInput').value || '';
            const utility = document.getElementById('filterUtility').value || '';
            const status = document.getElementById('filterStatus').value || '';
            const customer = document.getElementById('filterCustomer').value || '';

            if (search) params.push('search=' + encodeURIComponent(search));
            if (utility) params.push('utility_id=' + encodeURIComponent(utility));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (customer) params.push('customer_id=' + encodeURIComponent(customer));

            const res = await fetch(apiBase + 'get_meters' + (params.length ? '&' + params.join('&') : ''));
            const json = await res.json();

            if (!json.success) {
                showAlert('Error loading meters', 'danger');
                return;
            }

            window.metersData = json.meters || [];
            renderMeters(window.metersData);
        } catch (err) {
            showAlert('Error loading meters', 'danger');
            console.error(err);
        }
    }

    /*  ADD METER  */
    addMeterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = addMeterForm.querySelector('button[type="submit"]');
        const original = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registering...';

        const raw = Object.fromEntries(new FormData(addMeterForm).entries());
        const data = {
            customer_id: parseInt(raw.customer_id),
            utility_id: raw.utility_id,
            serial_number: raw.serial_number,
            installation_date: raw.installation_date,
            status: raw.status,
            location: raw.location || null,
            initial_reading: raw.initial_reading ? parseFloat(raw.initial_reading) : 0
        };

        try {
            const res = await fetch(apiBase + 'add_meter', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();

            if (json.success) {
                showAlert('Meter registered successfully!');
                addMeterModal.hide();
                addMeterForm.reset();
                loadMeters();
            } else {
                showAlert(json.message || 'Failed to register meter', 'danger');
            }
        } catch (err) {
            showAlert('Error registering meter', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
/*  OPEN RECORD READING MODAL  */
window.recordReading = function(meterId) {
    const meter = window.metersData.find(m => m.Meter_ID == meterId);

    if (!meter) {
        showAlert('Meter not found', 'danger');
        return;
    }

    document.getElementById('reading_meter_id').value = meter.Meter_ID;
    document.getElementById('reading_meter_display').value =
        `${meter.Meter_ID} - ${meter.customer_name || 'Unknown'}`;

    recordReadingModal.show();
};

    /*  RECORD READING */
    recordReadingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = recordReadingForm.querySelector('button[type="submit"]');
        const original = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Recording...';

        const raw = Object.fromEntries(new FormData(recordReadingForm).entries());
        const data = {
            meter_id: parseInt(raw.meter_id),
            current_reading: parseFloat(raw.current_reading),
            reading_date: raw.reading_date
        };

        try {
            const res = await fetch(apiBase + 'record_reading', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();

            if (json.success) {
                showAlert(`Reading recorded! Consumption: ${json.consumption.toFixed(2)} units`);
                recordReadingModal.hide();
                recordReadingForm.reset();
                loadMeters();
            } else {
                showAlert(json.message || 'Failed to record reading', 'danger');
            }
        } catch (err) {
            showAlert('Error recording reading', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /*  VIEW METER  */
    window.viewMeter = async function(meterId) {
        const res = await fetch(apiBase + 'get_meter&meter_id=' + meterId);
        const json = await res.json();
        if (!json.success) return showAlert('Could not load meter', 'danger');

        const m = json.meter;
        alert(`
            Meter ID: ${m.Meter_ID}
            Serial: ${m.Meter_Serial}
            Customer: ${m.customer_name}
            Utility: ${capitalize(m.utility_type)}
            Status: ${m.Status}
            Reading: ${m.Current_reading || 'N/A'}
        `);
    };

    /*  EDIT METER  */
    window.editMeter = async function(meterId) {
        const res = await fetch(apiBase + 'get_meter&meter_id=' + meterId);
        const json = await res.json();
        if (!json.success) return showAlert('Could not load meter', 'danger');

        const m = json.meter;
        document.getElementById('edit_meter_id').value = m.Meter_ID;
        document.getElementById('edit_meter_id_display').value = m.Meter_ID;
        document.getElementById('edit_customer_id').value = m.Customer_ID;
        document.getElementById('edit_utility_id').value = m.utility_type;
        document.getElementById('edit_serial_number').value = m.Meter_Serial;
        document.getElementById('edit_installation_date').value = m.Installation_date;
        document.getElementById('edit_status').value = m.Status;
        document.getElementById('edit_location').value = m.Location || '';
        editMeterModal.show();
    };

    editMeterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = editMeterForm.querySelector('button[type="submit"]');
        const original = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        const raw = Object.fromEntries(new FormData(editMeterForm).entries());
        const data = {
            meter_id: parseInt(raw.meter_id),
            customer_id: parseInt(raw.customer_id),
            utility_id: raw.utility_id,
            serial_number: raw.serial_number,
            installation_date: raw.installation_date,
            status: raw.status,
            location: raw.location || null
        };

        const res = await fetch(apiBase + 'update_meter', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();

        if (json.success) {
            showAlert('Meter updated successfully!');
            editMeterModal.hide();
            editMeterForm.reset();
            loadMeters();
        } else {
            showAlert(json.message || 'Update failed', 'danger');
        }

        btn.disabled = false;
        btn.innerHTML = original;
    });

   
    document.getElementById('addMeterModal').addEventListener('hidden.bs.modal', () => addMeterForm.reset());
    document.getElementById('recordReadingModal').addEventListener('hidden.bs.modal', () => recordReadingForm.reset());
    document.getElementById('editMeterModal').addEventListener('hidden.bs.modal', () => editMeterForm.reset());

    
    function escapeHtml(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
    function capitalize(s){ return (s||'').charAt(0).toUpperCase() + (s||'').slice(1); }
    function getUtilityBadge(t){ return t==='electricity'?'bg-primary':t==='water'?'bg-info':t==='gas'?'bg-warning':'bg-secondary'; }
    function getStatusBadge(s){
        s=(s||'').toLowerCase();
        return s==='active'?'bg-success':s==='faulty'?'bg-danger':'bg-secondary';
    }

})();
</script>

</body>
</html>