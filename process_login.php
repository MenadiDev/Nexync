<?php
session_start();
require_once 'PHP/config.php';
$db = new Database();

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header('Location: login.php?error=Please enter email and password');
    exit;
}

$sql = "SELECT User_ID, Name, Email, Role FROM [USER] WHERE Email = ? AND Password = ?";
$user = $db->fetchOne($sql, [$email, $password]);

if ($user) {
    $_SESSION['user_id'] = $user['User_ID'];
    $_SESSION['name'] = $user['Name'];
    $_SESSION['email'] = $user['Email'];
    $_SESSION['role'] = $user['Role'];
    

    if ($user['Role'] === 'Customer') {
        
        $customerSql = "SELECT Customer_ID FROM CUSTOMER WHERE User_ID = ?";
        $customer = $db->fetchOne($customerSql, [$user['User_ID']]);
        
        if ($customer) {
            $_SESSION['customer_id'] = $customer['Customer_ID'];
            header('Location: customer_dashboard.php');
        } else {
            header('Location: login.php?error=Customer account not found');
        }
    } else {
        
        header('Location: dashboard.php');
    }
} else {
    header('Location: login.php?error=Invalid credentials');
}
exit;
?>