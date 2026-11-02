<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

$ADMIN_EMAIL = "admin123@gmail.com";
$ADMIN_PASSWORD = "admin123";

/* Login check */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email === $ADMIN_EMAIL && $password === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        header("Location: adminDashboard.php");
        exit();
    } else {
        header("Location: adminLogin.html?error=1");
        exit();
    }
}

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

/* Fetch dashboard stats */
try {
    $db = DB::connect();
    $hasUsersActive = columnExists($db, 'users', 'is_active');
    $hasEstimatesActive = columnExists($db, 'fabric_estimates', 'is_active');
    $hasIdentificationsActive = columnExists($db, 'fabric_identifications', 'is_active');
    
    // Get total users count
    $userWhere = $hasUsersActive ? "WHERE is_active = 1" : "";
    $stmt = $db->query("SELECT COUNT(*) as total FROM users {$userWhere}");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get fabric types count (from label encoders/model data)
    // Since fabric types are stored in the ML model, we'll get unique types from a fabrics table if it exists
    try {
        $stmt = $db->query("SELECT COUNT(DISTINCT fabric_type) as total FROM fabrics");
        $totalFabricTypes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        // If table doesn't exist, get count from the ML model label encoders
        $totalFabricTypes = 9; // Default from create_demo_model.py
        
        // Try to read from label encoders file
        if (file_exists('label_encoders.pkl')) {
            try {
                // Count fabric types from Python
                $output = shell_exec('C:/xampp/htdocs/FabricSense/.venv/Scripts/python.exe -c "import pickle; f=open(\'label_encoders.pkl\',\'rb\'); encoders=pickle.load(f); print(len(encoders[\'fabric_type\'].classes_)); f.close()"');
                if ($output && is_numeric(trim($output))) {
                    $totalFabricTypes = intval(trim($output));
                }
            } catch (Exception $e) {
                // Keep default value
            }
        }
    }
    
    // Get fabric estimates count
    try {
        $estimateWhere = $hasEstimatesActive ? "WHERE is_active = 1" : "";
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates {$estimateWhere}");
        $totalEstimates = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $totalEstimates = 0;
    }

    // Get total identifications count
    try {
        $identWhere = $hasIdentificationsActive ? "WHERE is_active = 1" : "";
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_identifications {$identWhere}");
        $totalIdentifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $totalIdentifications = 0;
    }
    
    // Get recent estimates with user information
    try {
        $estimateFilter = $hasEstimatesActive ? "WHERE fe.is_active = 1" : "";
        $stmt = $db->query("
            SELECT fe.*, u.full_name as user_full_name
            FROM fabric_estimates fe
            LEFT JOIN users u ON fe.user_id = u.user_id
            {$estimateFilter}
            ORDER BY fe.created_at DESC 
            LIMIT 10
        ");
        $recentEstimates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recentEstimates = [];
    }

    // Build recent activity feed from real data
    $recentActivity = [];

    // Recent fabric identifications
    try {
        $identFilter = $hasIdentificationsActive ? "WHERE fi.is_active = 1" : "";
        $stmt = $db->query("
            SELECT fi.fabric_type, fi.confidence, fi.created_at, u.full_name
            FROM fabric_identifications fi
            LEFT JOIN users u ON fi.user_id = u.user_id
            {$identFilter}
            ORDER BY fi.created_at DESC
            LIMIT 5
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $name = $row['full_name'] ?: 'A user';
            $conf = is_numeric($row['confidence']) ? number_format($row['confidence'], 1) . '%' : 'N/A';
            $recentActivity[] = [
                'icon' => '🔍',
                'title' => 'New Fabric Identification',
                'desc' => $name . ' identified ' . $row['fabric_type'] . ' with ' . $conf . ' confidence',
                'time' => $row['created_at']
            ];
        }
    } catch (Exception $e) {
        // Ignore
    }

    // Recent user registrations
    try {
        $userFilter = $hasUsersActive ? "WHERE is_active = 1" : "";
        $stmt = $db->query("
            SELECT full_name, created_at
            FROM users
            {$userFilter}
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $name = $row['full_name'] ?: 'A user';
            $recentActivity[] = [
                'icon' => '👤',
                'title' => 'New User Registration',
                'desc' => $name . ' created a new account',
                'time' => $row['created_at']
            ];
        }
    } catch (Exception $e) {
        // Ignore
    }

    // Recent fabric estimates
    try {
        $estimateFilter = $hasEstimatesActive ? "WHERE fe.is_active = 1" : "";
        $stmt = $db->query("
            SELECT fe.garment_type, fe.created_at, u.full_name
            FROM fabric_estimates fe
            LEFT JOIN users u ON fe.user_id = u.user_id
            {$estimateFilter}
            ORDER BY fe.created_at DESC
            LIMIT 5
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $name = $row['full_name'] ?: 'A user';
            $garment = $row['garment_type'] ?: 'a garment';
            $recentActivity[] = [
                'icon' => '📏',
                'title' => 'Consumption Estimation',
                'desc' => $name . ' calculated fabric consumption for ' . $garment,
                'time' => $row['created_at']
            ];
        }
    } catch (Exception $e) {
        // Ignore
    }

    // Sort and limit to latest 6
    usort($recentActivity, function ($a, $b) {
        return strtotime($b['time'] ?? 'now') <=> strtotime($a['time'] ?? 'now');
    });
    $recentActivity = array_slice($recentActivity, 0, 6);
    
} catch (Exception $e) {
    $totalUsers = 0;
    $totalFabricTypes = 9;
    $totalEstimates = 0;
    $totalIdentifications = 0;
    $recentEstimates = [];
    $recentActivity = [];
}

function timeAgo($datetime) {
    if (!$datetime) {
        return 'Just now';
    }
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return 'Just now';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FabricSense</title>

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
            background: linear-gradient(135deg, #ff5a7a, #ffd6df);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
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

        /* ─── Main Content ─── */
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
            margin-bottom: 2.5rem;
        }

        .welcome-banner {
            background: white;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .welcome-text {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .welcome-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
        }

        .welcome-subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .welcome-chip {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: white;
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        /* ─── Stats Cards ─── */
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

        .stat-trend {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: rgba(67, 206, 162, 0.1);
            color: #43cea2;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        .stat-trend.down {
            background: rgba(233, 69, 96, 0.1);
            color: var(--accent);
        }

        /* ─── Data Tables ─── */
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

        .add-btn {
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .add-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(233, 69, 96, 0.4);
        }

        /* ─── Table Styles ─── */
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

        /* ─── Status Badges ─── */
        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, rgba(67, 206, 162, 0.15), rgba(24, 90, 157, 0.15));
            color: #185a9d;
        }

        .status-pending {
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.15), rgba(230, 126, 34, 0.15));
            color: #e67e22;
        }

        .status-inactive {
            background: linear-gradient(135deg, rgba(233, 69, 96, 0.15), rgba(255, 107, 129, 0.15));
            color: var(--accent);
        }

        /* ─── Action Buttons ─── */
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

        .btn-view {
            background: linear-gradient(135deg, #43cea2, #185a9d);
            color: white;
            box-shadow: 0 4px 10px rgba(67, 206, 162, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 206, 162, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
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

        /* ─── Recent Activity ─── */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .activity-item:hover {
            background: rgba(233, 69, 96, 0.03);
            border-left-color: var(--accent);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.3rem;
        }

        .activity-desc {
            color: #999;
            font-size: 0.9rem;
        }

        .activity-time {
            color: #999;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ─── Reports ─── */
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

        /* ─── Responsive ─── */
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
It should be paid once you download it. Please make it so that you can only download it once.
            .main-content {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .header-content {
                padding: 0 1rem;
            }

            .admin-logo-text {
                display: none;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 0.8rem 0.5rem;
            }
        }

        /* ─── Animations ─── */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card,
        .data-section {
            animation: fadeInUp 0.6s ease forwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
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
                                        <div class="notification-time"><?php echo timeAgo($note['created_at']); ?></div>
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
                <li><a href="#dashboard" class="active"><span class="menu-icon">📊</span> Dashboard</a></li>
                <li><a href="manageUsers.php"><span class="menu-icon">👥</span> User Management</a></li>
                <li><a href="adminFabricTypes.php"><span class="menu-icon">🧵</span> Fabric Types</a></li>
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1 class="page-title">Admin Dashboard Overview</h1>

            <div class="welcome-banner">
                <div class="welcome-text">
                    <div class="welcome-title">Welcome back, <?php echo htmlspecialchars($adminProfile['full_name'] ?? 'Admin'); ?> 👋</div>
                    <div class="welcome-subtitle">Here’s a quick snapshot of what’s happening in FabricSense today.</div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Users</span>
                        <div class="stat-icon purple">👥</div>
                    </div>
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">
                        Registered users
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Fabric Estimates</span>
                        <div class="stat-icon green">📏</div>
                    </div>
                    <div class="stat-number"><?php echo $totalEstimates; ?></div>
                    <div class="stat-label">
                        Total calculations made
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Identifications</span>
                        <div class="stat-icon orange">🧵</div>
                    </div>
                    <div class="stat-number"><?php echo $totalIdentifications; ?></div>
                    <div class="stat-label">Identified items</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Activity</h2>
                </div>
                <?php if (empty($recentActivity)): ?>
                    <div style="text-align: center; padding: 2rem; color: #999;">No recent activity yet.</div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><?php echo htmlspecialchars($activity['icon']); ?></div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                <div class="activity-desc"><?php echo htmlspecialchars($activity['desc']); ?></div>
                            </div>
                            <div class="activity-time"><?php echo timeAgo($activity['time']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

           

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
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                sessionStorage.removeItem('adminLoggedIn');
                window.location.href = 'adminLogin.html';
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

        // Add user function
        function addUser() {
            alert('Add User functionality - Opens modal/form');
        }

        // Sample action handlers
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', function() {
                alert('View details functionality');
            });
        });

        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                alert('Edit functionality');
            });
        });

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

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this item?')) {
                    // Remove the row with animation
                    const row = this.closest('tr');
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 300);
                }
            });
        });

        // Active menu highlighting
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>