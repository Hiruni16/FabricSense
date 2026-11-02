<?php
session_start();
header('Content-Type: application/json');
include 'DB.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'mark_all_read';

try {
    $db = DB::connect();
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        $countStmt = $db->query("SELECT COUNT(*) as total FROM admin_notifications WHERE is_read = 0");
        $unreadCount = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
        exit();
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
