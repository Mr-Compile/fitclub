<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's memberships with plan details and upgrade status
$query = "SELECT m.*, mp.name as plan_name, mp.price, mp.description,
          p.status as payment_status, p.reference_number, p.receipt_path,
          c.specialization, u.fullname as coach_name,
          ur.id as upgrade_request_id, ur.status as upgrade_status,
          ur.new_plan_id, mp_new.name as new_plan_name,
          p_upgrade.id as upgrade_payment_id, p_upgrade.status as upgrade_payment_status,
          p_upgrade.receipt_path as upgrade_receipt_path
          FROM user_memberships m
          JOIN membership_plans mp ON m.plan_id = mp.id
          LEFT JOIN payments p ON m.id = p.user_membership_id AND p.payment_type = 'initial'
          LEFT JOIN coaches c ON m.coach_id = c.id
          LEFT JOIN users u ON c.user_id = u.id
          LEFT JOIN plan_upgrade_requests ur ON m.id = ur.user_membership_id
          LEFT JOIN membership_plans mp_new ON ur.new_plan_id = mp_new.id
          LEFT JOIN payments p_upgrade ON m.id = p_upgrade.user_membership_id AND p_upgrade.payment_type = 'upgrade'
          WHERE m.user_id = :user_id
          ORDER BY m.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for upcoming membership expiration
$today = new DateTime();
$expiration_notices = [];

foreach ($memberships as $membership) {
    if ($membership['status'] === 'active') {
        $end_date = new DateTime($membership['end_date']);
        $days_remaining = $today->diff($end_date)->days;
        
        if ($days_remaining <= 3 && $days_remaining > 0) {
            $expiration_notices[] = [
                'message' => "Your {$membership['plan_name']} membership will expire in {$days_remaining} day" . ($days_remaining > 1 ? 's' : ''),
                'membership_id' => $membership['id'],
                'plan_id' => $membership['plan_id'],
                'plan_name' => $membership['plan_name'],
                'price' => $membership['price']
            ];
        }
    }
}

// Handle membership renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_membership'])) {
    $membership_id = $_POST['membership_id'];
    $plan_id = $_POST['plan_id'];
    
    // Get the membership plan details
    $plan_query = "SELECT duration, price FROM membership_plans WHERE id = :plan_id";
    $plan_stmt = $db->prepare($plan_query);
    $plan_stmt->bindParam(":plan_id", $plan_id);
    $plan_stmt->execute();
    $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current membership end date
    $current_query = "SELECT end_date FROM user_memberships WHERE id = :membership_id";
    $current_stmt = $db->prepare($current_query);
    $current_stmt->bindParam(":membership_id", $membership_id);
    $current_stmt->execute();
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate new end date
    $current_end_date = new DateTime($current['end_date']);
    $new_end_date = clone $current_end_date;
    $new_end_date->modify("+{$plan['duration']} months");
    
    // Generate reference number for payment
    $reference_number = 'FIT' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Create payment record
    $payment_query = "INSERT INTO payments (user_membership_id, amount, payment_method, reference_number, status, payment_type, is_upgrading) 
                     VALUES (:user_membership_id, :amount, 'qr_code', :reference_number, 'pending', 'renewal', 0)";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->bindParam(":user_membership_id", $membership_id);
    $payment_stmt->bindParam(":amount", $plan['price']);
    $payment_stmt->bindParam(":reference_number", $reference_number);
    
    if ($payment_stmt->execute()) {
        $new_payment_id = $db->lastInsertId();
        $_SESSION['success'] = "Renewal initiated! Please complete the payment to renew your membership.";
        $_SESSION['payment_reference'] = $reference_number;
        header("Location: renewal_payment_receipt.php?payment_id=" . $new_payment_id);
        exit();
    } else {
        $_SESSION['error'] = "Failed to initiate renewal. Please try again.";
    }
}

