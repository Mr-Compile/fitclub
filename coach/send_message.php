<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get coach's ID
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$coach_result = $stmt->get_result();
$coach = $coach_result->fetch_assoc();

if (!$coach) {
    http_response_code(404);
    exit('Coach not found');
}

$member_id = $_POST['member_id'] ?? 0;
$message = $_POST['message'] ?? '';
$attachment_path = null;

// Verify member is assigned to this coach
$stmt = $conn->prepare("
    SELECT u.id 
    FROM users u
    INNER JOIN user_memberships um ON u.id = um.user_id
    WHERE um.coach_id = ? AND um.user_id = ? AND um.status = 'active'
");
$stmt->bind_param("ii", $coach['id'], $member_id);
$stmt->execute();
$member_result = $stmt->get_result();

if (!$member_result->fetch_assoc()) {
    http_response_code(404);
    exit('Member not found or not assigned to you');
}

// Handle file upload if present
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/messages/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_extension, $allowed_extensions)) {
        $file_name = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
            $attachment_path = 'uploads/messages/' . $file_name;
        }
    }
}

// Insert message
$stmt = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, message, attachment_path, message_type) 
    VALUES (?, ?, ?, ?, 'coach_to_member')
");
$stmt->bind_param("iiss", $_SESSION['user_id'], $member_id, $message, $attachment_path);

if ($stmt->execute()) {
    // Update message notifications
    $stmt = $conn->prepare("
        INSERT INTO message_notifications (user_id, unread_count)
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE unread_count = unread_count + 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();

    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
} 