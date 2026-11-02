<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureIsActiveColumn(PDO $db, string $table): void {
    if (!columnExists($db, $table, 'is_active')) {
        $db->exec("ALTER TABLE {$table} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
}

/* Dashboard protection */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.html");
    exit();
}

/* Fetch admin profile for header */
$adminProfile = [
    'profile_image' => null,
    'full_name' => 'Admin'
];
try {
    $db_profile = DB::connect();
    $stmt = $db_profile->prepare("SELECT profile_image, full_name FROM admin_users WHERE email = :email");
    $stmt->execute(['email' => $_SESSION['admin_email']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $adminProfile = $profile;
    }
} catch (Exception $e) {
    // Keep default values
}

/* Fetch admin notifications */
$notificationItems = [];
$unreadCount = 0;
try {
    $db_notify = DB::connect();
    $countStmt = $db_notify->query("SELECT COUNT(*) as total FROM admin_notifications WHERE is_read = 0");
    $unreadCount = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $listStmt = $db_notify->query("SELECT id, title, message, created_at, is_read FROM admin_notifications ORDER BY created_at DESC LIMIT 8");
    $notificationItems = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notificationItems = [];
    $unreadCount = 0;
}

/* Handle mark as read */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    try {
        $db = DB::connect();
        $stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE message_id = :id");
        $stmt->execute(['id' => $_POST['mark_read_id']]);
    } catch (Exception $e) {
        // Ignore
    }
}

/* Handle delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $db = DB::connect();
        ensureIsActiveColumn($db, 'contact_messages');
        $stmt = $db->prepare("UPDATE contact_messages SET is_active = 0 WHERE message_id = :id AND is_active = 1");
        $stmt->execute(['id' => $_POST['delete_id']]);
    } catch (Exception $e) {
        // Ignore
    }
}

/* Fetch messages */
try {
    $db = DB::connect();
    $hasActive = columnExists($db, 'contact_messages', 'is_active');
    $where = $hasActive ? "WHERE is_active = 1" : "";
    $stmt = $db->query("SELECT * FROM contact_messages {$where} ORDER BY created_at DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FabricSense Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root { --primary:#1a1a2e; --accent:#ff5a7a; --accent-light:#ffd6df; --text-dark:#333; --bg-light:#f8f9fa; }
        body { font-family:'DM Sans', sans-serif; background:var(--bg-light); color:var(--text-dark); }

        .admin-header { background: linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%); color:#fff; padding:1.2rem 0; box-shadow:0 4px 20px rgba(0,0,0,0.15); position:sticky; top:0; z-index:1000; }
        .header-content { max-width:1600px; margin:0 auto; padding:0 2rem; display:flex; justify-content:space-between; align-items:center; }
        .admin-logo { display:flex; align-items:center; gap:1rem; }
        .admin-logo-icon { font-size:2rem; }
        .admin-logo-text { font-family:'Playfair Display', serif; font-size:1.8rem; font-weight:900; background:linear-gradient(135deg,#ffd6df 0%,#ff5a7a 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; letter-spacing: -1px; }
        .admin-info { display:flex; align-items:center; gap:2rem; }
        .notification-wrapper { position: relative; }
        .notification-btn { width:42px; height:42px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s ease; box-shadow:0 4px 12px rgba(0,0,0,0.15); }
        .notification-btn:hover { background:rgba(255,255,255,0.25); transform:translateY(-1px); }
        .notification-count { position:absolute; top:-4px; right:-4px; background:var(--accent); color:#fff; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; border:2px solid #1a1a2e; }
        .notification-panel { position:absolute; top:52px; right:0; width:320px; background:#fff; color:var(--text-dark); border-radius:14px; box-shadow:0 18px 40px rgba(0,0,0,0.18); opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.2s ease; z-index:1200; overflow:hidden; }
        .notification-panel.open { opacity:1; visibility:visible; transform:translateY(0); }
        .notification-header { padding:1rem 1.2rem; font-weight:700; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
        .notification-list { max-height:280px; overflow-y:auto; }
        .notification-item { padding:0.9rem 1.2rem; border-bottom:1px solid #f5f5f5; }
        .notification-item:last-child { border-bottom:none; }
        .notification-title { font-weight:700; margin-bottom:0.25rem; color:var(--primary); font-size:0.92rem; }
        .notification-message { font-size:0.85rem; color:#666; margin-bottom:0.35rem; }
        .notification-time { font-size:0.75rem; color:#999; }
        .admin-user { display:flex; align-items:center; padding:0.4rem; background:rgba(255,255,255,0.1); border-radius:50%; cursor:pointer; transition:all .3s; border:2px solid transparent; text-decoration:none; }
        .admin-user:hover { background:rgba(255,255,255,0.2); border-color:rgba(255,255,255,0.3); transform:scale(1.05); }
        .admin-avatar { width:40px; height:40px; background:linear-gradient(135deg,var(--accent),var(--accent-light)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
        .admin-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        .logout-btn { padding:0.7rem 1.8rem; background:var(--accent); color:#fff; border:none; border-radius:50px; cursor:pointer; font-weight:700; font-size:0.95rem; transition:all .3s; box-shadow:0 4px 15px rgba(233,69,96,0.3); }
        .logout-btn:hover { background:var(--accent-light); transform:translateY(-2px); box-shadow:0 6px 20px rgba(233,69,96,0.4); }

        .dashboard-container { display:flex; min-height:calc(100vh - 76px); max-width:1600px; margin:0 auto; }
        .sidebar { width:280px; background:#fff; box-shadow:4px 0 20px rgba(0,0,0,0.06); padding:2rem 0; }
        .sidebar-menu { list-style:none; }
        .sidebar-menu li { margin-bottom:0.5rem; padding:0 1rem; }
        .sidebar-menu a { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; color:var(--text-dark); text-decoration:none; font-weight:600; border-radius:12px; transition:all .3s; position:relative; }
        .sidebar-menu a::before { content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:4px; height:0; background:linear-gradient(135deg,var(--accent),var(--accent-light)); border-radius:0 4px 4px 0; transition:height .3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background:linear-gradient(135deg,rgba(233,69,96,0.08),rgba(255,107,129,0.08)); color:var(--accent); }
        .sidebar-menu a:hover::before, .sidebar-menu a.active::before { height:60%; }
        .menu-icon { font-size:1.4rem; width:28px; text-align:center; }

        .main-content { flex:1; padding:2.5rem; background:var(--bg-light); }
        .page-title { font-family:'Playfair Display', serif; font-size:2.5rem; background:linear-gradient(135deg,var(--primary),var(--accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:1rem; }
        .page-subtitle { color:#666; margin-bottom:2rem; }

        .data-section { background:#fff; padding:2rem; border-radius:20px; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:1rem; border-bottom:1px solid #f0f0f0; text-align:left; }
        thead { background:rgba(233,69,96,0.05); }
        th { font-weight:700; color:var(--primary); text-transform:uppercase; font-size:0.85rem; }

        .badge { padding:0.3rem 0.7rem; border-radius:12px; font-size:0.8rem; font-weight:700; }
        .badge-new { background:#ffe3ea; color:#e94560; }
        .badge-read { background:#e3f2fd; color:#1976d2; }
        .badge-replied { background:#e8f5e9; color:#2e7d32; }

        .btn { padding:0.5rem 1rem; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
        .btn-reply { background:var(--accent); color:#fff; text-decoration:none; display:inline-block; }
        .btn-mark { background:#f0f0f0; color:#333; }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="admin-logo">
                <span class="admin-logo-text">FabricSense</span>
            </div>
            <div class="admin-info">
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationToggle" title="Notifications" aria-label="Notifications">🔔</button>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-count"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                    <?php endif; ?>
                    <div class="notification-panel" id="notificationPanel">
                        <div class="notification-header">
                            <span>Notifications</span>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notificationItems)): ?>
                                <div class="notification-item">
                                    <div class="notification-message">No notifications yet.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notificationItems as $note): ?>
                                    <div class="notification-item">
                                        <div class="notification-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($note['message']); ?></div>
                                        <div class="notification-time"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($note['created_at']))); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <a href="adminProfile.php" class="admin-user" title="View Profile">
                    <div class="admin-avatar">
                        <?php if ($adminProfile['profile_image'] && file_exists($adminProfile['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($adminProfile['profile_image']); ?>" alt="Admin">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                    </div>
                </a>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="adminDashboard.php"><span class="menu-icon">📊</span> Dashboard</a></li>
                <li><a href="manageUsers.php"><span class="menu-icon">👥</span> User Management</a></li>
                <li><a href="adminFabricTypes.php"><span class="menu-icon">🧵</span> Fabric Types</a></li>
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php" class="active"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1 class="page-title">Messages</h1>
            <p class="page-subtitle">View and reply to contact messages.</p>

            <div class="data-section">
                <?php if (empty($messages)): ?>
                    <div style="text-align:center; padding:2rem; color:#999;">No messages yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td>#<?php echo $msg['message_id']; ?></td>
                                    <td><?php echo htmlspecialchars($msg['name']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['email']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                    <td>
                                        <?php
                                            $status = $msg['status'] ?? 'new';
                                            $badgeClass = $status === 'replied' ? 'badge-replied' : ($status === 'read' ? 'badge-read' : 'badge-new');
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($msg['created_at'])); ?></td>
                                    <td>
                                        <a class="btn btn-reply" href="manageMessage.php?id=<?php echo $msg['message_id']; ?>">View</a>
                                        <a class="btn btn-mark" href="manageMessage.php?id=<?php echo $msg['message_id']; ?>#reply">Reply</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this message?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $msg['message_id']; ?>">
                                            <button class="btn btn-mark" type="submit" style="background:#f8d7da; color:#721c24;">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Notifications dropdown
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationPanel = document.getElementById('notificationPanel');

        if (notificationToggle && notificationPanel) {
            notificationToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const opening = !notificationPanel.classList.contains('open');
                notificationPanel.classList.toggle('open');
                if (opening) {
                    fetch('notifications_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=mark_all_read' })
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.success) {
                                const badge = document.querySelector('.notification-count');
                                if (badge) badge.style.display = 'none';
                            }
                        })
                        .catch(() => {});
                }
            });

            document.addEventListener('click', function(e) {
                if (!notificationPanel.contains(e.target) && e.target !== notificationToggle) {
                    notificationPanel.classList.remove('open');
                }
            });
        }
    </script>
</body>
</html>
