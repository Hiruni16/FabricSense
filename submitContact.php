<?php
session_start();
include 'DB.php';

header('Content-Type: application/json');

// Debug: Log session info
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to send a message. Session user_id not found.',
        'debug' => [
            'session_id' => session_id(),
            'session_data' => $_SESSION
        ]
    ]);
    exit;
}

// Validate form data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please fill in all required fields'
    ]);
    exit;
}

try {
    $db = DB::connect();
    
    // Debug: Log the query and values
    error_log("Attempting to insert - user_id: $userId, name: $name, email: $email");
    
    // Insert contact message
    $stmt = $db->prepare("
        INSERT INTO contact_messages (user_id, name, email, phone, subject, message, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$userId, $name, $email, $phone, $subject, $message]);
    
    if ($result) {
        $insertedId = $db->lastInsertId();
        error_log("Message inserted successfully with ID: $insertedId");
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'message_id' => $insertedId,
            'debug' => [
                'inserted_id' => $insertedId,
                'user_id' => $userId
            ]
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Failed to insert: " . print_r($errorInfo, true));
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to insert message into database',
            'debug' => $errorInfo
        ]);
    }
    
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'debug' => [
            'trace' => $e->getTraceAsString()
        ]
    ]);
} catch (Exception $e) {
    error_log("General Exception: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
