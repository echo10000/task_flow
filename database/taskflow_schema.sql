CREATE DATABASE IF NOT EXISTS taskflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE taskflow;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    fullname VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status ENUM('planning','active','on_hold','completed') NOT NULL DEFAULT 'planning',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_projects_status (status),
    INDEX idx_projects_created_by (created_by),
    CONSTRAINT fk_projects_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_tasks_assigned_to (assigned_to),
    INDEX idx_tasks_due_date (due_date),
    CONSTRAINT fk_tasks_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned_to
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_tasks_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NULL,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_activity_task (task_id),
    INDEX idx_task_activity_created_at (created_at),
    CONSTRAINT fk_task_activity_task
        FOREIGN KEY (task_id) REFERENCES tasks(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_task_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (id, username, email, fullname, password, role) VALUES
    (1, 'admin', 'admin@example.com', 'System Administrator', '$2y$10$iQ.8CMqmzgnD6unXyXjjZOUbZUdbCEvwBzKXdFElr95KmW9lOqG2G', 'admin'),
    (2, 'john', 'john@example.com', 'John Santos', '$2y$10$cyS8LdwATgJTatN9VM1Fu.wvum6UDTWYUqsaDHR9ITQxUsPY.dsye', 'user');

INSERT IGNORE INTO projects (id, title, description, status, created_by) VALUES
    (1, 'Website Refresh', 'Refresh the company website content and launch checklist.', 'active', 1);

INSERT IGNORE INTO tasks (id, project_id, title, description, status, priority, assigned_to, created_by, due_date) VALUES
    (1, 1, 'Review homepage copy', 'Check section headings, CTA labels, and launch blockers.', 'pending', 'high', 2, 1, '2026-05-20');
