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

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createCustomer($db);
        break;
    case 'update':
        updateCustomer($db);
        break;
    case 'delete':
        deleteCustomer($db);
        break;
    default:
        $customers = fetchCustomers($db);
        break;
}


function fetchCustomers($db) {
    $sql = "SELECT c.Customer_ID, u.Name, u.Email, u.Phone, c.Address, c.Connection_date, c.Status
            FROM CUSTOMER c
            INNER JOIN [USER] u ON c.User_ID = u.User_ID
            ORDER BY c.Customer_ID ASC";
    return $db->fetchAll($sql);
}

function generateCustomerID($db) {
    $sql = "SELECT MAX(Customer_ID) AS max_id FROM CUSTOMER";
    $result = $db->fetchOne($sql);
    return $result ? ($result['max_id'] + 1) : 1001;
}

function createCustomer($db) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $connection_date = $_POST['connection_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Active';

    if (empty($name) || empty($email) || empty($phone) || empty($address)) {
        echo json_encode(['success'=>false, 'message'=>'All fields are required']);
        exit;
    }

    $checkEmail = $db->fetchOne("SELECT User_ID FROM [USER] WHERE Email = ?", [$email]);
    if ($checkEmail) {
        echo json_encode(['success'=>false, 'message'=>'Email already exists']);
        exit;
    }

    try {
        $sqlUser = "INSERT INTO [USER] (Name, Email, Phone, Password, Role) VALUES (?, ?, ?, 'defaultpass', 'Customer')";
        $userID = $db->insert($sqlUser, [$name, $email, $phone]);

        if (!$userID) {
            echo json_encode(['success'=>false, 'message'=>'Failed to create user']);
            exit;
        }

        $customerID = generateCustomerID($db);
        
        $sqlCustomer = "INSERT INTO CUSTOMER (User_ID, Customer_ID, Address, Connection_date, Status) VALUES (?, ?, ?, ?, ?)";
        $result = $db->insert($sqlCustomer, [$userID, $customerID, $address, $connection_date, $status]);

        if ($result) {
            echo json_encode(['success'=>true, 'message'=>'Customer created successfully', 'customer_id'=>$customerID]);
        } else {
            echo json_encode(['success'=>false, 'message'=>'Failed to create customer']);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'message'=>'Error: ' . $e->getMessage()]);
    }
    exit;
}

