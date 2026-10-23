<?php
require_once __DIR__ . '/../db.php';

// Redirect if admin is already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — SKZ Store</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <main class="main-content container" style="max-width: 440px; padding-top: 64px;">
        <div class="card">
            <h2 style="margin-bottom: 8px; color: var(--dark); text-align: center;">SKZ Store Admin</h2>
            <p style="text-align: center; color: var(--muted); font-size: 0.92rem; margin-bottom: 20px;">
                Sign in to manage products and customer orders.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-block">Sign In</button>
            </form>

            <div style="text-align: center; margin-top: 18px; font-size: 0.9rem;">
                <a href="../index.php">&larr; Back to SKZ Store</a>
            </div>
        </div>
    </main>
</body>
</html>