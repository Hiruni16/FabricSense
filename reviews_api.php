<?php
header('Content-Type: application/json');
require_once 'DB.php';

$reviews = [];

try {
    $db = DB::connect();
    $stmt = $db->query("SELECT r.rating, r.comment, r.created_at, u.full_name FROM reviews r JOIN users u ON r.user_id = u.user_id ORDER BY r.created_at DESC LIMIT 8");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $reviews[] = [
            'name' => $row['full_name'],
            'rating' => (int)$row['rating'],
            'comment' => $row['comment'],
            'date' => $row['created_at']
        ];
    }
} catch (Exception $e) {
    $reviews = [];
}

echo json_encode(['reviews' => $reviews]);
