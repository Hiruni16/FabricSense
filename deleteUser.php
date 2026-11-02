<?php
session_start();
include 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureIsActiveColumn(PDO $db, string $table): void {
    if (!columnExists($db, $table, 'is_active')) {
        $db->exec("ALTER TABLE {$table} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
}

/* ===== SECURITY CHECK (Admin only) ===== */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.html");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: manageUsers.php");
    exit();
}

try {
    $db = DB::connect();
    ensureIsActiveColumn($db, 'users');
    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$id]);
    
    header("Location: manageUsers.php");
    exit();
} catch (Exception $e) {
    echo "Error deleting user: " . $e->getMessage();
    exit();
}
?>
