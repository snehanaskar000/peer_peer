<?php
// user/messages.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$myId = getCurrentUserId();
$db = getDBConnection();

$selectedContactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
$contacts = [];
$activeContact = null;

try {
    // Fetch all message partners for the current user
    $contactsStmt = $db->prepare("SELECT u.id, u.name, u.profile_image, u.university,
                                  (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
                                  (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_msg_time,
                                  (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                                  FROM users u
                                  WHERE u.id IN (
                                      SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END 
                                      FROM messages 
                                      WHERE sender_id = ? OR receiver_id = ?
                                  )
                                  ORDER BY last_msg_time DESC");
    $contactsStmt->execute([$myId, $myId, $myId, $myId, $myId, $myId, $myId, $myId]);
    $contacts = $contactsStmt->fetchAll();

    // If a contact is selected, fetch their info
    if ($selectedContactId > 0) {
        $userStmt = $db->prepare("SELECT id, name, university, profile_image, average_rating FROM users WHERE id = ?");
        $userStmt->execute([$selectedContactId]);
        $activeContact = $userStmt->fetch();

        // If the contact is not in the recent thread list (e.g. starting a brand new conversation), add them manually
        $inList = false;
        foreach ($contacts as $c) {
            if ((int)$c['id'] === $selectedContactId) {
                $inList = true;
                break;
            }
        }
        if (!$inList && $activeContact) {
            // Push active contact to the top of the contacts list
            array_unshift($contacts, [
                'id' => $activeContact['id'],
                'name' => $activeContact['name'],
                'profile_image' => $activeContact['profile_image'],
                'university' => $activeContact['university'],
                'last_message' => 'Starting conversation...',
                'last_msg_time' => date('Y-m-d H:i:s'),
                'unread_count' => 0
            ]);
        }
    }

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="chat-container">
    <div class="row g-0 h-100">
        <!-- Left Pane: Conversations List -->
        <div class="col-md-4 chat-inbox-list">
            <div class="p-3 border-bottom border-border" style="background-color: rgba(255, 255, 255, 0.01);">
                <h5 class="mb-0 text-gradient"><i class="bi bi-chat-text me-2"></i>My Chats</h5>
            </div>
            
            <?php if (empty($contacts)): ?>
                <div class="text-center text-secondary py-5 px-3">
                    <i class="bi bi-chat-left-dots fs-1"></i>
                    <p class="mt-2 mb-0 small">No conversations started yet. Message a seller from a book details card to start exchanging!</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($contacts as $c): ?>
                        <div class="chat-item d-flex align-items-center <?php echo ($selectedContactId === (int)$c['id']) ? 'active' : ''; ?>" 
                             data-user-id="<?php echo $c['id']; ?>"
                             onclick="initChat(<?php echo $c['id']; ?>)">
                            <?php if ($c['profile_image']): ?>
                                <img src="../<?php echo sanitize($c['profile_image']); ?>" class="rounded-circle me-3" style="width: 44px; height: 44px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-3" style="width: 44px; height: 44px; font-weight: 600;">
                                    <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <h6 class="text-white mb-0 text-truncate small fw-bold"><?php echo sanitize($c['name']); ?></h6>
                                    <small class="text-secondary" style="font-size: 0.7rem;">
                                        <?php echo $c['last_msg_time'] ? date('h:i A', strtotime($c['last_msg_time'])) : ''; ?>
                                    </small>
                                </div>
                                <div class="text-secondary small text-truncate mt-1 text-muted" style="font-size: 0.8rem;">
                                    <?php echo sanitize($c['last_message'] ?: ''); ?>
                                </div>
                            </div>
                            <?php if ($c['unread_count'] > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.7rem;"><?php echo $c['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Pane: Active Conversation Chat -->
        <div class="col-md-8 chat-window">
            <?php if ($activeContact): ?>
                <!-- Chat Header -->
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <?php if ($activeContact['profile_image']): ?>
                            <img src="../<?php echo sanitize($activeContact['profile_image']); ?>" class="rounded-circle me-3" style="width: 42px; height: 42px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-3" style="width: 42px; height: 42px; font-weight: 600;">
                                <?php echo strtoupper(substr($activeContact['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h6 class="text-white mb-0"><?php echo sanitize($activeContact['name']); ?></h6>
                            <small class="text-secondary" style="font-size: 0.8rem;"><i class="bi bi-geo-alt me-1 text-gradient"></i><?php echo sanitize($activeContact['university']); ?></small>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-warning">★ <?php echo number_format($activeContact['average_rating'], 1); ?></small>
                    </div>
                </div>

                <!-- Messages Window -->
                <div class="chat-messages" id="chat-messages-container">
                    <!-- Loaded dynamically via chat.js -->
                </div>

                <!-- Input area -->
                <div class="chat-input-area">
                    <form id="chat-form">
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-custom" id="chat-input" placeholder="Type a message..." required autocomplete="off">
                            <button class="btn btn-primary-gradient" type="submit" id="chat-send-btn">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Chat Placeholder -->
                <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 text-secondary p-5">
                    <i class="bi bi-chat-square-dots-fill text-gradient" style="font-size: 5rem;"></i>
                    <h5 class="text-white mt-3">Select a conversation</h5>
                    <p class="small text-center text-muted" style="max-width: 320px;">Choose a contact from the left list or message a seller on their textbook page to start coordinates.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load Chat Script -->
<script src="../assets/js/chat.js"></script>

<?php if ($selectedContactId > 0): ?>
    <script>
        // Auto-initialize chat when redirecting with contact_id
        document.addEventListener('DOMContentLoaded', function() {
            initChat(<?php echo $selectedContactId; ?>);
        });
    </script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
