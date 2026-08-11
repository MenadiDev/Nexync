<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
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
                            <p class="text-muted">This page is restricted to Administrators only.</p>
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
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => $_SESSION['role']
];

$period = $_GET['period'] ?? 'this_month';
$startDate = '';
$endDate = '';

switch($period) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'this_week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d');
        break;
    case 'this_month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'last_month':
        $startDate = date('Y-m-01', strtotime('first day of last month'));
        $endDate = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        break;
}

function getSingleValue($row, $key, $default = 0) {
    return $row ? ($row[$key] ?? $default) : $default;
}

$row = $db->fetchOne("SELECT ISNULL(SUM(Total_amount), 0) as total FROM BILL WHERE Bill_date BETWEEN ? AND ?", [$startDate, $endDate]);
$totalRevenue = getSingleValue($row, 'total');

$periodLength = (strtotime($endDate) - strtotime($startDate)) / 86400;
$prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
$prevStartDate = date('Y-m-d', strtotime($prevEndDate . ' -' . floor($periodLength) . ' days'));

$row = $db->fetchOne("SELECT ISNULL(SUM(Total_amount), 0) as total FROM BILL WHERE Bill_date BETWEEN ? AND ?", [$prevStartDate, $prevEndDate]);
$prevRevenue = getSingleValue($row, 'total');
$revenueGrowth = $prevRevenue > 0 ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;

$row = $db->fetchOne("SELECT ISNULL(SUM(b.Total_amount), 0) as billed FROM BILL b WHERE b.Bill_date BETWEEN ? AND ?", [$startDate, $endDate]);
$totalBilled = getSingleValue($row, 'billed');

$row = $db->fetchOne("SELECT ISNULL(SUM(p.Amount_paid), 0) as collected FROM PAYMENT p LEFT JOIN BILL b ON p.Bill_ID = b.Bill_ID WHERE b.Bill_date BETWEEN ? AND ?", [$startDate, $endDate]);
$totalCollected = getSingleValue($row, 'collected');
$collectionRate = $totalBilled > 0 ? ($totalCollected / $totalBilled) * 100 : 0;

$row = $db->fetchOne("SELECT AVG(CAST(Units_consumed AS FLOAT)) as avg FROM METER_READING WHERE Reading_date BETWEEN ? AND ?", [$startDate, $endDate]);
$avgConsumption = getSingleValue($row, 'avg');

$row = $db->fetchOne("SELECT AVG(CAST(Units_consumed AS FLOAT)) as avg FROM METER_READING WHERE Reading_date BETWEEN ? AND ?", [$prevStartDate, $prevEndDate]);
$prevAvgConsumption = getSingleValue($row, 'avg');
$consumptionChange = $prevAvgConsumption > 0 ? (($avgConsumption - $prevAvgConsumption) / $prevAvgConsumption) * 100 : 0;

$row = $db->fetchOne("SELECT ISNULL(SUM(Total_amount), 0) as total FROM BILL WHERE Status IN ('Pending', 'Overdue')");
$outstanding = getSingleValue($row, 'total');

$row = $db->fetchOne("SELECT AVG(CAST(DATEDIFF(DAY, b.Bill_date, ISNULL((SELECT MIN(Payment_date) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), GETDATE())) AS FLOAT)) as avg_days FROM BILL b WHERE b.Status = 'Paid' AND b.Bill_date >= DATEADD(MONTH, -3, GETDATE())");
$dso = getSingleValue($row, 'avg_days');

$row = $db->fetchOne("SELECT COUNT(DISTINCT Customer_ID) as count FROM BILL WHERE Bill_date BETWEEN ? AND ?", [$startDate, $endDate]);
$activeCustomers = getSingleValue($row, 'count');
$revenuePerCustomer = $activeCustomers > 0 ? $totalRevenue / $activeCustomers : 0;

$row = $db->fetchOne("SELECT COUNT(*) as count FROM METER WHERE Status = 'Faulty'");
$faultyMeters = getSingleValue($row, 'count');

$row = $db->fetchOne("SELECT COUNT(*) as count FROM METER");
$totalMeters = getSingleValue($row, 'count');
$meterHealthRate = $totalMeters > 0 ? (($totalMeters - $faultyMeters) / $totalMeters) * 100 : 0;

$row = $db->fetchOne("SELECT AVG(CAST(DATEDIFF(DAY, mr.Reading_date, b.Bill_date) AS FLOAT)) as avg_days FROM BILL b LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID WHERE b.Bill_date >= DATEADD(MONTH, -3, GETDATE()) AND mr.Reading_date IS NOT NULL");
$avgBillingDelay = getSingleValue($row, 'avg_days');

$revenueTrend = $db->fetchAll("SELECT FORMAT(Bill_date, 'yyyy-MM') as month, SUM(Total_amount) as revenue FROM BILL WHERE Bill_date >= DATEADD(MONTH, -11, GETDATE()) GROUP BY FORMAT(Bill_date, 'yyyy-MM') ORDER BY month");

