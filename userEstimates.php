<?php
session_start();
require_once 'DB.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: userLogin.html");
    exit();
}

$userId = $_SESSION['user_id'];
$rows = [];
$errorMessage = '';

try {
    $db = DB::connect();

    $hasStatusStmt = $db->prepare("SHOW COLUMNS FROM fabric_estimates LIKE 'invoice_status'");
    $hasStatusStmt->execute();
    $hasStatus = (bool)$hasStatusStmt->fetch(PDO::FETCH_ASSOC);

    $select = "id, fabric_type, garment_type, size, fabric_width, quantity, fabric_length_total, created_at";
    if ($hasStatus) {
        $select .= ", invoice_status";
    }

    $stmt = $db->prepare("SELECT {$select} FROM fabric_estimates WHERE user_id = :id ORDER BY created_at DESC");
    $stmt->execute(['id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = 'Unable to load estimates.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Estimates - FabricSense</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary:#1a1a2e; --accent:#e94560; --accent-light:#ff6b81; --text:#333; --bg:#f6f7fb; --card:#fff; }
        body { font-family: 'DM Sans', sans-serif; color: var(--text); background: linear-gradient(135deg, #f8f9ff 0%, #fdf2f6 55%, #f3f7ff 100%); }

        /* Soft decorative background overlay */
        .bg-pattern { position: fixed; inset: 0; z-index: 0; pointer-events: none; background:
            radial-gradient(800px 800px at 15% 20%, rgba(233,69,96,0.08) 0%, transparent 60%),
            radial-gradient(700px 700px at 85% 75%, rgba(255,107,129,0.08) 0%, transparent 65%),
            radial-gradient(500px 500px at 50% 110%, rgba(26,26,46,0.06) 0%, transparent 70%);
            filter: saturate(1.05);
        }

        .page-wrap { position: relative; z-index: 1; max-width: 1280px; margin: 5rem auto; padding: 0 1.5rem; }
        .profile-card { display: grid; grid-template-columns: 300px 1fr; background: var(--card); border-radius: 22px; box-shadow: 0 30px 60px rgba(26,26,46,0.12); overflow: hidden; backdrop-filter: blur(0.4px); }

        .profile-sidebar { background: #fff; border-right: 1px solid #f0f0f0; }
        .sidebar-item { display: block; padding: 1rem 1.5rem; text-decoration: none; color: #444; border-bottom: 1px solid #f5f5f5; font-weight: 600; transition: all .2s ease; }
        .sidebar-item.active { background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; }
        .sidebar-item:hover { background: rgba(233,69,96,0.1); color: var(--accent); }
        .sidebar-item.active:hover { background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; }

        .profile-content { padding: 2rem 2.5rem; position: relative; }
        .close-btn { position: absolute; top: 18px; right: 18px; width: 32px; height: 32px; border-radius: 50%; border: none; background: #f3f4f8; color: #333; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s ease; text-decoration: none; }
        .close-btn:hover { background: #e9ecf3; transform: translateY(-1px); }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.7rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .alert { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-weight: 600; }
        .alert-error { background: rgba(233, 69, 96, 0.1); color: #c92a49; border: 1px solid rgba(233, 69, 96, 0.3); }
        .empty-state { padding: 2rem; text-align: center; color: #777; background: #fafafa; border: 1px dashed #ddd; border-radius: 12px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.9rem 0.8rem; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.95rem; }
        th { color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.78rem; }
        tbody tr:hover { background: rgba(233,69,96,0.03); }
        .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 999px; background: rgba(233,69,96,0.12); color: var(--accent); font-weight: 700; font-size: 0.8rem; }

        @media (max-width: 900px) {
            .profile-card { grid-template-columns: 1fr; }
            .profile-sidebar { display: flex; }
            .sidebar-item { flex: 1; text-align: center; border-bottom: none; border-right: 1px solid #f0f0f0; }
            .sidebar-item:last-child { border-right: none; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern" aria-hidden="true"></div>
    <div class="page-wrap">
        <div class="profile-card">
            <aside class="profile-sidebar">
                <a class="sidebar-item" href="userProfile.php">My Profile</a>
                <a class="sidebar-item" href="userIdentifications.php">My Identifications</a>
                <a class="sidebar-item active" href="userEstimates.php">My Estimates</a>
                <a class="sidebar-item" href="userReviews.php">My Reviews</a>
            </aside>

            <main class="profile-content">
                <a class="close-btn" href="loginHomePage.html" title="Close" aria-label="Close">×</a>
                <h2 class="section-title">My Estimates</h2>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <?php if (empty($rows)): ?>
                    <div class="empty-state">No estimates yet.</div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fabric</th>
                                    <th>Garment</th>
                                    <th>Size</th>
                                    <th>Width</th>
                                    <th>Qty</th>
                                    <th>Total (m)</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php $status = $row['invoice_status'] ?? 'Pending'; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['fabric_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['garment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['size']); ?></td>
                                        <td><?php echo htmlspecialchars($row['fabric_width']); ?></td>
                                        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                        <td><span class="badge"><?php echo is_numeric($row['fabric_length_total']) ? number_format((float)$row['fabric_length_total'], 2) : 'N/A'; ?></span></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))); ?></td>
                                        <td><?php echo htmlspecialchars($status ?: 'Pending'); ?></td>
                                        <td>
                                            <?php if (strtolower($status) === 'paid'): ?>
                                                Downloaded
                                            <?php else: ?>
                                                <a href="invoice.php?type=estimate&id=<?php echo (int)$row['id']; ?>&download=1">Download</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
