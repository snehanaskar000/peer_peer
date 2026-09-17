// assets/js/chat.js

let activeContactId = null;
let chatPollInterval = null;
let lastMessageId = 0;

function initChat(contactId) {
    activeContactId = contactId;
    lastMessageId = 0;
    
    // Clear any existing polling
    if (chatPollInterval) {
        clearInterval(chatPollInterval);
    }

    // Set active class in list
    document.querySelectorAll('.chat-item').forEach(item => {
        if (parseInt(item.getAttribute('data-user-id')) === contactId) {
            item.classList.add('active');
            // Remove unread indicators if any
            const unreadBadge = item.querySelector('.badge');
            if (unreadBadge) {
                unreadBadge.remove();
            }
        } else {
            item.classList.remove('active');
        }
    });

    // Load initial messages
    loadMessages(true);

    // Start polling every 2 seconds
    chatPollInterval = setInterval(loadMessages, 2000);
}

function loadMessages(shouldScroll = false) {
    if (!activeContactId) return;

    // Determine the base path of the api
    let baseUrl = '';
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        const src = scripts[i].getAttribute('src');
        if (src && src.includes('assets/js/chat.js')) {
            baseUrl = src.replace('assets/js/chat.js', '');
            break;
        }
    }

    fetch(`${baseUrl}api/chat.php?contact_id=${activeContactId}&last_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const chatBox = document.getElementById('chat-messages-container');
                if (!chatBox) return;

                // If loading a new chat (lastMessageId == 0), clear container
                if (lastMessageId === 0) {
                    chatBox.innerHTML = '';
                    if (data.messages.length === 0) {
                        chatBox.innerHTML = '<div class="text-center text-muted my-5">No messages yet. Start the conversation!</div>';
                    }
                }

                if (data.messages.length > 0) {
                    // Remove "No messages" placeholder if it exists
                    const placeholder = chatBox.querySelector('.text-muted');
                    if (placeholder) {
                        placeholder.remove();
                    }

                    data.messages.forEach(msg => {
                        const msgDiv = document.createElement('div');
                        const isSent = msg.is_sent;
                        
                        msgDiv.className = `chat-msg ${isSent ? 'chat-msg-sent' : 'chat-msg-received'}`;
                        msgDiv.innerHTML = `
                            <div class="chat-bubble shadow-sm">${escapeHtml(msg.message)}</div>
                            <span class="chat-meta">${formatTime(msg.created_at)}</span>
                        `;
                        chatBox.appendChild(msgDiv);
                        
                        // Update last message ID
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });

                    shouldScroll = true;
                }

                if (shouldScroll) {
                    scrollChatToBottom();
                }
            }
        })
        .catch(err => console.error('Error loading messages:', err));
}

function sendMessage() {
    const input = document.getElementById('chat-input');
    if (!input || !activeContactId) return;

    const message = input.value.trim();
    if (!message) return;

    // Disable input and button during send
    input.disabled = true;
    const sendBtn = document.getElementById('chat-send-btn');
    if (sendBtn) sendBtn.disabled = true;

    // Get CSRF Token
    const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

    let baseUrl = '';
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        const src = scripts[i].getAttribute('src');
        if (src && src.includes('assets/js/chat.js')) {
            baseUrl = src.replace('assets/js/chat.js', '');
            break;
        }
    }

    const formData = new FormData();
    formData.append('receiver_id', activeContactId);
    formData.append('message', message);
    formData.append('csrf_token', csrfToken);

    fetch(`${baseUrl}api/chat.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        input.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        
        if (data.status === 'success') {
            input.value = '';
            input.focus();
            loadMessages(true); // Load messages immediately and scroll
        } else {
            alert('Failed to send message: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        input.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        console.error('Error sending message:', err);
    });
}

function scrollChatToBottom() {
    const chatBox = document.getElementById('chat-messages-container');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatTime(dateString) {
    const date = new Date(dateString.replace(/-/g, '/')); // Compatibility fix for Safari
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Hook message submission from UI
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }

    const chatInput = document.getElementById('chat-input');
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
});
