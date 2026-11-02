<?php
session_start();
include 'DB.php';

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

/* Database setup for fabric_types */
try {
    $db = DB::connect();
    
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS fabric_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Check if table is empty and populate with defaults
    $countStmt = $db->query("SELECT COUNT(*) as total FROM fabric_types");
    $count = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($count == 0) {
        $defaultFabrics = ['Cotton', 'Silk', 'Polyester', 'Wool', 'Linen', 'Denim', 'Chiffon', 'Satin', 'Velvet'];
        $stmt = $db->prepare("INSERT INTO fabric_types (name) VALUES (:name)");
        foreach ($defaultFabrics as $fabric) {
            $stmt->execute(['name' => $fabric]);
        }
    }
} catch (Exception $e) {
    // Handle error
}

/* Handle form submissions */
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = DB::connect();
        
        if (isset($_POST['add_fabric'])) {
            $name = trim($_POST['fabric_name']);
            if (!empty($name)) {
                $stmt = $db->prepare("INSERT INTO fabric_types (name) VALUES (:name)");
                $stmt->execute(['name' => $name]);
                $message = 'Fabric type added successfully!';
            }
        } elseif (isset($_POST['edit_fabric'])) {
            $id = (int)$_POST['fabric_id'];
            $name = trim($_POST['fabric_name']);
            $status = $_POST['status'];
            if (!empty($name)) {
                $stmt = $db->prepare("UPDATE fabric_types SET name = :name, status = :status WHERE id = :id");
                $stmt->execute(['name' => $name, 'status' => $status, 'id' => $id]);
                $message = 'Fabric type updated successfully!';
            }
        } elseif (isset($_POST['delete_fabric'])) {
            $id = (int)$_POST['fabric_id'];
            $stmt = $db->prepare("DELETE FROM fabric_types WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $message = 'Fabric type deleted successfully!';
        } elseif (isset($_POST['toggle_status'])) {
            $id = (int)$_POST['fabric_id'];
            $stmt = $db->prepare("UPDATE fabric_types SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $message = 'Status updated successfully!';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

/* Fetch fabric types */
$fabricTypes = [];
try {
    $db = DB::connect();
    $stmt = $db->query("SELECT * FROM fabric_types ORDER BY name");
    $fabricTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fabricTypes = [];
}

/* Default garment types and sizes */
$garmentTypes = ['Shirt', 'Dress', 'Pants', 'Skirt', 'Jacket', 'Blouse', 'T-Shirt', 'Coat', 'Shorts'];
$sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
/* Counts for stats */
$fabricCount = count($fabricTypes);
$garmentCount = count($garmentTypes);
$sizeCount = count($sizes);
$activeCount = 0;
$inactiveCount = 0;
foreach ($fabricTypes as $f) {
    if (($f['status'] ?? '') === 'active') {
        $activeCount++;
    } elseif (($f['status'] ?? '') === 'inactive') {
        $inactiveCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Fabric Types - FabricSense</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #1a1a2e; --accent: #ff5a7a; --accent-light: #ffd6df; --purple: #667eea; --purple-dark: #764ba2; --text-dark: #333; --bg-light: #f8f9fa;
        }
        body { font-family: 'DM Sans', sans-serif; line-height: 1.6; color: var(--text-dark); background: var(--bg-light); }
        .admin-header { background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%); color: white; padding: 1.2rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 1000; }
        .header-content { max-width: 1600px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .admin-logo { display: flex; align-items: center; gap: 1rem; }
        .admin-logo-text { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; background: linear-gradient(135deg, #ffd6df 0%, #ff5a7a 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -1px; }
        .admin-info { display: flex; align-items: center; gap: 2rem; }
        .notification-wrapper { position: relative; }
        .notification-btn { width: 42px; height: 42px; border-radius: 50%; border: none; background: rgba(255, 255, 255, 0.15); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .notification-btn:hover { background: rgba(255, 255, 255, 0.25); transform: translateY(-1px); }
        .notification-count { position: absolute; top: -4px; right: -4px; background: var(--accent); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 999px; border: 2px solid #1a1a2e; }
        .notification-panel { position: absolute; top: 52px; right: 0; width: 320px; background: #fff; color: var(--text-dark); border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,0.18); opacity: 0; visibility: hidden; transform: translateY(10px); transition: all 0.2s ease; z-index: 1200; overflow: hidden; }
        .notification-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
        .notification-header { padding: 1rem 1.2rem; font-weight: 700; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .notification-list { max-height: 280px; overflow-y: auto; }
        .notification-item { padding: 0.9rem 1.2rem; border-bottom: 1px solid #f5f5f5; }
        .notification-item:last-child { border-bottom: none; }
        .notification-title { font-weight: 700; margin-bottom: 0.25rem; color: var(--primary); font-size: 0.92rem; }
        .notification-message { font-size: 0.85rem; color: #666; margin-bottom: 0.35rem; }
        .notification-time { font-size: 0.75rem; color: #999; }
        .admin-user { display: flex; align-items: center; gap: 0.8rem; padding: 0.4rem; background: rgba(255, 255, 255, 0.1); border-radius: 50%; backdrop-filter: blur(10px); cursor: pointer; transition: all 0.3s ease; border: 2px solid transparent; text-decoration: none; }
        .admin-user:hover { background: rgba(255, 255, 255, 0.2); border-color: rgba(255, 255, 255, 0.3); transform: scale(1.05); }
        .admin-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, var(--accent), var(--accent-light)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .admin-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .logout-btn { padding: 0.7rem 1.8rem; background: var(--accent); color: white; border: none; border-radius: 50px; cursor: pointer; font-weight: 700; font-size: 0.95rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3); }
        .logout-btn:hover { background: var(--accent-light); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4); }
        .dashboard-container { display: flex; min-height: calc(100vh - 76px); max-width: 1600px; margin: 0 auto; }
        .sidebar { width: 280px; background: white; box-shadow: 4px 0 20px rgba(0,0,0,0.06); padding: 2rem 0; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 0.5rem; padding: 0 1rem; }
        .sidebar-menu a { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; color: var(--text-dark); text-decoration: none; font-weight: 600; border-radius: 12px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
        .sidebar-menu a::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 0; background: linear-gradient(135deg, var(--accent), var(--accent-light)); border-radius: 0 4px 4px 0; transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: linear-gradient(135deg, rgba(233, 69, 96, 0.08), rgba(255, 107, 129, 0.08)); color: var(--accent); }
        .sidebar-menu a:hover::before, .sidebar-menu a.active::before { height: 60%; }
        .menu-icon { font-size: 1.4rem; width: 28px; text-align: center; }
        .main-content { flex: 1; padding: 2.5rem; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 2.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 1rem; }
        .page-subtitle { color: #666; font-size: 1.1rem; margin-bottom: 2.5rem; }
        /* Stats Cards */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card-small { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 1.5rem; transition: all 0.3s ease; }
        .stat-card-small:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        .stat-icon-small { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-icon-small.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon-small.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon-small.green { background: linear-gradient(135deg, #43cea2, #185a9d); }
        .stat-icon-small.teal { background: linear-gradient(135deg, #06beb6, #48b1bf); }
        .stat-content { flex: 1; }
        .stat-value { font-size: 2rem; font-weight: 900; color: var(--primary); line-height: 1; }
        .stat-label-small { color: #666; font-size: 0.9rem; margin-top: 0.3rem; }
        .message { padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .message.success { background: rgba(67, 206, 162, 0.1); color: #2d5a3d; border: 1px solid rgba(67, 206, 162, 0.2); }
        .message.error { background: rgba(233, 69, 96, 0.1); color: #a94442; border: 1px solid rgba(233, 69, 96, 0.2); }
        .data-section { background: white; padding: 2.5rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid rgba(233, 69, 96, 0.1); }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: white; box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4); }
        .btn-secondary { background: #e9ecef; color: #495057; }
        .btn-secondary:hover { background: #dee2e6; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { padding: 1rem; text-align: left; font-weight: 700; color: var(--primary); border-bottom: 2px solid rgba(233, 69, 96, 0.15); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { padding: 1rem; border-bottom: 1px solid #f0f0f0; }
        .table tr:hover { background: rgba(233, 69, 96, 0.02); }
        .status-active { color: #28a745; font-weight: 600; }
        .status-inactive { color: #dc3545; font-weight: 600; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { padding: 2rem; text-align: center; border-bottom: 1px solid #e9ecef; }
        .modal-body { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--primary); }
        .form-input { width: 100%; padding: 0.75rem; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-input:focus { outline: none; border-color: var(--accent); }
        .modal-footer { padding: 1.5rem 2rem; display: flex; gap: 1rem; justify-content: flex-end; border-top: 1px solid #e9ecef; }
        @media (max-width: 768px) { .main-content { padding: 1.5rem; } .page-title { font-size: 2rem; } .section-header { flex-direction: column; gap: 1rem; align-items: flex-start; } }
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
                    <button class="notification-btn" id="notificationToggle">🔔</button>
                    <?php if ($unreadCount > 0): ?><span class="notification-count"><?php echo $unreadCount; ?></span><?php endif; ?>
                    <div class="notification-panel" id="notificationPanel">
                        <div class="notification-header"><span>Notifications</span></div>
                        <div class="notification-list">
                            <?php if (empty($notificationItems)): ?>
                                <div class="notification-item"><div class="notification-message">No notifications yet.</div></div>
                            <?php else: ?>
                                <?php foreach ($notificationItems as $note): ?>
                                    <div class="notification-item">
                                        <div class="notification-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($note['message']); ?></div>
                                        <div class="notification-time"><?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <a href="adminProfile.php" class="admin-user">
                    <div class="admin-avatar">
                        <?php if ($adminProfile['profile_image'] && file_exists($adminProfile['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($adminProfile['profile_image']); ?>" alt="Admin">
                        <?php else: ?>👤<?php endif; ?>
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
                <li><a href="manageUsers.php"><span class="menu-icon">👥</span> User Management</a></li>
                <li><a href="adminFabricTypes.php" class="active"><span class="menu-icon">🧵</span> Fabric Types</a></li>
                <li><a href="manageIdentifications.php"><span class="menu-icon">🔍</span> Identifications</a></li>
                <li><a href="manageEstimates.php"><span class="menu-icon">📏</span> Estimations</a></li>
                <li><a href="adminProfile.php"><span class="menu-icon">👤</span> Profile</a></li>
                <li><a href="manageMessages.php"><span class="menu-icon">📧</span> Messages</a></li>
                <li><a href="reports.php"><span class="menu-icon">📄</span> Reports</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1 class="page-title">Fabric Types Management</h1>
            <p class="page-subtitle">Manage fabric types, garment types, and size options for the AI model</p>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card-small">
                    <div class="stat-icon-small orange">🧵</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $fabricCount; ?></div>
                        <div class="stat-label-small">Fabric Types</div>
                    </div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-icon-small purple">👕</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $garmentCount; ?></div>
                        <div class="stat-label-small">Garment Types</div>
                    </div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-icon-small green">📐</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $sizeCount; ?></div>
                        <div class="stat-label-small">Size Options</div>
                    </div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-icon-small teal">✅</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $activeCount; ?></div>
                        <div class="stat-label-small">Active Fabrics</div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Fabric Types Section -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Fabric Types</h2>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <input type="text" id="searchInput" class="form-input" placeholder="Search fabrics..." style="width: 250px;">
                        <button class="btn btn-primary" onclick="openModal('add')">Add Fabric Type</button>
                    </div>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fabricTypes as $fabric): ?>
                            <tr>
                                <td><?php echo $fabric['id']; ?></td>
                                <td><?php echo htmlspecialchars($fabric['name']); ?></td>
                                <td><span class="status-<?php echo $fabric['status']; ?>"><?php echo ucfirst($fabric['status']); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($fabric['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-secondary" onclick="openModal('edit', <?php echo $fabric['id']; ?>, '<?php echo htmlspecialchars($fabric['name']); ?>', '<?php echo $fabric['status']; ?>')">Edit</button>
                                    <button class="btn btn-secondary" onclick="toggleStatus(<?php echo $fabric['id']; ?>)">Toggle</button>
                                    <button class="btn btn-secondary" onclick="deleteFabric(<?php echo $fabric['id']; ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Garment Types and Sizes -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="data-section">
                    <h3 class="section-title" style="font-size: 1.3rem; margin-bottom: 1.5rem;">Garment Types (<?php echo count($garmentTypes); ?>)</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.8rem;">
                        <?php foreach ($garmentTypes as $garment): ?>
                            <span style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); padding: 0.6rem 1.2rem; border-radius: 50px; font-weight: 600; border: 2px solid rgba(102, 126, 234, 0.2); color: var(--primary);"><?php echo htmlspecialchars($garment); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="data-section">
                    <h3 class="section-title" style="font-size: 1.3rem; margin-bottom: 1.5rem;">Size Options (<?php echo count($sizes); ?>)</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.8rem;">
                        <?php foreach ($sizes as $size): ?>
                            <span style="background: linear-gradient(135deg, rgba(67, 206, 162, 0.1), rgba(24, 90, 157, 0.1)); padding: 0.6rem 1.2rem; border-radius: 50px; font-weight: 600; border: 2px solid rgba(67, 206, 162, 0.2); color: var(--primary);"><?php echo htmlspecialchars($size); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="fabricModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Fabric Type</h2>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="fabric_id" id="fabricId">
                    <div class="form-group">
                        <label class="form-label" for="fabricName">Fabric Name</label>
                        <input type="text" class="form-input" id="fabricName" name="fabric_name" required>
                    </div>
                    <div class="form-group" id="statusGroup" style="display: none;">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-input" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_fabric" id="submitBtn" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(type, id = null, name = '', status = 'active') {
            const modal = document.getElementById('fabricModal');
            const title = document.getElementById('modalTitle');
            const idField = document.getElementById('fabricId');
            const nameField = document.getElementById('fabricName');
            const statusField = document.getElementById('status');
            const statusGroup = document.getElementById('statusGroup');
            const submitBtn = document.getElementById('submitBtn');

            if (type === 'add') {
                title.textContent = 'Add Fabric Type';
                idField.value = '';
                nameField.value = '';
                statusGroup.style.display = 'none';
                submitBtn.name = 'add_fabric';
                submitBtn.textContent = 'Add';
            } else {
                title.textContent = 'Edit Fabric Type';
                idField.value = id;
                nameField.value = name;
                statusField.value = status;
                statusGroup.style.display = 'block';
                submitBtn.name = 'edit_fabric';
                submitBtn.textContent = 'Update';
            }
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('fabricModal').classList.remove('active');
        }

        function toggleStatus(id) {
            if (confirm('Toggle status?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="fabric_id" value="' + id + '"><input type="hidden" name="toggle_status" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteFabric(id) {
            if (confirm('Delete this fabric type?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="fabric_id" value="' + id + '"><input type="hidden" name="delete_fabric" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Notifications
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

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.table tbody tr').forEach(row => {
                const name = row.cells[1].textContent.toLowerCase();
                row.style.display = name.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>