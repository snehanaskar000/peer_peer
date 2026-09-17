<?php
// user/listings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$myId = getCurrentUserId();
$db = getDBConnection();

$action = $_GET['action'] ?? 'list';
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// ----------------------------------------------------
// SECURITY CHECK: Verify book ownership
// ----------------------------------------------------
if ($bookId > 0 && in_array($action, ['edit', 'delete', 'update', 'status', 'delete_image'])) {
    try {
        $stmt = $db->prepare("SELECT user_id FROM textbooks WHERE id = ?");
        $stmt->execute([$bookId]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId !== $myId) {
            $_SESSION['error_msg'] = "Access denied: You do not own this listing.";
            header("Location: listings.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// ----------------------------------------------------
// POST ROUTER
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "CSRF verification failed. Try again.";
        header("Location: listings.php");
        exit;
    }

    $postAction = $_POST['action'] ?? '';

    // Create New Listing
    if ($postAction === 'create') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $edition = trim($_POST['edition'] ?? '');
        $condition = trim($_POST['condition'] ?? '');
        $exchange_type = trim($_POST['exchange_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) $errors[] = "Title is required.";
        if (empty($author)) $errors[] = "Author is required.";
        if (empty($condition)) $errors[] = "Condition is required.";
        if (empty($exchange_type)) $errors[] = "Transaction Type is required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO textbooks (user_id, title, author, isbn, subject, edition, `condition`, price, exchange_type, description, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
                $stmt->execute([$myId, $title, $author, $isbn ?: null, $subject ?: null, $edition ?: null, $condition, $price, $exchange_type, $description ?: null]);
                $newBookId = $db->lastInsertId();

                // Process multiple file uploads
                if (isset($_FILES['book_images']) && !empty($_FILES['book_images']['name'][0])) {
                    $files = $_FILES['book_images'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                    $max_size = 2 * 1024 * 1024; // 2MB

                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            if (!in_array($files['type'][$i], $allowed_types)) {
                                $errors[] = "Image '" . $files['name'][$i] . "' skipped: invalid format.";
                                continue;
                            }
                            if ($files['size'][$i] > $max_size) {
                                $errors[] = "Image '" . $files['name'][$i] . "' skipped: exceeds 2MB.";
                                continue;
                            }

                            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                            $filename = uniqid('book_' . $newBookId . '_', true) . '.' . $ext;
                            $target_path = __DIR__ . '/../uploads/textbooks/' . $filename;

                            if (move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                                $imgStmt = $db->prepare("INSERT INTO textbook_images (textbook_id, image_path) VALUES (?, ?)");
                                $imgStmt->execute([$newBookId, 'uploads/textbooks/' . $filename]);
                            }
                        }
                    }
                }

                $db->commit();
                $_SESSION['success_msg'] = "Book listed successfully!";
                header("Location: listings.php");
                exit;

            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = "Failed to list book: " . $e->getMessage();
            }
        }
    }

    // Update Existing Listing
    if ($postAction === 'update') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $edition = trim($_POST['edition'] ?? '');
        $condition = trim($_POST['condition'] ?? '');
        $exchange_type = trim($_POST['exchange_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'available');

        if (empty($title)) $errors[] = "Title is required.";
        if (empty($author)) $errors[] = "Author is required.";
        if (empty($condition)) $errors[] = "Condition is required.";
        if (empty($exchange_type)) $errors[] = "Transaction Type is required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE textbooks 
                                      SET title = ?, author = ?, isbn = ?, subject = ?, edition = ?, `condition` = ?, price = ?, exchange_type = ?, description = ?, status = ? 
                                      WHERE id = ?");
                $stmt->execute([$title, $author, $isbn ?: null, $subject ?: null, $edition ?: null, $condition, $price, $exchange_type, $description ?: null, $status, $bookId]);

                // Process additional file uploads
                if (isset($_FILES['book_images']) && !empty($_FILES['book_images']['name'][0])) {
                    $files = $_FILES['book_images'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                    $max_size = 2 * 1024 * 1024; // 2MB

                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            if (!in_array($files['type'][$i], $allowed_types)) {
                                continue;
                            }
                            if ($files['size'][$i] > $max_size) {
                                continue;
                            }

                            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                            $filename = uniqid('book_' . $bookId . '_', true) . '.' . $ext;
                            $target_path = __DIR__ . '/../uploads/textbooks/' . $filename;

                            if (move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                                $imgStmt = $db->prepare("INSERT INTO textbook_images (textbook_id, image_path) VALUES (?, ?)");
                                $imgStmt->execute([$bookId, 'uploads/textbooks/' . $filename]);
                            }
                        }
                    }
                }

                $db->commit();
                $_SESSION['success_msg'] = "Listing updated successfully!";
                header("Location: listings.php");
                exit;

            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = "Failed to update book: " . $e->getMessage();
            }
        }
    }

    // Delete Listing
    if ($postAction === 'delete') {
        try {
            // Fetch images first to delete from storage
            $imgQuery = $db->prepare("SELECT image_path FROM textbook_images WHERE textbook_id = ?");
            $imgQuery->execute([$bookId]);
            $images = $imgQuery->fetchAll(PDO::FETCH_COLUMN);

            foreach ($images as $path) {
                $fullPath = __DIR__ . '/../' . $path;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            // Delete book records
            $stmt = $db->prepare("DELETE FROM textbooks WHERE id = ?");
            $stmt->execute([$bookId]);

            $_SESSION['success_msg'] = "Listing deleted successfully.";
            header("Location: listings.php");
            exit;

        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to delete listing: " . $e->getMessage();
            header("Location: listings.php");
            exit;
        }
    }

    // Delete Specific Image
    if ($postAction === 'delete_image') {
        $imageId = (int)$_POST['image_id'];
        try {
            $imgQuery = $db->prepare("SELECT image_path FROM textbook_images WHERE id = ? AND textbook_id = ?");
            $imgQuery->execute([$imageId, $bookId]);
            $path = $imgQuery->fetchColumn();

            if ($path) {
                $fullPath = __DIR__ . '/../' . $path;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                $delStmt = $db->prepare("DELETE FROM textbook_images WHERE id = ?");
                $delStmt->execute([$imageId]);
                $_SESSION['success_msg'] = "Image removed.";
            }
            header("Location: listings.php?action=edit&id=" . $bookId);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error deleting image: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------
// RENDER ROUTER
// ----------------------------------------------------
require_once __DIR__ . '/../includes/header.php';

if ($action === 'new'):
?>
    <!-- Create Book Form -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 text-gradient"><i class="bi bi-plus-circle me-2"></i>List a Textbook</h4>
                    <a href="listings.php" class="btn btn-outline-light border-secondary btn-sm">Back to Listings</a>
                </div>
                <div class="card-body-custom p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0"><?php foreach ($errors as $error) echo "<li>" . sanitize($error) . "</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="listings.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="hidden" name="action" value="create">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label text-secondary">Book Title *</label>
                                <input type="text" class="form-control form-control-custom" id="title" name="title" required placeholder="e.g. Introduction to Algorithms">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label text-secondary">Author Name(s) *</label>
                                <input type="text" class="form-control form-control-custom" id="author" name="author" required placeholder="e.g. Cormen, Leiserson">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="isbn" class="form-label text-secondary">ISBN Number</label>
                                <input type="text" class="form-control form-control-custom" id="isbn" name="isbn" placeholder="e.g. 9780262033848">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="subject" class="form-label text-secondary">Subject / Department</label>
                                <input type="text" class="form-control form-control-custom" id="subject" name="subject" placeholder="e.g. Computer Science">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edition" class="form-label text-secondary">Edition</label>
                                <input type="text" class="form-control form-control-custom" id="edition" name="edition" placeholder="e.g. 3rd Edition">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="condition" class="form-label text-secondary">Condition *</label>
                                <select class="form-select form-control-custom" id="condition" name="condition" required>
                                    <option value="New">New</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="exchange_type" class="form-label text-secondary">Transaction Type *</label>
                                <select class="form-select form-control-custom" id="exchange_type" name="exchange_type" onchange="togglePrice(this.value)" required>
                                    <option value="sell">Sell</option>
                                    <option value="exchange">Exchange</option>
                                    <option value="rent">Rent</option>
                                    <option value="donate">Donate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="price-wrapper">
                                <label for="price" class="form-label text-secondary">Price ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-custom" id="price" name="price" value="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="book_images" class="form-label text-secondary">Upload Book Images (Max 3, 2MB each)</label>
                            <input class="form-control form-control-custom" type="file" id="book_images" name="book_images[]" multiple accept="image/*">
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label text-secondary">Description</label>
                            <textarea class="form-control form-control-custom" id="description" name="description" rows="4" placeholder="Mention highlighting, missing pages, or exchange requirements..."></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-gradient py-2">Create Listing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    function togglePrice(val) {
        const wrapper = document.getElementById('price-wrapper');
        const input = document.getElementById('price');
        if (val === 'sell' || val === 'rent') {
            wrapper.style.display = 'block';
            if (input.value == 0) input.value = '10.00';
        } else {
            wrapper.style.display = 'none';
            input.value = '0.00';
        }
    }
    </script>

<?php
elseif ($action === 'edit' && $bookId > 0):
    try {
        $stmt = $db->prepare("SELECT * FROM textbooks WHERE id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        $imgQuery = $db->prepare("SELECT id, image_path FROM textbook_images WHERE textbook_id = ?");
        $imgQuery->execute([$bookId]);
        $images = $imgQuery->fetchAll();
    } catch (PDOException $e) {
        $errors[] = "Error loading book: " . $e->getMessage();
    }
?>
    <!-- Edit Book Form -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 text-gradient"><i class="bi bi-pencil-square me-2"></i>Edit Book Listing</h4>
                    <a href="listings.php" class="btn btn-outline-light border-secondary btn-sm">Cancel</a>
                </div>
                <div class="card-body-custom p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0"><?php foreach ($errors as $error) echo "<li>" . sanitize($error) . "</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="listings.php?id=<?php echo $bookId; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="hidden" name="action" value="update">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label text-secondary">Book Title *</label>
                                <input type="text" class="form-control form-control-custom" id="title" name="title" value="<?php echo sanitize($book['title']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label text-secondary">Author Name(s) *</label>
                                <input type="text" class="form-control form-control-custom" id="author" name="author" value="<?php echo sanitize($book['author']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="isbn" class="form-label text-secondary">ISBN Number</label>
                                <input type="text" class="form-control form-control-custom" id="isbn" name="isbn" value="<?php echo sanitize($book['isbn']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="subject" class="form-label text-secondary">Subject / Department</label>
                                <input type="text" class="form-control form-control-custom" id="subject" name="subject" value="<?php echo sanitize($book['subject']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edition" class="form-label text-secondary">Edition</label>
                                <input type="text" class="form-control form-control-custom" id="edition" name="edition" value="<?php echo sanitize($book['edition']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="condition" class="form-label text-secondary">Condition *</label>
                                <select class="form-select form-control-custom" id="condition" name="condition" required>
                                    <option value="New" <?php echo $book['condition'] === 'New' ? 'selected' : ''; ?>>New</option>
                                    <option value="Good" <?php echo $book['condition'] === 'Good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="Fair" <?php echo $book['condition'] === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="exchange_type" class="form-label text-secondary">Transaction Type *</label>
                                <select class="form-select form-control-custom" id="exchange_type" name="exchange_type" onchange="togglePrice(this.value)" required>
                                    <option value="sell" <?php echo $book['exchange_type'] === 'sell' ? 'selected' : ''; ?>>Sell</option>
                                    <option value="exchange" <?php echo $book['exchange_type'] === 'exchange' ? 'selected' : ''; ?>>Exchange</option>
                                    <option value="rent" <?php echo $book['exchange_type'] === 'rent' ? 'selected' : ''; ?>>Rent</option>
                                    <option value="donate" <?php echo $book['exchange_type'] === 'donate' ? 'selected' : ''; ?>>Donate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="price-wrapper" style="display: <?php echo in_array($book['exchange_type'], ['sell', 'rent']) ? 'block' : 'none'; ?>;">
                                <label for="price" class="form-label text-secondary">Price ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-custom" id="price" name="price" value="<?php echo number_format($book['price'], 2); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label text-secondary">Status</label>
                                <select class="form-select form-control-custom" id="status" name="status">
                                    <option value="available" <?php echo $book['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="sold" <?php echo $book['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                                    <option value="exchanged" <?php echo $book['status'] === 'exchanged' ? 'selected' : ''; ?>>Exchanged</option>
                                </select>
                            </div>
                        </div>

                        <!-- Display Current Images -->
                        <div class="mb-3">
                            <label class="form-label text-secondary d-block">Current Images</label>
                            <?php if (empty($images)): ?>
                                <span class="text-secondary small">No images uploaded yet.</span>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach ($images as $img): ?>
                                        <div class="col-auto position-relative">
                                            <img src="../<?php echo sanitize($img['image_path']); ?>" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                            <button type="submit" 
                                                    class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                    style="padding: 0.1rem 0.3rem; font-size: 0.75rem;"
                                                    onclick="return confirmImageDelete(<?php echo $img['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="book_images" class="form-label text-secondary">Add More Images</label>
                            <input class="form-control form-control-custom" type="file" id="book_images" name="book_images[]" multiple accept="image/*">
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label text-secondary">Description</label>
                            <textarea class="form-control form-control-custom" id="description" name="description" rows="4"><?php echo sanitize($book['description']); ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-gradient py-2">Update Listing</button>
                        </div>
                    </form>

                    <!-- Hidden Delete Image Helper Form -->
                    <form method="POST" id="deleteImgForm" action="listings.php?id=<?php echo $bookId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="hidden" name="action" value="delete_image">
                        <input type="hidden" name="image_id" id="delete_image_id" value="">
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    function togglePrice(val) {
        const wrapper = document.getElementById('price-wrapper');
        if (val === 'sell' || val === 'rent') {
            wrapper.style.display = 'block';
        } else {
            wrapper.style.display = 'none';
        }
    }
    function confirmImageDelete(imgId) {
        if (confirm("Are you sure you want to delete this image?")) {
            document.getElementById('delete_image_id').value = imgId;
            document.getElementById('deleteImgForm').submit();
            return true;
        }
        return false;
    }
    </script>

<?php
else: // list all own books
    try {
        $stmt = $db->prepare("SELECT t.*, 
                              (SELECT image_path FROM textbook_images WHERE textbook_id = t.id LIMIT 1) as main_image,
                              (SELECT COUNT(*) FROM transactions WHERE textbook_id = t.id AND status = 'pending') as pending_count
                              FROM textbooks t 
                              WHERE t.user_id = ? 
                              ORDER BY t.created_at DESC");
        $stmt->execute([$myId]);
        $myBooks = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error fetching books: ' . $e->getMessage() . '</div>';
    }
?>
    <!-- Listings View -->
    <div class="card card-custom">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-gradient"><i class="bi bi-journal-bookmark me-2"></i>My Book Listings</h4>
            <a href="listings.php?action=new" class="btn btn-primary-gradient btn-sm"><i class="bi bi-plus-lg me-1"></i>List a Book</a>
        </div>
        <div class="card-body-custom p-0">
            <?php if (empty($myBooks)): ?>
                <div class="text-center text-secondary py-5">
                    <i class="bi bi-book fs-1"></i>
                    <p class="mt-2 mb-0">You have not listed any textbooks yet.</p>
                    <a href="listings.php?action=new" class="text-gradient fw-bold text-decoration-none">Create your first listing now</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                        <thead>
                            <tr class="border-bottom border-border">
                                <th scope="col" class="ps-3" style="width: 80px;">Cover</th>
                                <th scope="col">Book Info</th>
                                <th scope="col">Condition</th>
                                <th scope="col">Type</th>
                                <th scope="col">Price</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end pe-3" style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myBooks as $book): ?>
                                <tr class="border-bottom border-border">
                                    <td class="ps-3">
                                        <?php if ($book['main_image']): ?>
                                            <img src="../<?php echo sanitize($book['main_image']); ?>" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center text-secondary" style="width: 50px; height: 50px;">
                                                <i class="bi bi-book-half"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white text-truncate" style="max-width: 250px;" title="<?php echo sanitize($book['title']); ?>">
                                            <?php echo sanitize($book['title']); ?>
                                        </div>
                                        <small class="text-secondary">By <?php echo sanitize($book['author']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-custom badge-<?php echo strtolower($book['condition']); ?>">
                                            <?php echo sanitize($book['condition']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-custom badge-<?php echo strtolower($book['exchange_type']); ?>">
                                            <?php echo strtoupper($book['exchange_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (in_array($book['exchange_type'], ['sell', 'rent'])): ?>
                                            <span class="text-success fw-bold">$<?php echo number_format($book['price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-custom badge-<?php echo strtolower($book['status']); ?>">
                                            <?php echo strtoupper($book['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-2">
                                            <?php if ($book['pending_count'] > 0): ?>
                                                <a href="transactions.php" class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Has pending trade requests!">
                                                    <i class="bi bi-exclamation-circle-fill"></i> (<?php echo $book['pending_count']; ?>)
                                                </a>
                                            <?php endif; ?>
                                            <a href="listings.php?action=edit&id=<?php echo $book['id']; ?>" class="btn btn-outline-light border-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Listing">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="listings.php?id=<?php echo $book['id']; ?>" onsubmit="return confirm('Are you sure you want to delete this listing? All images and records will be deleted.');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Delete Listing">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
