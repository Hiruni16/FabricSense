<?php
/**
 * Save Fabric Identification to Database
 * This endpoint receives identification results from the frontend and saves them to the database
 */

session_start();
include 'DB.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in',
        'message' => 'You must be logged in to save fabric identifications'
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
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Extract data
    $userId = $_SESSION['user_id'];
    $fabricType = $data['fabric_type'] ?? null;
    $confidence = $data['confidence'] ?? null;
    
    // Validate required fields
    if (!$fabricType || !$confidence) {
        throw new Exception('Missing required fields: fabric_type and confidence');
    }
    
    // Extract image information
    $imageFilename = $data['image_filename'] ?? null;
    $imageSizeKb = $data['image_size_kb'] ?? null;
    
    // Extract texture validation metrics
    $textureValidation = $data['texture_validation'] ?? [];
    $textureScore = $textureValidation['score'] ?? null;
    
    // Connect to database
    $db = DB::connect();
    
    // Prepare SQL statement
    $sql = "INSERT INTO fabric_identifications (
        user_id, 
        fabric_type, 
        confidence,
        image_filename,
        image_size_kb,
        texture_score
    ) VALUES (
        :user_id,
        :fabric_type,
        :confidence,
        :image_filename,
        :image_size_kb,
        :texture_score
    )";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':fabric_type', $fabricType, PDO::PARAM_STR);
    $stmt->bindParam(':confidence', $confidence, PDO::PARAM_STR);
    $stmt->bindParam(':image_filename', $imageFilename, PDO::PARAM_STR);
    $stmt->bindParam(':image_size_kb', $imageSizeKb, PDO::PARAM_INT);
    $stmt->bindParam(':texture_score', $textureScore, PDO::PARAM_STR);
    
    // Execute
    $stmt->execute();
    
    $insertId = $db->lastInsertId();

    // Admin notification
    try {
        $userName = $_SESSION['user_name'] ?? 'A user';
        $notify = $db->prepare("INSERT INTO admin_notifications (type, title, message, user_id, related_id) VALUES (:type, :title, :message, :user_id, :related_id)");
        $notify->execute([
            'type' => 'fabric_identification',
            'title' => 'New Fabric Identification',
            'message' => $userName . ' identified ' . $fabricType . ' (' . (is_numeric($confidence) ? number_format((float)$confidence, 1) : $confidence) . '%).',
            'user_id' => $userId,
            'related_id' => $insertId
        ]);
    } catch (Exception $e) {
        // Ignore notification errors
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Fabric identification saved successfully',
        'id' => $insertId,
        'user_id' => $userId,
        'fabric_type' => $fabricType,
        'confidence' => $confidence
    ]);
    
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
