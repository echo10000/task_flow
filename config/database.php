<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'taskflow';

try {
    $conn = new mysqli($host, $user, $pass);
    $conn->set_charset('utf8mb4');
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db);
    initializeDatabase($conn);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database connection failed. Please check that MySQL is running.");
}

function initializeDatabase($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(40) NOT NULL UNIQUE,
            email VARCHAR(120) NOT NULL UNIQUE,
            fullname VARCHAR(120) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            status ENUM('planning','active','on_hold','completed') NOT NULL DEFAULT 'planning',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_projects_status (status),
            CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
            priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            assigned_to INT NULL,
            created_by INT NULL,
            due_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tasks_project (project_id),
            INDEX idx_tasks_status (status),
            INDEX idx_tasks_due_date (due_date),
            CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS task_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NULL,
            user_id INT NULL,
            action VARCHAR(80) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_activity_task (task_id),
            INDEX idx_task_activity_created_at (created_at),
            CONSTRAINT fk_task_activity_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            CONSTRAINT fk_task_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Keep older classroom/demo installs compatible with current fields.
    $conn->query("ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL");
    if (!columnExists($conn, 'users', 'active')) {
        $conn->query("ALTER TABLE users ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
    }

    $adminId = ensureUser($conn, 'admin', 'admin@example.com', 'System Administrator', 'admin123', 'admin');
    $memberId = ensureUser($conn, 'john', 'john@example.com', 'John Santos', 'user123', 'user');

    $projectCount = (int)$conn->query("SELECT COUNT(*) as c FROM projects")->fetch_assoc()['c'];
    if ($projectCount === 0) {
        $createdBy = $adminId;
        $title = 'Website Refresh';
        $description = 'Refresh the company website content and launch checklist.';
        $status = 'active';
        $stmt = $conn->prepare("INSERT INTO projects (title, description, status, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $status, $createdBy);
        $stmt->execute();

        $projectId = $stmt->insert_id;
        $taskTitle = 'Review homepage copy';
        $taskDescription = 'Check section headings, CTA labels, and launch blockers.';
        $taskStatus = 'pending';
        $priority = 'high';
        $assignedTo = $memberId;
        $dueDate = '2026-05-20';
        $stmt = $conn->prepare("INSERT INTO tasks (project_id, title, description, status, priority, assigned_to, created_by, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiis", $projectId, $taskTitle, $taskDescription, $taskStatus, $priority, $assignedTo, $createdBy, $dueDate);
        $stmt->execute();
    }
}

function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function ensureUser($conn, $username, $email, $fullname, $plainPassword, $role) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        return (int)$existing['id'];
    }

    $password = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, fullname, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $fullname, $password, $role);
    $stmt->execute();
    return (int)$stmt->insert_id;
}

function getConnection() {
    global $conn;
    return $conn;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: $path");
    exit();
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfOrDie() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Security check failed. Please go back and try again.');
    }
}

function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed.');
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function enforceSessionTimeout($seconds = 1800) {
    if (!isset($_SESSION['user_id'])) {
        return true;
    }

    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity <= 0 || (time() - $lastActivity) > $seconds) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function refreshSessionUser() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    if (!enforceSessionTimeout()) {
        return false;
    }

    global $conn;
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, fullname, role, active FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || (int)$user['active'] !== 1) {
        session_destroy();
        return false;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    return true;
}

function currentUserFullname() {
    refreshSessionUser();
    return $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
}

function currentUserRole() {
    refreshSessionUser();
    return $_SESSION['role'] ?? 'user';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect("login.php");
    }

    if (!refreshSessionUser()) {
        redirect("login.php");
    }
}

function requireAdmin() {
    if (!isLoggedIn()) {
        redirect("login.php");
    }

    if (!refreshSessionUser()) {
        redirect("login.php");
    }

    if (!isAdmin()) {
        http_response_code(403);
        echo "Access denied. Admin only.";
        exit();
    }
}

function recordTaskActivity($conn, $taskId, $userId, $action, $details = null) {
    $stmt = $conn->prepare("INSERT INTO task_activity (task_id, user_id, action, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $taskId, $userId, $action, $details);
    $stmt->execute();
}

function getProjectProgress($conn, $projectId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, SUM(status = 'completed') as done
        FROM tasks
        WHERE project_id = ?
    ");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total = (int)$row['total'];

    if ($total === 0) {
        return 0;
    }

    return (int)round(((int)$row['done'] / $total) * 100);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    if (!isset($flash['msg']) && isset($flash['message'])) {
        $flash['msg'] = $flash['message'];
    }
    if (!isset($flash['message']) && isset($flash['msg'])) {
        $flash['message'] = $flash['msg'];
    }

    return $flash;
}

function isValidOption($value, $allowed, $default) {
    return in_array($value, $allowed, true) ? $value : $default;
}

function isValidDateInput($value) {
    if ($value === '' || $value === null) {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function appHeader($title, $subtitle = '', $aside = '') {
    $subtitleHtml = $subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '';
    $asideHtml = $aside !== '' ? $aside : '<div class="header-user">' . e(currentUserFullname()) . ' <span>(' . e(currentUserRole()) . ')</span></div>';

    return '<div class="header app-header"><div class="header-main"><span class="brand-icon" aria-hidden="true">T</span><div><h1>' . e($title) . '</h1>' . $subtitleHtml . '</div></div>' . $asideHtml . '</div>';
}

function appNav($active = '') {
    $items = [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'projects' => ['Projects', 'projects.php'],
        'tasks' => ['Tasks', 'tasks.php'],
    ];

    if (isAdmin()) {
        $items['project_create'] = ['New Project', 'project_create.php'];
        $items['task_create'] = ['New Task', 'task_create.php'];
        $items['users'] = ['Users', 'users.php'];
    }

    $html = '<div class="nav app-nav">';
    foreach ($items as $key => $item) {
        [$label, $href] = $item;
        $class = $active === $key ? ' class="active"' : '';
        $html .= '<a href="' . e($href) . '"' . $class . '>' . e($label) . '</a>';
    }
    $html .= '<div class="nav-account"><a href="profile.php"' . ($active === 'profile' ? ' class="active"' : '') . '>Profile</a><a href="logout.php">Logout</a></div></div>';

    return $html;
}

function verifyPassword($password, $storedHash) {
    if (password_get_info($storedHash)['algo'] !== 0) {
        return password_verify($password, $storedHash);
    }

    return hash_equals((string)$storedHash, md5($password));
}

function needsPasswordRehash($storedHash) {
    return password_get_info($storedHash)['algo'] === 0 || password_needs_rehash($storedHash, PASSWORD_DEFAULT);
}

function getStatusBadge($status) {
    $labels = [
        'planning' => 'Planning',
        'active' => 'Active',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
    ];
    $classes = [
        'planning' => 'badge badge-warning',
        'active' => 'badge badge-info',
        'completed' => 'badge badge-success',
        'on_hold' => 'badge badge-danger',
        'pending' => 'badge badge-muted',
        'in_progress' => 'badge badge-primary',
    ];

    $label = $labels[$status] ?? ucwords(str_replace('_', ' ', (string)$status));
    $class = $classes[$status] ?? 'badge badge-muted';
    return '<span class="' . $class . '">' . e($label) . '</span>';
}

function getPriorityBadge($priority) {
    $classes = [
        'low' => 'badge badge-success',
        'medium' => 'badge badge-warning',
        'high' => 'badge badge-danger',
    ];

    $class = $classes[$priority] ?? 'badge badge-muted';
    return '<span class="' . $class . '">' . e(ucfirst((string)$priority)) . '</span>';
}
?>
