<?php
require_once 'config/database.php';
requireAdmin();
requirePost();
verifyCsrfOrDie();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    setFlash('success', 'Project and its tasks were deleted.');
} else {
    setFlash('error', 'Project not found.');
}
redirect("projects.php");
?>
