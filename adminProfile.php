<?php
session_start();
include 'DB.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.html');
    exit();
}

$successMessage = '';
$errorMessage = '';
$currentEmail = $_SESSION['admin_email'] ?? 'admin123@gmail.com';
$db = null;

try {
    $db = DB::connect();
    $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(25) DEFAULT NULL,
        role VARCHAR(50) DEFAULT 'Administrator',
        address TEXT DEFAULT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    $errorMessage = 'Database connection failed. Please verify DB.php credentials.';
}

$admin = [
    'admin_id' => null,
    'full_name' => 'Administrator',
    'email' => $currentEmail,
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'phone' => '',
    'role' => 'Administrator',
    'address' => '',
    'profile_image' => null,
    'created_at' => date('Y-m-d H:i:s')
];


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

if ($db) {
    try {
        $stmt = $db->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $currentEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $admin = $row;
        } else {
            $stmt = $db->prepare('INSERT INTO admin_users (full_name, email, password, role) VALUES (:name, :email, :pwd, :role)');
            $stmt->execute([
                'name' => 'Administrator',
                'email' => $currentEmail,
                'pwd' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'Administrator'
            ]);
            $stmt = $db->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $currentEmail]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $errorMessage = 'Unable to load admin profile. ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $db) {
    try {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'Administrator');
        $address = trim($_POST['address'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($fullName === '' || $email === '') {
            throw new Exception('Full name and email are required.');
        }

        $profileImage = $admin['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/admin/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                throw new Exception('Only JPG, JPEG, PNG, and GIF images are allowed.');
            }
            $fileName = 'admin_' . time() . '.' . $ext;
            $target = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                throw new Exception('Failed to upload profile image.');
            }
            if ($admin['profile_image'] && file_exists($admin['profile_image'])) {
                @unlink($admin['profile_image']);
            }
            $profileImage = $target;
        } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception('Error uploading profile image.');
        }

        $stmt = $db->prepare('UPDATE admin_users SET full_name = :name, email = :email, phone = :phone, role = :role, address = :address, profile_image = :image WHERE admin_id = :id');
        $stmt->execute([
            'name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'address' => $address,
            'image' => $profileImage,
            'id' => $admin['admin_id']
        ]);

        if ($newPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match.');
            }
            if (!password_verify($currentPassword, $admin['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE admin_users SET password = :pwd WHERE admin_id = :id');
            $stmt->execute(['pwd' => $hashed, 'id' => $admin['admin_id']]);
        }

        $_SESSION['admin_email'] = $email;
        $successMessage = 'Profile updated successfully.';

        $stmt = $db->prepare('SELECT * FROM admin_users WHERE admin_id = :id');
        $stmt->execute(['id' => $admin['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - FabricSense</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a2e;
            --accent: #ff5a7a;
            --accent-light: #ffd6df;
            --text-dark: #333;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        header { background:linear-gradient(135deg,#0f0c29,#302b63,#24243e); color:#fff; padding:1.2rem 0; box-shadow:0 4px 20px rgba(0,0,0,0.15); position:sticky; top:0; z-index:1000; }
        .admin-header { background:linear-gradient(135deg,#0f0c29,#302b63,#24243e); color:#fff; padding:1.2rem 0; box-shadow:0 4px 20px rgba(0,0,0,0.15); position:sticky; top:0; z-index:1000; }
        .header-content { max-width:1600px; margin:0 auto; padding:0 2rem; display:flex; justify-content:space-between; align-items:center; }
        .admin-logo { display:flex; align-items:center; gap:1rem; }
        .admin-logo-text { font-family:'Playfair Display', serif; font-size:1.8rem; font-weight:900; background:linear-gradient(135deg,var(--accent-light) 0%,var(--accent) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; letter-spacing:-1px; }
        .admin-info { display:flex; align-items:center; gap:2rem; }
        .notification-wrapper { position:relative; }
        .notification-btn { width:42px; height:42px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s ease; box-shadow:0 4px 12px rgba(0,0,0,0.15); font-size:1.2rem; }
        .notification-btn:hover { background:rgba(255,255,255,0.25); transform:translateY(-1px); }
        .notification-count { position:absolute; top:-4px; right:-4px; background:var(--accent); color:#fff; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; border:2px solid var(--primary); }
        .notification-panel { position:absolute; top:52px; right:0; width:320px; background:#fff; color:#212121; border-radius:14px; box-shadow:0 18px 40px rgba(0,0,0,0.18); opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.2s ease; z-index:1200; overflow:hidden; }
        .notification-panel.open { opacity:1; visibility:visible; transform:translateY(0); }
        .notification-header { padding:1rem 1.2rem; font-weight:700; border-bottom:1px solid #f0f0f0; }
        .notification-list { max-height:280px; overflow-y:auto; }
        .notification-item { padding:0.9rem 1.2rem; border-bottom:1px solid #f5f5f5; }
        .notification-item:last-child { border-bottom:none; }
        .notification-title { font-weight:700; margin-bottom:0.25rem; color:var(--primary); font-size:0.92rem; }
        .notification-message { font-size:0.85rem; color:#666; margin-bottom:0.35rem; }
        .notification-time { font-size:0.75rem; color:#999; }
        .admin-user { display:flex; align-items:center; gap:0.8rem; padding:0.4rem; background:rgba(255,255,255,0.1); border-radius:50%; cursor:pointer; transition:all 0.3s ease; border:2px solid transparent; text-decoration:none; }
        .admin-user:hover { background:rgba(255,255,255,0.2); border-color:rgba(255,255,255,0.3); transform:scale(1.05); }
        .admin-avatar { width:40px; height:40px; background:linear-gradient(135deg,var(--accent),var(--accent-light)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
        .admin-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        .logout-btn { padding:0.7rem 1.8rem; background:var(--accent); color:#fff; border:none; border-radius:50px; cursor:pointer; font-weight:700; font-size:0.95rem; transition:all 0.3s; box-shadow:0 4px 15px rgba(255,90,122,0.3); }
        .logout-btn:hover { background:var(--accent-light); transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,90,122,0.4); }
        .dashboard-container { display:flex; min-height:calc(100vh - 76px); }
        .sidebar { width:280px; background:white; box-shadow:4px 0 20px rgba(0,0,0,0.06); padding:2rem 0; }
        .sidebar-menu { list-style:none; }
        .sidebar-menu li { margin-bottom:0.5rem; padding:0 1rem; }
        .sidebar-menu a { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; color:var(--text-dark); text-decoration:none; font-weight:600; border-radius:12px; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position:relative; }
        .sidebar-menu a::before { content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:4px; height:0; background:linear-gradient(135deg, var(--accent), var(--accent-light)); border-radius:0 4px 4px 0; transition:height 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-menu a:hover, .sidebar-menu a.active { background:linear-gradient(135deg, rgba(233, 69, 96, 0.08), rgba(255, 107, 129, 0.08)); color:var(--accent); }
        .sidebar-menu a:hover::before, .sidebar-menu a.active::before { height:60%; }
        .menu-icon { font-size:1.4rem; width:28px; text-align:center; }
        main { flex:1; margin:2rem; padding:0; max-width:none; }
        .breadcrumb { display:flex; align-items:center; gap:0.5rem; margin-bottom:1.5rem; font-size:0.95rem; }
        .breadcrumb a { color:var(--accent); text-decoration:none; font-weight:600; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb span { color:#8d99ae; }
        .page-title { font-family:'Playfair Display', serif; font-size:2rem; margin:0 0 0.5rem 0; color:var(--primary); }
        .card { background:#fff; border-radius:20px; box-shadow:0 15px 45px rgba(15,12,41,0.08); overflow:hidden; }
        .card-header { display:flex; justify-content:space-between; align-items:center; padding:1.8rem; border-bottom:1px solid #f0f0f0; }
        .profile-summary { display:flex; align-items:center; gap:1.2rem; }
        .avatar { width:90px; height:90px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent-light)); display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:#fff; overflow:hidden; border:4px solid #fff; box-shadow:0 6px 18px rgba(255,90,122,0.3); }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .summary-text h2 { margin:0; font-family:'Playfair Display', serif; }
        .role-pill { display:inline-block; padding:0.2rem 0.8rem; border-radius:999px; background:var(--accent-light); color:#d81b60; font-size:0.85rem; font-weight:700; }
        .card-body { padding:1.8rem; display:grid; gap:2rem; }
        .alert { padding:0.9rem 1.1rem; border-radius:10px; font-weight:600; }
        .alert.success { background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9; }
        .alert.error { background:#ffebee; color:#b71c1c; border:1px solid #ffcdd2; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; }
        .info-tile { background:#f9fafc; border:1px solid #eef0f6; border-radius:14px; padding:1rem; }
        .info-label { font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; color:#8d99ae; margin-bottom:0.4rem; }
        form input, form textarea { width:100%; padding:0.85rem 1rem; border:2px solid #e3e7f0; border-radius:10px; font-size:1rem; font-family:'DM Sans', sans-serif; }
        form input:focus, form textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(255,90,122,0.15); }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem; }
        .actions { display:flex; gap:1rem; flex-wrap:wrap; }
        .btn-primary { background:linear-gradient(135deg,var(--accent),#ff8aa0); color:#fff; border:none; padding:0.9rem 2rem; border-radius:10px; font-weight:700; cursor:pointer; }
        .btn-secondary { background:#f0f2f8; color:#4a4a4a; border:none; padding:0.9rem 2rem; border-radius:10px; font-weight:600; cursor:pointer; }
        @media (max-width:600px){ .card-header { flex-direction:column; align-items:flex-start; gap:1rem; } }
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
                        <?php if ($admin['profile_image'] && file_exists($admin['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Admin">
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
                <li><a href="#adminProfile.php" class="active"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>
        <main>
           
            <h1 class="page-title">Admin Profile</h1>
        <div class="card">
            <div class="card-header">
                <div class="profile-summary">
                    <div class="avatar">
                        <?php if (!empty($admin['profile_image']) && file_exists($admin['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                    </div>
                    <div class="summary-text">
                        <h2><?php echo htmlspecialchars($admin['full_name']); ?></h2>
                        <div><?php echo htmlspecialchars($admin['email']); ?></div>
                        <div class="role-pill"><?php echo htmlspecialchars($admin['role'] ?? 'Administrator'); ?></div>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.85rem; color:#8d99ae;">Member since</div>
                    <strong><?php echo htmlspecialchars(date('Y-m-d', strtotime($admin['created_at']))); ?></strong>
                </div>
            </div>
            <div class="card-body">
                <?php if ($successMessage): ?><div class="alert success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
                <?php if ($errorMessage): ?><div class="alert error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>

                <div class="info-grid">
                    <div class="info-tile">
                        <div class="info-label">Phone</div>
                        <div><?php echo $admin['phone'] ? htmlspecialchars($admin['phone']) : 'Not provided'; ?></div>
                    </div>
                    <div class="info-tile">
                        <div class="info-label">Address</div>
                        <div><?php echo $admin['address'] ? htmlspecialchars($admin['address']) : 'Not provided'; ?></div>
                    </div>
                    <div class="info-tile">
                        <div class="info-label">Last Login</div>
                        <div><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <h3>Update Profile</h3>
                    <div class="form-grid">
                        <div>
                            <label>Profile Picture</label>
                            <input type="file" name="profile_image" accept="image/*">
                        </div>
                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Role</label>
                            <input type="text" name="role" value="<?php echo htmlspecialchars($admin['role'] ?? 'Administrator'); ?>">
                        </div>
                    </div>
                    <div style="margin-top:1rem;">
                        <label>Address</label>
                        <textarea name="address" rows="3"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                    </div>

                    <h3 style="margin-top:2rem;">Change Password</h3>
                    <div class="form-grid">
                        <div>
                            <label>Current Password</label>
                            <input type="password" name="current_password">
                        </div>
                        <div>
                            <label>New Password</label>
                            <input type="password" name="new_password">
                        </div>
                        <div>
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password">
                        </div>
                    </div>

                    <div class="actions" style="margin-top:1.5rem;">
                        <button type="submit" name="update_profile" class="btn-primary">Save Changes</button>
                        <button type="button" class="btn-secondary" onclick="window.location.href='adminDashboard.php'">Cancel</button>
                    </div>
                </form>
            </div>
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
                notificationPanel.classList.toggle('open');
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
