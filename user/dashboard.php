<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's assessment data
$query = "SELECT * FROM pre_assessments WHERE user_id = :user_id AND completed = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get membership plans
$query = "SELECT * FROM membership_plans ORDER BY price";
$stmt = $db->prepare($query);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recommended coaches based on BMI and specialization
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

    $query = "SELECT c.*, u.fullname, u.email 
              FROM coaches c 
              JOIN users u ON c.user_id = u.id 
              WHERE c.specialization = :specialization 
              ORDER BY COALESCE(c.rating, 0) DESC, c.clients_count DESC 
              LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":specialization", $specialization);
    $stmt->execute();
    $recommended_coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's current membership if any
$query = "SELECT m.*, mp.name as plan_name, mp.price 
          FROM user_memberships m 
          JOIN membership_plans mp ON m.plan_id = mp.id 
          WHERE m.user_id = :user_id 
          AND m.status = 'active' 
          AND m.end_date >= CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$current_membership = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's progress data
$query = "SELECT * FROM member_progress WHERE member_id = :user_id ORDER BY entry_date DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$latest_progress = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's active workout plan
$query = "SELECT wp.*, mwp.start_date, mwp.end_date 
          FROM member_workout_plans mwp 
          JOIN workout_plans wp ON mwp.workout_plan_id = wp.id 
          WHERE mwp.member_id = :user_id AND mwp.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$active_workout = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's active diet plan
$query = "SELECT dp.*, mdp.start_date, mdp.end_date 
          FROM member_diet_plans mdp 
          JOIN diet_plans dp ON mdp.diet_plan_id = dp.id 
          WHERE mdp.member_id = :user_id AND mdp.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$active_diet = $stmt->fetch(PDO::FETCH_ASSOC);

// Get calorie tracking for the last 7 days
$query = "SELECT date, calories_burned 
          FROM calorie_tracking 
          WHERE member_id = :user_id 
          AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
          ORDER BY date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$calorie_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assessments
$query = "SELECT a.*, u.fullname as coach_name 
          FROM assessments a 
          JOIN users u ON a.coach_id = u.id 
          WHERE a.member_id = :user_id 
          ORDER BY a.assessment_date DESC 
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all active coaches with their details
$query = "SELECT c.*, u.fullname, u.email, u.phone,
          (SELECT COUNT(*) FROM user_memberships WHERE coach_id = c.id AND status = 'active') as active_clients,
          (SELECT COUNT(*) FROM member_progress mp 
           JOIN user_memberships um ON mp.member_id = um.user_id 
           WHERE um.coach_id = c.id AND mp.weight < (
               SELECT weight FROM member_progress 
               WHERE member_id = mp.member_id 
               ORDER BY entry_date ASC LIMIT 1
           )) as successful_clients
          FROM coaches c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.active = 1
          ORDER BY c.rating DESC, c.clients_count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get coach's workout plans count
$query = "SELECT coach_id, COUNT(*) as workout_plans_count 
          FROM workout_plans 
          WHERE is_active = 1 
          GROUP BY coach_id";
$stmt = $db->prepare($query);
$stmt->execute();
$coach_workout_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get coach's diet plans count
$query = "SELECT coach_id, COUNT(*) as diet_plans_count 
          FROM diet_plans 
          WHERE is_active = 1 
          GROUP BY coach_id";
