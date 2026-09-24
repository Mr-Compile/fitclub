<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all upgrade requests with payment info
$query = "SELECT ur.*, m.id as membership_id, m.user_id, m.status as membership_status,
                 mp_current.name as current_plan_name, mp_current.price as current_price,
                 mp_new.name as new_plan_name, mp_new.price as new_price,
                 p.id as payment_id, p.status as payment_status, p.reference_number,
                 p.receipt_path, p.amount as payment_amount,
                 u.username, u.fullname
          FROM plan_upgrade_requests ur
          JOIN user_memberships m ON ur.user_membership_id = m.id
          JOIN membership_plans mp_current ON ur.current_plan_id = mp_current.id
          JOIN membership_plans mp_new ON ur.new_plan_id = mp_new.id
          LEFT JOIN payments p ON m.id = p.user_membership_id AND p.payment_type = 'upgrade'
          JOIN users u ON m.user_id = u.id
          ORDER BY ur.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$upgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle upgrade and payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $upgrade_id = filter_var($_POST['upgrade_id'], FILTER_VALIDATE_INT);
    $payment_id = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if (!$upgrade_id) {
        $_SESSION['error'] = "Invalid upgrade request ID";
        header("Location: upgrades.php");
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        // Get upgrade request details with payment info
        $query = "SELECT ur.*, m.id as membership_id, m.user_id, m.plan_id as current_plan_id,
                        p.id as payment_id, p.status as payment_status
                 FROM plan_upgrade_requests ur
                 JOIN user_memberships m ON ur.user_membership_id = m.id
                 LEFT JOIN payments p ON m.id = p.user_membership_id AND p.payment_type = 'upgrade'
                 WHERE ur.id = :upgrade_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':upgrade_id', $upgrade_id, PDO::PARAM_INT);
        $stmt->execute();
        $upgrade = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$upgrade) {
            throw new Exception("Upgrade request not found");
        }

        if ($action === 'approve') {
            // Verify payment exists and is pending
            if (!$upgrade['payment_id'] || $upgrade['payment_status'] !== 'pending') {
                throw new Exception("No pending payment found for this upgrade request");
            }

            // Update payment status
            $query = "UPDATE payments 
                     SET status = 'completed' 
                     WHERE id = :payment_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':payment_id', $upgrade['payment_id'], PDO::PARAM_INT);
            $stmt->execute();

            // Update upgrade request status
            $query = "UPDATE plan_upgrade_requests 
                     SET status = 'approved',
                         processed_at = CURRENT_TIMESTAMP
                     WHERE id = :upgrade_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':upgrade_id', $upgrade_id, PDO::PARAM_INT);
            $stmt->execute();

            // Update membership with new plan
            $query = "UPDATE user_memberships 
                     SET plan_id = :new_plan_id,
                         status = 'active'
                     WHERE id = :membership_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':new_plan_id', $upgrade['new_plan_id'], PDO::PARAM_INT);
            $stmt->bindParam(':membership_id', $upgrade['membership_id'], PDO::PARAM_INT);
            $stmt->execute();

            // Log the upgrade
            $query = "INSERT INTO membership_logs (user_membership_id, action, details)
                     VALUES (:membership_id, 'upgrade_approved', :details)";
            $stmt = $db->prepare($query);
            $details = json_encode([
                'old_plan' => $upgrade['current_plan_id'],
                'new_plan' => $upgrade['new_plan_id'],
                'payment_id' => $upgrade['payment_id']
            ]);
            $stmt->bindParam(':membership_id', $upgrade['membership_id'], PDO::PARAM_INT);
            $stmt->bindParam(':details', $details);
            $stmt->execute();
            
            $_SESSION['success'] = "Upgrade request and payment approved successfully.";
        } elseif ($action === 'reject') {
            // Update payment status if exists
            if ($upgrade['payment_id']) {
                $query = "UPDATE payments 
                         SET status = 'failed' 
                         WHERE id = :payment_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':payment_id', $upgrade['payment_id'], PDO::PARAM_INT);
                $stmt->execute();
            }

            // Update upgrade request status
            $query = "UPDATE plan_upgrade_requests 
                     SET status = 'rejected',
                         processed_at = CURRENT_TIMESTAMP
                     WHERE id = :upgrade_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':upgrade_id', $upgrade_id, PDO::PARAM_INT);
            $stmt->execute();

            // Reset membership status
            $query = "UPDATE user_memberships 
                     SET status = 'active' 
                     WHERE id = :membership_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':membership_id', $upgrade['membership_id'], PDO::PARAM_INT);
            $stmt->execute();

            // Log the rejection
            $query = "INSERT INTO membership_logs (user_membership_id, action, details)
                     VALUES (:membership_id, 'upgrade_rejected', :details)";
            $stmt = $db->prepare($query);
            $details = json_encode([
                'reason' => 'Admin rejected',
                'current_plan' => $upgrade['current_plan_id'],
                'requested_plan' => $upgrade['new_plan_id']
            ]);
            $stmt->bindParam(':membership_id', $upgrade['membership_id'], PDO::PARAM_INT);
            $stmt->bindParam(':details', $details);
            $stmt->execute();
            
            $_SESSION['success'] = "Upgrade request rejected successfully.";
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Upgrade processing error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process upgrade request: " . $e->getMessage();
    }
    
    header("Location: upgrades.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Requests - FitClub</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Upgrade Requests</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage membership plan upgrade requests</p>
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

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                        <i class="fas fa-exchange-alt text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Requests</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($upgrades); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $pending = count(array_filter($upgrades, function($u) { return $u['status'] === 'pending'; }));
                            echo $pending;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Approved Requests</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $approved = count(array_filter($upgrades, function($u) { return $u['status'] === 'approved'; }));
                            echo $approved;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrades Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Plan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Plan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upgrade Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($upgrades as $upgrade): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($upgrade['fullname']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($upgrade['username']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($upgrade['current_plan_name']); ?></div>
                                    <div class="text-sm text-gray-500">₱<?php echo number_format($upgrade['current_price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($upgrade['new_plan_name']); ?></div>
                                    <div class="text-sm text-gray-500">₱<?php echo number_format($upgrade['new_price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ₱<?php echo number_format($upgrade['upgrade_amount'], 2); ?>
                                    </div>
                                    <?php if ($upgrade['reference_number']): ?>
                                        <div class="text-xs text-gray-500">Ref: <?php echo htmlspecialchars($upgrade['reference_number']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($upgrade['payment_id']): ?>
                                        <span class="status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $upgrade['payment_status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                ($upgrade['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($upgrade['payment_status'])); ?>
                                        </span>
                                        <?php if ($upgrade['receipt_path']): ?>
                                            <a href="../uploads/receipts/<?php echo htmlspecialchars($upgrade['receipt_path']); ?>" 
                                               target="_blank" 
                                               class="ml-2 text-indigo-600 hover:text-indigo-900 text-xs">
                                                <i class="fas fa-receipt"></i> View Receipt
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">No payment yet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $upgrade['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                            ($upgrade['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($upgrade['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($upgrade['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <?php if ($upgrade['status'] === 'pending'): ?>
                                            <?php if ($upgrade['payment_id'] && $upgrade['payment_status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="upgrade_id" value="<?php echo $upgrade['id']; ?>">
                                                    <input type="hidden" name="payment_id" value="<?php echo $upgrade['payment_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="text-green-600 hover:text-green-900 transition-colors" title="Approve Upgrade & Payment">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="upgrade_id" value="<?php echo $upgrade['id']; ?>">
                                                    <input type="hidden" name="payment_id" value="<?php echo $upgrade['payment_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 transition-colors" title="Reject Upgrade & Payment">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-500">Waiting for payment</span>
                                            <?php endif; ?>
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