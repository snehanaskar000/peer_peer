<?php
// register.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$name = $email = $university = $department = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "CSRF security verification failed. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $university = trim($_POST['university'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validation
        if (empty($name)) $errors[] = "Name is required.";
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        if (empty($university)) $errors[] = "University is required.";

        // Check if email already exists
        if (empty($errors)) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "An account with this email already exists.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }

        // Handle Profile Image Upload
        $profile_image_path = null;
        if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "File upload failed with error code " . $file['error'];
            } elseif (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Invalid image file type. Only JPG, PNG, and WEBP are allowed.";
            } elseif ($file['size'] > $max_size) {
                $errors[] = "Image size exceeds the 2MB limit.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('profile_', true) . '.' . $ext;
                $target_dir = __DIR__ . '/uploads/profiles/';
                
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $target_path = $target_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $profile_image_path = 'uploads/profiles/' . $filename;
                } else {
                    $errors[] = "Failed to save the uploaded image.";
                }
            }
        }

        // Insert into Database
        if (empty($errors)) {
            try {
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, university, department, phone, profile_image, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $email,
                    $passHash,
                    $university,
                    $department ?: null,
                    $phone ?: null,
                    $profile_image_path,
                    'user'
                ]);

                $_SESSION['success_msg'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Error registering user: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-8 col-lg-6">
        <div class="card card-custom">
            <div class="card-header-custom text-center py-4">
                <h2 class="mb-0 text-gradient">Create Account</h2>
                <p class="text-secondary mb-0 mt-2">Join the P2P Textbook Exchange community</p>
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

                <form method="POST" action="register.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

                    <!-- Profile Image Upload & Preview -->
                    <div class="text-center mb-4">
                        <label for="profile_image" class="form-label d-block text-secondary">Profile Image</label>
                        <div class="d-inline-block position-relative mb-2">
                            <img id="avatar-preview" src="#" alt="Avatar Preview" class="rounded-circle d-none" style="width: 100px; height: 100px; object-fit: cover; border: 2px solid var(--accent-primary);">
                            <div id="avatar-placeholder" class="rounded-circle d-flex align-items-center justify-content-center text-secondary border border-border" style="width: 100px; height: 100px; font-size: 2rem; background-color: var(--bg-input);">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        </div>
                        <input class="form-control form-control-custom form-control-sm mx-auto" style="max-width: 250px;" type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this, 'avatar-preview'); document.getElementById('avatar-placeholder').classList.add('d-none');">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label text-secondary">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="name" name="name" value="<?php echo sanitize($name); ?>" required placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label text-secondary">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-custom" id="email" name="email" value="<?php echo sanitize($email); ?>" required placeholder="e.g. name@university.edu">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label text-secondary">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="Min 6 characters">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label text-secondary">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="university" class="form-label text-secondary">College / University <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="university" name="university" value="<?php echo sanitize($university); ?>" required placeholder="e.g. Harvard University">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label text-secondary">Department</label>
                            <input type="text" class="form-control form-control-custom" id="department" name="department" value="<?php echo sanitize($department); ?>" placeholder="e.g. Computer Science">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label text-secondary">Contact Number</label>
                        <input type="text" class="form-control form-control-custom" id="phone" name="phone" value="<?php echo sanitize($phone); ?>" placeholder="e.g. +1 (555) 000-0000">
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary-gradient py-2">Create Account</button>
                    </div>

                    <p class="text-center text-secondary mb-0">Already have an account? <a href="login.php" class="text-gradient fw-bold text-decoration-none">Login here</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
