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
    $db->beginTransaction();

    $payment_id = $_POST['payment_id'] ?? null;
    if (!$payment_id) {
        throw new Exception('Missing payment ID');
    }

    // Get payment and membership info, ensure it's a renewal and belongs to user
    $query = "SELECT p.*, m.user_id, m.id as membership_id FROM payments p JOIN user_memberships m ON p.user_membership_id = m.id WHERE p.id = :payment_id AND m.user_id = :user_id AND p.payment_type = 'renewal'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invalid renewal payment');
    }
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    $membership_id = $payment['membership_id'];

    // Handle receipt upload
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/receipts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $filename = 'renewal_receipt_' . $payment_id . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $targetPath)) {
            throw new Exception('Failed to upload receipt');
        }
        // Update payment record
        $query = "UPDATE payments SET receipt_path = :receipt_path, status = 'pending' WHERE id = :payment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':receipt_path', $filename);
        $stmt->bindParam(':payment_id', $payment_id);
        $stmt->execute();
        // Set membership status to pending
        $query = "UPDATE user_memberships SET payment_status = 'pending', status = 'pending' WHERE id = :membership_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':membership_id', $membership_id);
        $stmt->execute();
        $db->commit();
        $_SESSION['success'] = 'Renewal receipt uploaded successfully. Your renewal is pending approval.';
        header('Location: membership.php?status=pending');
        exit();
    } else {
        throw new Exception('No receipt uploaded');
    }
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header('Location: renewal_payment_receipt.php?payment_id=' . ($payment_id ?? ''));
    exit();
} 