<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Create Client Account - DMAC Shipping</title>
    <link rel="stylesheet" href="../../../public/css/dashboard.css">
</head>
<body class="auth-bg">
    <div class="auth-container">
        <h2>Create Client Account</h2>
        <p>Only clients can register here. Employee/Admin accounts are created inside the admin dashboard.</p>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert-success alert-error">
                <?php
                    $error = $_GET['error'];
                    if ($error === 'missing') echo 'Please complete all required fields.';
                    elseif ($error === 'email') echo 'Please enter a valid email address.';
                    elseif ($error === 'weak') echo 'Password must be at least 8 characters.';
                    elseif ($error === 'taken') echo 'This email is already registered.';
                    elseif ($error === 'server') echo 'Registration failed. Please check your database connection or try again.';
                    else echo 'Registration failed. Please try again.';
                ?>
            </div>
        <?php endif; ?>
        
        <form action="process-register.php" method="POST">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="client_firstname" required placeholder="John">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="client_lastname" required placeholder="Doe">
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="client_contact" required placeholder="0917XXXXXXX">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="client_email" required placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="client_password" required minlength="8" placeholder="Minimum 8 characters">
            </div>
            <button type="submit" class="btn-primary">Register Account</button>
        </form>
        
        <p class="auth-switch">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
