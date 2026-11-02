<?php


header("Content-Type: application/json");
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

/* Basic validation */
if ($full_name === "" || $email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required"
    ]);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode([
        "status" => "error",
        "message" => "Passwords do not match"
    ]);
    exit;
}

try {
    $db = DB::connect();

    /* Check if email already exists */
    $check = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Email already registered"
        ]);
        exit;
    }
    /* Hash password */
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    /* Insert user */
    $stmt = $db->prepare("
        INSERT INTO users (full_name, email, password, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([$full_name, $email, $hashedPassword]);
    $newUserId = $db->lastInsertId();

    // Admin notification
    try {
        $notify = $db->prepare("INSERT INTO admin_notifications (type, title, message, user_id, related_id) VALUES (:type, :title, :message, :user_id, :related_id)");
        $notify->execute([
            'type' => 'user_registered',
            'title' => 'New User Registration',
            'message' => $full_name . ' created a new account.',
            'user_id' => $newUserId ?: null,
            'related_id' => $newUserId ?: null
        ]);
    } catch (Exception $e) {
        // Ignore notification errors
    }

    echo json_encode([
        "status" => "success",
        "message" => "Account created successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

// require_once 'DB.php';

// header('Content-Type: application/json; charset=UTF-8');

// $response = [
//     "status" => "error",
//     "message" => "Unknown error"
// ];

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     $response['message'] = 'Invalid request method';
//     echo json_encode($response);
//     exit;
// }

// $fullName        = trim($_POST['full_name'] ?? '');
// $email           = trim($_POST['email'] ?? '');
// $password        = $_POST['password'] ?? '';
// $confirmPassword = $_POST['confirmPassword'] ?? '';

// if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
//     $response['message'] = 'All fields are required!';
//     echo json_encode($response);
//     exit;
// }

// if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//     $response['message'] = 'Invalid email address!';
//     echo json_encode($response);
//     exit;
// }

// if ($password !== $confirmPassword) {
//     $response['message'] = 'Passwords do not match!';
//     echo json_encode($response);
//     exit;
// }

// if (strlen($password) < 8) {
//     $response['message'] = 'Password must be at least 8 characters!';
//     echo json_encode($response);
//     exit;
// }

// try {
//     $conn = DB::connect();

//     $check = $conn->prepare(
//         "SELECT user_id FROM users WHERE email = :email LIMIT 1"
//     );
//     $check->execute(['email' => $email]);

//     if ($check->rowCount() > 0) {
//         $response['message'] = 'Email already registered!';
//         echo json_encode($response);
//         exit;
//     }

//     $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

//     $stmt = $conn->prepare(
//         "INSERT INTO users (full_name, email, password)
//          VALUES (:full_name, :email, :password)"
//     );

//     $stmt->execute([
//         'full_name' => $fullName,
//         'email'     => $email,
//         'password'  => $hashedPassword
//     ]);

//     $response['status']  = 'success';
//     $response['message'] = 'User registered successfully!';
//     echo json_encode($response);

// } catch (PDOException $e) {
//     http_response_code(500);
//     $response['message'] = 'Database error';
//     echo json_encode($response);
// }
