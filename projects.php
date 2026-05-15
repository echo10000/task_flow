<?php
require_once 'config/database.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
if (isAdmin()) {
    $projects = $conn->query("SELECT p.*, u.username as creator, COUNT(t.id) as task_count, SUM(t.status = 'completed') as completed_count FROM projects p LEFT JOIN users u ON p.created_by = u.id LEFT JOIN tasks t ON t.project_id = p.id GROUP BY p.id ORDER BY p.created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT p.*, u.username as creator, COUNT(t.id) as task_count, SUM(t.status = 'completed') as completed_count FROM projects p INNER JOIN tasks t ON t.project_id = p.id LEFT JOIN users u ON p.created_by = u.id WHERE t.assigned_to = ? GROUP BY p.id ORDER BY p.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $projects = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Projects</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader(isAdmin() ? 'All Projects' : 'My Projects', isAdmin() ? 'Manage every project, status, and delivery signal.' : 'Review the projects connected to your assigned work.'); ?>
    <?php echo appNav('projects'); ?>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <?php if ($projects && $projects->num_rows > 0): ?>
        <div class="grid">
            <?php while ($p = $projects->fetch_assoc()): ?>
                <div class="card">
                    <div class="card-header"><strong><?php echo e($p['title']); ?></strong><?php echo getStatusBadge($p['status']); ?></div>
                    <div class="card-body">
                        <?php echo e(substr($p['description'] ?? '', 0, 120)); ?>
                        <?php $progress = (int)$p['task_count'] > 0 ? (int)round(((int)$p['completed_count'] / (int)$p['task_count']) * 100) : 0; ?>
                        <div class="progress-wrap"><div class="progress-bar" style="--progress: <?php echo $progress; ?>%"></div></div>
                        <div class="meta"><?php echo (int)$p['task_count']; ?> task(s) - <?php echo $progress; ?>% complete</div>
                    </div>
                    <div class="card-footer">
                        <div class="meta">Created by: <?php echo e($p['creator'] ?? 'Unknown'); ?><br><?php echo e(date('M d, Y', strtotime($p['created_at']))); ?></div>
                        <div>
                            <a href="project_view.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            <a href="tasks.php?project=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-secondary">Tasks</a>
                            <?php if (isAdmin()): ?>
                                <a href="project_edit.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <form method="POST" action="project_delete.php" class="inline-form" onsubmit="return confirm('Delete this project and all of its tasks?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="section empty-state">No projects found. <?php if (isAdmin()): ?><a href="project_create.php">Create your first project</a><?php endif; ?></div>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
        <div class="center-actions"><a href="project_create.php" class="btn btn-primary">Create New Project</a></div>
    <?php endif; ?>
</div>
</body>
</html>
