<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Start transaction
    $db->beginTransaction();

    // Get form data
    $membership_id = $_POST['membership_id'] ?? null;

    if (!$membership_id) {
        throw new Exception('Missing membership ID');
    }

    // Verify membership exists and belongs to user
    $query = "SELECT m.*, p.id as payment_id, p.reference_number 
              FROM user_memberships m 
              JOIN payments p ON m.id = p.user_membership_id 
              WHERE m.id = :membership_id AND m.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":membership_id", $membership_id);
    $stmt->bindParam(":user_id", $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('Invalid membership');
    }

    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    // Handle receipt upload
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/receipts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $membership['id'] . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $targetPath)) {
            throw new Exception('Failed to upload receipt');
        }

        // Update payment record with receipt path
        $query = "UPDATE payments SET 
                  receipt_path = :receipt_path,
                  status = 'pending'
                  WHERE id = :payment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':receipt_path', $filename);
        $stmt->bindParam(':payment_id', $membership['payment_id']);
        $stmt->execute();

        // Update membership status to pending
        $query = "UPDATE user_memberships SET 
                  payment_status = 'pending', 
                  status = 'pending' 
                  WHERE id = :membership_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':membership_id', $membership_id);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        $_SESSION['success'] = 'Receipt uploaded successfully. Your membership is pending approval.';
        header('Location: membership.php?status=pending');
        exit();
    } else {
        throw new Exception('No receipt uploaded');
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header('Location: payment_receipt.php?membership_id=' . $membership_id);
    exit();
}
?> 