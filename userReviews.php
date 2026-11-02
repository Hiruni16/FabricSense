<?php
session_start();
require_once 'DB.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: userLogin.html");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$reviewSuccess = '';
$reviewError = '';
$userReviews = [];

try {
    $db = DB::connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            throw new Exception('Please select a rating between 1 and 5.');
        }

        if ($comment === '' || strlen($comment) < 10) {
            throw new Exception('Please write a review of at least 10 characters.');
        }

        $insert = $db->prepare("INSERT INTO reviews (user_id, rating, comment) VALUES (:user_id, :rating, :comment)");
        $insert->execute([
            'user_id' => $userId,
            'rating' => $rating,
            'comment' => $comment
        ]);

        $reviewSuccess = 'Thank you! Your review has been posted.';
    }

    if ($userName === '') {
        $nameStmt = $db->prepare("SELECT full_name FROM users WHERE user_id = :id LIMIT 1");
        $nameStmt->execute(['id' => $userId]);
        $userName = $nameStmt->fetchColumn() ?: '';
    }

    $reviewsStmt = $db->prepare("SELECT rating, comment, created_at FROM reviews WHERE user_id = :id ORDER BY created_at DESC LIMIT 10");
    $reviewsStmt->execute(['id' => $userId]);
    $userReviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reviewError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - FabricSense</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary:#1a1a2e; --accent:#e94560; --accent-light:#ff6b81; --text:#333; --bg:#f6f7fb; --card:#fff; }
        body { font-family: 'DM Sans', sans-serif; color: var(--text); background: linear-gradient(135deg, #f8f9ff 0%, #fdf2f6 55%, #f3f7ff 100%); }

        /* Soft decorative background overlay */
        .bg-pattern { position: fixed; inset: 0; z-index: 0; pointer-events: none; background:
            radial-gradient(800px 800px at 15% 20%, rgba(233,69,96,0.08) 0%, transparent 60%),
            radial-gradient(700px 700px at 85% 75%, rgba(255,107,129,0.08) 0%, transparent 65%),
            radial-gradient(500px 500px at 50% 110%, rgba(26,26,46,0.06) 0%, transparent 70%);
            filter: saturate(1.05);
        }

        .page-wrap { position: relative; z-index: 1; max-width: 1280px; margin: 5rem auto; padding: 0 1.5rem; }
        .profile-card { display: grid; grid-template-columns: 300px 1fr; background: var(--card); border-radius: 22px; box-shadow: 0 30px 60px rgba(26,26,46,0.12); overflow: hidden; backdrop-filter: blur(0.4px); }

        .profile-sidebar { background: #fff; border-right: 1px solid #f0f0f0; }
        .sidebar-item { display: block; padding: 1rem 1.5rem; text-decoration: none; color: #444; border-bottom: 1px solid #f5f5f5; font-weight: 600; transition: all .2s ease; }
        .sidebar-item.active { background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; }
        .sidebar-item:hover { background: rgba(233,69,96,0.1); color: var(--accent); }
        .sidebar-item.active:hover { background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; }

        .profile-content { padding: 2rem 2.5rem; position: relative; }
        .close-btn { position: absolute; top: 18px; right: 18px; width: 32px; height: 32px; border-radius: 50%; border: none; background: #f3f4f8; color: #333; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s ease; text-decoration: none; }
        .close-btn:hover { background: #e9ecf3; transform: translateY(-1px); }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.7rem; margin-bottom: 0.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .section-subtitle { color: #666; margin-bottom: 1.5rem; }

        .alert { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-weight: 600; }
        .alert-success { background: rgba(67, 206, 162, 0.15); color: #185a9d; border: 1px solid rgba(67, 206, 162, 0.4); }
        .alert-error { background: rgba(233, 69, 96, 0.1); color: #c92a49; border: 1px solid rgba(233, 69, 96, 0.3); }

        .form-group { margin-bottom: 1.2rem; }
        .input-readonly { width: 100%; padding: 0.9rem 1rem; border-radius: 12px; border: 1.5px solid #e1e5ea; font-size: 1rem; background: #f7f7fb; color: #666; }
        label { display: block; font-weight: 600; margin-bottom: 0.4rem; }
        input { width: 100%; padding: 0.9rem 1rem; border-radius: 12px; border: 1.5px solid #e1e5ea; font-size: 1rem; background: #fff; }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(233,69,96,0.15); }

        .rating-row { display: flex; gap: 0.8rem; align-items: center; margin-bottom: 1rem; }
        .rating-stars { display: flex; gap: 0.4rem; flex-direction: row-reverse; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 1.4rem; cursor: pointer; color: #ccc; }
        .rating-stars input:checked ~ label { color: #ffb400; }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label { color: #ffb400; }

        .review-textarea { width: 100%; min-height: 120px; padding: 0.9rem 1rem; border-radius: 12px; border: 1.5px solid #e1e5ea; font-size: 1rem; resize: vertical; }
        .review-textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(233,69,96,0.15); }

        .form-actions { text-align: right; margin-top: 1.4rem; }
        .btn-save { padding: 0.8rem 1.9rem; border: none; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; font-weight: 700; cursor: pointer; box-shadow: 0 10px 22px rgba(233,69,96,0.3); }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 14px 26px rgba(233,69,96,0.35); }

        .review-list { display: grid; gap: 1rem; margin-top: 1.5rem; }
        .review-card { background: #fff; border: 1px solid #f0f0f0; border-radius: 14px; padding: 1rem 1.2rem; }
        .review-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; }
        .review-stars { color: #ffb400; font-size: 0.95rem; letter-spacing: 1px; }
        .review-date { color: #999; font-size: 0.85rem; }
        .review-body { color: #444; }

        @media (max-width: 900px) {
            .profile-card { grid-template-columns: 1fr; }
            .profile-sidebar { display: flex; }
            .sidebar-item { flex: 1; text-align: center; border-bottom: none; border-right: 1px solid #f0f0f0; }
            .sidebar-item:last-child { border-right: none; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern" aria-hidden="true"></div>
    <div class="page-wrap">
        <div class="profile-card">
            <aside class="profile-sidebar">
                <a class="sidebar-item" href="userProfile.php">My Profile</a>
                <a class="sidebar-item" href="userIdentifications.php">My Identifications</a>
                <a class="sidebar-item" href="userEstimates.php">My Estimates</a>
                <a class="sidebar-item active" href="userReviews.php">My Reviews</a>
            </aside>

            <main class="profile-content">
                <a class="close-btn" href="loginHomePage.html" title="Close" aria-label="Close">×</a>
                <h2 class="section-title">My Reviews</h2>
                <p class="section-subtitle">Share your experience so others can learn from it.</p>

                <?php if ($reviewSuccess): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($reviewSuccess); ?></div>
                <?php endif; ?>

                <?php if ($reviewError): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($reviewError); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="submit_review" value="1">
                    <div class="form-group">
                        <label for="reviewer_name">Your Name</label>
                        <div id="reviewer_name" class="input-readonly"><?php echo htmlspecialchars($userName ?: 'User'); ?></div>
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="rating-row">
                            <div class="rating-stars">
                                <input type="radio" id="star5" name="rating" value="5">
                                <label for="star5">★</label>
                                <input type="radio" id="star4" name="rating" value="4">
                                <label for="star4">★</label>
                                <input type="radio" id="star3" name="rating" value="3">
                                <label for="star3">★</label>
                                <input type="radio" id="star2" name="rating" value="2">
                                <label for="star2">★</label>
                                <input type="radio" id="star1" name="rating" value="1" required>
                                <label for="star1">★</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comment">Your Review</label>
                        <textarea id="comment" name="comment" class="review-textarea" placeholder="Tell us about your experience..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <button class="btn-save" type="submit">Post review</button>
                    </div>
                </form>

                <?php if (!empty($userReviews)): ?>
                    <div class="review-list">
                        <?php foreach ($userReviews as $review): ?>
                            <div class="review-card">
                                <div class="review-meta">
                                    <div class="review-stars">
                                        <?php echo str_repeat('★', (int)$review['rating']); ?>
                                    </div>
                                    <div class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($review['created_at']))); ?></div>
                                </div>
                                <div class="review-body"><?php echo htmlspecialchars($review['comment']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
