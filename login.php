<?php
require_once 'config/database.php';

if (isLoggedIn() && refreshSessionUser()) {
    redirect("dashboard.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && (int)$row['active'] !== 1) {
            $error = 'This account is inactive. Please contact an administrator.';
        } elseif ($row && verifyPassword($password, $row['password'])) {
            if (needsPasswordRehash($row['password'])) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $newHash, $row['id']);
                $update->execute();
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['last_activity'] = time();
            redirect("dashboard.php");
        } else {
            $error = 'Invalid username, email, or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
<div class="auth-shell">
    <a class="brand-link" href="index.php">TaskFlow</a>
<div class="login-container">
    <h1 class="auth-title">TaskFlow</h1>
    <p class="auth-subtitle">Project and task management</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username" value="<?php echo e($_POST['username'] ?? ''); ?>" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Login</button>
    </form>

    <div class="auth-links">
        <a href="register.php">Create an account</a>
    </div>
</div>
</div>
</body>
</html>
