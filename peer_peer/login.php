<?php
// login.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "CSRF security verification failed. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) $errors[] = "Email is required.";
        if (empty($password)) $errors[] = "Password is required.";

        if (empty($errors)) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Start session and store user data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_image'] = $user['profile_image'];

                    $_SESSION['success_msg'] = "Welcome back, " . $user['name'] . "!";
                    
                    if ($user['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: user/dashboard.php");
                    }
                    exit;
                } else {
                    $errors[] = "Incorrect email or password.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card card-custom">
            <div class="card-header-custom text-center py-4">
                <h2 class="mb-0 text-gradient">Login</h2>
                <p class="text-secondary mb-0 mt-2">Sign in to exchange your textbooks</p>
            </div>
            <div class="card-body-custom p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label text-secondary">Email Address</label>
                        <input type="email" class="form-control form-control-custom" id="email" name="email" value="<?php echo sanitize($email); ?>" required placeholder="e.g. john@university.edu">
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <label for="password" class="form-label text-secondary mb-0">Password</label>
                        </div>
                        <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="Enter password">
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary-gradient py-2">Sign In</button>
                    </div>

                    <p class="text-center text-secondary mb-0">Don't have an account? <a href="register.php" class="text-gradient fw-bold text-decoration-none">Register here</a></p>
                </form>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
