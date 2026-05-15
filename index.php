<?php
require_once 'config/database.php';

$isLoggedIn = isLoggedIn() && refreshSessionUser();
$sessionName = $isLoggedIn ? currentUserFullname() : '';
$sessionRole = $isLoggedIn ? currentUserRole() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Project and Task Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="landing-page">
    <main class="landing-shell">
        <nav class="landing-nav" aria-label="Public navigation">
            <a class="brand-mark" href="index.php" aria-label="TaskFlow home">
                <span class="brand-icon" aria-hidden="true">T</span>
                <span>TaskFlow</span>
            </a>
            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <span class="session-pill">Signed in as <?php echo e($sessionName); ?> (<?php echo e($sessionRole); ?>)</span>
                    <a href="dashboard.php" class="btn btn-primary">Continue</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                    <a href="register.php" class="btn btn-primary">Create account</a>
                <?php endif; ?>
            </div>
        </nav>

        <section class="hero">
            <div class="hero-copy">
                <p class="section-kicker">Project clarity for focused teams</p>
                <h1>Organize projects, tasks, and team momentum in one calm workspace.</h1>
                <p>
                    TaskFlow helps teams plan work, assign ownership, monitor progress, and keep
                    everyday delivery visible without adding another complicated process.
                </p>
                <div class="hero-actions">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard.php" class="btn btn-primary">Continue to dashboard</a>
                        <a href="logout.php" class="btn btn-secondary">Use another account</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-primary">Create account</a>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="hero-panel" aria-label="TaskFlow workspace preview">
                <div class="workspace-preview">
                    <div class="preview-top">
                        <div class="preview-title">
                            <strong>Product launch</strong>
                            <span>Live project snapshot</span>
                        </div>
                        <span class="badge badge-success">On track</span>
                    </div>
                    <div class="preview-grid">
                        <div class="preview-stat">
                            <strong>12</strong>
                            <span>Open tasks</span>
                        </div>
                        <div class="preview-stat">
                            <strong>84%</strong>
                            <span>Progress</span>
                        </div>
                        <div class="preview-stat">
                            <strong>5</strong>
                            <span>Owners</span>
                        </div>
                    </div>
                    <div class="progress-wrap progress-large" aria-hidden="true">
                        <div class="progress-bar" style="--progress: 84%;"></div>
                    </div>
                    <div class="preview-list">
                        <div class="preview-row">
                            <span class="status-dot"></span>
                            <span>Finalize release checklist</span>
                            <span class="badge badge-primary">Today</span>
                        </div>
                        <div class="preview-row">
                            <span class="status-dot teal"></span>
                            <span>Review dashboard metrics</span>
                            <span class="badge badge-info">Active</span>
                        </div>
                        <div class="preview-row">
                            <span class="status-dot warning"></span>
                            <span>Confirm access permissions</span>
                            <span class="badge badge-warning">Review</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="landing-section" aria-labelledby="features-title">
            <p class="section-kicker">Everything your workflow needs</p>
            <h2 id="features-title">Built around the work teams do every day.</h2>
            <p class="section-intro">
                From planning to completion, TaskFlow keeps project information structured,
                searchable, and easy to act on.
            </p>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">&#9632;</div>
                    <h3>Projects</h3>
                    <p>Create organized project spaces with clear descriptions, ownership, status,
                    and progress that everyone can understand at a glance.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">&#10003;</div>
                    <h3>Tasks</h3>
                    <p>Break work into assignable tasks, track priority and due dates, and move
                    details forward without losing context.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">&#9685;</div>
                    <h3>Dashboard</h3>
                    <p>See active projects, task totals, progress signals, and recent activity from
                    one focused command center.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">&#9670;</div>
                    <h3>Security</h3>
                    <p>Use account-based access, authenticated sessions, and role-aware workflows to
                    keep team data in the right hands.</p>
                </article>
            </div>

            <div class="security-note">
                <div>
                    <h2>Private work stays behind authenticated access.</h2>
                    <p>
                        Public visitors can learn about TaskFlow here, while projects, tasks,
                        dashboards, users, and profiles remain available only after login.
                    </p>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-primary">Open dashboard</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Create account</a>
                <?php endif; ?>
            </div>
        </section>

        <footer class="landing-footer">
            <span>&copy; <?php echo date('Y'); ?> TaskFlow. Built for organized teamwork.</span>
            <span>Projects | Tasks | Dashboard | Security</span>
        </footer>
    </main>
</body>
</html>
