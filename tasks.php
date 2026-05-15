<?php
require_once 'config/database.php';
requireLogin();

$project_filter = isset($_GET['project']) ? (int)$_GET['project'] : 0;
$userId = (int)$_SESSION['user_id'];
$search = trim($_GET['q'] ?? '');
$statusFilter = isValidOption($_GET['status'] ?? '', ['', 'pending', 'in_progress', 'completed'], '');
$priorityFilter = isValidOption($_GET['priority'] ?? '', ['', 'low', 'medium', 'high'], '');
$sort = isValidOption($_GET['sort'] ?? 'due_date', ['due_date', 'priority', 'status', 'title', 'project'], 'due_date');
$sortSql = [
    'due_date' => "t.due_date IS NULL, t.due_date ASC",
    'priority' => "FIELD(t.priority, 'high', 'medium', 'low'), t.due_date IS NULL, t.due_date ASC",
    'status' => "FIELD(t.status, 'pending', 'in_progress', 'completed'), t.due_date IS NULL, t.due_date ASC",
    'title' => "t.title ASC",
    'project' => "p.title ASC, t.due_date IS NULL, t.due_date ASC",
][$sort];

$where = [];
$types = '';
$params = [];

if (!isAdmin()) {
    $where[] = 't.assigned_to = ?';
    $types .= 'i';
    $params[] = $userId;
}
if ($project_filter > 0) {
    $where[] = 't.project_id = ?';
    $types .= 'i';
    $params[] = $project_filter;
}
if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ? OR p.title LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($statusFilter !== '') {
    $where[] = 't.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($priorityFilter !== '') {
    $where[] = 't.priority = ?';
    $types .= 's';
    $params[] = $priorityFilter;
}

$sql = "SELECT t.*, p.title as project_title, u.fullname as assigned_name
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY " . $sortSql;

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tasks = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Tasks</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader(isAdmin() ? 'All Tasks' : 'My Tasks', 'Search, filter, prioritize, and update work without losing context.'); ?>
    <?php echo appNav('tasks'); ?>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <form method="GET" class="filter-bar">
        <?php if ($project_filter > 0): ?><input type="hidden" name="project" value="<?php echo (int)$project_filter; ?>"><?php endif; ?>
        <div class="form-group"><label>Search</label><input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Task, project, description"></div>
        <div class="form-group"><label>Status</label><select name="status">
            <option value="">All statuses</option>
            <?php foreach (['pending', 'in_progress', 'completed'] as $status): ?>
                <option value="<?php echo e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $status))); ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="form-group"><label>Priority</label><select name="priority">
            <option value="">All priorities</option>
            <?php foreach (['high', 'medium', 'low'] as $priority): ?>
                <option value="<?php echo e($priority); ?>" <?php echo $priorityFilter === $priority ? 'selected' : ''; ?>><?php echo e(ucfirst($priority)); ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="form-group"><label>Sort</label><select name="sort">
            <?php foreach (['due_date' => 'Due date', 'priority' => 'Priority', 'status' => 'Status', 'title' => 'Title', 'project' => 'Project'] as $value => $label): ?>
                <option value="<?php echo e($value); ?>" <?php echo $sort === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="tasks.php<?php echo $project_filter > 0 ? '?project=' . (int)$project_filter : ''; ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Task</th><th>Project</th><th>Assigned To</th><th>Status</th><th>Priority</th><th>Due Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($tasks && $tasks->num_rows > 0): ?>
                    <?php while ($t = $tasks->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo e($t['title']); ?></strong><br><small><?php echo e(substr($t['description'] ?? '', 0, 50)); ?></small></td>
                            <td><?php echo e($t['project_title'] ?? 'No Project'); ?></td>
                            <td><?php echo e($t['assigned_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo getStatusBadge($t['status']); ?></td>
                            <td><?php echo getPriorityBadge($t['priority']); ?></td>
                            <td><?php echo $t['due_date'] ? e(date('M d, Y', strtotime($t['due_date']))) : 'No date'; ?></td>
                            <td>
                                <a href="task_edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-warning btn-sm"><?php echo isAdmin() ? 'Edit' : 'Update'; ?></a>
                                <?php if (isAdmin()): ?>
                                    <form method="POST" action="task_delete.php" class="inline-form" onsubmit="return confirm('Delete this task?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-state">No tasks found. <?php if (isAdmin()): ?><a href="task_create.php">Create your first task</a><?php endif; ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
