<?php
require_once 'config/database.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$_SESSION['user_id'];

if (isAdmin()) {
    $stmt = $conn->prepare("SELECT t.*, p.title as project_title, u.fullname as assigned_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT t.*, p.title as project_title, u.fullname as assigned_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ? AND t.assigned_to = ? LIMIT 1");
    $stmt->bind_param("ii", $id, $userId);
}
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    setFlash('error', 'Task not found or not assigned to you.');
    redirect("tasks.php");
}

$users = $conn->query("SELECT id, fullname FROM users WHERE active = 1 OR id = " . (int)$task['assigned_to'] . " ORDER BY fullname");
$projects = $conn->query("SELECT id, title FROM projects ORDER BY title");
$allowedStatuses = ['pending', 'in_progress', 'completed'];
$allowedPriorities = ['low', 'medium', 'high'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $status = isValidOption($_POST['status'] ?? 'pending', $allowedStatuses, 'pending');

    if (!isAdmin()) {
        $update = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
        $update->bind_param("sii", $status, $id, $userId);
        $update->execute();
        if ($status !== $task['status']) {
            recordTaskActivity($conn, $id, $userId, 'status_changed', 'Status changed from ' . $task['status'] . ' to ' . $status . '.');
        }
        setFlash('success', 'Task status updated.');
        redirect("tasks.php");
    }

    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = isValidOption($_POST['priority'] ?? 'medium', $allowedPriorities, 'medium');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $dueDateValue = $due_date === '' ? null : $due_date;

    $projectExists = false;
    $userExists = false;
    if ($project_id > 0) {
        $check = $conn->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
        $check->bind_param("i", $project_id);
        $check->execute();
        $projectExists = (bool)$check->get_result()->fetch_assoc();
    }
    if ($assigned_to > 0) {
        $check = $conn->prepare("SELECT id FROM users WHERE id = ? AND active = 1 LIMIT 1");
        $check->bind_param("i", $assigned_to);
        $check->execute();
        $userExists = (bool)$check->get_result()->fetch_assoc();
    }

    if ($title === '') {
        $error = 'Title is required.';
    } elseif (strlen($title) > 180) {
        $error = 'Title must be 180 characters or fewer.';
    } elseif (!$projectExists) {
        $error = 'Please choose a valid project.';
    } elseif (!$userExists) {
        $error = 'Please choose an active assignee.';
    } elseif (!isValidDateInput($dueDateValue)) {
        $error = 'Please enter a valid due date.';
    } else {
        $update = $conn->prepare("UPDATE tasks SET project_id = ?, title = ?, description = ?, status = ?, priority = ?, assigned_to = ?, due_date = ? WHERE id = ?");
        $update->bind_param("issssisi", $project_id, $title, $description, $status, $priority, $assigned_to, $dueDateValue, $id);
        $update->execute();
        recordTaskActivity($conn, $id, $userId, 'updated', 'Task details updated.');
        setFlash('success', 'Task updated successfully.');
        redirect("tasks.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isAdmin() ? 'Edit Task' : 'Update Task'; ?> - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader(isAdmin() ? 'Edit Task' : 'Update Task Status', isAdmin() ? 'Adjust assignment, priority, due date, and progress.' : 'Move your assigned task to the right status.'); ?>
    <?php echo appNav('tasks'); ?>
    <div class="form-container">
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <?php if (isAdmin()): ?>
                <div class="form-group"><label>Project</label><select name="project_id" required>
                    <?php $selectedProject = (int)($_POST['project_id'] ?? $task['project_id']); ?>
                    <?php while ($p = $projects->fetch_assoc()): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo $selectedProject === (int)$p['id'] ? 'selected' : ''; ?>><?php echo e($p['title']); ?></option>
                    <?php endwhile; ?>
                </select></div>
                <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo e($_POST['title'] ?? $task['title']); ?>" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?php echo e($_POST['description'] ?? $task['description']); ?></textarea></div>
            <?php else: ?>
                <div class="task-summary">
                    <strong><?php echo e($task['title']); ?></strong>
                    <p><?php echo e($task['description'] ?? ''); ?></p>
                    <div class="meta">Project: <?php echo e($task['project_title'] ?? 'No Project'); ?> - Assigned to: <?php echo e($task['assigned_name'] ?? 'Unassigned'); ?></div>
                </div>
            <?php endif; ?>

            <div class="form-group"><label>Status</label><select name="status">
                <?php $selectedStatus = $_POST['status'] ?? $task['status']; ?>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $status))); ?></option>
                <?php endforeach; ?>
            </select></div>

            <?php if (isAdmin()): ?>
                <div class="form-group"><label>Priority</label><select name="priority">
                    <?php $selectedPriority = $_POST['priority'] ?? $task['priority']; ?>
                    <?php foreach ($allowedPriorities as $priority): ?>
                        <option value="<?php echo e($priority); ?>" <?php echo $selectedPriority === $priority ? 'selected' : ''; ?>><?php echo e(ucfirst($priority)); ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Assign To</label><select name="assigned_to" required>
                    <?php $selectedUser = (int)($_POST['assigned_to'] ?? $task['assigned_to']); ?>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo $selectedUser === (int)$u['id'] ? 'selected' : ''; ?>><?php echo e($u['fullname']); ?></option>
                    <?php endwhile; ?>
                </select></div>
                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?php echo e($_POST['due_date'] ?? $task['due_date']); ?>"></div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary"><?php echo isAdmin() ? 'Update Task' : 'Update Status'; ?></button>
            <a href="tasks.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
