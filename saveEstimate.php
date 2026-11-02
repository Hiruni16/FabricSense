<?php
/**
 * Save Fabric Consumption Estimate
 * This endpoint saves user estimates to the database
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate required fields
    $required = ['fabric_type', 'garment_type', 'size', 'width', 'quantity', 
                 'fabric_length_per_garment', 'fabric_length_total'];
    
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Connect to database
    $db = DB::connect();
    
    // Get user ID from data or session
    $userId = isset($data['user_id']) ? $data['user_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    $userName = isset($data['user_name']) ? $data['user_name'] : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null);
    $userEmail = isset($data['user_email']) ? $data['user_email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null);
    
    // Prepare SQL
    $sql = "INSERT INTO fabric_estimates 
            (user_id, user_name, user_email, fabric_type, garment_type, size, fabric_width, 
             quantity, fabric_length_per_garment, fabric_length_total) 
            VALUES 
            (:user_id, :user_name, :user_email, :fabric_type, :garment_type, :size, :fabric_width, 
             :quantity, :fabric_length_per_garment, :fabric_length_total)";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':user_name', $userName);
    $stmt->bindParam(':user_email', $userEmail);
    $stmt->bindParam(':fabric_type', $data['fabric_type']);
    $stmt->bindParam(':garment_type', $data['garment_type']);
    $stmt->bindParam(':size', $data['size']);
    $stmt->bindParam(':fabric_width', $data['width']);
    $stmt->bindParam(':quantity', $data['quantity']);
    $stmt->bindParam(':fabric_length_per_garment', $data['fabric_length_per_garment']);
    $stmt->bindParam(':fabric_length_total', $data['fabric_length_total']);
    
    // Execute
    $stmt->execute();
    $estimateId = $db->lastInsertId();

    // Admin notification
    try {
        $displayName = $userName ?: 'A user';
        $notify = $db->prepare("INSERT INTO admin_notifications (type, title, message, user_id, related_id) VALUES (:type, :title, :message, :user_id, :related_id)");
        $notify->execute([
            'type' => 'fabric_estimate',
            'title' => 'New Consumption Estimate',
            'message' => $displayName . ' created an estimate for ' . $data['garment_type'] . ' (' . $data['fabric_type'] . ').',
            'user_id' => $userId,
            'related_id' => $estimateId
        ]);
    } catch (Exception $e) {
        // Ignore notification errors
    }
    
    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Estimate saved successfully',
        'estimate_id' => $estimateId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
