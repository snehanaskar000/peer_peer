<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$db = getDBConnection();

try {
    // 1. Calculate statistics
    $stmt1 = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int)$stmt1->fetchColumn();

    $stmt2 = $db->query("SELECT COUNT(*) FROM textbooks WHERE status = 'available'");
    $activeListings = (int)$stmt2->fetchColumn();

    $stmt3 = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'");
    $completedTrades = (int)$stmt3->fetchColumn();

    $stmt4 = $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pendingReports = (int)$stmt4->fetchColumn();

    // 2. Fetch recent reports
    $repStmt = $db->query("SELECT r.*, u1.name as reporter_name, u2.name as reported_name 
                           FROM reports r
                           JOIN users u1 ON r.reported_by = u1.id
                           JOIN users u2 ON r.reported_user = u2.id
                           WHERE r.status = 'pending'
                           ORDER BY r.created_at DESC LIMIT 5");
    $recentReports = $repStmt->fetchAll();

    // 3. Fetch recent system activity (latest transactions)
    $actStmt = $db->query("SELECT t.*, tk.title as book_title, s.name as seller_name, b.name as buyer_name 
                           FROM transactions t
                           JOIN textbooks tk ON t.textbook_id = tk.id
                           JOIN users s ON t.seller_id = s.id
                           JOIN users b ON t.buyer_id = b.id
                           ORDER BY t.created_at DESC LIMIT 5");
    $recentActivity = $actStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h2 class="mb-1"><i class="bi bi-shield-lock me-2 text-gradient"></i>Admin Dashboard</h2>
        <p class="text-secondary mb-0">Overview of users, listings, transactions and reports.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <a href="../index.php" class="btn btn-outline-light border-border me-2">View Marketplace</a>
        <a href="users.php" class="btn btn-primary-gradient">Manage Users</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center">
                <div class="me-3 p-3 rounded-3 bg-input"><i class="bi bi-people fs-3 text-gradient"></i></div>
                <div>
                    <div class="text-secondary small">Total Students</div>
                    <div class="h4 mb-0 fw-bold"><?php echo $totalUsers; ?></div>
                </div>
            </div>
            <div class="mt-3"><a href="users.php" class="small text-decoration-none hover-accent-color">View all users <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center">
                <div class="me-3 p-3 rounded-3 bg-input"><i class="bi bi-book-half fs-3 text-gradient"></i></div>
                <div>
                    <div class="text-secondary small">Active Listings</div>
                    <div class="h4 mb-0 fw-bold"><?php echo $activeListings; ?></div>
                </div>
            </div>
            <div class="mt-3"><a href="listings.php" class="small text-decoration-none hover-accent-color">Moderate listings <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center">
                <div class="me-3 p-3 rounded-3 bg-input"><i class="bi bi-check2-circle fs-3 text-success"></i></div>
                <div>
                    <div class="text-secondary small">Completed Trades</div>
                    <div class="h4 mb-0 fw-bold text-success"><?php echo $completedTrades; ?></div>
                </div>
            </div>
            <div class="mt-3"><span class="small text-secondary">Verified exchanges</span></div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-custom p-3 border-danger">
            <div class="d-flex align-items-center">
                <div class="me-3 p-3 rounded-3 bg-input"><i class="bi bi-flag-fill fs-3 text-danger"></i></div>
                <div>
                    <div class="text-secondary small">Pending Reports</div>
                    <div class="h4 mb-0 fw-bold text-danger"><?php echo $pendingReports; ?></div>
                </div>
            </div>
            <div class="mt-3"><a href="reports.php" class="small text-danger text-decoration-none">Review reports <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap">
            <a href="users.php?action=new" class="btn btn-outline-secondary">+ Add Admin</a>
            <a href="listings.php" class="btn btn-outline-secondary">Moderate Listings</a>
            <a href="reports.php" class="btn btn-outline-danger">View Reports</a>
            <a href="transactions.php" class="btn btn-outline-success">Export Transactions</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-activity me-2 text-gradient"></i>Latest Transactions</h5>
                <a href="transactions.php" class="small text-decoration-none">See all</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-clock-history fs-1"></i>
                        <p class="mt-2 mb-0">No recent transactions found.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($recentActivity as $act): ?>
                            <div class="list-group-item bg-transparent border-border p-3 d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <div class="small text-secondary"><?php echo date('M d, h:i A', strtotime($act['created_at'])); ?></div>
                                    <div class="fw-bold text-truncate" style="max-width: 420px;"><?php echo sanitize($act['book_title']); ?></div>
                                    <div class="small text-secondary">Seller: <?php echo sanitize($act['seller_name']); ?> · Buyer: <?php echo sanitize($act['buyer_name']); ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge badge-custom badge-<?php echo strtolower($act['status']); ?>"><?php echo strtoupper($act['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-custom h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-danger"><i class="bi bi-flag me-2"></i>Recent Reports</h5>
                <a href="reports.php" class="small text-decoration-none">All reports</a>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($recentReports)): ?>
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2 mb-0">No pending reports.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($recentReports as $rep): ?>
                            <div class="list-group-item bg-transparent border-border p-3">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <div class="small text-secondary"><?php echo date('M d, h:i A', strtotime($rep['created_at'])); ?></div>
                                        <div class="fw-bold"><?php echo sanitize($rep['reported_name']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-danger">PENDING</span>
                                    </div>
                                </div>
                                <p class="text-secondary small mb-2"><?php echo sanitize($rep['reason']); ?></p>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="reports.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                        <input type="hidden" name="report_id" value="<?php echo $rep['id']; ?>">
                                        <input type="hidden" name="action" value="resolve">
                                        <button type="submit" class="btn btn-sm btn-success">Resolve</button>
                                    </form>
                                    <a href="reports.php?action=view&id=<?php echo $rep['id']; ?>" class="btn btn-sm btn-outline-secondary">Details</a>
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
