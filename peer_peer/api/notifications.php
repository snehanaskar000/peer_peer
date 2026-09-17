<?php
// api/notifications.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$myId = getCurrentUserId();

try {
    $db = getDBConnection();

    // 1. Unread messages count
    $stmtMsg = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmtMsg->execute([$myId]);
    $unreadMessages = (int)$stmtMsg->fetchColumn();

    // 2. Pending exchange requests count (received requests)
    $stmtReq = $db->prepare("SELECT COUNT(*) FROM transactions WHERE seller_id = ? AND status = 'pending'");
    $stmtReq->execute([$myId]);
    $pendingRequests = (int)$stmtReq->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'unread_messages' => $unreadMessages,
        'pending_requests' => $pendingRequests
    ]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
