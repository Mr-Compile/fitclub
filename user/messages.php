<?php
session_start();
require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

// Get user's coach
$query = "SELECT c.*, u.id as user_id, u.username, u.fullname, c.specialization 
          FROM coaches c 
          INNER JOIN users u ON c.user_id = u.id
          INNER JOIN user_memberships um ON c.id = um.coach_id 
          WHERE um.user_id = :user_id AND um.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

// If no active coach found, redirect to dashboard
if (!$coach) {
    header("Location: dashboard.php");
    exit();
}

// Get conversation history
$query = "SELECT m.*, 
          CASE 
              WHEN m.sender_id = :user_id THEN 'sent'
              ELSE 'received'
          END as message_type,
          CASE 
              WHEN m.sender_id = :user_id THEN u.username
              ELSE cu.username
          END as sender_name
          FROM messages m
          LEFT JOIN users u ON m.sender_id = u.id
          LEFT JOIN users cu ON m.receiver_id = cu.id
          WHERE (m.sender_id = :user_id AND m.receiver_id = :coach_user_id)
          OR (m.sender_id = :coach_user_id AND m.receiver_id = :user_id)
          ORDER BY m.created_at ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->bindParam(":coach_user_id", $coach['user_id']);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark messages as read
$query = "UPDATE messages SET is_read = 1 
          WHERE receiver_id = :user_id AND is_read = 0";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();

// Reset unread count
$query = "UPDATE message_notifications SET unread_count = 0 
          WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FitClub</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'components/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Chat Header -->
            <div class="bg-gray-400 px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-circle text-white text-3xl"></i>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-xl font-semibold text-white">
                            Chat with <?php echo htmlspecialchars($coach['username']); ?>
                        </h2>
                        <p class="text-gray-100">
                            <?php echo htmlspecialchars($coach['specialization']); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="h-[600px] overflow-y-auto p-6" id="chat-messages">
                <?php foreach ($messages as $message): ?>
                    <div class="flex <?php echo $message['message_type'] === 'sent' ? 'justify-end' : 'justify-start'; ?> mb-4">
                        <div class="max-w-[70%] <?php echo $message['message_type'] === 'sent' ? 'bg-gray-300 text-black' : 'bg-gray-100 text-black'; ?> rounded-lg px-4 py-2">
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
                            <div class="text-xs mt-1 text-black">
                                <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Message Input -->
            <div class="border-t border-gray-200 px-6 py-4">
                <form id="message-form" class="flex items-center space-x-4" enctype="multipart/form-data">
                    <div class="flex-grow">
                        <input type="text" 
                               id="message-input" 
                               name="message"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 text-black"
                               placeholder="Type your message...">
                    </div>
                    <div class="flex items-center space-x-2">
                        <label for="attachment" class="cursor-pointer">
                            <i class="fas fa-image text-gray-500 hover:text-gray-700 text-xl"></i>
                            <input type="file" 
                                   id="attachment" 
                                   name="attachment"
                                   accept="image/*" 
                                   class="hidden">
                        </label>
                        <button type="submit" 
                                class="bg-gray-400 text-black px-4 py-2 rounded-lg hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Scroll to bottom of chat
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Handle message submission
        document.getElementById('message-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            const attachmentInput = document.getElementById('attachment');
            
            if (!message && !attachmentInput.files.length) return;

            const formData = new FormData();
            formData.append('message', message);
            formData.append('coach_id', '<?php echo $coach['id']; ?>');
            
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

        // Auto-refresh messages every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>

    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-black text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>
