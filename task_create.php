<?php
require_once 'config/database.php';
requireAdmin();

$users = $conn->query("SELECT id, fullname FROM users WHERE active = 1 ORDER BY fullname");
$projects = $conn->query("SELECT id, title FROM projects ORDER BY title");
$allowedPriorities = ['low', 'medium', 'high'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = isValidOption($_POST['priority'] ?? 'medium', $allowedPriorities, 'medium');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $created_by = (int)$_SESSION['user_id'];
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
        $stmt = $conn->prepare("INSERT INTO tasks (project_id, title, description, priority, assigned_to, created_by, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiis", $project_id, $title, $description, $priority, $assigned_to, $created_by, $dueDateValue);
        $stmt->execute();
        recordTaskActivity($conn, (int)$stmt->insert_id, $created_by, 'created', 'Task created and assigned.');
        setFlash('success', 'Task created successfully.');
        redirect("tasks.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('Create New Task', 'Assign clear work to an active teammate and connect it to a project.'); ?>
    <?php echo appNav('task_create'); ?>
    <div class="form-container">
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-group"><label>Project</label><select name="project_id" required>
                <option value="">Select Project</option>
                <?php while ($p = $projects->fetch_assoc()): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)($_POST['project_id'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>><?php echo e($p['title']); ?></option>
                <?php endwhile; ?>
            </select></div>
            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo e($_POST['title'] ?? ''); ?>" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?php echo e($_POST['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label>Priority</label><select name="priority">
                <?php foreach ($allowedPriorities as $priority): ?>
                    <option value="<?php echo e($priority); ?>" <?php echo (($_POST['priority'] ?? 'medium') === $priority) ? 'selected' : ''; ?>><?php echo e(ucfirst($priority)); ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Assign To</label><select name="assigned_to" required>
                <option value="">Select User</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo (int)($_POST['assigned_to'] ?? 0) === (int)$u['id'] ? 'selected' : ''; ?>><?php echo e($u['fullname']); ?></option>
                <?php endwhile; ?>
            </select></div>
            <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?php echo e($_POST['due_date'] ?? ''); ?>"></div>
            <button type="submit" class="btn btn-primary">Create Task</button>
            <a href="tasks.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
