<?php
require_once 'config/database.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$error = '';

if (!$user) {
    session_destroy();
    redirect("login.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid name and email.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $check->bind_param("si", $email, $user_id);
        $check->execute();

        if ($check->get_result()->fetch_assoc()) {
            $error = 'That email address is already used by another account.';
        } else {
            $update = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $update->bind_param("ssi", $fullname, $email, $user_id);
            $update->execute();
            $_SESSION['fullname'] = $fullname;
            setFlash('success', 'Profile updated.');
            redirect("profile.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('My Profile', 'Keep your name and contact details current.'); ?>
    <?php echo appNav('profile'); ?>

    <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <div class="form-container">
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-group"><label>Username</label><input type="text" value="<?php echo e($user['username']); ?>" disabled></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="<?php echo e($_POST['fullname'] ?? $user['fullname']); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo e($_POST['email'] ?? $user['email']); ?>" required></div>
            <div class="form-group"><label>Role</label><input type="text" value="<?php echo e($user['role']); ?>" disabled></div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
</div>
</body>
</html>
