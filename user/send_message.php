<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    http_response_code(403);
    exit('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();

// Get user's coach
$query = "SELECT c.*, u.id as user_id 
          FROM coaches c 
          INNER JOIN users u ON c.user_id = u.id
          INNER JOIN user_memberships um ON c.id = um.coach_id 
          WHERE um.user_id = :user_id AND um.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    http_response_code(404);
    exit('Coach not found');
}

$message = $_POST['message'] ?? '';
$attachment_path = null;

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
$query = "INSERT INTO messages (sender_id, receiver_id, message, attachment_path, message_type) 
          VALUES (:sender_id, :receiver_id, :message, :attachment_path, 'member_to_coach')";
$stmt = $db->prepare($query);
$stmt->bindParam(":sender_id", $_SESSION['user_id']);
$stmt->bindParam(":receiver_id", $coach['user_id']);
$stmt->bindParam(":message", $message);
$stmt->bindParam(":attachment_path", $attachment_path);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
} 