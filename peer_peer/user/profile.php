<?php
// user/profile.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$myId = getCurrentUserId();
$db = getDBConnection();
$errors = [];

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "CSRF verification failed. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $university = trim($_POST['university'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) $errors[] = "Name is required.";
        if (empty($university)) $errors[] = "University is required.";

        // Handle Profile Image Upload
        $profile_image_path = null;
        if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Upload failed with error code: " . $file['error'];
            } elseif (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Invalid format. Only JPG, PNG, and WEBP are allowed.";
            } elseif ($file['size'] > $max_size) {
                $errors[] = "Image size exceeds 2MB.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('profile_', true) . '.' . $ext;
                $target_path = __DIR__ . '/../uploads/profiles/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $profile_image_path = 'uploads/profiles/' . $filename;
                } else {
                    $errors[] = "Failed to save uploaded profile image.";
                }
            }
        }

        if (empty($errors)) {
            try {
                if ($profile_image_path) {
                    $stmt = $db->prepare("UPDATE users SET name = ?, university = ?, department = ?, phone = ?, profile_image = ? WHERE id = ?");
                    $stmt->execute([$name, $university, $department ?: null, $phone ?: null, $profile_image_path, $myId]);
                    $_SESSION['user_image'] = $profile_image_path; // update session
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, university = ?, department = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $university, $department ?: null, $phone ?: null, $myId]);
                }
                $_SESSION['user_name'] = $name; // update session
                $_SESSION['success_msg'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database update error: " . $e->getMessage();
            }
        }
    }
}

// Recalculate average rating cache
try {
    $ratingStmt = $db->prepare("SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?");
    $ratingStmt->execute([$myId]);
    $newAvg = $ratingStmt->fetchColumn();
    $newAvg = $newAvg !== null ? round($newAvg, 2) : 0.00;

    $updateRating = $db->prepare("UPDATE users SET average_rating = ? WHERE id = ?");
    $updateRating->execute([$newAvg, $myId]);

    // Fetch user details
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$myId]);
    $user = $userStmt->fetch();

    if (!$user) {
        die("User session not found.");
    }

    // Fetch reviews received
    $reviewsStmt = $db->prepare("SELECT r.*, u.name as reviewer_name, u.profile_image as reviewer_image, tk.title as book_title
                                 FROM reviews r
                                 JOIN users u ON r.reviewer_id = u.id
                                 JOIN transactions t ON r.transaction_id = t.id
                                 JOIN textbooks tk ON t.textbook_id = tk.id
                                 WHERE r.reviewee_id = ?
                                 ORDER BY r.created_at DESC");
    $reviewsStmt->execute([$myId]);
    $reviews = $reviewsStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <!-- Left Column: Edit Profile Form -->
    <div class="col-lg-6 mb-4">
        <div class="card card-custom">
            <div class="card-header-custom">
                <h4 class="mb-0 text-gradient"><i class="bi bi-person-gear me-2"></i>My Profile Details</h4>
            </div>
            <div class="card-body-custom p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Profile Pic Display -->
                    <div class="text-center mb-4">
                        <?php if ($user['profile_image']): ?>
                            <img src="../<?php echo sanitize($user['profile_image']); ?>" id="profile-img-preview" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid var(--accent-primary);">
                        <?php else: ?>
                            <div id="profile-placeholder" class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center text-white mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <img id="profile-img-preview" src="#" alt="Preview" class="rounded-circle mb-3 d-none" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid var(--accent-primary);">
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <label for="profile_image" class="form-label text-secondary small">Change Profile Picture</label>
                            <input class="form-control form-control-custom form-control-sm mx-auto" style="max-width: 280px;" type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this, 'profile-img-preview'); if(document.getElementById('profile-placeholder')) { document.getElementById('profile-placeholder').classList.add('d-none'); }">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary">Email Address (Cannot change)</label>
                        <input type="email" class="form-control form-control-custom bg-black bg-opacity-20 text-muted" value="<?php echo sanitize($user['email']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label text-secondary">Full Name</label>
                        <input type="text" class="form-control form-control-custom" id="name" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="university" class="form-label text-secondary">University / College</label>
                        <input type="text" class="form-control form-control-custom" id="university" name="university" value="<?php echo sanitize($user['university']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label text-secondary">Department</label>
                        <input type="text" class="form-control form-control-custom" id="department" name="department" value="<?php echo sanitize($user['department']); ?>">
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label text-secondary">Phone Number</label>
                        <input type="text" class="form-control form-control-custom" id="phone" name="phone" value="<?php echo sanitize($user['phone']); ?>">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gradient py-2">Save Profile Updates</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Reputation & Reviews -->
    <div class="col-lg-6 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h4 class="mb-0 text-gradient"><i class="bi bi-star me-2"></i>My Reputation</h4>
                <div class="d-flex align-items-center bg-input border border-border px-3 py-1 rounded-pill">
                    <span class="text-warning fw-bold fs-5 me-2">★ <?php echo number_format($user['average_rating'], 2); ?></span>
                    <small class="text-secondary">Average</small>
                </div>
            </div>
            <div class="card-body-custom p-4">
                <h5 class="text-white mb-3">Student Reviews (<?php echo count($reviews); ?>)</h5>
                
                <?php if (empty($reviews)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-chat-square-quote fs-1"></i>
                        <p class="mt-2 mb-0">No reviews received yet. Complete transactions with students to earn ratings.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-y-auto" style="max-height: 450px;">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="review-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <?php if ($rev['reviewer_image']): ?>
                                            <img src="../<?php echo sanitize($rev['reviewer_image']); ?>" class="rounded-circle me-2" style="width: 28px; height: 28px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-2" style="width: 28px; height: 28px; font-size: 0.8rem; font-weight: 600;">
                                                <?php echo strtoupper(substr($rev['reviewer_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="text-white small fw-bold"><?php echo sanitize($rev['reviewer_name']); ?></span>
                                    </div>
                                    <div class="star-rating small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo ($i <= $rev['rating']) ? '-fill' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="text-secondary small mb-1">Book: <strong class="text-white"><?php echo sanitize($rev['book_title']); ?></strong></p>
                                <p class="text-secondary small mb-0 font-italic">"<?php echo sanitize($rev['comment']); ?>"</p>
                                <div class="text-end">
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
