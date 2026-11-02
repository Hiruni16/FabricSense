<?php
require_once 'DB.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json; charset=UTF-8');

$response = [
    "status" => "error",
    "message" => "Invalid email or password"
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode($response);
    exit;
}

try {
    $conn = DB::connect();

    $hasActive = columnExists($conn, 'users', 'is_active');
    $activeFilter = $hasActive ? " AND is_active = 1" : "";
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, password FROM users WHERE email = :email{$activeFilter}"
    );
    $stmt->execute(['email' => $email]);

    if ($stmt->rowCount() === 0) {
        echo json_encode($response);
        exit;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($password, $user['password'])) {
        echo json_encode($response);
        exit;
    }

    //  Login success
    session_start();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode([
        "status" => "success",
        "message" => "Login successful"
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
}
