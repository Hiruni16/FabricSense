<?php
session_start();
require_once 'DB.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: userLogin.html");
    exit();
}

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

try {
    $db = DB::connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($fullName === '' || $email === '') {
            throw new Exception('Full name and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address.');
        }

        $check = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id <> :id LIMIT 1");
        $check->execute(['email' => $email, 'id' => $userId]);
        if ($check->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('This email is already in use by another account.');
        }

        $params = [
            'full_name' => $fullName,
            'email' => $email,
            'id' => $userId
        ];

        $sql = "UPDATE users SET full_name = :full_name, email = :email, updated_at = NOW()";

        if ($newPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            if (strlen($newPassword) < 6) {
                throw new Exception('Password must be at least 6 characters.');
            }
            $sql .= ", password = :password";
            $params['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        $sql .= " WHERE user_id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;

        $successMessage = 'Profile updated successfully.';
    }

    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE user_id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found.');
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $user = $user ?? ['full_name' => '', 'email' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FabricSense</title>
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
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.7rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .alert { padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; font-weight: 600; }
        .alert-success { background: rgba(67, 206, 162, 0.15); color: #185a9d; border: 1px solid rgba(67, 206, 162, 0.4); }
        .alert-error { background: rgba(233, 69, 96, 0.1); color: #c92a49; border: 1px solid rgba(233, 69, 96, 0.3); }

        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.4rem; }
        input { width: 100%; padding: 0.9rem 1rem; border-radius: 12px; border: 1.5px solid #e1e5ea; font-size: 1rem; background: #fff; }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(233,69,96,0.15); }

        .form-actions { text-align: right; margin-top: 1.4rem; }
        .btn-save { padding: 0.8rem 1.9rem; border: none; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--accent-light)); color: #fff; font-weight: 700; cursor: pointer; box-shadow: 0 12px 26px rgba(233,69,96,0.3); }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 16px 30px rgba(233,69,96,0.35); }


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
                <a class="sidebar-item active" href="userProfile.php">My Profile</a>
                <a class="sidebar-item" href="userIdentifications.php">My Identifications</a>
                <a class="sidebar-item" href="userEstimates.php">My Estimates</a>
                <a class="sidebar-item" href="userReviews.php">My Reviews</a>
            </aside>

            <main class="profile-content">
                <a class="close-btn" href="loginHomePage.html" title="Close" aria-label="Close">×</a>
                <h2 class="section-title">My Profile</h2>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                    </div>

                    <div class="form-actions">
                        <button class="btn-save" type="submit">Save changes</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
