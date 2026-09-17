<?php
// user/transactions.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$myId = getCurrentUserId();
$db = getDBConnection();
$errors = [];

// ----------------------------------------------------
// TRANSACTION & POST ACTIONS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "CSRF verification failed. Try again.";
        header("Location: transactions.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create trade/purchase request
    if ($action === 'request') {
        $bookId = (int)($_POST['textbook_id'] ?? 0);

        try {
            // Check book status and owner
            $stmt = $db->prepare("SELECT * FROM textbooks WHERE id = ?");
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();

            if (!$book || $book['status'] !== 'available') {
                $_SESSION['error_msg'] = "Sorry, this book is no longer available.";
                header("Location: ../index.php");
                exit;
            }

            if ((int)$book['user_id'] === $myId) {
                $_SESSION['error_msg'] = "You cannot request your own textbook.";
                header("Location: ../index.php");
                exit;
            }

            // Check if there is already an active request
            $chk = $db->prepare("SELECT id FROM transactions WHERE textbook_id = ? AND buyer_id = ? AND status = 'pending'");
            $chk->execute([$bookId, $myId]);
            if ($chk->fetch()) {
                $_SESSION['error_msg'] = "You already have a pending request for this book.";
                header("Location: transactions.php");
                exit;
            }

            // Create Transaction
            $db->beginTransaction();
            
            $insTrans = $db->prepare("INSERT INTO transactions (textbook_id, buyer_id, seller_id, status) VALUES (?, ?, ?, 'pending')");
            $insTrans->execute([$bookId, $myId, $book['user_id']]);
            $transId = $db->lastInsertId();

            // Insert initial chat message
            $msg = "Hi! I am interested in exchanging/buying your textbook: '" . $book['title'] . "'. Let's coordinate a meeting place!";
            $insMsg = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insMsg->execute([$myId, $book['user_id'], $msg]);

            $db->commit();
            $_SESSION['success_msg'] = "Request sent successfully! A chat thread has been started with the owner.";
            header("Location: transactions.php");
            exit;

        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Failed to send request: " . $e->getMessage();
            header("Location: ../index.php");
            exit;
        }
    }

    // Action 2: Accept request
    if ($action === 'accept') {
        $transId = (int)($_POST['transaction_id'] ?? 0);

        try {
            $db->beginTransaction();

            // Fetch transaction details
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND seller_id = ? AND status = 'pending'");
            $stmt->execute([$transId, $myId]);
            $trans = $stmt->fetch();

            if (!$trans) {
                throw new Exception("Transaction request not found.");
            }

            // Update transaction status
            $upTrans = $db->prepare("UPDATE transactions SET status = 'accepted' WHERE id = ?");
            $upTrans->execute([$transId]);

            // Update textbook status
            $upBook = $db->prepare("UPDATE textbooks SET status = 'pending' WHERE id = ?");
            $upBook->execute([$trans['textbook_id']]);

            // Send system message in chat
            $sysMsg = "System Alert: Your request has been ACCEPTED by the seller! Please agree on the exchange terms.";
            $insMsg = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insMsg->execute([$myId, $trans['buyer_id'], $sysMsg]);

            $db->commit();
            $_SESSION['success_msg'] = "You accepted the request. Get in touch via Chat!";
            header("Location: transactions.php");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Error accepting request: " . $e->getMessage();
            header("Location: transactions.php");
            exit;
        }
    }

    // Action 3: Reject request
    if ($action === 'reject') {
        $transId = (int)($_POST['transaction_id'] ?? 0);

        try {
            $stmt = $db->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ? AND seller_id = ? AND status = 'pending'");
            $stmt->execute([$transId, $myId]);
            $_SESSION['success_msg'] = "Request rejected.";
            header("Location: transactions.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            header("Location: transactions.php");
            exit;
        }
    }

    // Action 4: Cancel request
    if ($action === 'cancel') {
        $transId = (int)($_POST['transaction_id'] ?? 0);

        try {
            $db->beginTransaction();

            // Fetch transaction
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
            $stmt->execute([$transId, $myId, $myId]);
            $trans = $stmt->fetch();

            if (!$trans || in_array($trans['status'], ['completed', 'cancelled', 'rejected'])) {
                throw new Exception("Transaction cannot be cancelled in its current state.");
            }

            // Update status
            $upTrans = $db->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?");
            $upTrans->execute([$transId]);

            // Set book back to available
            $upBook = $db->prepare("UPDATE textbooks SET status = 'available' WHERE id = ?");
            $upBook->execute([$trans['textbook_id']]);

            $db->commit();
            $_SESSION['success_msg'] = "Transaction cancelled.";
            header("Location: transactions.php");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Error: " . $e->getMessage();
            header("Location: transactions.php");
            exit;
        }
    }

    // Action 5: Complete transaction
    if ($action === 'complete') {
        $transId = (int)($_POST['transaction_id'] ?? 0);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT t.*, tk.exchange_type FROM transactions t JOIN textbooks tk ON t.textbook_id = tk.id WHERE t.id = ? AND t.seller_id = ? AND t.status = 'accepted'");
            $stmt->execute([$transId, $myId]);
            $trans = $stmt->fetch();

            if (!$trans) {
                throw new Exception("Transaction not found or not accepted yet.");
            }

            // Update transaction status to completed
            $upTrans = $db->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
            $upTrans->execute([$transId]);

            // Update textbook status based on transaction type
            $finalStatus = ($trans['exchange_type'] === 'sell') ? 'sold' : 'exchanged';
            $upBook = $db->prepare("UPDATE textbooks SET status = ? WHERE id = ?");
            $upBook->execute([$finalStatus, $trans['textbook_id']]);

            // Send completion message
            $sysMsg = "System Alert: Transaction marked as COMPLETED by the seller! Please leave a review for each other.";
            $insMsg = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insMsg->execute([$myId, $trans['buyer_id'], $sysMsg]);

            $db->commit();
            $_SESSION['success_msg'] = "Transaction marked as Completed. Thank you!";
            header("Location: transactions.php");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Error: " . $e->getMessage();
            header("Location: transactions.php");
            exit;
        }
    }

    // Action 6: Submit user review
    if ($action === 'review') {
        $transId = (int)($_POST['transaction_id'] ?? 0);
        $revieweeId = (int)($_POST['reviewee_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) $errors[] = "Rating must be between 1 and 5 stars.";

        if (empty($errors)) {
            try {
                // Verify transaction completed and user was involved
                $stmt = $db->prepare("SELECT id FROM transactions WHERE id = ? AND status = 'completed' AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$transId, $myId, $myId]);
                if (!$stmt->fetch()) {
                    throw new Exception("Unauthorized or transaction is not completed.");
                }

                // Check if user already left a review for this transaction
                $chk = $db->prepare("SELECT id FROM reviews WHERE transaction_id = ? AND reviewer_id = ?");
                $chk->execute([$transId, $myId]);
                if ($chk->fetch()) {
                    throw new Exception("You have already reviewed this transaction.");
                }

                $db->beginTransaction();
                // Save review
                $insReview = $db->prepare("INSERT INTO reviews (transaction_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                $insReview->execute([$transId, $myId, $revieweeId, $rating, $comment ?: null]);

                // Recalculate reviewee average rating
                $avgStmt = $db->prepare("SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?");
                $avgStmt->execute([$revieweeId]);
                $newAvg = $avgStmt->fetchColumn();
                $newAvg = $newAvg !== null ? round($newAvg, 2) : 0.00;

                $upUser = $db->prepare("UPDATE users SET average_rating = ? WHERE id = ?");
                $upUser->execute([$newAvg, $revieweeId]);

                $db->commit();
                $_SESSION['success_msg'] = "Review submitted successfully!";
                header("Location: transactions.php");
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['error_msg'] = "Failed to submit review: " . $e->getMessage();
                header("Location: transactions.php");
                exit;
            }
        }
    }

    // Action 7: Report user
    if ($action === 'report') {
        $reportedUser = (int)($_POST['reported_user'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) $errors[] = "Report reason is required.";

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("INSERT INTO reports (reported_by, reported_user, reason, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$myId, $reportedUser, $reason]);
                $_SESSION['success_msg'] = "Thank you. The report has been sent to our moderators for review.";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }
}

// ----------------------------------------------------
// FETCH TRANSACTIONS FOR RENDERING
// ----------------------------------------------------
try {
    // 1. Incoming requests: where I am the seller
    $incStmt = $db->prepare("SELECT t.*, b.name as buyer_name, b.average_rating as buyer_rating, tk.title as book_title, tk.condition as book_condition, tk.exchange_type, tk.price 
                             FROM transactions t
                             JOIN users b ON t.buyer_id = b.id
                             JOIN textbooks tk ON t.textbook_id = tk.id
                             WHERE t.seller_id = ?
                             ORDER BY t.created_at DESC");
    $incStmt->execute([$myId]);
    $incomingRequests = $incStmt->fetchAll();

    // 2. Sent requests: where I am the buyer
    $sentStmt = $db->prepare("SELECT t.*, s.name as seller_name, s.average_rating as seller_rating, tk.title as book_title, tk.condition as book_condition, tk.exchange_type, tk.price 
                              FROM transactions t
                              JOIN users s ON t.seller_id = s.id
                              JOIN textbooks tk ON t.textbook_id = tk.id
                              WHERE t.buyer_id = ?
                              ORDER BY t.created_at DESC");
    $sentStmt->execute([$myId]);
    $sentRequests = $sentStmt->fetchAll();

    // Helper to check if a review was left by current user for a transaction
    $reviewsCache = [];
    $revStmt = $db->prepare("SELECT transaction_id FROM reviews WHERE reviewer_id = ?");
    $revStmt->execute([$myId]);
    $reviewsCache = $revStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="text-gradient mb-1">My Trades & Transactions</h2>
        <p class="text-secondary mb-0">Track all buy, sell, swap, rent, and donate requests.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $error) echo "<li>" . sanitize($error) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs border-border mb-4" id="tradeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active text-white" id="received-tab" data-bs-toggle="tab" data-bs-target="#received-pane" type="button" role="tab" aria-controls="received-pane" aria-selected="true">
            Requests Received (<?php echo count($incomingRequests); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-white" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent-pane" type="button" role="tab" aria-controls="sent-pane" aria-selected="false">
            Requests Sent (<?php echo count($sentRequests); ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="tradeTabsContent">
    <!-- Tab 1: Received Requests -->
    <div class="tab-pane fade show active" id="received-pane" role="tabpanel" aria-labelledby="received-tab" tabindex="0">
        <div class="card card-custom border-0 p-0">
            <div class="card-body-custom p-0">
                <?php if (empty($incomingRequests)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">You haven't received any textbook requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                            <thead>
                                <tr class="border-bottom border-border">
                                    <th scope="col" class="ps-3">Book Requested</th>
                                    <th scope="col">Student (Buyer)</th>
                                    <th scope="col">Type / Value</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end pe-3" style="width: 250px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomingRequests as $req): ?>
                                    <tr class="border-bottom border-border">
                                        <td class="ps-3">
                                            <div class="fw-bold text-white"><?php echo sanitize($req['book_title']); ?></div>
                                            <span class="badge badge-custom badge-<?php echo strtolower($req['book_condition']); ?>"><?php echo sanitize($req['book_condition']); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo sanitize($req['buyer_name']); ?></div>
                                            <small class="text-warning">★ <?php echo number_format($req['buyer_rating'], 1); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($req['exchange_type'] == 'sell'): ?>
                                                <span class="text-success fw-bold">$<?php echo number_format($req['price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-custom badge-<?php echo strtolower($req['exchange_type']); ?>"><?php echo ucfirst($req['exchange_type']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-secondary"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></small></td>
                                        <td>
                                            <span class="badge badge-custom badge-<?php echo strtolower($req['status']); ?>">
                                                <?php echo strtoupper($req['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-2">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <form method="POST" action="transactions.php">
                                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="accept">
                                                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                                    </form>
                                                    <form method="POST" action="transactions.php">
                                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                    </form>
                                                <?php elseif ($req['status'] === 'accepted'): ?>
                                                    <a href="messages.php?contact_id=<?php echo $req['buyer_id']; ?>" class="btn btn-primary-gradient btn-sm"><i class="bi bi-chat-dots me-1"></i> Chat</a>
                                                    <form method="POST" action="transactions.php">
                                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="complete">
                                                        <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                                    </form>
                                                    <form method="POST" action="transactions.php">
                                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                                    </form>
                                                <?php elseif ($req['status'] === 'completed'): ?>
                                                    <?php if (!in_array($req['id'], $reviewsCache)): ?>
                                                        <button class="btn btn-info btn-sm text-white" onclick="openReviewModal(<?php echo $req['id']; ?>, <?php echo $req['buyer_id']; ?>, '<?php echo sanitize($req['buyer_name']); ?>')">
                                                            Rate Buyer
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-secondary small">Reviewed <i class="bi bi-check-lg text-success"></i></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-secondary">-</span>
                                                <?php endif; ?>
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
    </div>

    <!-- Tab 2: Sent Requests -->
    <div class="tab-pane fade" id="sent-pane" role="tabpanel" aria-labelledby="sent-tab" tabindex="0">
        <div class="card card-custom border-0 p-0">
            <div class="card-body-custom p-0">
                <?php if (empty($sentRequests)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">You haven't requested any textbooks yet.</p>
                        <a href="../index.php" class="text-gradient fw-bold text-decoration-none">Explore books now</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                            <thead>
                                <tr class="border-bottom border-border">
                                    <th scope="col" class="ps-3">Book Title</th>
                                    <th scope="col">Owner (Seller)</th>
                                    <th scope="col">Type / Value</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end pe-3" style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sentRequests as $req): ?>
                                    <tr class="border-bottom border-border">
                                        <td class="ps-3">
                                            <div class="fw-bold text-white"><?php echo sanitize($req['book_title']); ?></div>
                                            <span class="badge badge-custom badge-<?php echo strtolower($req['book_condition']); ?>"><?php echo sanitize($req['book_condition']); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo sanitize($req['seller_name']); ?></div>
                                            <small class="text-warning">★ <?php echo number_format($req['seller_rating'], 1); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($req['exchange_type'] == 'sell'): ?>
                                                <span class="text-success fw-bold">$<?php echo number_format($req['price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-custom badge-<?php echo strtolower($req['exchange_type']); ?>"><?php echo ucfirst($req['exchange_type']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-secondary"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></small></td>
                                        <td>
                                            <span class="badge badge-custom badge-<?php echo strtolower($req['status']); ?>">
                                                <?php echo strtoupper($req['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-2">
                                                <?php if (in_array($req['status'], ['pending', 'accepted'])): ?>
                                                    <a href="messages.php?contact_id=<?php echo $req['seller_id']; ?>" class="btn btn-primary-gradient btn-sm"><i class="bi bi-chat-dots me-1"></i> Chat</a>
                                                    <form method="POST" action="transactions.php">
                                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel request?');">Cancel</button>
                                                    </form>
                                                <?php elseif ($req['status'] === 'completed'): ?>
                                                    <?php if (!in_array($req['id'], $reviewsCache)): ?>
                                                        <button class="btn btn-info btn-sm text-white" onclick="openReviewModal(<?php echo $req['id']; ?>, <?php echo $req['seller_id']; ?>, '<?php echo sanitize($req['seller_name']); ?>')">
                                                            Rate Seller
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-secondary small">Reviewed <i class="bi bi-check-lg text-success"></i></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-secondary">-</span>
                                                <?php endif; ?>
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
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border text-light">
            <div class="modal-header card-header-custom border-border">
                <h5 class="modal-title">Submit Review for <span id="reviewer-target-name" class="text-gradient">User</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="transactions.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="transaction_id" id="review_trans_id" value="">
                    <input type="hidden" name="reviewee_id" id="review_reviewee_id" value="">

                    <!-- Star rating selection -->
                    <div class="text-center mb-4">
                        <label class="form-label d-block text-secondary mb-2">Give Star Rating</label>
                        <div class="star-rating-interactive">
                            <input type="radio" id="star5" name="rating" value="5" required><label for="star5" class="bi bi-star-fill"></label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" class="bi bi-star-fill"></label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" class="bi bi-star-fill"></label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" class="bi bi-star-fill"></label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" class="bi bi-star-fill"></label>
                        </div>
                    </div>

                    <!-- Comment textarea -->
                    <div class="mb-3">
                        <label for="comment" class="form-label text-secondary">Comments</label>
                        <textarea class="form-control form-control-custom" id="comment" name="comment" rows="4" placeholder="Tell us about the transaction: punctuality, condition matching, responsiveness..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-light border-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-gradient">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReviewModal(transId, userId, name) {
    document.getElementById('reviewer-target-name').textContent = name;
    document.getElementById('review_trans_id').value = transId;
    document.getElementById('review_reviewee_id').value = userId;
    
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    modal.show();
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
