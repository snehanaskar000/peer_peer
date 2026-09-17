<?php
// user/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$myId = getCurrentUserId();
$db = getDBConnection();

try {
    // 1. Stats calculations
    $stmt1 = $db->prepare("SELECT COUNT(*) FROM textbooks WHERE user_id = ? AND status = 'available'");
    $stmt1->execute([$myId]);
    $activeListings = (int)$stmt1->fetchColumn();

    $stmt2 = $db->prepare("SELECT COUNT(*) FROM transactions WHERE seller_id = ? AND status = 'pending'");
    $stmt2->execute([$myId]);
    $pendingRequests = (int)$stmt2->fetchColumn();

    $stmt3 = $db->prepare("SELECT average_rating FROM users WHERE id = ?");
    $stmt3->execute([$myId]);
    $avgRating = (float)$stmt3->fetchColumn();

    $stmt4 = $db->prepare("SELECT COUNT(*) FROM transactions WHERE (seller_id = ? OR buyer_id = ?) AND status = 'completed'");
    $stmt4->execute([$myId, $myId]);
    $completedTrades = (int)$stmt4->fetchColumn();

    // 2. Fetch recent incoming requests
    $reqStmt = $db->prepare("SELECT t.*, b.name as buyer_name, b.average_rating as buyer_rating, tk.title as book_title, tk.exchange_type, tk.price 
                              FROM transactions t
                              JOIN users b ON t.buyer_id = b.id
                              JOIN textbooks tk ON t.textbook_id = tk.id
                              WHERE t.seller_id = ? AND t.status = 'pending'
                              ORDER BY t.created_at DESC LIMIT 5");
    $reqStmt->execute([$myId]);
    $incomingRequests = $reqStmt->fetchAll();

    // 3. Fetch active chat partners
    // We get distinct users who have sent messages to us or received messages from us
    $chatStmt = $db->prepare("SELECT u.id, u.name, u.profile_image, u.university,
                              (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
                              (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_msg_time,
                              (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                              FROM users u
                              WHERE u.id IN (
                                  SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END 
                                  FROM messages 
                                  WHERE sender_id = ? OR receiver_id = ?
                              )
                              ORDER BY last_msg_time DESC LIMIT 5");
    $chatStmt->execute([$myId, $myId, $myId, $myId, $myId, $myId, $myId, $myId]);
    $recentChats = $chatStmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    $activeListings = $pendingRequests = $completedTrades = 0;
    $avgRating = 0.0;
    $incomingRequests = [];
    $recentChats = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h2 class="text-gradient mb-1">Student Dashboard</h2>
        <p class="text-secondary mb-0">Welcome back, <?php echo sanitize($_SESSION['user_name']); ?>! Manage your textbooks, trades, and messages here.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <a href="listings.php?action=new" class="btn btn-primary-gradient"><i class="bi bi-plus-lg me-1"></i>List New Book</a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-5">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-secondary small mb-1">My Active Books</div>
            <div class="stat-number text-white"><?php echo $activeListings; ?></div>
            <a href="listings.php" class="text-gradient small text-decoration-none">View Listings <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-secondary small mb-1">Incoming Requests</div>
            <div class="stat-number text-warning"><?php echo $pendingRequests; ?></div>
            <a href="transactions.php" class="text-warning small text-decoration-none">Manage Trades <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-secondary small mb-1">Completed Trades</div>
            <div class="stat-number text-success"><?php echo $completedTrades; ?></div>
            <a href="transactions.php#history" class="text-success small text-decoration-none">Trade History <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="text-secondary small mb-1">Average Reputation</div>
            <div class="stat-number text-info d-flex align-items-center">
                <?php echo number_format($avgRating, 1); ?>
                <i class="bi bi-star-fill text-warning ms-2" style="font-size: 1.25rem;"></i>
            </div>
            <a href="profile.php" class="text-info small text-decoration-none">View Reviews <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Active Offers / Requests -->
    <div class="col-lg-6 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h4 class="mb-0 text-gradient"><i class="bi bi-arrow-left-right me-2"></i>Recent Trade Requests</h4>
                <a href="transactions.php" class="btn btn-outline-light border-secondary btn-sm">All Requests</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($incomingRequests)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">No pending trade requests at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($incomingRequests as $req): ?>
                            <div class="list-group-item bg-transparent border-border p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6 class="text-white mb-0"><?php echo sanitize($req['book_title']); ?></h6>
                                    <div>
                                        <?php if ($req['exchange_type'] == 'sell'): ?>
                                            <span class="text-success small fw-bold">$<?php echo number_format($req['price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-custom badge-<?php echo strtolower($req['exchange_type']); ?>"><?php echo ucfirst($req['exchange_type']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-secondary">Requested by: <strong><?php echo sanitize($req['buyer_name']); ?></strong> (★<?php echo number_format($req['buyer_rating'], 1); ?>)</small>
                                        <div class="text-secondary small"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="transactions.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Accept</button>
                                        </form>
                                        <form method="POST" action="transactions.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Reject</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Chats -->
    <div class="col-lg-6 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h4 class="mb-0 text-gradient"><i class="bi bi-chat-dots me-2"></i>Active Conversations</h4>
                <a href="messages.php" class="btn btn-outline-light border-secondary btn-sm">All Messages</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($recentChats)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-chat-left-text fs-1"></i>
                        <p class="mt-2 mb-0">No active messages. Browse books to make trade offers!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($recentChats as $chat): ?>
                            <a href="messages.php?contact_id=<?php echo $chat['id']; ?>" class="list-group-item list-group-item-action bg-transparent border-border p-3 d-flex align-items-center text-decoration-none">
                                <?php if ($chat['profile_image']): ?>
                                    <img src="../<?php echo sanitize($chat['profile_image']); ?>" class="rounded-circle me-3" style="width: 42px; height: 42px; object-fit: cover;" alt="Partner Photo">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-3" style="width: 42px; height: 42px; font-weight: 600;">
                                        <?php echo strtoupper(substr($chat['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <h6 class="text-white mb-0 text-truncate"><?php echo sanitize($chat['name']); ?></h6>
                                        <small class="text-secondary" style="font-size: 0.75rem;"><?php echo date('M d, h:i A', strtotime($chat['last_msg_time'])); ?></small>
                                    </div>
                                    <p class="text-secondary small mb-0 text-truncate text-muted">
                                        <?php echo sanitize($chat['last_message']); ?>
                                    </p>
                                </div>
                                <?php if ($chat['unread_count'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-2"><?php echo $chat['unread_count']; ?></span>
                                <?php endif; ?>
                            </a>
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
