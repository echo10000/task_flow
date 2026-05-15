<?php
require_once 'config/database.php';
requireAdmin();

$error = '';
$allowedStatuses = ['planning', 'active', 'on_hold', 'completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isValidOption($_POST['status'] ?? 'planning', $allowedStatuses, 'planning');
    $createdBy = (int)$_SESSION['user_id'];

    if ($title === '') {
        $error = 'Title is required.';
    } elseif (strlen($title) > 180) {
        $error = 'Title must be 180 characters or fewer.';
    } else {
        $stmt = $conn->prepare("INSERT INTO projects (title, description, status, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $status, $createdBy);
        $stmt->execute();
        setFlash('success', 'Project created successfully.');
        redirect("projects.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('Create New Project', 'Set up a focused project space for tasks, ownership, and progress.'); ?>
    <?php echo appNav('project_create'); ?>

    <div class="form-container">
        <h2>Project Details</h2>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-group"><label>Title *</label><input type="text" name="title" value="<?php echo e($_POST['title'] ?? ''); ?>" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="5"><?php echo e($_POST['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?php echo e($status); ?>" <?php echo (($_POST['status'] ?? 'planning') === $status) ? 'selected' : ''; ?>>
                            <?php echo e(ucwords(str_replace('_', ' ', $status))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create Project</button>
            <a href="projects.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
