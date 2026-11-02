<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
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

/* Fetch fabric identifications */
try {
    $db = DB::connect();
    $hasActive = columnExists($db, 'fabric_identifications', 'is_active');
    $identFilter = $hasActive ? "WHERE fi.is_active = 1" : "";
    $activeWhere = $hasActive ? "WHERE is_active = 1" : "";
    
    // Get all identifications with user information
    $stmt = $db->query("
        SELECT 
            fi.*,
            u.full_name as user_name,
            u.email as user_email
        FROM fabric_identifications fi
        LEFT JOIN users u ON fi.user_id = u.user_id
        {$identFilter}
        ORDER BY fi.created_at DESC
    ");
    $identifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(confidence) as avg_confidence,
            fabric_type,
            COUNT(*) as type_count
        FROM fabric_identifications
        {$activeWhere}
        GROUP BY fabric_type
        ORDER BY type_count DESC
    ");
    $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $totalStmt = $db->query("SELECT COUNT(*) as total FROM fabric_identifications {$activeWhere}");
    $totalCount = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get average confidence
    $avgStmt = $db->query("SELECT AVG(confidence) as avg_conf FROM fabric_identifications {$activeWhere}");
    $avgConfidence = $avgStmt->fetch(PDO::FETCH_ASSOC)['avg_conf'];
    
} catch (Exception $e) {
    $identifications = [];
    $stats = [];
    $totalCount = 0;
    $avgConfidence = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fabric Identifications - Admin Dashboard</title>
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

        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 76px);
            max-width: 1600px;
            margin: 0 auto;
        }

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

        .main-content {
            flex: 1;
            padding: 2.5rem;
            background: var(--bg-light);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .data-section {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(233, 69, 96, 0.1);
        }

        .search-container {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-family: 'DM Sans', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.2rem;
        }

        .search-results-info {
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .highlight {
            background-color: #fff59d;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.05), rgba(255, 107, 129, 0.05));
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid rgba(233, 69, 96, 0.15);
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: rgba(233, 69, 96, 0.02);
        }

        .confidence-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .confidence-high {
            background: linear-gradient(135deg, rgba(67, 206, 162, 0.15), rgba(24, 90, 157, 0.15));
            color: #185a9d;
        }

        .confidence-medium {
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.15), rgba(230, 126, 34, 0.15));
            color: #e67e22;
        }

        .confidence-low {
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.15), rgba(255, 107, 129, 0.15));
            color: var(--accent);
        }

        .fabric-type {
            font-weight: 700;
            color: var(--primary);
        }

        .btn-delete {
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(233, 69, 96, 0.3);
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

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 16px;
            width: 92%;
            max-width: 460px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.2s ease;
        }

        .modal-header {
            padding: 1.8rem 2rem 1rem;
            text-align: center;
        }

        .modal-header h2 {
            margin: 0.3rem 0 0;
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 0 2rem 1.6rem;
            text-align: center;
            color: #666;
        }

        .modal-footer {
            padding: 0 2rem 2rem;
            display: flex;
            gap: 0.8rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.8rem 1.6rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-confirm {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: #fff;
        }

        .btn-confirm:hover {
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: #e9ecef;
            color: #495057;
        }

        .btn-cancel:hover {
            background: #dee2e6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
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
                <li><a href="#manageIdentifications.php" class="active"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="container">
                <h1 class="page-title">Fabric Identifications Management</h1>

                <?php if (($_GET['status'] ?? '') === 'deactivated'): ?>
                    <div class="alert alert-success">Identification deactivated successfully.</div>
                <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalCount); ?></div>
                <div class="stat-label">Total Identifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($avgConfidence, 1); ?>%</div>
                <div class="stat-label">Average Confidence</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($stats); ?></div>
                <div class="stat-label">Fabric Types Identified</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    if (!empty($stats)) {
                        echo htmlspecialchars($stats[0]['fabric_type']);
                    } else {
                        echo "N/A";
                    }
                ?></div>
                <div class="stat-label">Most Identified</div>
            </div>
        </div>

        <!-- Identifications Table -->
        <div class="data-section">
            <h2 class="section-title">All Identifications</h2>
            
            <!-- Search Bar -->
            <div class="search-container">
                <span class="search-icon">🔍</span>
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input" 
                    placeholder="Search by user name, email, fabric type, or image filename..."
                    onkeyup="searchTable()"
                >
                <div id="searchResults" class="search-results-info"></div>
            </div>

            <div class="table-wrapper">
                <table id="identificationsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Fabric Type</th>
                            <th>Confidence</th>
                            <th>Image Filename</th>
                            <th>Texture Score</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($identifications)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🔍</div>
                                        <p>No fabric identifications yet. Identifications will appear here when users upload and identify fabric images.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($identifications as $item): ?>
                            <tr>
                                <td><strong>#<?php echo $item['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['user_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($item['user_email']); ?></small>
                                </td>
                                <td><span class="fabric-type"><?php echo htmlspecialchars($item['fabric_type']); ?></span></td>
                                <td>
                                    <?php 
                                    $conf = $item['confidence'];
                                    $confClass = $conf >= 90 ? 'confidence-high' : ($conf >= 80 ? 'confidence-medium' : 'confidence-low');
                                    ?>
                                    <span class="confidence-badge <?php echo $confClass; ?>">
                                        <?php echo number_format($conf, 1); ?>%
                                    </span>
                                </td>
                                <td><?php echo $item['image_filename'] ? htmlspecialchars($item['image_filename']) : '<em style="color: #ccc;">N/A</em>'; ?></td>
                                <td><?php echo $item['texture_score'] ? number_format($item['texture_score'], 1) : 'N/A'; ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <button class="btn-delete" onclick="openDeactivateModal(<?php echo $item['id']; ?>)">Deactivate</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fabric Types Statistics -->
        <div class="data-section">
            <h2 class="section-title">Fabric Types Statistics</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fabric Type</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 2rem; color: #999;">No data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['fabric_type']); ?></strong></td>
                                <td><?php echo $stat['type_count']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = ($stat['type_count'] / $totalCount) * 100;
                                    echo number_format($percentage, 1) . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div>
    </main>
</div>

    <div id="deactivateModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="deactivateTitle">
            <div class="modal-header">
                <div class="warning-icon">⚠️</div>
                <h2 id="deactivateTitle">Deactivate Identification</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate this identification record? It will be hidden from active lists.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn btn-confirm" type="button" onclick="confirmDeactivate()">Yes, Deactivate</button>
                <button class="modal-btn btn-cancel" type="button" onclick="closeDeactivateModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('identificationsTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = tbody.getElementsByTagName('tr');
            let visibleCount = 0;
            
            // Clear previous highlights
            clearHighlights();
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                
                // Skip empty state row
                if (row.querySelector('.empty-state')) {
                    continue;
                }
                
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                // Search in User, Fabric Type, and Image Filename columns
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    
                    if (cellText.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        
                        // Highlight matching text
                        if (filter.length > 0) {
                            highlightText(cells[j], filter);
                        }
                    }
                }
                
                // Show/hide row based on search
                if (found || filter === '') {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }
            
            // Update search results info
            const resultsInfo = document.getElementById('searchResults');
            if (filter.length > 0) {
                resultsInfo.textContent = `Found ${visibleCount} result${visibleCount !== 1 ? 's' : ''}`;
            } else {
                resultsInfo.textContent = '';
            }
        }
        
        function highlightText(element, searchText) {
            // Skip if element has children (like buttons)
            if (element.querySelector('button') || element.querySelector('span.confidence-badge')) {
                return;
            }
            
            const text = element.textContent;
            const regex = new RegExp(`(${escapeRegex(searchText)})`, 'gi');
            
            if (regex.test(text)) {
                const highlightedText = text.replace(regex, '<span class="highlight">$1</span>');
                element.innerHTML = highlightedText;
            }
        }
        
        function clearHighlights() {
            const highlights = document.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
                parent.normalize();
            });
        }
        
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

        let identificationToDeactivate = null;

        function openDeactivateModal(id) {
            identificationToDeactivate = id;
            document.getElementById('deactivateModal').classList.add('active');
        }

        function closeDeactivateModal() {
            document.getElementById('deactivateModal').classList.remove('active');
            identificationToDeactivate = null;
        }

        function confirmDeactivate() {
            if (!identificationToDeactivate) return;

            fetch('deleteFabricIdentification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: identificationToDeactivate })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'manageIdentifications.php?status=deactivated';
                } else {
                    closeDeactivateModal();
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                closeDeactivateModal();
                alert('Error deactivating identification');
                console.error('Error:', error);
            });
        }

        document.getElementById('deactivateModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeactivateModal();
            }
        });
    </script>
</body>
</html>
