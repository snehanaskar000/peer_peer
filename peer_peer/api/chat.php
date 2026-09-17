<?php
// api/chat.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$myId = getCurrentUserId();
$db = getDBConnection();

// GET Request: Fetch messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    if ($contactId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid contact ID']);
        exit;
    }

    try {
        // Mark messages from this contact as read
        $updateStmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $updateStmt->execute([$contactId, $myId]);

        // Get messages
        $stmt = $db->prepare("SELECT id, sender_id, receiver_id, message, created_at, (sender_id = ?) as is_sent 
                              FROM messages 
                              WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                                AND id > ?
                              ORDER BY created_at ASC");
        $stmt->execute([$myId, $myId, $contactId, $contactId, $myId, $lastId]);
        $messages = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// POST Request: Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = trim($_POST['message'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Verify CSRF
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'CSRF verification failed']);
        exit;
    }

    if ($receiverId <= 0 || empty($message)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Receiver ID and message content are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$myId, $receiverId, $message]);

        echo json_encode([
            'status' => 'success',
            'message_id' => $db->lastInsertId()
        ]);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
exit;
