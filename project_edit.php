<?php
require_once 'config/database.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    setFlash('error', 'Project not found.');
    redirect("projects.php");
}

$error = '';
$allowedStatuses = ['planning', 'active', 'on_hold', 'completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isValidOption($_POST['status'] ?? 'planning', $allowedStatuses, 'planning');

    if ($title === '') {
        $error = 'Title is required.';
    } elseif (strlen($title) > 180) {
        $error = 'Title must be 180 characters or fewer.';
    } else {
        $update = $conn->prepare("UPDATE projects SET title = ?, description = ?, status = ? WHERE id = ?");
        $update->bind_param("sssi", $title, $description, $status, $id);
        $update->execute();
        setFlash('success', 'Project updated successfully.');
        redirect("projects.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-page">
<div class="container">
    <?php echo appHeader('Edit Project', 'Update the project details, status, and direction.'); ?>
    <?php echo appNav('projects'); ?>
    <div class="form-container">
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo e($_POST['title'] ?? $project['title']); ?>" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="5"><?php echo e($_POST['description'] ?? $project['description']); ?></textarea></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <?php $selectedStatus = $_POST['status'] ?? $project['status']; ?>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?php echo e($status); ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>>
                            <?php echo e(ucwords(str_replace('_', ' ', $status))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update Project</button>
            <a href="projects.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