function updateCustomer($db) {
    $customer_id = $_POST['customer_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $connection_date = $_POST['connection_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Active';

    if (!$customer_id) {
        $_SESSION['error'] = "Customer ID is required for update.";
        header('Location: customers.php');
        exit;
    }

    if (empty($name) || empty($email) || empty($phone) || empty($address)) {
        $_SESSION['error'] = "All fields are required.";
        header('Location: customers.php');
        exit;
    }

    try {
        $sqlCustomer = "UPDATE CUSTOMER SET Address = ?, Connection_date = ?, Status = ? WHERE Customer_ID = ?";
        $db->update($sqlCustomer, [$address, $connection_date, $status, $customer_id]);

        $sqlUser = "UPDATE [USER] SET Name = ?, Email = ?, Phone = ? 
                    WHERE User_ID = (SELECT User_ID FROM CUSTOMER WHERE Customer_ID = ?)";
        $db->update($sqlUser, [$name, $email, $phone, $customer_id]);

        echo json_encode(['success'=>true, 'message'=>'Customer updated successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'message'=>'Failed to update customer: ' . $e->getMessage()]);
    }
    exit;
}

function deleteCustomer($db) {
    $customer_id = $_GET['customer_id'] ?? null;
    
    if (!$customer_id) {
        $_SESSION['error'] = "Customer ID is required for deletion.";
        header('Location: customers.php');
        exit;
    }

    try {
        $meterCheck = $db->fetchOne("SELECT COUNT(*) as count FROM METER WHERE Customer_ID = ?", [$customer_id]);
        
        if ($meterCheck && $meterCheck['count'] > 0) {
            $_SESSION['error'] = "Cannot delete customer with existing meters. Please remove meters first.";
            header('Location: customers.php');
            exit;
        }

        $customerData = $db->fetchOne("SELECT User_ID FROM CUSTOMER WHERE Customer_ID = ?", [$customer_id]);
        
        if (!$customerData) {
            $_SESSION['error'] = "Customer not found.";
            header('Location: customers.php');
            exit;
        }

        $sql = "DELETE FROM CUSTOMER WHERE Customer_ID = ?";
        $result = $db->delete($sql, [$customer_id]);

        if ($result) {
            $db->delete("DELETE FROM [USER] WHERE User_ID = ?", [$customerData['User_ID']]);
            $_SESSION['success'] = "Customer deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete customer.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting customer: " . $e->getMessage();
    }
    
    header('Location: customers.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Management - Nexsync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/style.css">
<?php include 'includes/sidebar_styles.php'; ?>
</head>
<body class="bg-light">

<?php include 'includes/sidebar.php'; ?>

            
            <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-primary"><i class="bi bi-people me-2"></i>Customer Management</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-person-plus me-1"></i> Add Customer
                </button>
            </div>

            <!-- Alerts -->
            <?php
            if(!empty($_SESSION['success'])){ 
                echo '<div class="alert alert-success alert-dismissible fade show">
                        '.$_SESSION['success'].'
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>'; 
                unset($_SESSION['success']); 
            }
            if(!empty($_SESSION['error'])){ 
                echo '<div class="alert alert-danger alert-dismissible fade show">
                        '.$_SESSION['error'].'
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>'; 
                unset($_SESSION['error']); 
            }
            ?>

            <!-- Customer Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card utility-card border-left-primary shadow h-100">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Customers</div>
                            <div class="h5 mb-0 font-weight-bold"><?= count($customers) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card utility-card border-left-success shadow h-100">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= count(array_filter($customers, fn($c) => $c['Status'] === 'Active')) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card utility-card border-left-secondary shadow h-100">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Inactive</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= count(array_filter($customers, fn($c) => $c['Status'] === 'Inactive')) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card utility-card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">This Month</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= count(array_filter($customers, fn($c) => date('Y-m', strtotime($c['Connection_date'])) === date('Y-m'))) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, phone...">
                        </div>
                        <div class="col-md-3">
                            <select id="statusFilter" class="form-select">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                <i class="bi bi-x-circle me-1"></i> Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Connection Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($customers)): ?>
                                    <?php foreach($customers as $customer): ?>
                                    <tr data-id="<?= $customer['Customer_ID'] ?>"
                                        data-name="<?= htmlspecialchars($customer['Name']) ?>"
                                        data-email="<?= htmlspecialchars($customer['Email']) ?>"
                                        data-phone="<?= htmlspecialchars($customer['Phone']) ?>"
                                        data-address="<?= htmlspecialchars($customer['Address']) ?>"
                                        data-connection="<?= $customer['Connection_date'] ?>"
                                        data-status="<?= $customer['Status'] ?>">

                                        <td><strong><?= htmlspecialchars($customer['Customer_ID']) ?></strong></td>
                                        <td><?= htmlspecialchars($customer['Name']) ?></td>
                                        <td><?= htmlspecialchars($customer['Email']) ?></td>
                                        <td><?= htmlspecialchars($customer['Phone']) ?></td>
                                        <td><?= htmlspecialchars($customer['Address']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($customer['Connection_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $customer['Status'] == 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($customer['Status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewCustomer(<?= $customer['Customer_ID'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="editCustomer(<?= $customer['Customer_ID'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteCustomer(<?= $customer['Customer_ID'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center text-muted">No customers found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addCustomerForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="example@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="0771234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea name="address" class="form-control" rows="2" required placeholder="Enter full address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Connection Date</label>
                        <input type="date" name="connection_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Customer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Customer ID:</strong>
                        <p id="viewCustomerID"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong>
                        <p id="viewName"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong>
                        <p id="viewEmail"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Phone:</strong>
                        <p id="viewPhone"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3">
                        <strong>Address:</strong>
                        <p id="viewAddress"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Connection Date:</strong>
                        <p id="viewConnectionDate"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong>
                        <p id="viewStatusBadge"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editCustomerForm">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="editCustomerID">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" id="editPhone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea name="address" id="editAddress" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Connection Date</label>
                        <input type="date" name="connection_date" id="editConnectionDate" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const viewCustomerModal = new bootstrap.Modal(document.getElementById('viewCustomerModal'));
const editCustomerModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));

function showAlert(message, type = 'success') {
    const alertsArea = document.getElementById('alertsArea');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    alertsArea.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}

const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const tableRows = document.querySelectorAll('table tbody tr');

function filterTable() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusTerm = statusFilter.value.toLowerCase();

    tableRows.forEach(row => {
        if (!row.dataset.name) return;
        
        const name = row.dataset.name.toLowerCase();
        const email = row.dataset.email.toLowerCase();
        const phone = row.dataset.phone.toLowerCase();
        const status = row.dataset.status.toLowerCase();

        const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm);
        const matchesStatus = !statusTerm || status === statusTerm;

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

function clearFilters() {
    searchInput.value = '';
    statusFilter.value = '';
    filterTable();
}

searchInput.addEventListener('input', filterTable);
statusFilter.addEventListener('change', filterTable);

// View customer function
function viewCustomer(customerID) {
    const row = document.querySelector(`tr[data-id="${customerID}"]`);
    if (!row) return;

    document.getElementById('viewCustomerID').textContent = customerID;
    document.getElementById('viewName').textContent = row.dataset.name;
    document.getElementById('viewEmail').textContent = row.dataset.email;
    document.getElementById('viewPhone').textContent = row.dataset.phone;
    document.getElementById('viewAddress').textContent = row.dataset.address;
    document.getElementById('viewConnectionDate').textContent = row.dataset.connection;
    
    const statusBadge = row.dataset.status === 'Active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
    document.getElementById('viewStatusBadge').innerHTML = statusBadge;

    viewCustomerModal.show();
}

// Edit customer function
function editCustomer(customerID) {
    const row = document.querySelector(`tr[data-id="${customerID}"]`);
    if (!row) return;

    document.getElementById('editCustomerID').value = customerID;
    document.getElementById('editName').value = row.dataset.name;
    document.getElementById('editEmail').value = row.dataset.email;
    document.getElementById('editPhone').value = row.dataset.phone;
    document.getElementById('editAddress').value = row.dataset.address;
    document.getElementById('editConnectionDate').value = row.dataset.connection;
    document.getElementById('editStatus').value = row.dataset.status;

    editCustomerModal.show();
}

// Delete customer function
function deleteCustomer(customerID) {
    if (confirm('Are you sure you want to delete this customer?')) {
        window.location.href = 'customers.php?action=delete&customer_id=' + customerID;
    }
}

// Form submission for adding customers
const addForm = document.getElementById('addCustomerForm');
if (addForm) {
    addForm.addEventListener('submit', function(e){
        e.preventDefault();
        
        const submitBtn = addForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';
        
        const formData = new FormData(addForm);

        fetch('customers.php?action=create', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if(data.success){
                bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
                addForm.reset();
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error(err);
            showAlert('An error occurred. Please try again.', 'danger');
        });
    });
}

// Form submission for editing customers
const editForm = document.getElementById('editCustomerForm');
if (editForm) {
    editForm.addEventListener('submit', function(e){
        e.preventDefault();
        
        const submitBtn = editForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        const formData = new FormData(editForm);

        fetch('customers.php?action=update', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if(data.success){
                editCustomerModal.hide();
                editForm.reset();
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error(err);
            showAlert('An error occurred. Please try again.', 'danger');
        });
    });
}
</script>
</body>
</html>