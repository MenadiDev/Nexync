async function loadDashboardStats() {
    try {
        const response = await fetch('api/dashboard.php');
        const stats = await response.json();
        
        
        document.querySelectorAll('.card .h5')[0].textContent = stats.total_customers?.toLocaleString() || '0';
        document.querySelectorAll('.card .h5')[1].textContent = '$' + (stats.monthly_revenue || 0).toLocaleString();
        document.querySelectorAll('.card .h5')[2].textContent = stats.pending_bills || '0';
        document.querySelectorAll('.card .h5')[3].textContent = stats.overdue_bills || '0';
        
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}


async function loadRecentBills() {
    try {
        const response = await fetch('api/bills.php?limit=5');
        const bills = await response.json();
        
        const tbody = document.querySelector('#recentBillsTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        bills.forEach(bill => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>BL-${bill.Bill_ID}</td>
                <td>${bill.Customer_Name}</td>
                <td>$${bill.Total_amount}</td>
                <td>${bill.Due_date}</td>
                <td><span class="badge bg-${getStatusColor(bill.Status)}">${bill.Status}</span></td>
                <td><button class="btn btn-sm btn-outline-primary">View</button></td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading recent bills:', error);
    }
}

function getStatusColor(status) {
    const colors = {
        'Paid': 'success',
        'Pending': 'warning', 
        'Overdue': 'danger'
    };
    return colors[status] || 'secondary';
}


document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadRecentBills();
    
    document.querySelector('button[onclick*="Add Customer"]')?.addEventListener('click', function() {
        window.location.href = 'customers.html';
    });
    
    document.querySelector('button[onclick*="Record Reading"]')?.addEventListener('click', function() {
        window.location.href = 'meters.html';
    });
    
    document.querySelector('button[onclick*="Generate Bills"]')?.addEventListener('click', function() {
        window.location.href = 'billing.html';
    });
    
    document.querySelector('button[onclick*="View Reports"]')?.addEventListener('click', function() {
        window.location.href = 'reports.html';
    });
});