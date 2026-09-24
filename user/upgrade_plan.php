<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['membership_id'])) {
    header("Location: membership.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get current membership details
$query = "SELECT m.*, mp.name as plan_name, mp.price as current_price, mp.id as current_plan_id
          FROM user_memberships m
          JOIN membership_plans mp ON m.plan_id = mp.id
          WHERE m.id = :membership_id AND m.user_id = :user_id AND m.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":membership_id", $_GET['membership_id']);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$current_membership = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_membership) {
    $_SESSION['error'] = "Invalid membership or not eligible for upgrade.";
    header("Location: membership.php");
    exit();
}

// Get available plans for upgrade (plans with higher price)
$query = "SELECT * FROM membership_plans 
          WHERE price > :current_price AND active = 1 
          ORDER BY price ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":current_price", $current_membership['current_price']);
$stmt->execute();
$upgrade_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active coaches with their user information and specializations
$query = "SELECT c.id, c.specialization, u.fullname, u.email
          FROM coaches c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.active = 1
          ORDER BY u.fullname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$available_coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_plan_id'])) {
    $new_plan_id = $_POST['new_plan_id'];
    
    // Get new plan details
    $query = "SELECT * FROM membership_plans WHERE id = :plan_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":plan_id", $new_plan_id);
    $stmt->execute();
    $new_plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$new_plan) {
        $_SESSION['error'] = "Selected plan not found.";
        header("Location: upgrade_plan.php?membership_id=" . $_GET['membership_id']);
        exit();
    }
    
    if ($new_plan['price'] <= $current_membership['current_price']) {
        $_SESSION['error'] = "Cannot upgrade to a plan with lower or equal price.";
        header("Location: upgrade_plan.php?membership_id=" . $_GET['membership_id']);
        exit();
    }
    
    // Validate coach selection for Pro and Premium plans
    $selected_coach_id = null;
    if ($current_membership['plan_name'] === 'Basic' && ($new_plan['name'] === 'Pro' || $new_plan['name'] === 'Premium')) {
        if (!isset($_POST['coach_id']) || !isset($_POST['coach_id'][$new_plan_id]) || empty($_POST['coach_id'][$new_plan_id])) {
            $_SESSION['error'] = "Please select a coach for " . $new_plan['name'] . " plan.";
            header("Location: upgrade_plan.php?membership_id=" . $_GET['membership_id']);
            exit();
        }
        $selected_coach_id = $_POST['coach_id'][$new_plan_id];
    }
    
    // Calculate upgrade amount
    $upgrade_amount = $new_plan['price'] - $current_membership['current_price'];
    
    try {
        $db->beginTransaction();
        
        // Check if there's already a pending upgrade request
        $query = "SELECT id FROM plan_upgrade_requests 
                 WHERE user_membership_id = :membership_id 
                 AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":membership_id", $_GET['membership_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("You already have a pending upgrade request.");
        }
        
        // Create upgrade request
        $query = "INSERT INTO plan_upgrade_requests 
                 (user_membership_id, current_plan_id, new_plan_id, upgrade_amount, status) 
                 VALUES (:membership_id, :current_plan_id, :new_plan_id, :upgrade_amount, 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":membership_id", $_GET['membership_id']);
        $stmt->bindParam(":current_plan_id", $current_membership['current_plan_id']);
        $stmt->bindParam(":new_plan_id", $new_plan_id);
        $stmt->bindParam(":upgrade_amount", $upgrade_amount);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create upgrade request.");
        }
        
        $upgrade_id = $db->lastInsertId();
        
        // Update membership with coach if selected
        if ($new_plan['name'] !== 'Basic' && $selected_coach_id) {
            $query = "UPDATE user_memberships SET coach_id = :coach_id WHERE id = :membership_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":coach_id", $selected_coach_id);
            $stmt->bindParam(":membership_id", $_GET['membership_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update coach assignment.");
            }
        }
        
        // Create initial payment record
        $query = "INSERT INTO payments 
                 (user_membership_id, amount, payment_method, status, payment_type, is_upgrading) 
                 VALUES 
                 (:membership_id, :amount, 'qr_code', 'pending', 'upgrade', 1)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":membership_id", $_GET['membership_id']);
        $stmt->bindParam(":amount", $upgrade_amount);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create payment record.");
        }
        
        $db->commit();
        
        // Redirect to payment receipt page
        header("Location: upgrade_payment_receipt.php?upgrade_id=" . $upgrade_id);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: upgrade_plan.php?membership_id=" . $_GET['membership_id']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Membership - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Upgrade Membership</h1>
                    <p class="mt-2 text-gray-600">Current Plan: <?php echo htmlspecialchars($current_membership['plan_name']); ?></p>
                </div>

                <?php if (empty($upgrade_plans)): ?>
                    <div class="text-center py-4">
                        <p class="text-gray-600">No upgrade options available at this time.</p>
                        <a href="membership.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Memberships
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-6" id="upgradeForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($upgrade_plans as $plan): ?>
                                <div class="border rounded-lg p-6 hover:border-indigo-500 cursor-pointer">
                                    <input type="radio" name="new_plan_id" value="<?php echo $plan['id']; ?>" class="hidden" id="plan_<?php echo $plan['id']; ?>">
                                    <label for="plan_<?php echo $plan['id']; ?>" class="block cursor-pointer">
                                        <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                        <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($plan['description']); ?></p>
                                        <div class="mt-4">
                                            <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($plan['price'], 2); ?></p>
                                            <p class="text-sm text-gray-500">Duration: <?php echo $plan['duration']; ?> month(s)</p>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-sm text-gray-600">Upgrade Amount:</p>
                                            <p class="text-lg font-semibold text-green-600">
                                            ₱<?php echo number_format($plan['price'] - $current_membership['current_price'], 2); ?>
                                            </p>
                                        </div>
                                        
                                        <?php if ($current_membership['plan_name'] === 'Basic' && ($plan['name'] === 'Pro' || $plan['name'] === 'Premium')): ?>
                                            <div class="mt-4 coach-selection" data-plan="<?php echo $plan['id']; ?>" style="display: none;">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Select a Coach</label>
                                                <select name="coach_id[<?php echo $plan['id']; ?>]" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                    <option value="">Choose a coach...</option>
                                                    <?php foreach ($available_coaches as $coach): ?>
                                                        <option value="<?php echo $coach['id']; ?>">
                                                            <?php echo htmlspecialchars($coach['fullname']); ?> 
                                                            (<?php echo ucfirst($coach['specialization']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="mt-2 text-sm text-gray-500">
                                                    Select a coach to guide you through your fitness journey
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="membership.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                <i class="fas fa-arrow-up mr-2"></i> Request Upgrade
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Add visual feedback for plan selection
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Hide all coach selections first
                document.querySelectorAll('.coach-selection').forEach(selection => {
                    selection.style.display = 'none';
                });
                
                // Remove selected border from all plans
                document.querySelectorAll('.border').forEach(border => {
                    border.classList.remove('border-indigo-500');
                });
                
                if (this.checked) {
                    // Add border to selected plan
                    this.closest('.border').classList.add('border-indigo-500');
                    
                    // Show coach selection if it's Pro or Premium plan
                    const coachSelection = this.closest('.border').querySelector('.coach-selection');
                    if (coachSelection) {
                        coachSelection.style.display = 'block';
                    }
                }
            });
        });

        // Form submission validation
        document.getElementById('upgradeForm').addEventListener('submit', function(e) {
            const selectedPlan = document.querySelector('input[name="new_plan_id"]:checked');
            if (!selectedPlan) {
                e.preventDefault();
                alert('Please select a plan to upgrade to.');
                return;
            }

            const selectedPlanId = selectedPlan.value;
            const coachSelection = document.querySelector(`.coach-selection[data-plan="${selectedPlanId}"]`);
            
            if (coachSelection && coachSelection.style.display !== 'none') {
                const coachSelect = coachSelection.querySelector('select');
                if (!coachSelect.value) {
                    e.preventDefault();
                    alert('Please select a coach for this plan.');
                    return;
                }
            }
        });
    </script>
</body>
</html> 