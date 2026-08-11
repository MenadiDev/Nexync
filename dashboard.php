<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'PHP/config.php';
$db = new Database();

$currentUser = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['name'] ?? 'User',
    'role' => $_SESSION['role'] ?? 'User'
];


function getSingleValue($row, $key) {
    return $row ? (int)$row[$key] : 0;
}

// Total Customers
$row = $db->fetchOne("SELECT COUNT(*) as count FROM CUSTOMER");
$totalCustomers = getSingleValue($row, 'count');

// Monthly Revenue
$row = $db->fetchOne("
    SELECT ISNULL(SUM(Amount_paid), 0) as total 
    FROM PAYMENT 
    WHERE MONTH(Payment_date) = MONTH(GETDATE()) 
      AND YEAR(Payment_date) = YEAR(GETDATE())
");
$monthlyRevenue = $row ? (float)$row['total'] : 0;

// Pending Bills
$row = $db->fetchOne("SELECT COUNT(*) as count FROM BILL WHERE Status='Pending'");
$pendingBills = getSingleValue($row, 'count');

// Overdue Bills
$row = $db->fetchOne("SELECT COUNT(*) as count FROM BILL WHERE Due_date < GETDATE() AND Status IN ('Pending', 'Overdue')");
$overdueBills = getSingleValue($row, 'count');

// Today's collections
$row = $db->fetchOne("
    SELECT ISNULL(SUM(Amount_paid), 0) as total 
    FROM PAYMENT 
    WHERE CAST(Payment_date AS DATE) = CAST(GETDATE() AS DATE)
");
$todayCollections = $row ? (float)$row['total'] : 0;

// New customers this month
$row = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM CUSTOMER 
    WHERE MONTH(Connection_date) = MONTH(GETDATE()) 
      AND YEAR(Connection_date) = YEAR(GETDATE())
");
$newCustomersThisMonth = getSingleValue($row, 'count');

// Collection rate
$row = $db->fetchOne("SELECT ISNULL(SUM(Total_amount), 0) as total FROM BILL WHERE Status='Paid'");
$totalBilled = $row ? (float)$row['total'] : 0;

$row = $db->fetchOne("SELECT ISNULL(SUM(Amount_paid), 0) as total FROM PAYMENT");
$totalCollected = $row ? (float)$row['total'] : 0;

$collectionRate = $totalBilled > 0 ? ($totalCollected / $totalBilled) * 100 : 0;

// Active meters count
$row = $db->fetchOne("SELECT COUNT(*) as count FROM METER WHERE Status='Active'");
$activeMeters = getSingleValue($row, 'count');

// Recent bills
$recentBills = $db->fetchAll("
    SELECT TOP 5 
        b.Bill_ID,
        b.Bill_date,
        b.Due_date,
        b.Total_amount,
        b.Status,
        b.Billing_Period,
        u.Name as Customer_Name,
        ut.Utility_Type
    FROM BILL b 
    LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID
    LEFT JOIN [USER] u ON c.User_ID = u.User_ID
    LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID
    LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID
    LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID
    ORDER BY b.Bill_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Nexsync Utility System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        #sidebar {
            width: 220px;
            background-color: #0b0f4a;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1030;
            overflow-y: auto;
        }
        
        #sidebar .nav-link {
            color: #cfd8ff;
            border-radius: 8px;
            margin: 0 8px;
            transition: all 0.2s;
        }
        
        #sidebar .nav-link.active,
        #sidebar .nav-link:hover {
            background-color: #1a25a0;
            color: #ffffff !important;
            font-weight: 500;
        }
        
        #sidebar .nav-link i {
            min-width: 24px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 220px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .utility-card {
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .utility-card:hover {
            transform: translateY(-5px);
        }
        
        .border-left-primary {
            border-left: 4px solid #4e73df;
        }
        
        .border-left-success {
            border-left: 4px solid #1cc88a;
        }
        
        .border-left-info {
            border-left: 4px solid #36b9cc;
        }
        
        .border-left-warning {
            border-left: 4px solid #f6c23e;
        }
        
        .border-left-danger {
            border-left: 4px solid #e74a3b;
        }
        
        .border-left-secondary {
            border-left: 4px solid #858796;
        }
        
        .user-avatar {
           color: #ffffff;
           opacity: 0.9;
        }

        .user-avatar i {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -220px;
            }
            
            #sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div id="sidebar" class="d-flex flex-column text-white">
        <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5 text-light">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Nexsync
                </span>
            </div>
        </div>

<div class="p-3 border-bottom">
    <div class="text-center">
        <div class="user-avatar mb-2">
            <i class="bi bi-person-circle fs-1"></i>
        </div>
        <div class="fw-bold text-white mb-1">
            <?php echo htmlspecialchars($currentUser['name']); ?>
        </div>
        <span class="badge bg-primary bg-opacity-25 text-white px-3 py-2">
            <i class="bi bi-shield-check me-1"></i>
            <?php echo htmlspecialchars($currentUser['role']); ?>
        </span>
    </div>
</div>

        <ul class="nav flex-column mt-3 flex-grow-1">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link d-flex align-items-center px-3 py-2 active">
                    <i class="bi bi-speedometer2 me-2 fs-5"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="customers.php" class="nav-link d-flex align-items-center px-3 py-2">
                    <i class="bi bi-people me-2 fs-5"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="meters.php" class="nav-link d-flex align-items-center px-3 py-2">
                    <i class="bi bi-speedometer me-2 fs-5"></i>
                    <span>Meters</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="billing.php" class="nav-link d-flex align-items-center px-3 py-2">
                    <i class="bi bi-receipt me-2 fs-5"></i>
                    <span>Billing</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link d-flex align-items-center px-3 py-2">
                    <i class="bi bi-credit-card me-2 fs-5"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link d-flex align-items-center px-3 py-2">
                    <i class="bi bi-graph-up me-2 fs-5"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <div class="mt-auto p-3 border-top">
            <a href="logout.php" class="btn btn-outline-light w-100 d-flex align-items-center justify-content-center">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-primary mb-0">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard Overview
            </h4>
            <div class="text-muted">
                <i class="bi bi-calendar-event me-1"></i> <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Total Customers -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Customers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalCustomers; ?></div>
                                <div class="text-success small">
                                    <i class="bi bi-arrow-up"></i> +<?php echo $newCustomersThisMonth; ?> this month
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Monthly Revenue</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($monthlyRevenue, 2); ?></div>
                                <div class="text-success small">
                                    <i class="bi bi-cash-coin"></i> $<?php echo number_format($todayCollections, 2); ?> today
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Collection Rate -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Collection Rate</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($collectionRate, 1); ?>%</div>
                                <div class="text-success small">
                                    <i class="bi bi-graph-up"></i> Efficient
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-percent fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Bills -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Pending Bills</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pendingBills; ?></div>
                                <div class="text-warning small">
                                    <i class="bi bi-clock"></i> Awaiting payment
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overdue Payments -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Overdue Bills</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overdueBills; ?></div>
                                <div class="text-danger small">
                                    <i class="bi bi-exclamation-triangle"></i> Needs attention
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Meters -->
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card utility-card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    Active Meters</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeMeters; ?></div>
                                <div class="text-info small">
                                    <i class="bi bi-speedometer2"></i> Installed
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-speedometer2 fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <a href="customers.php" class="btn btn-outline-primary btn-lg p-3 w-100 text-decoration-none">
                                    <i class="bi bi-person-plus d-block mb-2" style="font-size: 2rem;"></i>
                                    Add Customer
                                </a>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <a href="meters.php" class="btn btn-outline-success btn-lg p-3 w-100 text-decoration-none">
                                    <i class="bi bi-speedometer d-block mb-2" style="font-size: 2rem;"></i>
                                    Record Reading
                                </a>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <a href="billing.php" class="btn btn-outline-info btn-lg p-3 w-100 text-decoration-none">
                                    <i class="bi bi-receipt d-block mb-2" style="font-size: 2rem;"></i>
                                    Generate Bills
                                </a>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <a href="reports.php" class="btn btn-outline-warning btn-lg p-3 w-100 text-decoration-none">
                                    <i class="bi bi-graph-up d-block mb-2" style="font-size: 2rem;"></i>
                                    View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Bills</h6>
                        <a href="billing.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Customer</th>
                                        <th>Utility Type</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentBills)): ?>
                                        <?php foreach($recentBills as $bill): ?>
                                        <tr>
                                            <td><strong>BL-<?php echo str_pad($bill['Bill_ID'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bill['Customer_Name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php
                                                    $utilityType = $bill['Utility_Type'] ?? 'N/A';
                                                    $badgeClass = 'secondary';
                                                    if($utilityType == 'Electricity') $badgeClass = 'primary';
                                                    elseif($utilityType == 'Water') $badgeClass = 'info';
                                                    elseif($utilityType == 'Gas') $badgeClass = 'warning';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($utilityType); ?>
                                                </span>
                                            </td>
                                            <td><strong>$<?php echo number_format($bill['Total_amount'], 2); ?></strong></td>
                                            <td><?php echo date('Y-m-d', strtotime($bill['Due_date'])); ?></td>
                                            <td>
                                                <?php
                                                    $status = $bill['Status'] ?? 'N/A';
                                                    $statusClass = 'secondary';
                                                    if($status == 'Paid') $statusClass = 'success';
                                                    elseif($status == 'Pending') $statusClass = 'warning';
                                                    elseif($status == 'Overdue') $statusClass = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="billing.php" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No recent bills found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>