// Debug information
if (empty($memberships)) {
    $_SESSION['error'] = "No memberships found for this user.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .membership-card {
            transition: transform 0.3s ease;
        }
        .membership-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .status-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">My Memberships</h1>
            <p class="text-gray-600">Manage your membership plans and track your subscription status</p>
        </div>

        <div class="space-y-6">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($expiration_notices)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 p-6 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <?php foreach ($expiration_notices as $notice): ?>
                                <div class="flex justify-between items-center mb-4 last:mb-0">
                                    <div>
                                        <h3 class="text-lg font-medium text-yellow-800">Membership Expiring Soon</h3>
                                        <p class="mt-1 text-sm text-yellow-700"><?php echo htmlspecialchars($notice['message']); ?></p>
                                        <p class="text-sm font-medium text-yellow-800 mt-2">
                                            Renewal amount: ₱<?php echo number_format($notice['price'], 2); ?>
                                        </p>
                                    </div>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="membership_id" value="<?php echo $notice['membership_id']; ?>">
                                        <input type="hidden" name="plan_id" value="<?php echo $notice['plan_id']; ?>">
                                        <button type="submit" name="renew_membership" 
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-gray-900 hover:bg-gray-800 transition-colors">
                                            <i class="fas fa-sync-alt mr-2"></i> Renew Now
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Active Memberships</h2>
                    <a href="select_plan.php" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-gray-900 hover:bg-gray-800 transition-colors">
                        <i class="fas fa-plus mr-2"></i> New Membership
                    </a>
                </div>

                <div class="space-y-6">
                    <?php foreach ($memberships as $membership): ?>
                        <div class="membership-card border rounded-xl p-6 <?php echo $membership['status'] === 'active' ? 'border-green-200 bg-green-50' : 'border-gray-200'; ?>">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($membership['plan_name']); ?></h3>
                                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($membership['description']); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="status-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php 
                                        echo $membership['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                            ($membership['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                    ?>">
                                        <i class="fas <?php 
                                            echo $membership['status'] === 'active' ? 'fa-check-circle' : 
                                                ($membership['status'] === 'pending' ? 'fa-clock' : 'fa-times-circle'); 
                                        ?> mr-1"></i>
                                        <?php echo ucfirst($membership['status']); ?>
                                        <?php if ($membership['upgrade_status'] === 'pending'): ?>
                                            <span class="ml-1">(Upgrade Pending)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="flex items-center">
                                        <div class="p-3 bg-gray-100 rounded-lg">
                                            <i class="fas fa-calendar-alt text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm text-gray-500">Membership Period</p>
                                            <p class="text-base font-semibold text-gray-900">
                                                <?php echo date('M d, Y', strtotime($membership['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($membership['end_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="flex items-center">
                                        <div class="p-3 bg-gray-100 rounded-lg">
                                            <i class="fas fa-tag text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm text-gray-500">Amount</p>
                                            <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($membership['price'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($membership['coach_name']): ?>
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="flex items-center">
                                        <div class="p-3 bg-gray-100 rounded-lg">
                                            <i class="fas fa-user-tie text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm text-gray-500">Assigned Coach</p>
                                            <p class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($membership['coach_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo ucwords(str_replace('_', ' ', $membership['specialization'])); ?> Specialist</p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($membership['reference_number']): ?>
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="flex items-center">
                                        <div class="p-3 bg-gray-100 rounded-lg">
                                            <i class="fas fa-hashtag text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm text-gray-500">Reference Number</p>
                                            <p class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($membership['reference_number']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6 flex space-x-4">
                                <?php if ($membership['status'] === 'pending'): ?>
                                    <a href="payment_receipt.php?membership_id=<?php echo $membership['id']; ?>" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-gray-900 hover:bg-gray-800 transition-colors">
                                        <i class="fas fa-upload mr-2"></i> Upload Payment Receipt
                                    </a>
                                <?php elseif ($membership['status'] === 'active' && $membership['payment_status'] === 'completed' && !$membership['upgrade_request_id']): ?>
                                    <a href="upgrade_plan.php?membership_id=<?php echo $membership['id']; ?>" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-gray-900 hover:bg-gray-800 transition-colors">
                                        <i class="fas fa-arrow-up mr-2"></i> Upgrade Plan
                                    </a>
                                <?php endif; ?>

                                <?php if ($membership['upgrade_status'] === 'pending'): ?>
                                    <div class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <span>Upgrade request pending approval</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4 space-y-2">
                                <?php if ($membership['upgrade_request_id']): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500">Upgrade Status:</span>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php
                                            switch($membership['upgrade_status']) {
                                                case 'pending':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'approved':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'rejected':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                            }
                                            ?>">
                                            <?php echo ucfirst($membership['upgrade_status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($membership['upgrade_status'] === 'pending'): ?>
                                        <?php if ($membership['upgrade_payment_status'] === 'pending'): ?>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm text-gray-500">Payment Status:</span>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            </div>
                                            <div class="mt-2">
                                                <a href="upgrade_payment_receipt.php?upgrade_id=<?php echo $membership['upgrade_request_id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
                                                    <i class="fas fa-upload mr-1"></i> Upload Payment Receipt
                                                </a>
                                            </div>
                                        <?php elseif ($membership['upgrade_payment_status'] === 'completed'): ?>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm text-gray-500">Payment Status:</span>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Completed
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html> 