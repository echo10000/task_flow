<?php
require_once 'config/database.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

if (isAdmin()) {
    $total_projects = $conn->query("SELECT COUNT(*) as c FROM projects")->fetch_assoc()['c'];
    $active_projects = $conn->query("SELECT COUNT(*) as c FROM projects WHERE status = 'active'")->fetch_assoc()['c'];
    $total_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks")->fetch_assoc()['c'];
    $completed_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'completed'")->fetch_assoc()['c'];
    $overdue_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE due_date < CURDATE() AND status <> 'completed'")->fetch_assoc()['c'];
    $recent_projects = $conn->query("SELECT p.*, u.username as creator FROM projects p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC LIMIT 5");
    $recent_tasks = $conn->query("SELECT t.*, p.title as project_title FROM tasks t LEFT JOIN projects p ON t.project_id = p.id ORDER BY t.created_at DESC LIMIT 5");
} else {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as c FROM projects p INNER JOIN tasks t ON t.project_id = p.id WHERE t.assigned_to = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $total_projects = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as c FROM projects p INNER JOIN tasks t ON t.project_id = p.id WHERE t.assigned_to = ? AND p.status = 'active'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $active_projects = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $total_tasks = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ? AND status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $completed_tasks = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status <> 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $overdue_tasks = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT DISTINCT p.*, u.username as creator FROM projects p INNER JOIN tasks t ON t.project_id = p.id LEFT JOIN users u ON p.created_by = u.id WHERE t.assigned_to = ? ORDER BY p.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recent_projects = $stmt->get_result();

    $stmt = $conn->prepare("SELECT t.*, p.title as project_title FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? ORDER BY t.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recent_tasks = $stmt->get_result();
}
$completion_rate = (int)$total_tasks > 0 ? (int)round(((int)$completed_tasks / (int)$total_tasks) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader(isAdmin() ? 'TaskFlow Dashboard' : 'My Work Dashboard', 'Track project health, upcoming work, and team momentum from one place.'); ?>
    <?php echo appNav('dashboard'); ?>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><h3><?php echo (int)$total_projects; ?></h3><p><?php echo isAdmin() ? 'Total Projects' : 'Assigned Projects'; ?></p></div>
        <div class="stat-card"><h3><?php echo (int)$active_projects; ?></h3><p>Active Projects</p></div>
        <div class="stat-card"><h3><?php echo (int)$total_tasks; ?></h3><p><?php echo isAdmin() ? 'Total Tasks' : 'My Tasks'; ?></p></div>
        <div class="stat-card"><h3><?php echo (int)$completed_tasks; ?></h3><p>Completed Tasks</p></div>
        <div class="stat-card"><h3><?php echo (int)$overdue_tasks; ?></h3><p>Overdue Tasks</p></div>
        <div class="stat-card"><h3><?php echo (int)$completion_rate; ?>%</h3><p>Completion Rate</p></div>
    </div>

    <div class="section">
        <div class="section-header"><h2>Overall Progress</h2><span class="meta"><?php echo (int)$completed_tasks; ?> of <?php echo (int)$total_tasks; ?> tasks complete</span></div>
        <div class="progress-wrap progress-large"><div class="progress-bar" style="--progress: <?php echo $completion_rate; ?>%"></div></div>
    </div>

    <div class="section">
        <div class="section-header"><h2><?php echo isAdmin() ? 'Recent Projects' : 'My Projects'; ?></h2><a href="projects.php">View All</a></div>
        <?php if ($recent_projects->num_rows > 0): ?>
            <div class="grid">
                <?php while ($p = $recent_projects->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-header"><strong><?php echo e($p['title']); ?></strong><?php echo getStatusBadge($p['status']); ?></div>
                        <div class="card-body"><?php echo e(substr($p['description'] ?? '', 0, 100)); ?></div>
                        <div class="card-footer">
                            <div class="meta">Created by: <?php echo e($p['creator'] ?? 'Unknown'); ?></div>
                            <a href="tasks.php?project=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">View Tasks</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No projects yet. <?php if (isAdmin()): ?><a href="project_create.php">Create your first project</a><?php endif; ?></p>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-header"><h2><?php echo isAdmin() ? 'Recent Tasks' : 'My Recent Tasks'; ?></h2><a href="tasks.php">View All</a></div>
        <?php if ($recent_tasks->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Priority</th><th>Due Date</th><th></th></tr></thead>
                    <tbody>
                        <?php while ($t = $recent_tasks->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($t['title']); ?></strong></td>
                                <td><?php echo e($t['project_title'] ?? 'No Project'); ?></td>
                                <td><?php echo getStatusBadge($t['status']); ?></td>
                                <td><?php echo getPriorityBadge($t['priority']); ?></td>
                                <td><?php echo $t['due_date'] ? e(date('M d', strtotime($t['due_date']))) : 'No date'; ?></td>
                                <td><a href="task_edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-warning">Update</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No tasks yet. <?php if (isAdmin()): ?><a href="task_create.php">Create your first task</a><?php endif; ?></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
