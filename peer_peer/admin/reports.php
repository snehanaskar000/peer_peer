<?php
// admin/reports.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$db = getDBConnection();

// Handle Report Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "CSRF verification failed.";
        header("Location: reports.php");
        exit;
    }

    $reportId = (int)($_POST['report_id'] ?? 0);

    try {
        $stmt = $db->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success_msg'] = "Report ticket marked as resolved.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }

    header("Location: reports.php");
    exit;
}

// Fetch all reports
try {
    $stmt = $db->query("SELECT r.*, u1.name as reporter_name, u1.email as reporter_email, u2.name as reported_name, u2.email as reported_email
                        FROM reports r
                        JOIN users u1 ON r.reported_by = u1.id
                        JOIN users u2 ON r.reported_user = u2.id
                        ORDER BY r.created_at DESC");
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="text-warning mb-1"><i class="bi bi-flag me-2"></i>Abuse & Dispute Tickets</h2>
        <p class="text-secondary mb-0">Review reports filed by students against other users on the marketplace.</p>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="text-white mb-0"><i class="bi bi-ticket-perforated me-2"></i>All Report Tickets</h5>
        <span class="text-secondary small"><?php echo count($reports); ?> total tickets</span>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($reports)): ?>
            <div class="text-center text-secondary py-5">
                <i class="bi bi-shield-check text-success fs-1"></i>
                <p class="mt-2 mb-0">System clean! No reports have been submitted.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0" style="background-color: var(--bg-card);">
                    <thead>
                        <tr class="border-bottom border-border">
                            <th scope="col" class="ps-3" style="width: 60px;">ID</th>
                            <th scope="col">Reported By</th>
                            <th scope="col">Reported User</th>
                            <th scope="col" style="max-width: 300px;">Reason</th>
                            <th scope="col">Date Filed</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end pe-3" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $rep): ?>
                            <tr class="border-bottom border-border">
                                <td class="ps-3 text-secondary"><?php echo $rep['id']; ?></td>
                                <td>
                                    <div class="text-white"><?php echo sanitize($rep['reporter_name']); ?></div>
                                    <small class="text-secondary"><?php echo sanitize($rep['reporter_email']); ?></small>
                                </td>
                                <td>
                                    <div class="text-white fw-bold"><?php echo sanitize($rep['reported_name']); ?></div>
                                    <small class="text-secondary"><?php echo sanitize($rep['reported_email']); ?></small>
                                </td>
                                <td style="max-width: 300px;" class="text-wrap small text-secondary">
                                    <?php echo sanitize($rep['reason']); ?>
                                </td>
                                <td><small class="text-secondary"><?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?></small></td>
                                <td>
                                    <span class="badge <?php echo $rep['status'] === 'resolved' ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                                        <?php echo strtoupper($rep['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <?php if ($rep['status'] === 'pending'): ?>
                                        <form method="POST" action="reports.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="report_id" value="<?php echo $rep['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Resolve</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-secondary small italic">Resolved <i class="bi bi-check-circle-fill text-success ms-1"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
