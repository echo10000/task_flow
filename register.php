<?php
require_once 'config/database.php';

if (isLoggedIn() && refreshSessionUser()) {
    redirect("dashboard.php");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $fullname === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
        $error = 'Username must be 3-40 characters and may contain letters, numbers, dots, dashes, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param("ss", $username, $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $stmt = $conn->prepare("INSERT INTO users (username, email, fullname, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $fullname, $hashed, $role);
            $stmt->execute();

            $success = 'Registration successful. You can now log in.';
            $_POST = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
<div class="auth-shell">
    <a class="brand-link" href="index.php">TaskFlow</a>
<div class="login-container auth-register">
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-subtitle">Join TaskFlow to manage your projects</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?> <a href="login.php">Login here</a></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" required value="<?php echo e($_POST['username'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="fullname">Full Name *</label>
            <input type="text" id="fullname" name="fullname" required value="<?php echo e($_POST['fullname'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="password">Password * (minimum 8 characters)</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password *</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Register</button>
    </form>

    <div class="auth-links">
        <a href="login.php">Already have an account? Login</a>
    </div>
</div>
</div>
</body>
</html>
