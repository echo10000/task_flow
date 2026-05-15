<?php
require_once 'config/database.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    setFlash('error', 'User not found.');
    redirect("users.php");
}

$error = '';
$allowedRoles = ['admin', 'user'];
$activeChecked = (int)$user['active'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = isValidOption($_POST['role'] ?? 'user', $allowedRoles, 'user');
    $active = isset($_POST['active']) ? 1 : 0;
    $activeChecked = $active === 1;
    $newPassword = $_POST['new_password'] ?? '';

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $otherActiveAdmins = (int)$stmt->get_result()->fetch_assoc()['c'];

    if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid name and email.';
    } elseif ($id === (int)$_SESSION['user_id'] && ($role !== 'admin' || $active !== 1)) {
        $error = 'You cannot remove your own active admin access.';
    } elseif ($user['role'] === 'admin' && ((int)$user['active'] === 1) && ($role !== 'admin' || $active !== 1) && $otherActiveAdmins === 0) {
        $error = 'At least one active admin account is required.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $check->bind_param("si", $email, $id);
        $check->execute();

        if ($check->get_result()->fetch_assoc()) {
            $error = 'That email address is already used by another account.';
        } else {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role = ?, active = ?, password = ? WHERE id = ?");
                $update->bind_param("sssisi", $fullname, $email, $role, $active, $hash, $id);
            } else {
                $update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role = ?, active = ? WHERE id = ?");
                $update->bind_param("sssii", $fullname, $email, $role, $active, $id);
            }
            $update->execute();

            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = $role;
            }

            setFlash('success', 'User updated successfully.');
            redirect("users.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('Manage User', 'Update account details for ' . $user['username'] . '.'); ?>
    <?php echo appNav('users'); ?>

    <div class="form-container">
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-group"><label>Username</label><input type="text" value="<?php echo e($user['username']); ?>" disabled></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="<?php echo e($_POST['fullname'] ?? $user['fullname']); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo e($_POST['email'] ?? $user['email']); ?>" required></div>
            <div class="form-group"><label>Role</label><select name="role">
                <?php $selectedRole = $_POST['role'] ?? $user['role']; ?>
                <?php foreach ($allowedRoles as $role): ?>
                    <option value="<?php echo e($role); ?>" <?php echo $selectedRole === $role ? 'selected' : ''; ?>><?php echo e(ucfirst($role)); ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="form-check"><input type="checkbox" id="active" name="active" <?php echo $activeChecked ? 'checked' : ''; ?>><label for="active">Active account</label></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Leave blank to keep current password"></div>
            <button type="submit" class="btn btn-primary">Save User</button>
            <a href="users.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