$utilityRevenue = $db->fetchAll("SELECT ISNULL(ut.Utility_Type, 'Unknown') as Utility_Type, ISNULL(SUM(b.Total_amount), 0) as revenue FROM BILL b LEFT JOIN METER_READING mr ON b.Reading_ID = mr.Reading_ID LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID WHERE b.Bill_date BETWEEN ? AND ? GROUP BY ut.Utility_Type", [$startDate, $endDate]);

$consumptionByUtility = $db->fetchAll("SELECT ISNULL(ut.Utility_Type, 'Unknown') as Utility_Type, AVG(CAST(mr.Units_consumed AS FLOAT)) as avg_consumption, MAX(CAST(mr.Units_consumed AS FLOAT)) as max_consumption, MIN(CAST(mr.Units_consumed AS FLOAT)) as min_consumption FROM METER_READING mr LEFT JOIN METER m ON mr.Meter_ID = m.Meter_ID LEFT JOIN UTILITY ut ON m.Utility_ID = ut.Utility_ID WHERE mr.Reading_date BETWEEN ? AND ? GROUP BY ut.Utility_Type", [$startDate, $endDate]);

$paymentMethods = $db->fetchAll("SELECT Payment_Method, COUNT(*) as count, SUM(Amount_paid) as total FROM PAYMENT WHERE Payment_date BETWEEN ? AND ? GROUP BY Payment_Method", [$startDate, $endDate]);

$topCustomers = $db->fetchAll("SELECT TOP 10 c.Customer_ID, ISNULL(u.Name, 'Unknown') as Name, SUM(p.Amount_paid) as total_paid, COUNT(DISTINCT p.Payment_ID) as payment_count FROM PAYMENT p LEFT JOIN BILL b ON p.Bill_ID = b.Bill_ID LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID LEFT JOIN [USER] u ON c.User_ID = u.User_ID WHERE p.Payment_date BETWEEN ? AND ? GROUP BY c.Customer_ID, u.Name HAVING SUM(p.Amount_paid) > 0 ORDER BY total_paid DESC", [$startDate, $endDate]);