$stmt = $db->prepare($query);
$stmt->execute();
$coach_diet_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative arrays for easy lookup
$workout_plans_count = [];
$diet_plans_count = [];
foreach ($coach_workout_plans as $plan) {
    $workout_plans_count[$plan['coach_id']] = $plan['workout_plans_count'];
}
foreach ($coach_diet_plans as $plan) {
    $diet_plans_count[$plan['coach_id']] = $plan['diet_plans_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .membership-card {
            transition: transform 0.3s ease;
        }
        .membership-card:hover {
            transform: translateY(-2px);
        }
        .coach-card {
            transition: all 0.3s ease;
        }
        .coach-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .activity-item {
            transition: background-color 0.3s ease;
        }
        .activity-item:hover {
            background-color: #f9fafb;
        }
        .hover-scale {
            transition: transform 0.2s ease;
        }
        .hover-scale:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'components/navigation.php'; ?>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="bg-white rounded-xl shadow-sm p-8 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                        <p class="text-gray-500">Track your fitness journey and achieve your goals with FitClub</p>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-dumbbell text-6xl text-gray-200"></i>
                    </div>
                </div>
            </div>

            <?php if (!$assessment): ?>
                <!-- No Assessment Warning -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-8 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-yellow-800">Complete Your Assessment</h3>
                            <p class="mt-2 text-sm text-yellow-700">
                                Get personalized recommendations by completing your pre-assessment. This will help us match you with the perfect coach and create a tailored fitness plan.
                            </p>
                            <div class="mt-4">
                                <a href="pre_assessment.php" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-yellow-700 bg-yellow-100 hover:bg-yellow-200 transition-colors">
                                    <i class="fas fa-clipboard-check mr-2"></i>
                                    Take Assessment Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Membership -->
            <?php if ($current_membership): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Current Membership</h2>
                        <span class="px-4 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                            <?php echo htmlspecialchars($current_membership['plan_name']); ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                    <i class="fas fa-calendar-check text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Start Date</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo date('M d, Y', strtotime($current_membership['start_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                    <i class="fas fa-calendar-times text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">End Date</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo date('M d, Y', strtotime($current_membership['end_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-500">
                                    <i class="fas fa-tag text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Monthly Fee</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        ₱<?php echo number_format($current_membership['price'], 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Progress Tracking Section -->
            <?php if ($latest_progress): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Your Progress</h2>
                        <a href="progress.php" class="text-sm text-gray-600 hover:text-gray-700">
                            View Full Progress <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                    <i class="fas fa-weight text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Current Weight</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo number_format($latest_progress['weight'], 1); ?> kg
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                    <i class="fas fa-percentage text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Body Fat</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo number_format($latest_progress['body_fat'], 1); ?>%
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-500">
                                    <i class="fas fa-dumbbell text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Muscle Mass</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo number_format($latest_progress['muscle_mass'], 1); ?> kg
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                    <i class="fas fa-calendar-alt text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Last Updated</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo date('M d, Y', strtotime($latest_progress['entry_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Active Plans Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php if ($active_workout): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-900">Active Workout Plan</h2>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                Active
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($active_workout['name']); ?>
                        </h3>
                        <p class="text-gray-600 mb-4">
                            <?php echo htmlspecialchars($active_workout['description']); ?>
                        </p>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Duration</p>
                                <p class="font-medium text-gray-900"><?php echo $active_workout['duration_weeks']; ?> weeks</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Difficulty</p>
                                <p class="font-medium text-gray-900"><?php echo ucfirst($active_workout['difficulty_level']); ?></p>
                            </div>
                        </div>
                        <a href="workout_plan.php?id=<?php echo $active_workout['id']; ?>" 
                           class="block w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                            View Plan Details
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($active_diet): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-900">Active Diet Plan</h2>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                Active
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($active_diet['name']); ?>
                        </h3>
                        <p class="text-gray-600 mb-4">
                            <?php echo htmlspecialchars($active_diet['description']); ?>
                        </p>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Daily Calories</p>
                                <p class="font-medium text-gray-900"><?php echo $active_diet['calories']; ?> kcal</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Duration Type</p>
                                <p class="font-medium text-gray-900"><?php echo ucfirst($active_diet['duration_type']); ?></p>
                            </div>
                        </div>
                        <a href="diet_plan.php?id=<?php echo $active_diet['id']; ?>" 
                           class="block w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                            View Plan Details
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Calorie Tracking Section -->
            <?php if (!empty($calorie_data)): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Weekly Calorie Burn</h2>
                        <a href="calorie_tracking.php" class="text-sm text-gray-600 hover:text-gray-700">
                            View Full History <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="grid grid-cols-7 gap-4">
                        <?php 
                        $total_calories = 0;
                        foreach ($calorie_data as $data): 
                            $total_calories += $data['calories_burned'];
                        ?>
                            <div class="text-center">
                                <div class="bg-white rounded-lg p-3 border border-gray-100">
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('D', strtotime($data['date'])); ?>
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo $data['calories_burned']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">kcal</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-500">Total Calories Burned This Week</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_calories; ?> kcal</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Latest Assessment Section -->
            <?php if ($latest_assessment): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Latest Assessment</h2>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                            <?php echo date('M d, Y', strtotime($latest_assessment['assessment_date'])); ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                    <i class="fas fa-user-md text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Assessed By</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($latest_assessment['coach_name']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                    <i class="fas fa-weight text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Weight</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo number_format($latest_assessment['weight'], 1); ?> kg
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-gray-100">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-500">
                                    <i class="fas fa-percentage text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Body Fat</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo number_format($latest_assessment['body_fat'], 1); ?>%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($latest_assessment['notes']): ?>
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-500 mb-2">Coach's Notes</p>
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($latest_assessment['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Membership Plans -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Membership Plans</h2>
                    <?php if (!$current_membership): ?>
                        <a href="membership.php" class="text-sm text-gray-600 hover:text-gray-700">
                            View All Plans <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($plans as $plan): ?>
                        <div class="membership-card border rounded-xl p-6 <?php echo $current_membership && $current_membership['plan_id'] == $plan['id'] ? 'border-gray-700 bg-gray-50' : 'border-gray-200 hover:border-gray-300'; ?>">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <?php if ($current_membership && $current_membership['plan_id'] == $plan['id']): ?>
                                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                        Current Plan
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-3xl font-bold text-gray-900 mb-4">
                                ₱<?php echo number_format($plan['price'], 2); ?>
                                <span class="text-base font-normal text-gray-500">/month</span>
                            </p>
                            <ul class="space-y-3 mb-6">
                                <?php 
                                $features = explode("\n", $plan['features']);
                                foreach ($features as $feature): 
                                    if (trim($feature) !== ''): 
                                ?>
                                    <li class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <?php echo htmlspecialchars(trim($feature)); ?>
                                    </li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </ul>
                            <?php if ($current_membership): ?>
                                <?php if ($current_membership['plan_id'] == $plan['id']): ?>
                                    <button disabled class="w-full bg-gray-100 text-gray-700 px-4 py-2 rounded-lg font-medium">
                                        Current Plan
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-100 text-gray-400 px-4 py-2 rounded-lg font-medium cursor-not-allowed">
                                        Upgrade Available
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="select_plan.php?plan_id=<?php echo $plan['id']; ?>" 
                                   class="block w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                                    Select Plan
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Coaches Section -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Our Coaches</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($all_coaches as $coach): ?>
                        <div class="coach-card bg-white border border-gray-200 rounded-xl p-6">
                            <div class="flex items-center mb-6">
                                <div class="flex-shrink-0">
                                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-circle text-3xl text-blue-500"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-bold text-gray-900">
                                        <?php echo htmlspecialchars($coach['fullname']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 font-medium">
                                        <?php echo ucwords(str_replace('_', ' ', $coach['specialization'])); ?> Specialist
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-3 mb-6">
                                <div class="flex items-center text-sm text-gray-600">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-star text-yellow-500"></i>
                                    </div>
                                    <span class="font-medium"><?php echo number_format($coach['rating'], 1); ?></span>
                                    <span class="text-gray-400 ml-1">/ 5.0</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-users text-purple-500"></i>
                                    </div>
                                    <span><?php echo $coach['active_clients']; ?> Active Clients</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-graduation-cap text-green-500"></i>
                                    </div>
                                    <span><?php echo $coach['experience_years']; ?> Years Experience</span>
                                </div>
                            </div>
                            <button onclick="openCoachModal(<?php echo htmlspecialchars(json_encode($coach)); ?>, 
                                    <?php echo isset($workout_plans_count[$coach['id']]) ? $workout_plans_count[$coach['id']] : 0; ?>, 
                                    <?php echo isset($diet_plans_count[$coach['id']]) ? $diet_plans_count[$coach['id']] : 0; ?>)"
                                    class="block w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                                View Profile
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            

            <!-- Coach Modal -->
            <div id="coachModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <!-- Modal Header with Gradient Background -->
                    <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-r from-gray-800 to-gray-900 rounded-t-md"></div>
                    
                    <!-- Close Button -->
                    <button onclick="closeCoachModal()" class="absolute top-4 right-4 text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>

                    <!-- Coach Profile Section -->
                    <div class="relative pt-20 pb-6">
                        <div class="flex items-center justify-center mb-6">
                            <div class="w-24 h-24 bg-white rounded-full shadow-lg flex items-center justify-center border-4 border-white">
                                <i class="fas fa-user-circle text-5xl text-gray-600"></i>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold text-center text-gray-900 mb-2" id="modalCoachName"></h3>
                        <p class="text-center text-gray-600 font-medium" id="modalCoachSpecialization"></p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white rounded-lg p-4 text-center border border-gray-100">
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-star text-yellow-500 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-600">Rating</p>
                            <p class="text-lg font-bold text-gray-900" id="modalCoachRating"></p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center border border-gray-100">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-users text-purple-500 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-600">Active Clients</p>
                            <p class="text-lg font-bold text-gray-900" id="modalCoachClients"></p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center border border-gray-100">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-graduation-cap text-green-500 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-600">Experience</p>
                            <p class="text-lg font-bold text-gray-900" id="modalCoachExperience"></p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center border border-gray-100">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-trophy text-blue-500 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-600">Success Rate</p>
                            <p class="text-lg font-bold text-gray-900" id="modalCoachSuccess"></p>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-white rounded-lg p-6 mb-6 border border-gray-100">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-address-card text-blue-500 mr-2"></i>
                            Contact Information
                        </h4>
                        <div class="space-y-3">
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-envelope text-blue-500"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="font-medium text-gray-900" id="modalCoachEmail"></p>
                                </div>
                            </div>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-phone text-purple-500"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Phone</p>
                                    <p class="font-medium text-gray-900" id="modalCoachPhone"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Plans -->
                    <div class="bg-white rounded-lg p-6 border border-gray-100">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-dumbbell text-green-500 mr-2"></i>
                            Available Plans
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-running text-green-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Workout Plans</p>
                                        <p class="text-lg font-bold text-gray-900" id="modalCoachWorkoutPlans"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-utensils text-blue-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Diet Plans</p>
                                        <p class="text-lg font-bold text-gray-900" id="modalCoachDietPlans"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function openCoachModal(coach, workoutPlansCount, dietPlansCount) {
                    document.getElementById('coachModal').classList.remove('hidden');
                    document.getElementById('modalCoachName').textContent = coach.fullname;
                    document.getElementById('modalCoachEmail').textContent = coach.email;
                    document.getElementById('modalCoachPhone').textContent = coach.phone || 'Not provided';
                    document.getElementById('modalCoachSpecialization').textContent = 
                        coach.specialization.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                    document.getElementById('modalCoachRating').textContent = coach.rating + ' / 5.0';
                    document.getElementById('modalCoachExperience').textContent = coach.experience_years + ' years';
                    document.getElementById('modalCoachClients').textContent = coach.active_clients;
                    document.getElementById('modalCoachSuccess').textContent = 
                        Math.round((coach.successful_clients / Math.max(coach.clients_count, 1)) * 100) + '%';
                    document.getElementById('modalCoachWorkoutPlans').textContent = workoutPlansCount;
                    document.getElementById('modalCoachDietPlans').textContent = dietPlansCount;
                }

                function closeCoachModal() {
                    document.getElementById('coachModal').classList.add('hidden');
                }

                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('coachModal');
                    if (event.target === modal) {
                        closeCoachModal();
                    }
                }
            </script>
        </main>
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