<?php
// admin/listings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$db = getDBConnection();

// Handle Listing Deletion by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "CSRF verification failed.";
        header("Location: listings.php");
        exit;
    }

    $bookId = (int)($_POST['textbook_id'] ?? 0);

    try {
        // Fetch images to delete files
        $imgStmt = $db->prepare("SELECT image_path FROM textbook_images WHERE textbook_id = ?");
        $imgStmt->execute([$bookId]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($images as $path) {
            $fullPath = __DIR__ . '/../' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Delete from DB (cascades)
        $delStmt = $db->prepare("DELETE FROM textbooks WHERE id = ?");
        $delStmt->execute([$bookId]);

        $_SESSION['success_msg'] = "Listing has been successfully removed by Admin moderation.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Failed to remove listing: " . $e->getMessage();
    }

    header("Location: listings.php");
    exit;
}

// Fetch all listings
try {
    $stmt = $db->query("SELECT t.*, u.name as owner_name, u.email as owner_email,
                        (SELECT image_path FROM textbook_images WHERE textbook_id = t.id LIMIT 1) as main_image
                        FROM textbooks t
                        JOIN users u ON t.user_id = u.id
                        ORDER BY t.created_at DESC");
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="text-warning mb-1"><i class="bi bi-book me-2"></i>Textbook Listings Moderation</h2>
        <p class="text-secondary mb-0">Browse and manage all uploaded textbooks. Delete spam or prohibited items.</p>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="text-white mb-0"><i class="bi bi-book-half me-2"></i>All Textbook Listings</h5>
        <span class="text-secondary small"><?php echo count($listings); ?> total listings</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                <thead>
                    <tr class="border-bottom border-border">
                        <th scope="col" class="ps-3" style="width: 80px;">Cover</th>
                        <th scope="col">Book Info</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Details</th>
                        <th scope="col">Price / Type</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-3" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $book): ?>
                        <tr class="border-bottom border-border">
                            <td class="ps-3">
                                <?php if ($book['main_image']): ?>
                                    <img src="../<?php echo sanitize($book['main_image']); ?>" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center text-secondary" style="width: 48px; height: 48px;">
                                        <i class="bi bi-book-half"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-white fw-bold text-truncate" style="max-width: 250px;" title="<?php echo sanitize($book['title']); ?>">
                                    <?php echo sanitize($book['title']); ?>
                                </div>
                                <small class="text-secondary">By <?php echo sanitize($book['author']); ?></small>
                            </td>
                            <td>
                                <div><?php echo sanitize($book['owner_name']); ?></div>
                                <small class="text-secondary"><?php echo sanitize($book['owner_email']); ?></small>
                            </td>
                            <td>
                                <div class="small">ISBN: <?php echo sanitize($book['isbn'] ?: 'N/A'); ?></div>
                                <span class="badge badge-custom badge-<?php echo strtolower($book['condition']); ?>"><?php echo sanitize($book['condition']); ?></span>
                                <small class="text-secondary">Ed. <?php echo sanitize($book['edition'] ?: '-'); ?></small>
                            </td>
                            <td>
                                <?php if (in_array($book['exchange_type'], ['sell', 'rent'])): ?>
                                    <span class="text-success fw-bold">$<?php echo number_format($book['price'], 2); ?></span>
                                    <small class="text-secondary small">(<?php echo ucfirst($book['exchange_type']); ?>)</small>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-<?php echo strtolower($book['exchange_type']); ?>"><?php echo ucfirst($book['exchange_type']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-custom badge-<?php echo strtolower($book['status']); ?>">
                                    <?php echo strtoupper($book['status']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <form method="POST" action="listings.php" onsubmit="return confirm('Are you sure you want to delete this listing from the database? This action is permanent.');" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="textbook_id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Remove Listing">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
