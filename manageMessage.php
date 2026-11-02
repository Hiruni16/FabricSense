<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureMessageColumns(PDO $db): void {
    // Ensure status column
    if (!columnExists($db, 'contact_messages', 'status')) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'new'");
    }
    // Ensure reply_text column
    if (!columnExists($db, 'contact_messages', 'reply_text')) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN reply_text TEXT NULL");
    }
    // Ensure reply_at timestamp
    if (!columnExists($db, 'contact_messages', 'reply_at')) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN reply_at TIMESTAMP NULL DEFAULT NULL");
    }
}

/* Dashboard protection */
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.html');
    exit();
}

// Basic header data
$adminProfile = [ 'profile_image' => null, 'full_name' => 'Admin' ];
$notificationItems = []; $unreadCount = 0;
try {
    $db_profile = DB::connect();
    $stmt = $db_profile->prepare('SELECT profile_image, full_name FROM admin_users WHERE email = :email');
    $stmt->execute(['email' => $_SESSION['admin_email']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) { $adminProfile = $profile; }

    $countStmt = $db_profile->query("SELECT COUNT(*) as total FROM admin_notifications WHERE is_read = 0");
    $unreadCount = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $listStmt = $db_profile->query("SELECT id, title, message, created_at, is_read FROM admin_notifications ORDER BY created_at DESC LIMIT 8");
    $notificationItems = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

// Get message id
$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = null; $error = '';$info = '';

try {
    $db = DB::connect();
    ensureMessageColumns($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['mark_read'])) {
            $stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE message_id = :id");
            $stmt->execute(['id' => $messageId]);
            $info = 'Message marked as read.';
        } elseif (isset($_POST['reply_text'])) {
            $reply = trim($_POST['reply_text']);
            $stmt = $db->prepare("UPDATE contact_messages SET status = 'replied', reply_text = :reply, reply_at = NOW() WHERE message_id = :id");
            $stmt->execute(['reply' => $reply, 'id' => $messageId]);
            $info = 'Reply saved.';
        }
    }

    $stmt = $db->prepare('SELECT * FROM contact_messages WHERE message_id = :id');
    $stmt->execute(['id' => $messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$message) {
        $error = 'Message not found.';
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Message - FabricSense Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root { --primary:#1a1a2e; --accent:#ff5a7a; --accent-light:#ffd6df; --text-dark:#333; --bg-light:#f8f9fa; }
        body { font-family:'DM Sans', sans-serif; background:var(--bg-light); color:var(--text-dark); }
        .admin-header { background: linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%); color:#fff; padding:1.2rem 0; box-shadow:0 4px 20px rgba(0,0,0,0.15); position:sticky; top:0; z-index:1000; }
        .header-content { max-width:1600px; margin:0 auto; padding:0 2rem; display:flex; justify-content:space-between; align-items:center; }
        .admin-logo-text { font-family:'Playfair Display', serif; font-size:1.8rem; font-weight:900; background:linear-gradient(135deg,#ffd6df 0%,#ff5a7a 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .admin-info { display:flex; align-items:center; gap:2rem; }
        .notification-wrapper { position: relative; }
        .notification-btn { width:42px; height:42px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .notification-count { position:absolute; top:-4px; right:-4px; background:var(--accent); color:#fff; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; border:2px solid #1a1a2e; }
        .dashboard-container { display:flex; min-height:calc(100vh - 76px); max-width:1200px; margin:0 auto; }
        .sidebar { width:280px; background:#fff; box-shadow:4px 0 20px rgba(0,0,0,0.06); padding:2rem 0; }
        .sidebar-menu { list-style:none; }
        .sidebar-menu li { margin-bottom:0.5rem; padding:0 1rem; }
        .sidebar-menu a { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; color:var(--text-dark); text-decoration:none; font-weight:600; border-radius:12px; }
        .main-content { flex:1; padding:2.5rem; }
        .page-title { font-family:'Playfair Display', serif; font-size:2rem; background:linear-gradient(135deg,var(--primary),var(--accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:1rem; }
        .card { background:#fff; padding:2rem; border-radius:16px; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; }
        .form-group { margin-bottom:1rem; }
        .form-label { font-weight:700; color:var(--primary); margin-bottom:0.5rem; display:block; }
        .form-input, .form-textarea { width:100%; padding:0.75rem; border:2px solid #e1e8ed; border-radius:10px; font-size:1rem; }
        .form-textarea { min-height:140px; resize:vertical; }
        .btn { padding:0.6rem 1.2rem; border:none; border-radius:10px; font-weight:700; cursor:pointer; }
        .btn-primary { background:linear-gradient(135deg,var(--accent),var(--accent-light)); color:#fff; }
        .btn-secondary { background:#e9ecef; color:#495057; }
        .badge { padding:0.3rem 0.7rem; border-radius:12px; font-size:0.8rem; font-weight:700; }
        .badge-new { background:#ffe3ea; color:#e94560; }
        .badge-read { background:#e3f2fd; color:#1976d2; }
        .badge-replied { background:#e8f5e9; color:#2e7d32; }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="admin-logo"><span class="admin-logo-text">FabricSense</span></div>
            <div class="admin-info">
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationToggle">🔔</button>
                    <?php if ($unreadCount > 0): ?><span class="notification-count"><?php echo $unreadCount; ?></span><?php endif; ?>
                </div>
                <a href="adminProfile.php" class="admin-user">
                    <div class="admin-avatar">
                        <?php if ($adminProfile['profile_image'] && file_exists($adminProfile['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($adminProfile['profile_image']); ?>" alt="Admin" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>👤<?php endif; ?>
                    </div>
                </a>
                <button class="btn btn-primary" onclick="logout()">Logout</button>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
       

        <main class="main-content">
            <h1 class="page-title">Message Details</h1>

            <?php if ($error): ?>
                <div class="card" style="color:#a94442; background:#fdecea; border:1px solid #f5c6cb;"><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <div>
                            <strong>#<?php echo $message['message_id']; ?></strong>
                            <?php
                                $status = $message['status'] ?? 'new';
                                $badgeClass = $status === 'replied' ? 'badge-replied' : ($status === 'read' ? 'badge-read' : 'badge-new');
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>" style="margin-left:0.5rem;"><?php echo ucfirst($status); ?></span>
                        </div>
                        <a class="btn btn-secondary" href="manageMessages.php">Back</a>
                    </div>

                    <div class="row">
                        <div>
                            <div class="form-group"><span class="form-label">Name</span><?php echo htmlspecialchars($message['name']); ?></div>
                            <div class="form-group"><span class="form-label">Email</span><?php echo htmlspecialchars($message['email']); ?></div>
                            <div class="form-group"><span class="form-label">Subject</span><?php echo htmlspecialchars($message['subject']); ?></div>
                            <div class="form-group"><span class="form-label">Received</span><?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?></div>
                        </div>
                        <div>
                            <div class="form-group"><span class="form-label">Message</span><div style="white-space:pre-wrap; border:1px solid #e1e8ed; border-radius:10px; padding:0.75rem;"><?php echo nl2br(htmlspecialchars($message['message'] ?? '')); ?></div></div>
                        </div>
                    </div>

                    <form method="post" style="margin-top:1rem;">
                        <button class="btn btn-secondary" type="submit" name="mark_read">Mark as Read</button>
                    </form>
                </div>

                <div class="card" id="reply" style="margin-top:1.5rem;">
                    <h2 style="margin-bottom:1rem;">Reply</h2>
                    <form method="post">
                        <div class="form-group">
                            <label class="form-label" for="reply_text">Message to send</label>
                            <textarea class="form-textarea" id="reply_text" name="reply_text" placeholder="Write your reply here..."><?php echo htmlspecialchars($message['reply_text'] ?? ''); ?></textarea>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn btn-primary" type="submit">Save Reply</button>
                            <a class="btn btn-secondary" href="manageMessages.php">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
