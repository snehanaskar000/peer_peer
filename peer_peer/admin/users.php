<?php
// admin/users.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$myId = getCurrentUserId();
$db = getDBConnection();
$errors = [];

// Handle Admin Actions on Users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "CSRF verification failed.";
        header("Location: users.php");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId === $myId) {
        $_SESSION['error_msg'] = "You cannot modify your own administrator account.";
        header("Location: users.php");
        exit;
    }

    if ($action === 'change_role') {
        $newRole = trim($_POST['role'] ?? 'user');
        if (in_array($newRole, ['user', 'admin'])) {
            try {
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
                $_SESSION['success_msg'] = "User role updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Failed to update role: " . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        try {
            // Delete user avatar file if exists
            $imgStmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
            $imgStmt->execute([$userId]);
            $path = $imgStmt->fetchColumn();

            if ($path && file_exists(__DIR__ . '/../' . $path)) {
                unlink(__DIR__ . '/../' . $path);
            }

            // Database cascade deletes dependent entries
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['success_msg'] = "User account and all related listings/messages deleted.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to delete user: " . $e->getMessage();
        }
    }

    header("Location: users.php");
    exit;
}

// Fetch all users
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="text-warning mb-1"><i class="bi bi-people me-2"></i>User Accounts Administration</h2>
        <p class="text-secondary mb-0">Modify student roles or delete user accounts violating terms.</p>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white"><i class="bi bi-list-columns-reverse me-2"></i>All Registered Users</h5>
        <span class="text-secondary small"><?php echo count($users); ?> users registered</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                <thead>
                    <tr class="border-bottom border-border">
                        <th scope="col" class="ps-3" style="width: 60px;">ID</th>
                        <th scope="col">Name / Email</th>
                        <th scope="col">College / Department</th>
                        <th scope="col">Reputation</th>
                        <th scope="col">Role</th>
                        <th scope="col">Registered</th>
                        <th scope="col" class="text-end pe-3" style="width: 250px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-bottom border-border">
                            <td class="ps-3 text-secondary"><?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="../<?php echo sanitize($user['profile_image']); ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-2" style="width: 32px; height: 32px; font-size: 0.8rem; font-weight: 600;">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="text-white fw-bold"><?php echo sanitize($user['name']); ?></div>
                                        <small class="text-secondary"><?php echo sanitize($user['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo sanitize($user['university'] ?: 'N/A'); ?></div>
                                <small class="text-secondary"><?php echo sanitize($user['department'] ?: ''); ?></small>
                            </td>
                            <td>
                                <span class="text-warning fw-bold">★ <?php echo number_format($user['average_rating'], 1); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                    <?php echo strtoupper($user['role']); ?>
                                </span>
                            </td>
                            <td><small class="text-secondary"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></td>
                            <td class="text-end pe-3">
                                <?php if ($user['id'] !== $myId): ?>
                                    <div class="d-inline-flex gap-2">
                                        <!-- Role toggle form -->
                                        <form method="POST" action="users.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="change_role">
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <input type="hidden" name="role" value="user">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Demote to User">
                                                    Demote
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="role" value="admin">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Promote to Admin">
                                                    Promote
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Delete account form -->
                                        <form method="POST" action="users.php" onsubmit="return confirm('DANGER: Delete this user account? All listings, chats, and trade records will be permanently removed!');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Delete User">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary small italic">Current Admin</span>
                                <?php endif; ?>
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
