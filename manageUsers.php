<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

/* ===== SECURITY CHECK (Admin only) ===== */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.html");
    exit();
}

/* ===== FETCH ADMIN PROFILE ===== */
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

/* ===== FETCH ADMIN NOTIFICATIONS ===== */
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

/* ===== FETCH USERS ===== */
try {
    $db = DB::connect();
    $hasActive = columnExists($db, 'users', 'is_active');
    $where = $hasActive ? "WHERE is_active = 1" : "";
    $sql = "SELECT user_id, full_name, email, created_at FROM users {$where} ORDER BY user_id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | FabricSense Admin</title>

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
            --purple: #667eea;
            --purple-dark: #764ba2;
            --text-dark: #333;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--bg-light);
            overflow-x: hidden;
        }

         /* ─── Header ─── */
        .admin-header {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            color: white;
            padding: 1.2rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .admin-logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #ffd6df 0%, #ff5a7a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }


        .admin-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .notification-wrapper {
            position: relative;
        }

        .notification-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }

        .notification-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--accent);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            border: 2px solid #1a1a2e;
        }

        .notification-panel {
            position: absolute;
            top: 52px;
            right: 0;
            width: 320px;
            background: #fff;
            color: var(--text-dark);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.18);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.2s ease;
            z-index: 1200;
            overflow: hidden;
        }

        .notification-panel.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-header {
            padding: 1rem 1.2rem;
            font-weight: 700;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            max-height: 280px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--primary);
            font-size: 0.92rem;
        }

        .notification-message {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.35rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #999;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.4rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            backdrop-filter: blur(10px);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
        }

        .admin-user:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Delete Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }

        .modal-header {
            padding: 2rem 2rem 1rem;
            text-align: center;
        }

        .warning-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        .modal-header h2 {
            color: var(--text-dark);
            font-size: 1.75rem;
            margin: 0;
            font-weight: 700;
        }

        .modal-body {
            padding: 1rem 2rem 2rem;
            text-align: center;
        }

        .modal-body p {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }

        .modal-footer {
            padding: 1.5rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 130px;
        }

        .btn-delete-confirm {
            background: linear-gradient(135deg, #e94560, #ff6b81);
            color: white;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .btn-delete-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4);
        }

        .btn-cancel {
            background: #e9ecef;
            color: #495057;
        }

        .btn-cancel:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logout-btn {
            padding: 0.7rem 1.8rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .logout-btn:hover {
            background: var(--accent-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4);
        }

        /* ─── Dashboard Container ─── */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 76px);
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ─── Sidebar ─── */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 4px 0 20px rgba(0,0,0,0.06);
            padding: 2rem 0;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
            padding: 0 1rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 0 4px 4px 0;
            transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.08), rgba(255, 107, 129, 0.08));
            color: var(--accent);
        }

        .sidebar-menu a:hover::before,
        .sidebar-menu a.active::before {
            height: 60%;
        }

        .menu-icon {
            font-size: 1.4rem;
            width: 28px;
            text-align: center;
        }

        /* ===== MAIN CONTENT ===== */
        .content {
            flex: 1;
            padding: 30px;
        }

        h1 {
            margin-bottom: 20px;
        }

        /* ===== TABLE ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        th, td {
            padding: 15px;
            text-align: center;
        }

        th {
            background: #2d2a63;
            color: white;
        }

        tr:nth-child(even) {
            background: #f2f2f2;
        }

        /* ===== BUTTONS ===== */
        .delete-btn {
            background: #ff4d4d;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #e60000;
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-content h2 {
            margin-top: 0;
            color: #333;
        }

        .modal-content p {
            color: #666;
            margin: 15px 0 25px 0;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-confirm, .btn-cancel {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-confirm {
            background: #ff4d4d;
            color: white;
        }

        .btn-confirm:hover {
            background: #e60000;
        }

        .btn-cancel {
            background: #ddd;
            color: #333;
        }

        .btn-cancel:hover {
            background: #ccc;
        }

        /* ===== SEARCH BAR ===== */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: #ff5a7a;
            box-shadow: 0 0 0 3px rgba(255, 90, 122, 0.1);
        }

        .search-icon {
            font-size: 18px;
            color: #666;
        }

        .highlight {
            background-color: #ffeb3b;
            font-weight: bold;
            padding: 2px 0;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .alert-success {
            background: #e7f7ee;
            color: #1e7a4a;
            border: 1px solid #bfe7d0;
        }
    </style>
</head>

<body>

     <!-- Admin Header -->
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

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="adminDashboard.php"><span class="menu-icon">📊</span> Dashboard</a></li>
                <li><a href="manageUsers.php" class="active"><span class="menu-icon">👥</span> User Management</a></li>
                <li><a href="adminFabricTypes.php"><span class="menu-icon">🧵</span> Fabric Types</a></li>
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="content">
        <h1>Manage Users</h1>

        <?php if (($_GET['status'] ?? '') === 'deactivated'): ?>
            <div class="alert alert-success">User deactivated successfully.</div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-container">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" class="search-box" placeholder="Search by name or email..." onkeyup="searchUsers()">
        </div>

        <table id="usersTable">
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Registered At</th>
                <th>Action</th>
            </tr>

            <?php if (count($users) > 0) { ?>
                <?php foreach ($users as $row) { ?>
                    <tr class="user-row">
                        <td><?= $row["user_id"] ?></td>
                        <td class="user-name"><?= htmlspecialchars($row["full_name"]) ?></td>
                        <td class="user-email"><?= htmlspecialchars($row["email"]) ?></td>
                        <td><?= $row["created_at"] ?></td>
                        <td>
                            <button class="delete-btn" onclick="openDeleteModal(<?= $row['user_id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')">Deactivate</button>
                        </td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="5">No users found</td>
                </tr>
            <?php } ?>
        </table>

        <div id="noResults" class="no-results" style="display: none;">
            No users found matching your search.
        </div>

    </div>
    </div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h2>⚠️ Deactivate User</h2>
        <p>Are you sure you want to deactivate <strong id="userName"></strong>? This will set the account to inactive.</p>
        <div class="modal-buttons">
            <button class="btn-confirm" onclick="confirmDelete()">Yes, Deactivate</button>
            <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
    let userIdToDelete = null;

    function openDeleteModal(userId, userName) {
        userIdToDelete = userId;
        document.getElementById('userName').textContent = userName;
        document.getElementById('deleteModal').classList.add('show');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        userIdToDelete = null;
    }

    function confirmDelete() {
        if (userIdToDelete === null) return;

        // Send delete request
        fetch(`deleteUser.php?id=${userIdToDelete}`)
            .then(response => {
                if (response.ok) {
                    window.location.href = 'manageUsers.php?status=deactivated';
                } else {
                    alert('Error deleting user');
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            closeDeleteModal();
        }
    }

    // Search functionality with highlighting
    function searchUsers() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase().trim();
        const table = document.getElementById('usersTable');
        const rows = table.getElementsByClassName('user-row');
        const noResults = document.getElementById('noResults');
        
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            const nameCell = rows[i].getElementsByClassName('user-name')[0];
            const emailCell = rows[i].getElementsByClassName('user-email')[0];
            
            // Get original text
            const nameText = nameCell.textContent || nameCell.innerText;
            const emailText = emailCell.textContent || emailCell.innerText;
            
            // Check if search term matches
            const nameMatch = nameText.toLowerCase().indexOf(filter) > -1;
            const emailMatch = emailText.toLowerCase().indexOf(filter) > -1;
            
            if (nameMatch || emailMatch || filter === '') {
                rows[i].style.display = '';
                visibleCount++;
                
                // Highlight matching text
                if (filter !== '') {
                    if (nameMatch) {
                        nameCell.innerHTML = highlightText(nameText, filter);
                    } else {
                        nameCell.textContent = nameText;
                    }
                    
                    if (emailMatch) {
                        emailCell.innerHTML = highlightText(emailText, filter);
                    } else {
                        emailCell.textContent = emailText;
                    }
                } else {
                    // Reset highlighting when search is empty
                    nameCell.textContent = nameText;
                    emailCell.textContent = emailText;
                }
            } else {
                rows[i].style.display = 'none';
            }
        }

        // Show/hide no results message
        if (visibleCount === 0 && filter !== '') {
            noResults.style.display = 'block';
            table.style.display = 'none';
        } else {
            noResults.style.display = 'none';
            table.style.display = 'table';
        }
    }

    // Function to highlight matching text
    function highlightText(text, query) {
        if (!query) return text;
        
        const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
    }

    // Escape special regex characters
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

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
