<?php 
session_start(); 
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexSync Login</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, #0d0d0f, #000000 80%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            color: white;
        }

       
        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: float 8s infinite ease-in-out;
        }

        .circle1 {
            width: 300px;
            height: 300px;
            background: #0047ff;
            top: -50px;
            left: -50px;
        }

        .circle2 {
            width: 350px;
            height: 350px;
            background: #00d1ff;
            bottom: -80px;
            right: -40px;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-40px); }
            100% { transform: translateY(0px); }
        }

        .login-box {
            width: 380px;
            padding: 40px;
            background: rgba(255,255,255,0.08);
            border-radius: 18px;
            box-shadow: 0 0 25px rgba(0, 174, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            animation: fadeIn 0.8s ease;
            z-index: 5;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .title {
            text-align: center;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #84baff;
            letter-spacing: 1px;
        }

        .error-message {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .input-field {
            width: 100%;
            margin-bottom: 18px;
        }

        .input-field input {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: #d1e4ff;
            outline: none;
            font-size: 15px;
            transition: all 0.2s;
        }

        .input-field input:focus {
            background: rgba(255,255,255,0.18);
            box-shadow: 0 0 10px rgba(0, 149, 255, 0.5);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(90deg, #0066ff, #00c8ff);
            color: white;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            box-shadow: 0 0 12px rgba(0, 153, 255, 0.4);
        }

        .btn:hover {
            transform: scale(1.03);
            box-shadow: 0 0 18px rgba(0, 174, 255, 0.7);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .footer {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            opacity: 0.7;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>

    <div class="circle circle1"></div>
    <div class="circle circle2"></div>

    <form class="login-box" action="process_login.php" method="POST" id="loginForm">
        <div class="title">NexSync Login</div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="input-field">
            <input type="email" name="email" placeholder="Email" required autofocus>
        </div>

        <div class="input-field">
            <input type="password" name="password" placeholder="Password" required>
        </div>

        <button type="submit" class="btn" id="loginBtn">Login</button>

        <div class="footer">© 2025 NexSync – Smart Utility Management</div>
    </form>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Logging in...';
        });

        <?php if ($error): ?>
            setTimeout(function() {
                const errorDiv = document.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.style.transition = 'opacity 0.5s';
                    errorDiv.style.opacity = '0';
                    setTimeout(() => errorDiv.remove(), 500);
                }
            }, 5000);
        <?php endif; ?>
    </script>

</body>
</html>