<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Login - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
</head>
<body class="auth-bg">
    <div class="auth-container">
        <h2>DMAC Shipping Login</h2>
        <p>One login for clients, admin, and employees.</p>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert-success">Client registration successful. Please login.</div>
        <?php endif; ?>

        <?php if(isset($_GET['created'])): ?>
            <div class="alert-success">Employee account created successfully.</div>
        <?php endif; ?>

        <?php if(isset($_GET['pending'])): ?>
            <div class="alert-success alert-warning">Your employee account is still pending approval.</div>
        <?php endif; ?>

        <?php if(isset($_GET['rejected'])): ?>
            <div class="alert-success alert-error">Your employee account was rejected. Please contact the main admin.</div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert-success alert-error">
                <?php
                    $error = $_GET['error'];
                    if ($error === 'missing') echo 'Email and password are required.';
                    elseif ($error === 'email') echo 'Please enter a valid email address.';
                    elseif ($error === 'server') echo 'Server error. Please try again.';
                    elseif ($error === 'locked') echo 'Too many failed login attempts. Please try again after 15 minutes.';
                    else echo 'Invalid email or password.';
                ?>
            </div>
        <?php endif; ?>

        <form action="process-login.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="client or employee email">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <p class="auth-switch">New client? <a href="register.php">Register here</a></p>
        <p class="auth-note">Employee/Admin accounts are created inside the admin dashboard only.</p>
    </div>
</body>
</html>
