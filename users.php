<?php
require_once 'config/database.php';
requireAdmin();

$users = $conn->query("
    SELECT u.id, u.username, u.email, u.fullname, u.role, u.active, u.created_at,
           COUNT(t.id) as task_count,
           COALESCE(SUM(t.status = 'completed'), 0) as completed_count
    FROM users u
    LEFT JOIN tasks t ON t.assigned_to = u.id
    GROUP BY u.id, u.username, u.email, u.fullname, u.role, u.active, u.created_at
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('Manage Users', 'Review roles, account status, task load, and team access.'); ?>
    <?php echo appNav('users'); ?>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table>
            <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Tasks</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo e($u['username']); ?></td>
                        <td><?php echo e($u['fullname']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><span class="badge <?php echo $u['role'] === 'admin' ? 'badge-danger' : 'badge-success'; ?>"><?php echo e(ucfirst($u['role'])); ?></span></td>
                        <td><span class="badge <?php echo (int)$u['active'] === 1 ? 'badge-success' : 'badge-muted'; ?>"><?php echo (int)$u['active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                        <td><?php echo (int)$u['completed_count']; ?>/<?php echo (int)$u['task_count']; ?></td>
                        <td><?php echo e(date('M d, Y', strtotime($u['created_at']))); ?></td>
                        <td><a href="user_edit.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-warning">Manage</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
