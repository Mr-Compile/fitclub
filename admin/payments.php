<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = filter_var($_POST['payment_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if (!$payment_id) {
        $_SESSION['error'] = "Invalid payment ID";
        header("Location: payments.php");
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        // First verify the payment exists
        $check_query = "SELECT id FROM payments WHERE id = :payment_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            throw new Exception("Payment ID {$payment_id} does not exist in the database");
        }
        
        // Get payment details
        $query = "SELECT p.*, m.id as membership_id, m.user_id, m.status as membership_status
                 FROM payments p 
                 JOIN user_memberships m ON p.user_membership_id = m.id 
                 WHERE p.id = :payment_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("Payment details not found for ID: " . $payment_id);
        }

        if ($action === 'approve') {
            // Update payment status
            $query = "UPDATE payments SET status = 'completed' WHERE id = :payment_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
            $stmt->execute();

            // Update membership status
            $query = "UPDATE user_memberships 
                     SET payment_status = 'completed',
                         status = 'active'
                     WHERE id = :membership_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':membership_id', $payment['membership_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success'] = "Payment approved successfully.";
        } elseif ($action === 'reject') {
            // Update payment status
            $query = "UPDATE payments SET status = 'failed' WHERE id = :payment_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success'] = "Payment rejected successfully.";
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Payment processing error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process payment: " . $e->getMessage();
    }
    
    header("Location: payments.php");
    exit();
}

// Get all payments (excluding upgrade payments)
$query = "SELECT p.*, u.username, u.fullname, mp.name as plan_name,
                 m.status as membership_status
          FROM payments p
          JOIN user_memberships m ON p.user_membership_id = m.id
          JOIN users u ON m.user_id = u.id
          JOIN membership_plans mp ON m.plan_id = mp.id
          WHERE p.payment_type != 'upgrade'
          ORDER BY p.payment_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-container {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #E5E7EB #F3F4F6;
        }
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background-color: #E5E7EB;
            border-radius: 4px;
        }
        .hover-row:hover {
            background-color: #F9FAFB;
        }

        .status-badge {
            transition: all 0.2s ease;
        }
        .status-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Payment History</h1>
                    <p class="mt-1 text-sm text-gray-500">View and manage all payment transactions</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            </div>
        <?php endif; ?>



        <!-- Payments Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['fullname']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['username']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($payment['plan_name']); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo ucfirst($payment['payment_type']); ?> Payment
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ₱<?php echo number_format($payment['amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($payment['reference_number']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                            ($payment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($payment['status'])); ?>
                                    </span>
                                    <?php if ($payment['receipt_path']): ?>
                                        <a href="../assets/receipts/<?php echo htmlspecialchars($payment['receipt_path']); ?>" 
                                           target="_blank" 
                                           class="ml-2 text-indigo-600 hover:text-indigo-900 text-xs">
                                            <i class="fas fa-receipt"></i> View Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="text-green-600 hover:text-green-900 transition-colors" title="Approve Payment">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="text-red-600 hover:text-red-900 transition-colors" title="Reject Payment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>

</body>
</html> 