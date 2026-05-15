<?php
require_once 'config/database.php';
requireAdmin();
requirePost();
verifyCsrfOrDie();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    setFlash('success', 'Task deleted.');
} else {
    setFlash('error', 'Task not found.');
}
redirect("tasks.php");
?>
