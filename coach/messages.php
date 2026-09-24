<?php
session_start();
require_once '../includes/database.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$conn = new mysqli("localhost", "root", "", "fitclub");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get coach's ID first
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$coach_result = $stmt->get_result();
$coach = $coach_result->fetch_assoc();

if (!$coach) {
    die("Coach not found");
}

// Get coach's assigned members
$members = [];
$selected_member = null;
$messages = [];

try {
    // Get all members assigned to this coach
    $query = "
        SELECT u.id, u.fullname, 
               (SELECT COUNT(*) FROM messages 
                WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) as unread_count,
               (SELECT message FROM messages 
                WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?)
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages 
                WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?)
                ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM users u
        INNER JOIN user_memberships um ON u.id = um.user_id
        WHERE um.coach_id = ? AND um.status = 'active'
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiiiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $coach['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $members = $result->fetch_all(MYSQLI_ASSOC);

    // If a member is selected, get their messages
    if (isset($_GET['member_id'])) {
        $member_id = (int)$_GET['member_id'];
        
        // Verify the member is assigned to this coach
        $stmt = $conn->prepare("
            SELECT u.* FROM users u
            INNER JOIN user_memberships um ON u.id = um.user_id
            WHERE um.coach_id = ? AND um.user_id = ? AND um.status = 'active'
        ");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $coach['id'], $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_member = $result->fetch_assoc();

        if ($selected_member) {
            // Get messages between coach and member
            $stmt = $conn->prepare("
                SELECT m.*, 
                       CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type
                FROM messages m
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
            ");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("iiiii", $_SESSION['user_id'], $_SESSION['user_id'], $member_id, $member_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = $result->fetch_all(MYSQLI_ASSOC);

            // Mark messages as read
            $stmt = $conn->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
            ");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ii", $member_id, $_SESSION['user_id']);
            $stmt->execute();
        }
    }
} catch (Exception $e) {
    error_log("Error in messages.php: " . $e->getMessage());
    die("An error occurred while loading messages. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Coach Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 8rem);
        }
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        .message-bubble.sent {
            background-color: #1F2937;
            color: white;
            border-radius: 1rem 1rem 0 1rem;
        }
        .message-bubble.received {
            background-color: #F3F4F6;
            color: #1F2937;
            border-radius: 1rem 1rem 1rem 0;
        }
        .member-list {
            height: calc(100vh - 8rem);
        }
        .member-item {
            transition: all 0.2s ease;
        }
        .member-item:hover {
            background-color: #F3F4F6;
        }
        .member-item.active {
            background-color: #EEF2FF;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Messages</h1>
            <p class="text-gray-600">Communicate with your assigned members</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex h-[700px]">
                <!-- Members List -->
                <div class="w-1/3 border-r border-gray-200 bg-gray-50">
                    <div class="p-4 border-b border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">Members</h2>
                            <span class="bg-gray-100 text-gray-800 text-sm font-medium px-2.5 py-0.5 rounded-full">
                                <?php echo count($members); ?> Active
                            </span>
                        </div>
                    </div>
                    <div class="overflow-y-auto member-list">
                        <?php foreach ($members as $member): ?>
                            <a href="?member_id=<?php echo $member['id']; ?>" 
                               class="block p-4 member-item <?php echo $selected_member && $selected_member['id'] == $member['id'] ? 'active' : ''; ?>">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600 text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($member['fullname']); ?>
                                            </h3>
                                            <?php if ($member['unread_count'] > 0): ?>
                                                <span class="bg-gray-600 text-white text-xs font-medium px-2 py-1 rounded-full">
                                                    <?php echo $member['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($member['last_message']): ?>
                                            <p class="text-sm text-gray-500 truncate">
                                                <?php echo htmlspecialchars($member['last_message']); ?>
                                            </p>
                                            <p class="text-xs text-gray-400 mt-1">
                                                <?php echo date('M j, g:i a', strtotime($member['last_message_time'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">No messages yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex-1 flex flex-col">
                    <?php if ($selected_member): ?>
                        <!-- Chat Header -->
                        <div class="bg-white px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-xl font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($selected_member['fullname']); ?>
                                    </h2>
                                    <p class="text-gray-500">
                                        Member
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div class="flex-1 overflow-y-auto p-6 bg-gray-50" id="chat-messages">
                            <?php foreach ($messages as $message): ?>
                                <div class="flex <?php echo $message['message_type'] === 'sent' ? 'justify-end' : 'justify-start'; ?> mb-4">
                                    <div class="message-bubble <?php echo $message['message_type'] === 'sent' ? 'sent' : 'received'; ?> px-4 py-2">
                                        <?php if ($message['attachment_path']): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($message['attachment_path']); ?>" 
                                                     alt="Attachment" 
                                                     class="max-w-full rounded-lg"
                                                     style="max-height: 300px; object-fit: contain;">
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($message['message'])): ?>
                                            <p><?php echo htmlspecialchars($message['message']); ?></p>
                                        <?php endif; ?>
                                        <div class="text-xs mt-1 <?php echo $message['message_type'] === 'sent' ? 'text-gray-200' : 'text-gray-500'; ?>">
                                            <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="bg-white border-t border-gray-200 px-6 py-4">
                            <form id="message-form" class="flex items-center space-x-4" enctype="multipart/form-data">
                                <div class="flex-grow">
                                    <input type="text" 
                                           id="message-input" 
                                           name="message"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500"
                                           placeholder="Type your message...">
                                </div>
                                <div class="flex items-center space-x-2">
                                    <label for="attachment" class="cursor-pointer p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                        <i class="fas fa-image text-gray-500 hover:text-gray-600 text-xl"></i>
                                        <input type="file" 
                                               id="attachment" 
                                               name="attachment"
                                               accept="image/*" 
                                               class="hidden">
                                    </label>
                                    <button type="submit" 
                                            class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center bg-gray-50">
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                    <i class="fas fa-comments text-gray-600 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Select a member to start chatting</h3>
                                <p class="text-gray-500">Choose a member from the list to view your conversation</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Scroll to bottom of chat
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Handle message submission
        const messageForm = document.getElementById('message-form');
        if (messageForm) {
            messageForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const messageInput = document.getElementById('message-input');
                const message = messageInput.value.trim();
                const attachmentInput = document.getElementById('attachment');
                
                if (!message && !attachmentInput.files.length) return;

                const formData = new FormData();
                formData.append('message', message);
                formData.append('member_id', '<?php echo $member_id; ?>');
                
                if (attachmentInput.files.length) {
                    formData.append('attachment', attachmentInput.files[0]);
                }

                try {
                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        messageInput.value = '';
                        attachmentInput.value = '';
                        location.reload(); // Refresh to show new message
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                }
            });
        }

        // Auto-refresh messages every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
     <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>