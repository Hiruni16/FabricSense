<?php
session_start();
include 'DB.php';

/* Dashboard protection */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.html");
    exit();
}

/* Fetch admin profile */
$adminProfile = [
    'profile_image' => null,
    'full_name' => 'Admin'
];
try {
    $db_temp = DB::connect();
    $stmt = $db_temp->prepare("SELECT profile_image, full_name FROM admin_users WHERE email = :email");
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

function columnExists($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

/* Report data */
$totalUsers = 0;
$totalIdentifications = 0;
$totalEstimates = 0;
$totalMessages = 0;
$newMessages = 0;
$newUsers30 = 0;
$estimates30 = 0;
$avgConfidenceDisplay = 'N/A';
$paidEstimates = 0;
$paidIdentifications = 0;

try {
    $db = DB::connect();

    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_identifications");
    $totalIdentifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates");
    $totalEstimates = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages");
    $totalMessages = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
    $newMessages = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newUsers30 = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $estimates30 = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $db->query("SELECT AVG(confidence) as avg_confidence FROM fabric_identifications");
    $avgConfidence = $stmt->fetch(PDO::FETCH_ASSOC)['avg_confidence'] ?? null;
    if (is_numeric($avgConfidence)) {
        $avgConfidence = (float)$avgConfidence;
        if ($avgConfidence <= 1) {
            $avgConfidence *= 100;
        }
        $avgConfidenceDisplay = number_format($avgConfidence, 1) . '%';
    }

    if (columnExists($db, 'fabric_estimates', 'invoice_status')) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates WHERE invoice_status = 'Paid'");
        $paidEstimates = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if (columnExists($db, 'fabric_identifications', 'invoice_status')) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_identifications WHERE invoice_status = 'Paid'");
        $paidIdentifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Exception $e) {
    // Keep defaults
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FabricSense Admin</title>
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

        .admin-logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
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
            padding: 2.5rem 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-family: 'Playfair Display', serif;
        }

        .page-subtitle {
            color: #666;
            margin-bottom: 2.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stat-title {
            color: #666;
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #43cea2, #185a9d);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #999;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .data-section {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 2.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(233, 69, 96, 0.1);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .reports-actions {
            display: flex;
            gap: 0.8rem;
        }

        .report-btn {
            padding: 0.6rem 1.4rem;
            background: white;
            color: var(--accent);
            border: 2px solid rgba(233, 69, 96, 0.3);
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .report-btn:hover {
            background: rgba(233, 69, 96, 0.08);
            border-color: rgba(233, 69, 96, 0.6);
            transform: translateY(-1px);
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .report-card {
            display: flex;
            gap: 1rem;
            padding: 1.4rem;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(233, 69, 96, 0.08);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.1);
        }

        .report-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.15), rgba(255, 107, 129, 0.2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .report-title {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.35rem;
        }

        .report-desc {
            color: #777;
            font-size: 0.92rem;
            margin-bottom: 0.7rem;
        }

        .report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .report-pill {
            padding: 0.25rem 0.7rem;
            background: rgba(67, 206, 162, 0.12);
            color: #1c7c62;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .report-pill.alt {
            background: rgba(102, 126, 234, 0.12);
            color: #4b5cc4;
        }

        .report-pill.warn {
            background: rgba(243, 156, 18, 0.15);
            color: #b36b00;
        }

        @media (max-width: 1024px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 1rem 0;
            }

            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding: 0 1rem;
            }

            .sidebar-menu li {
                margin-bottom: 0;
                margin-right: 0.5rem;
                padding: 0;
            }

            .sidebar-menu a {
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 1.5rem;
            }
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
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php" class="active"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Live admin metrics and usage insights from your FabricSense data.</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Users</span>
                        <div class="stat-icon purple">👥</div>
                    </div>
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">All registered users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Identifications</span>
                        <div class="stat-icon orange">🧵</div>
                    </div>
                    <div class="stat-number"><?php echo $totalIdentifications; ?></div>
                    <div class="stat-label">Fabric identifications run</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Estimates</span>
                        <div class="stat-icon green">📏</div>
                    </div>
                    <div class="stat-number"><?php echo $totalEstimates; ?></div>
                    <div class="stat-label">Fabric estimates created</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Messages</span>
                        <div class="stat-icon red">📧</div>
                    </div>
                    <div class="stat-number"><?php echo $totalMessages; ?></div>
                    <div class="stat-label"><?php echo $newMessages; ?> new messages</div>
                </div>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Reports Overview</h2>
                    <div class="reports-actions">
                        <button class="report-btn" id="downloadSummaryBtn" type="button">Download Summary</button>
                        <button class="report-btn" id="exportCsvBtn" type="button">Export CSV</button>
                    </div>
                </div>
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-icon">👥</div>
                        <div>
                            <div class="report-title">User Growth</div>
                            <div class="report-desc">Signups and activity trends across the last month.</div>
                            <div class="report-meta">
                                <span class="report-pill">+<?php echo $newUsers30; ?> in 30 days</span>
                                <span class="report-pill alt">Total: <?php echo $totalUsers; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">🧵</div>
                        <div>
                            <div class="report-title">Identification Accuracy</div>
                            <div class="report-desc">Average confidence across recorded identifications.</div>
                            <div class="report-meta">
                                <span class="report-pill">Avg: <?php echo $avgConfidenceDisplay; ?></span>
                                <span class="report-pill alt">Total: <?php echo $totalIdentifications; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">📏</div>
                        <div>
                            <div class="report-title">Estimation Usage</div>
                            <div class="report-desc">Estimates generated and activity in the last month.</div>
                            <div class="report-meta">
                                <span class="report-pill">+<?php echo $estimates30; ?> in 30 days</span>
                                <span class="report-pill alt">Total: <?php echo $totalEstimates; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">💳</div>
                        <div>
                            <div class="report-title">Revenue & Billing</div>
                            <div class="report-desc">Paid invoices from estimates and identifications.</div>
                            <div class="report-meta">
                                <span class="report-pill">Paid estimates: <?php echo $paidEstimates; ?></span>
                                <span class="report-pill alt">Paid IDs: <?php echo $paidIdentifications; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">📧</div>
                        <div>
                            <div class="report-title">Support & Messages</div>
                            <div class="report-desc">Message volume and unresolved inquiries.</div>
                            <div class="report-meta">
                                <span class="report-pill warn">New: <?php echo $newMessages; ?></span>
                                <span class="report-pill alt">Total: <?php echo $totalMessages; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">🛠️</div>
                        <div>
                            <div class="report-title">System Health</div>
                            <div class="report-desc">Admin notifications and model stability signals.</div>
                            <div class="report-meta">
                                <span class="report-pill">Unread: <?php echo $unreadCount; ?></span>
                                <span class="report-pill alt">Alerts: <?php echo count($notificationItems); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                sessionStorage.removeItem('adminLoggedIn');
                window.location.href = 'adminLogin.html';
            }
        }

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

        const reportStats = {
            totalUsers: <?php echo (int)$totalUsers; ?>,
            totalIdentifications: <?php echo (int)$totalIdentifications; ?>,
            totalEstimates: <?php echo (int)$totalEstimates; ?>,
            totalMessages: <?php echo (int)$totalMessages; ?>,
            newMessages: <?php echo (int)$newMessages; ?>,
            newUsers30: <?php echo (int)$newUsers30; ?>,
            estimates30: <?php echo (int)$estimates30; ?>,
            avgConfidence: "<?php echo htmlspecialchars($avgConfidenceDisplay); ?>",
            paidEstimates: <?php echo (int)$paidEstimates; ?>,
            paidIdentifications: <?php echo (int)$paidIdentifications; ?>,
            unreadNotifications: <?php echo (int)$unreadCount; ?>,
            alertCount: <?php echo (int)count($notificationItems); ?>
        };

        function downloadText(filename, text) {
            const blob = new Blob([text], { type: 'text/plain' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        }

        function downloadCsv(filename, rows) {
            const csv = rows.map(row => row.map(value => {
                const text = String(value ?? '');
                return '"' + text.replace(/"/g, '""') + '"';
            }).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        }

        function buildSummaryText(stats) {
            return [
                'FabricSense Reports Summary',
                'Generated: ' + new Date().toLocaleString(),
                '',
                'Overview',
                'Total users: ' + stats.totalUsers,
                'Total identifications: ' + stats.totalIdentifications,
                'Total estimates: ' + stats.totalEstimates,
                'Total messages: ' + stats.totalMessages,
                'New messages: ' + stats.newMessages,
                '',
                '30-day Activity',
                'New users (30 days): ' + stats.newUsers30,
                'Estimates (30 days): ' + stats.estimates30,
                '',
                'Quality & Revenue',
                'Average confidence: ' + stats.avgConfidence,
                'Paid estimates: ' + stats.paidEstimates,
                'Paid identifications: ' + stats.paidIdentifications,
                '',
                'System Health',
                'Unread notifications: ' + stats.unreadNotifications,
                'Alerts: ' + stats.alertCount
            ].join('\n');
        }

        const downloadSummaryBtn = document.getElementById('downloadSummaryBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');

        if (downloadSummaryBtn) {
            downloadSummaryBtn.addEventListener('click', function() {
                const summary = buildSummaryText(reportStats);
                const filename = 'fabricSense-report-summary.txt';
                downloadText(filename, summary);
            });
        }

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', function() {
                const rows = [
                    ['Metric', 'Value'],
                    ['Total users', reportStats.totalUsers],
                    ['Total identifications', reportStats.totalIdentifications],
                    ['Total estimates', reportStats.totalEstimates],
                    ['Total messages', reportStats.totalMessages],
                    ['New messages', reportStats.newMessages],
                    ['New users (30 days)', reportStats.newUsers30],
                    ['Estimates (30 days)', reportStats.estimates30],
                    ['Average confidence', reportStats.avgConfidence],
                    ['Paid estimates', reportStats.paidEstimates],
                    ['Paid identifications', reportStats.paidIdentifications],
                    ['Unread notifications', reportStats.unreadNotifications],
                    ['Alerts', reportStats.alertCount]
                ];
                const filename = 'fabricSense-report-metrics.csv';
                downloadCsv(filename, rows);
            });
        }
    </script>
</body>
</html>
