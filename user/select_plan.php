<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['plan_id'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get selected plan
$query = "SELECT * FROM membership_plans WHERE id = :plan_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":plan_id", $_GET['plan_id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: dashboard.php");
    exit();
}

$plan = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all coaches with their user information
$query = "SELECT c.*, u.fullname, u.email 
          FROM coaches c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.active = 1
          ORDER BY c.rating DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's assessment data
$query = "SELECT * FROM pre_assessments WHERE user_id = :user_id AND completed = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recommended coaches based on BMI
$recommended_coach_ids = [];
if ($assessment) {
    $bmi = $assessment['bmi'];
    $specialization = '';
    
    if ($bmi < 18.5) {
        $specialization = 'bulk';
    } elseif ($bmi < 25) {
        $specialization = 'strength';
    } else {
        $specialization = 'weight_loss';
    }

    $query = "SELECT id FROM coaches WHERE specialization = :specialization AND active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":specialization", $specialization);
    $stmt->execute();
    $recommended_coach_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Set coach_id based on plan type
        $coach_id = null;
        if ($plan['name'] !== 'Basic') {
            if (!isset($_POST['coach_id'])) {
                throw new Exception("Please select a coach");
            }
            $coach_id = $_POST['coach_id'];
            
            // Verify coach exists and is active
            $query = "SELECT * FROM coaches WHERE id = :coach_id AND active = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":coach_id", $coach_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Selected coach is not available");
            }
        }

        // Calculate membership dates
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+1 month'));

        // Insert membership
        $query = "INSERT INTO user_memberships (
            user_id, plan_id, coach_id, start_date, end_date, 
            payment_status, status, is_pending
        ) VALUES (
            :user_id, :plan_id, :coach_id, :start_date, :end_date,
            'pending', 'pending', 1
        )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":plan_id", $plan['id']);
        $stmt->bindParam(":coach_id", $coach_id);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        $membership_id = $db->lastInsertId();

        // Create payment record
        $query = "INSERT INTO payments (
            user_membership_id, amount, payment_method, status, qr_code_path, is_upgrading
        ) VALUES (
            :membership_id, :amount, 'qr_code', 'pending', 'qr-code.png', :is_upgrading
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":membership_id", $membership_id);
        $stmt->bindParam(":amount", $plan['price']);
        $is_upgrading = isset($_POST['is_upgrade']) ? 1 : 0;
        $stmt->bindParam(":is_upgrading", $is_upgrading);
        $stmt->execute();

        $db->commit();
        $_SESSION['success_message'] = "Membership request submitted successfully! Please complete the payment using the QR code.";
        header("Location: payment_receipt.php?membership_id=" . $membership_id);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Plan - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .plan-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-5px);
        }
        .coach-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .coach-card:hover {
            transform: translateY(-3px);
            border-color: #374151;
        }
        .coach-card.selected {
            border-color: #374151;
            background-color: #F3F4F6;
        }
        .feature-item {
            transition: all 0.3s ease;
        }
        .feature-item:hover {
            transform: translateX(5px);
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gray-900 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../index.php" class="flex items-center">
                        <i class="fas fa-dumbbell text-gray-300 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-white">FitClub</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white transition-colors duration-200">
                        <i class="fas fa-arrow-left"></i>
                        <span class="ml-2">Back to Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="plan-card p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                        <i class="fas fa-dumbbell text-2xl text-blue-600"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Complete Your Membership</h1>
                    <p class="text-gray-600 mt-2">Select your coach and complete your membership purchase</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span class="block sm:inline"><?php echo $error; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <!-- Plan Details -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-crown text-yellow-500 mr-2"></i>
                            Selected Plan
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Plan Name</p>
                                <p class="text-xl font-medium text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-500">Price</p>
                                <p class="text-3xl font-bold text-gray-900">$<?php echo number_format($plan['price'], 2); ?></p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <p class="text-sm text-gray-500 mb-3">Features</p>
                            <ul class="space-y-3">
                                <?php foreach (explode("\n", $plan['features']) as $feature): ?>
                                    <li class="feature-item flex items-center text-sm text-gray-600">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        <?php echo htmlspecialchars(trim($feature)); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Coach Selection - Only show for non-basic plans -->
                    <?php if ($plan['name'] !== 'Basic'): ?>
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-user-friends text-purple-500 mr-2"></i>
                            Select Your Coach
                        </h2>
                        <?php if (empty($all_coaches)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600">No coaches available at the moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($all_coaches as $coach): ?>
                                    <div class="coach-card rounded-lg p-4 <?php echo isset($_POST['coach_id']) && $_POST['coach_id'] == $coach['id'] ? 'selected' : ''; ?>">
                                        <label class="flex items-start cursor-pointer">
                                            <input type="radio" name="coach_id" value="<?php echo $coach['id']; ?>" <?php echo $plan['name'] !== 'Basic' ? 'required' : ''; ?>
                                                class="mt-1 h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300">
                                            <div class="ml-3 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($coach['fullname']); ?>
                                                    </p>
                                                    <?php if (in_array($coach['id'], $recommended_coach_ids)): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            <i class="fas fa-star mr-1"></i>
                                                            Recommended
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600 font-medium">
                                                    <?php echo ucwords(str_replace('_', ' ', $coach['specialization'])); ?> Specialist
                                                </p>
                                                <div class="mt-3 space-y-2">
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                                            <i class="fas fa-star text-yellow-500"></i>
                                                        </div>
                                                        <span>Rating: <?php echo number_format($coach['rating'], 1); ?>/5.0</span>
                                                    </div>
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                                            <i class="fas fa-users text-purple-500"></i>
                                                        </div>
                                                        <span><?php echo $coach['clients_count']; ?> Active Clients</span>
                                                    </div>
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                                            <i class="fas fa-graduation-cap text-green-500"></i>
                                                        </div>
                                                        <span><?php echo $coach['experience_years']; ?> Years Experience</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Information -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-credit-card text-green-500 mr-2"></i>
                            Payment Information
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <p class="text-gray-600">
                                    After selecting your coach, you will be redirected to a secure payment page where you can complete your purchase using QR code payment.
                                </p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-green-500 mt-1 mr-3"></i>
                                <p class="text-gray-600">
                                    Your payment information is secure and encrypted. We never store your payment details.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="dashboard.php" class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-lg transition-all duration-200 hover:scale-105">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Complete Purchase
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Add coach selection animation
        document.querySelectorAll('input[name="coach_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove selected class from all coach cards
                document.querySelectorAll('.coach-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Add selected class to the selected coach card
                this.closest('.coach-card').classList.add('selected');
            });
        });
    </script>
</body>
</html> 