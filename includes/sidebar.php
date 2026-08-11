
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
            <a href="dashboard.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2 fs-5"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="customers.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                <i class="bi bi-people me-2 fs-5"></i>
                <span>Customers</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="meters.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'meters.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer me-2 fs-5"></i>
                <span>Meters</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="billing.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : ''; ?>">
                <i class="bi bi-receipt me-2 fs-5"></i>
                <span>Billing</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="payments.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card me-2 fs-5"></i>
                <span>Payments</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link d-flex align-items-center px-3 py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
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