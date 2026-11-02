<?php
/**
 * Delete Fabric Identification
 * Admin endpoint to delete identification records
 */

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

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Admin access required'
    ]);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['id'])) {
        throw new Exception('Identification ID is required');
    }
    
    $id = (int)$data['id'];
    
    // Connect to database
    $db = DB::connect();
    
    ensureIsActiveColumn($db, 'fabric_identifications');

    // Soft-delete the identification
    $stmt = $db->prepare("UPDATE fabric_identifications SET is_active = 0 WHERE id = :id AND is_active = 1");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Fabric identification deactivated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Not found',
            'message' => 'Identification not found or already inactive'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
