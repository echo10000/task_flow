<?php
require_once 'config/database.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$_SESSION['user_id'];

if (isAdmin()) {
    $stmt = $conn->prepare("SELECT p.*, u.username as creator FROM projects p LEFT JOIN users u ON p.created_by = u.id WHERE p.id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT DISTINCT p.*, u.username as creator FROM projects p INNER JOIN tasks t ON t.project_id = p.id LEFT JOIN users u ON p.created_by = u.id WHERE p.id = ? AND t.assigned_to = ? LIMIT 1");
    $stmt->bind_param("ii", $id, $userId);
}
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    setFlash('error', 'Project not found or not available to you.');
    redirect("projects.php");
}

if (isAdmin()) {
    $stmt = $conn->prepare("SELECT t.*, u.fullname as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ? ORDER BY FIELD(t.status, 'pending', 'in_progress', 'completed'), t.due_date IS NULL, t.due_date ASC");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT t.*, u.fullname as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ? AND t.assigned_to = ? ORDER BY FIELD(t.status, 'pending', 'in_progress', 'completed'), t.due_date IS NULL, t.due_date ASC");
    $stmt->bind_param("ii", $id, $userId);
}
$stmt->execute();
$tasks = $stmt->get_result();

$summary = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
$taskRows = [];
while ($task = $tasks->fetch_assoc()) {
    $summary['total']++;
    $summary[$task['status']]++;
    $taskRows[] = $task;
}
$progress = $summary['total'] > 0 ? (int)round(($summary['completed'] / $summary['total']) * 100) : 0;

$activity = [];
if ($taskRows) {
    $taskIds = array_column($taskRows, 'id');
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $types = str_repeat('i', count($taskIds));
    $stmt = $conn->prepare("SELECT a.*, u.fullname, t.title as task_title FROM task_activity a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN tasks t ON a.task_id = t.id WHERE a.task_id IN ($placeholders) ORDER BY a.created_at DESC LIMIT 8");
    $stmt->bind_param($types, ...$taskIds);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($project['title']); ?> - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader($project['title'], $project['description'] ?: 'No description provided.', '<div class="header-user">' . getStatusBadge($project['status']) . '</div>'); ?>
    <?php echo appNav('projects'); ?>

    <div class="stats-grid">
        <div class="stat-card"><h3><?php echo $progress; ?>%</h3><p>Complete</p></div>
        <div class="stat-card"><h3><?php echo (int)$summary['pending']; ?></h3><p>Pending</p></div>
        <div class="stat-card"><h3><?php echo (int)$summary['in_progress']; ?></h3><p>In Progress</p></div>
        <div class="stat-card"><h3><?php echo (int)$summary['completed']; ?></h3><p>Completed</p></div>
    </div>

    <div class="section">
        <div class="section-header">
            <h2>Project Progress</h2>
            <div class="filter-actions">
                <span class="meta">Created by <?php echo e($project['creator'] ?? 'Unknown'); ?></span>
                <?php if (isAdmin()): ?><a href="project_edit.php?id=<?php echo (int)$id; ?>" class="btn btn-sm btn-warning">Edit Project</a><?php endif; ?>
            </div>
        </div>
        <div class="progress-wrap progress-large"><div class="progress-bar" style="--progress: <?php echo $progress; ?>%"></div></div>
    </div>

    <div class="section">
        <div class="section-header"><h2>Tasks</h2><a href="tasks.php?project=<?php echo (int)$id; ?>">Open filtered task list</a></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Priority</th><th>Due</th><th></th></tr></thead>
                <tbody>
                <?php if ($taskRows): ?>
                    <?php foreach ($taskRows as $task): ?>
                        <tr>
                            <td><strong><?php echo e($task['title']); ?></strong><br><small><?php echo e(substr($task['description'] ?? '', 0, 80)); ?></small></td>
                            <td><?php echo e($task['assigned_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo getStatusBadge($task['status']); ?></td>
                            <td><?php echo getPriorityBadge($task['priority']); ?></td>
                            <td><?php echo $task['due_date'] ? e(date('M d, Y', strtotime($task['due_date']))) : 'No date'; ?></td>
                            <td><a href="task_edit.php?id=<?php echo (int)$task['id']; ?>" class="btn btn-sm btn-warning">Update</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty-state">No tasks found for this project.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-header"><h2>Recent Activity</h2></div>
        <?php if ($activity): ?>
            <div class="activity-list">
                <?php foreach ($activity as $item): ?>
                    <div class="activity-item">
                        <strong><?php echo e(ucwords(str_replace('_', ' ', $item['action']))); ?></strong>
                        <span><?php echo e($item['task_title'] ?? 'Task'); ?> - <?php echo e($item['details'] ?? ''); ?></span>
                        <small><?php echo e($item['fullname'] ?? 'System'); ?>, <?php echo e(date('M d, Y g:i A', strtotime($item['created_at']))); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No activity yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
