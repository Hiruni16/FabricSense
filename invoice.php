<?php
session_start();
require_once 'DB.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: userLogin.html");
    exit();
}

$userId = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$download = isset($_GET['download']);

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

try {
    $db = DB::connect();

    $userStmt = $db->prepare("SELECT full_name, email FROM users WHERE user_id = :id LIMIT 1");
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found.');
    }

    $record = null;
    $invoiceTitle = '';
    $table = '';

    if ($type === 'estimate') {
        $table = 'fabric_estimates';
        $stmt = $db->prepare("SELECT * FROM fabric_estimates WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $invoiceTitle = 'Fabric Estimate Invoice';
    } elseif ($type === 'identification') {
        $table = 'fabric_identifications';
        $stmt = $db->prepare("SELECT * FROM fabric_identifications WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $invoiceTitle = 'Fabric Identification Invoice';
    }

    if (!$record) {
        throw new Exception('Invoice not found.');
    }

    if ($download) {
        $hasStatus = columnExists($db, $table, 'invoice_status');
        if ($hasStatus) {
            $currentStatus = $record['invoice_status'] ?? 'Pending';
            if (strtolower($currentStatus) === 'paid') {
                http_response_code(403);
                echo 'Invoice already downloaded.';
                exit();
            }

            $hasDownloadedAt = columnExists($db, $table, 'invoice_downloaded_at');
            if ($hasDownloadedAt) {
                $update = $db->prepare("UPDATE {$table} SET invoice_status = 'Paid', invoice_downloaded_at = NOW() WHERE id = :id AND user_id = :user_id");
            } else {
                $update = $db->prepare("UPDATE {$table} SET invoice_status = 'Paid' WHERE id = :id AND user_id = :user_id");
            }
            $update->execute(['id' => $id, 'user_id' => $userId]);
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="invoice-' . $type . '-' . $id . '.html"');
    }

} catch (Exception $e) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit();
}

$issueDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($invoiceTitle); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f6f7fb; color: #111; padding: 2.5rem; }
        .invoice { max-width: 860px; margin: 0 auto; background: #fff; padding: 3rem 3.5rem; border-radius: 10px; box-shadow: 0 18px 40px rgba(0,0,0,0.08); }
        .brand { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .brand-mark { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #e94560, #ff6b81); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; }
        .brand-text h1 { font-size: 1.3rem; letter-spacing: 0.5px; }
        .brand-text p { font-size: 0.9rem; color: #666; }
        .title { text-align: center; font-weight: 700; margin: 2rem 0 1.5rem; letter-spacing: 1px; }
        .meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .meta div { font-size: 0.95rem; }
        .label { color: #555; font-weight: 600; width: 140px; display: inline-block; }
        .amount { text-align: right; font-weight: 700; margin: 2rem 0; }
        .details { margin-top: 1.2rem; }
        .details h3 { margin-bottom: 0.6rem; }
        .details ul { padding-left: 1.2rem; color: #444; }
        .footer { text-align: center; margin-top: 2.5rem; color: #555; font-style: italic; }
        .divider { border-top: 1px solid #eee; margin: 1.5rem 0; }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="brand">
            <div class="brand-mark">FS</div>
            <div class="brand-text">
                <h1>FabricSense</h1>
                <p>AI Fabric Identification & Estimation</p>
            </div>
        </div>

        <div class="title">INVOICE</div>

        <div class="meta">
            <div><span class="label">Invoice Date:</span> <?php echo htmlspecialchars($issueDate); ?></div>
            <div><span class="label">Client Name:</span> <?php echo htmlspecialchars($user['full_name']); ?></div>
            <div><span class="label">Client Email:</span> <?php echo htmlspecialchars($user['email']); ?></div>
            <div><span class="label">Service Type:</span> <?php echo htmlspecialchars($type === 'estimate' ? 'Fabric Estimation' : 'Fabric Identification'); ?></div>
        </div>

        <div class="divider"></div>

        <?php if ($type === 'estimate'): ?>
            <div class="meta">
                <div><span class="label">Fabric Type:</span> <?php echo htmlspecialchars($record['fabric_type']); ?></div>
                <div><span class="label">Garment Type:</span> <?php echo htmlspecialchars($record['garment_type']); ?></div>
                <div><span class="label">Size:</span> <?php echo htmlspecialchars($record['size']); ?></div>
                <div><span class="label">Fabric Width:</span> <?php echo htmlspecialchars($record['fabric_width']); ?></div>
                <div><span class="label">Quantity:</span> <?php echo htmlspecialchars($record['quantity']); ?></div>
                <div><span class="label">Total (m):</span> <?php echo htmlspecialchars(number_format((float)$record['fabric_length_total'], 2)); ?></div>
            </div>
        <?php else: ?>
            <div class="meta">
                <div><span class="label">Fabric Type:</span> <?php echo htmlspecialchars($record['fabric_type']); ?></div>
                <div><span class="label">Confidence:</span> <?php echo htmlspecialchars(number_format((float)$record['confidence'], 1)); ?>%</div>
                <div><span class="label">Image:</span> <?php echo htmlspecialchars($record['image_filename'] ?: '—'); ?></div>
                <div><span class="label">Date:</span> <?php echo htmlspecialchars(date('Y-m-d', strtotime($record['created_at']))); ?></div>
            </div>
        <?php endif; ?>

        <div class="footer">Thank you for choosing FabricSense!</div>
    </div>
</body>
</html>