$topOutstanding = $db->fetchAll("SELECT TOP 10 b.Bill_ID, b.Total_amount, b.Due_date, c.Customer_ID, ISNULL(u.Name, 'Unknown') as Customer_Name, DATEDIFF(DAY, b.Due_date, GETDATE()) as Days_Overdue, ISNULL((SELECT SUM(Amount_paid) FROM PAYMENT WHERE Bill_ID = b.Bill_ID), 0) as Amount_Paid FROM BILL b LEFT JOIN CUSTOMER c ON b.Customer_ID = c.Customer_ID LEFT JOIN [USER] u ON c.User_ID = u.User_ID WHERE b.Status IN ('Pending', 'Overdue') AND b.Due_date < GETDATE() ORDER BY b.Total_amount DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Nexsync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-change { font-size: 0.85em; margin-top: 5px; }
        .metric-change.positive { color: #1cc88a; }
        .metric-change.negative { color: #e74a3b; }
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .col-md-9 { flex: 0 0 100%; max-width: 100%; }
        }
    </style>
    <?php include 'includes/sidebar_styles.php'; ?>
</head>
<body class="bg-light">
<?php include 'includes/sidebar.php'; ?>

            <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-primary">
                    <i class="bi bi-graph-up me-2"></i>Reports & Analytics
                </h4>
                <div class="no-print">
                    <button class="btn btn-outline-primary me-2" onclick="exportToCSV()">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print/PDF
                    </button>
                </div>
            </div>

            <div class="card shadow mb-4 no-print">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Time Period</label>
                            <select name="period" class="form-select" id="periodSelect" onchange="toggleCustomDates()">
                                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="this_week" <?php echo $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="startDateDiv" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-3" id="endDateDiv" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-1"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totalRevenue, 2); ?></div>
                            <div class="metric-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-<?php echo $revenueGrowth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo number_format(abs($revenueGrowth), 1); ?>% vs previous
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Collection Rate</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($collectionRate, 1); ?>%</div>
                            <small class="text-muted">$<?php echo number_format($totalCollected, 0); ?> / $<?php echo number_format($totalBilled, 0); ?></small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Consumption</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgConsumption, 1); ?> units</div>
                            <div class="metric-change <?php echo $consumptionChange >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-<?php echo $consumptionChange >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo number_format(abs($consumptionChange), 1); ?>% vs previous
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Revenue/Customer</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($revenuePerCustomer, 2); ?></div>
                            <small class="text-muted"><?php echo $activeCustomers; ?> active customers</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Outstanding</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($outstanding, 2); ?></div>
                            <small class="text-muted">Pending & Overdue</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Days Sales Outstanding</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($dso, 0); ?> days</div>
                            <small class="text-muted">Avg collection time</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Meter Health</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($meterHealthRate, 1); ?>%</div>
                            <small class="text-muted"><?php echo $faultyMeters; ?> faulty of <?php echo $totalMeters; ?></small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card utility-card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Billing Efficiency</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgBillingDelay, 1); ?> days</div>
                            <small class="text-muted">Reading to bill time</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-8 col-lg-7 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-currency-dollar me-1"></i>Revenue Trend (Last 12 Months)
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-pie-chart me-1"></i>Revenue by Utility
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="utilityPieChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-lightning-charge me-1"></i>Consumption Analysis
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="consumptionChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-credit-card me-1"></i>Payment Methods
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="paymentMethodChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-trophy me-1"></i>Top 10 Customers
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th>Payments</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($topCustomers) > 0): $rank = 1; foreach($topCustomers as $customer): ?>
                                        <tr>
                                            <td><strong><?php echo $rank++; ?></strong></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                                <small class="text-muted">ID: <?php echo $customer['Customer_ID']; ?></small>
                                            </td>
                                            <td><?php echo $customer['payment_count']; ?></td>
                                            <td class="fw-bold text-success">$<?php echo number_format($customer['total_paid'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No data available</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>Top Outstanding Bills
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bill ID</th>
                                            <th>Customer</th>
                                            <th>Balance</th>
                                            <th>Overdue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($topOutstanding) > 0): foreach($topOutstanding as $bill): ?>
                                        <tr>
                                            <td><strong>BL-<?php echo str_pad($bill['Bill_ID'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($bill['Customer_Name']); ?></div>
                                                <small class="text-muted">ID: <?php echo $bill['Customer_ID']; ?></small>
                                            </td>
                                            <td class="fw-bold text-danger">$<?php echo number_format($bill['Total_amount'] - $bill['Amount_Paid'], 2); ?></td>
                                            <td><span class="badge bg-danger"><?php echo $bill['Days_Overdue']; ?> days</span></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No overdue bills</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-muted">Report Period</h6>
                            <p class="fw-bold"><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Generated On</h6>
                            <p class="fw-bold"><?php echo date('M d, Y H:i A'); ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Total Revenue</h6>
                            <p class="fw-bold">$<?php echo number_format($totalRevenue, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleCustomDates() {
    const period = document.getElementById('periodSelect').value;
    const display = period === 'custom' ? 'block' : 'none';
    document.getElementById('startDateDiv').style.display = display;
    document.getElementById('endDateDiv').style.display = display;
}

function exportToCSV() {
    let csv = 'Metric,Value\n';
    csv += 'Period,<?php echo $period; ?>\n';
    csv += 'Start Date,<?php echo $startDate; ?>\n';
    csv += 'End Date,<?php echo $endDate; ?>\n';
    csv += 'Total Revenue,$<?php echo number_format($totalRevenue, 2); ?>\n';
    csv += 'Collection Rate,<?php echo number_format($collectionRate, 2); ?>%\n';
    csv += 'Outstanding,$<?php echo number_format($outstanding, 2); ?>\n';
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'nexsync_report_<?php echo $startDate; ?>_to_<?php echo $endDate; ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

const revenueTrendData = <?php echo json_encode($revenueTrend); ?>;
const revenueCtx = document.getElementById('revenueChart');

if (revenueCtx && revenueTrendData.length > 0) {
    new Chart(revenueCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: revenueTrendData.map(d => d.month),
            datasets: [{
                label: 'Revenue ($)',
                data: revenueTrendData.map(d => parseFloat(d.revenue)),
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return '$' + value.toLocaleString(); 
                        }
                    }
                }
            }
        }
    });
}
const utilityData = <?php echo json_encode($utilityRevenue); ?>;
const utilityCtx = document.getElementById('utilityPieChart');
if (utilityCtx && utilityData.length > 0) {
    new Chart(utilityCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: utilityData.map(d => d.Utility_Type),
            datasets: [{
                data: utilityData.map(d => parseFloat(d.revenue)),
                backgroundColor: ['#4e73df', '#36b9cc', '#f6c23e', '#e74a3b', '#1cc88a']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

const consumptionData = <?php echo json_encode($consumptionByUtility); ?>;
const consumptionCtx = document.getElementById('consumptionChart');
if (consumptionCtx && consumptionData.length > 0) {
    new Chart(consumptionCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: consumptionData.map(d => d.Utility_Type),
            datasets: [
                {
                    label: 'Average',
                    data: consumptionData.map(d => parseFloat(d.avg_consumption)),
                    backgroundColor: '#4e73df'
                },
                {
                    label: 'Maximum',
                    data: consumptionData.map(d => parseFloat(d.max_consumption)),
                    backgroundColor: '#e74a3b'
                },
                {
                    label: 'Minimum',
                    data: consumptionData.map(d => parseFloat(d.min_consumption)),
                    backgroundColor: '#1cc88a'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

const paymentData = <?php echo json_encode($paymentMethods); ?>;
const paymentCtx = document.getElementById('paymentMethodChart');
if (paymentCtx && paymentData.length > 0) {
    new Chart(paymentCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: paymentData.map(d => d.Payment_Method),
            datasets: [
                {
                    label: 'Count',
                    data: paymentData.map(d => parseInt(d.count)),
                    backgroundColor: '#1cc88a',
                    yAxisID: 'y'
                },
                {
                    label: 'Amount ($)',
                    data: paymentData.map(d => parseFloat(d.total)),
                    backgroundColor: '#4e73df',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}
</script>
</body>
</html>