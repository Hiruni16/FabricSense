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

/* Fetch fabric estimates */
try {
    $db = DB::connect();
    $hasActive = columnExists($db, 'fabric_estimates', 'is_active');
    $estimateFilter = $hasActive ? "WHERE fe.is_active = 1" : "";
    $activeWhere = $hasActive ? "WHERE is_active = 1" : "";
    
    // Get all estimates with user information
    $stmt = $db->query("
        SELECT fe.*, u.full_name as user_full_name
        FROM fabric_estimates fe
        LEFT JOIN users u ON fe.user_id = u.user_id
        {$estimateFilter}
        ORDER BY fe.created_at DESC
    ");
    $estimates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates {$activeWhere}");
    $totalEstimates = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->query("SELECT SUM(fabric_length_total) as total FROM fabric_estimates {$activeWhere}");
    $totalFabric = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(DISTINCT fabric_type) as total FROM fabric_estimates {$activeWhere}");
    $uniqueFabrics = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->query("SELECT COUNT(DISTINCT garment_type) as total FROM fabric_estimates {$activeWhere}");
    $uniqueGarments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $estimates = [];
    $totalEstimates = 0;
    $totalFabric = 0;
    $uniqueFabrics = 0;
    $uniqueGarments = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Estimates - FabricSense Admin</title>

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

        /* Header */
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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2.5rem;
            background: var(--bg-light);
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card-small {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card-small:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .stat-icon-small {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .stat-icon-small.red {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
        }

        .stat-icon-small.blue {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .stat-icon-small.green {
            background: linear-gradient(135deg, #43cea2, #185a9d);
        }

        .stat-icon-small.orange {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
        }

        .stat-label-small {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }

        /* Data Section */
        .data-section {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(233, 69, 96, 0.1);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-box {
            padding: 0.8rem 1.5rem;
            border: 2px solid #e1e8ed;
            border-radius: 50px;
            font-size: 0.95rem;
            width: 300px;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        /* Table */
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
            padding: 1.2rem 1rem;
            text-align: left;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid rgba(233, 69, 96, 0.15);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.02), rgba(255, 107, 129, 0.02));
        }

        .fabric-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: var(--purple);
        }

        .garment-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            background: linear-gradient(135deg, rgba(67, 206, 162, 0.1), rgba(24, 90, 157, 0.1));
            color: #185a9d;
        }

        .fabric-amount {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--accent);
        }

        /* Action Button */
        .action-btn {
            padding: 0.5rem 1rem;
            margin: 0 0.3rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: white;
            box-shadow: 0 4px 10px rgba(233, 69, 96, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(233, 69, 96, 0.4);
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #999;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .search-box {
                width: 100%;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 0.8rem 0.5rem;
            }
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

    <div class="dashboard-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="adminDashboard.php"><span class="menu-icon">📊</span> Dashboard</a></li>
                <li><a href="manageUsers.php"><span class="menu-icon">👥</span> User Management</a></li>
                <li><a href="adminFabricTypes.php"><span class="menu-icon">🧵</span> Fabric Types</a></li>
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="#manageEstimates.php" class="active"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
        <h1 class="page-title">Fabric Consumption Estimates</h1>
        <p class="page-subtitle">View and manage all user fabric consumption calculations</p>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card-small">
                <div class="stat-icon-small red">📏</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $totalEstimates; ?></div>
                    <div class="stat-label-small">Total Estimates</div>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small blue">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalFabric, 2); ?> m</div>
                    <div class="stat-label-small">Total Fabric Calculated</div>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small green">🧵</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $uniqueFabrics; ?></div>
                    <div class="stat-label-small">Fabric Types Used</div>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small orange">👕</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $uniqueGarments; ?></div>
                    <div class="stat-label-small">Garment Types</div>
                </div>
            </div>
        </div>

        <!-- Estimates Table -->
        <div class="data-section">
            <div class="section-header">
                <h2 class="section-title">All Fabric Estimates</h2>
                <input type="text" id="searchInput" class="search-box" placeholder="🔍 Search estimates...">
            </div>

            <?php if (empty($estimates)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📏</div>
                    <h3>No Estimates Yet</h3>
                    <p>Fabric consumption estimates will appear here when users calculate their fabric needs.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table id="estimatesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Fabric Type</th>
                                <th>Garment Type</th>
                                <th>Size</th>
                                <th>Width</th>
                                <th>Quantity</th>
                                <th>Per Garment</th>
                                <th>Total Fabric</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estimates as $estimate): ?>
                            <tr>
                                <td><strong>#<?php echo $estimate['id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($estimate['user_full_name'] ?? $estimate['user_name'] ?? 'Unknown User'); ?></strong></td>
                                <td><span class="fabric-badge"><?php echo htmlspecialchars($estimate['fabric_type']); ?></span></td>
                                <td><span class="garment-badge"><?php echo htmlspecialchars($estimate['garment_type']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($estimate['size']); ?></strong></td>
                                <td><?php echo $estimate['fabric_width']; ?>"</td>
                                <td><?php echo $estimate['quantity']; ?></td>
                                <td><?php echo number_format($estimate['fabric_length_per_garment'], 2); ?> m</td>
                                <td><span class="fabric-amount"><?php echo number_format($estimate['fabric_length_total'], 2); ?> m</span></td>
                                <td><?php echo date('M j, Y H:i', strtotime($estimate['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn btn-delete" onclick="deleteEstimate(<?php echo $estimate['id']; ?>)" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Deactivate</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="warning-icon">⚠️</div>
                <h2>Deactivate Estimate</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate this estimate? It will be hidden from active lists.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn btn-delete-confirm" onclick="confirmDelete()">Yes, Deactivate</button>
                <button class="modal-btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Delete estimate modal functions
        let estimateToDelete = null;

        function deleteEstimate(estimateId) {
            estimateToDelete = estimateId;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            estimateToDelete = null;
        }

        async function confirmDelete() {
            if (!estimateToDelete) return;

            try {
                const response = await fetch('deleteEstimate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: estimateToDelete })
                });

                const result = await response.json();

                if (result.success) {
                    closeDeleteModal();
                    // Remove the row with animation
                    const row = document.querySelector(`button[onclick="deleteEstimate(${estimateToDelete})"]`).closest('tr');
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        // Reload page to update stats
                        location.reload();
                    }, 300);
                } else {
                    closeDeleteModal();
                    location.reload();
                }
            } catch (error) {
                closeDeleteModal();
                location.reload();
            }
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#estimatesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 30);
            });
        });

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
</main>
</div>
</body>
</html>
