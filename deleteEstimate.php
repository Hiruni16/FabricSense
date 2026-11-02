<?php
/**
 * Delete Fabric Estimate
 * Admin endpoint to delete a fabric estimate by ID
 */

session_start();

header('Content-Type: application/json');

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

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['id'])) {
        throw new Exception("Estimate ID is required");
    }
    
    $estimateId = intval($data['id']);
    
    // Connect to database
    $db = DB::connect();
    
    ensureIsActiveColumn($db, 'fabric_estimates');

    // Soft-delete the estimate
    $stmt = $db->prepare("UPDATE fabric_estimates SET is_active = 0 WHERE id = :id AND is_active = 1");
    $stmt->bindParam(':id', $estimateId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Estimate deactivated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Estimate not found or already inactive'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
