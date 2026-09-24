<?php
require_once '../includes/auth_check.php';
require_once '../includes/database.php';

// Check if user is a member
if ($_SESSION['role'] !== 'member') {
    header('Location: ../index.php');
    exit();
}

$member_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'log_meal':
                $meal_id = $_POST['meal_id'];
                $meal_date = $_POST['meal_date'];
                $notes = $_POST['notes'];

                try {
                    $stmt = $db->prepare("INSERT INTO meal_logs (member_id, meal_id, meal_date, notes) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$member_id, $meal_id, $meal_date, $notes]);
                    $message = "Meal logged successfully!";
                } catch (PDOException $e) {
                    $error = "Error logging meal: " . $e->getMessage();
                }
                break;
            case 'submit_feedback':
                $diet_plan_id = $_POST['diet_plan_id'];
                $rating = $_POST['rating'];
                $feedback = $_POST['feedback'];

                try {
                    // Get member_diet_plan_id
                    $stmt = $db->prepare("
                        SELECT id 
                        FROM member_diet_plans 
                        WHERE member_id = ? AND diet_plan_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$member_id, $diet_plan_id]);
                    $member_diet_plan = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($member_diet_plan) {
                        $stmt = $db->prepare("
                            INSERT INTO diet_plan_feedback (member_diet_plan_id, rating, feedback)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$member_diet_plan['id'], $rating, $feedback]);
                        $message = "Thank you for your feedback!";
                    }
                } catch (PDOException $e) {
                    $error = "Error submitting feedback: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch member's diet plans
try {
    $stmt = $db->prepare("
        SELECT mdp.*, dp.name, dp.description, dp.specialization, dp.duration_type, dp.calories, dp.protein, dp.carbs, dp.fats, c.user_id as coach_id, u.fullname as coach_name
        FROM member_diet_plans mdp
        JOIN diet_plans dp ON mdp.diet_plan_id = dp.id
        JOIN coaches c ON dp.coach_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE mdp.member_id = ? AND mdp.status = 'active'
        ORDER BY mdp.start_date DESC
    ");
    $stmt->execute([$member_id]);
    $diet_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch calorie tracking data
    $stmt = $db->prepare("
        SELECT
            d.date,
            COALESCE(meals.calories_consumed, 0) AS calories_consumed,
            COALESCE(exs.calories_burned, 0) AS calories_burned,
            (COALESCE(meals.calories_consumed, 0) - COALESCE(exs.calories_burned, 0)) AS net_calories,
            ((COALESCE(meals.calories_consumed, 0) - COALESCE(exs.calories_burned, 0)) / 3500) AS expected_weight_change
        FROM (
            SELECT ? - INTERVAL n DAY AS date
            FROM (
                SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29
            ) days
        ) d
        LEFT JOIN (
            SELECT
                meal_date,
                SUM(dpm.calories) AS calories_consumed
            FROM meal_logs ml
            JOIN diet_plan_meals dpm ON ml.meal_id = dpm.id
            WHERE ml.member_id = ?
            GROUP BY meal_date
        ) meals ON d.date = meals.meal_date
        LEFT JOIN (
            SELECT
                exercise_date,
                SUM(calories_burned) AS calories_burned
            FROM exercise_logs
            WHERE member_id = ?
            GROUP BY exercise_date
        ) exs ON d.date = exs.exercise_date
        WHERE d.date >= (
            SELECT start_date 
            FROM member_diet_plans 
            WHERE member_id = ? AND status = 'active' 
            ORDER BY start_date DESC 
            LIMIT 1
        )
        ORDER BY d.date DESC
        LIMIT 30
    ");
    $stmt->execute([date('Y-m-d'), $member_id, $member_id, $member_id]);
    $calorie_tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get member's current weight
    $stmt = $db->prepare("
        SELECT weight 
        FROM member_progress 
        WHERE member_id = ? 
        ORDER BY entry_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$member_id]);
    $current_weight = $stmt->fetch(PDO::FETCH_ASSOC)['weight'] ?? null;

    // Calculate total expected weight change
    $total_expected_change = 0;
    foreach ($calorie_tracking as $tracking) {
        $total_expected_change += $tracking['expected_weight_change'];
    }
    $expected_future_weight = $current_weight ? $current_weight + $total_expected_change : null;
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $diet_plans = [];
    $calorie_tracking = [];
    $current_weight = null;
    $total_expected_change = 0;
    $expected_future_weight = null;
}

// Function to check if meal is completed for today
function isMealCompletedToday($db, $member_id, $meal_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed
        FROM meal_logs
        WHERE member_id = ? AND meal_id = ? AND DATE(meal_date) = CURDATE()
    ");
    $stmt->execute([$member_id, $meal_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['completed'] > 0;
}

// Function to check if meal can be logged
function canLogMeal($db, $member_id, $meal_id) {
    // Get meal's day number
    $stmt = $db->prepare("
        SELECT meal_order, diet_plan_id 
        FROM diet_plan_meals 
        WHERE id = ?
    ");
    $stmt->execute([$meal_id]);
    $meal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meal) return false;
    
    // Calculate which day this meal belongs to
    $day = ceil($meal['meal_order'] / 3); // Assuming 3 meals per day
    
    // Get the start date of the diet plan
    $stmt = $db->prepare("
        SELECT start_date 
        FROM member_diet_plans 
        WHERE member_id = ? AND diet_plan_id = ? AND status = 'active'
    ");
    $stmt->execute([$member_id, $meal['diet_plan_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) return false;
    
    // Calculate the target date for this meal
    $target_date = date('Y-m-d', strtotime($plan['start_date'] . ' + ' . ($day - 1) . ' days'));
    
    // Check if today is the target date
    return date('Y-m-d') === $target_date;
}

// Function to get meal completion stats
function getMealCompletionStats($db, $member_id, $diet_plan_id) {
    // Get total meals for today
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_meals
        FROM diet_plan_meals
        WHERE diet_plan_id = ? AND meal_order <= 3
    ");
    $stmt->execute([$diet_plan_id]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total_meals'];

    // Get completed meals for today
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed_meals
        FROM meal_logs ml
        JOIN diet_plan_meals dpm ON ml.meal_id = dpm.id
        WHERE ml.member_id = ? 
        AND dpm.diet_plan_id = ?
        AND DATE(ml.meal_date) = CURDATE()
        AND dpm.meal_order <= 3
    ");
    $stmt->execute([$member_id, $diet_plan_id]);
    $completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed_meals'];

    return [
        'total_meals' => $total,
        'completed_meals' => $completed
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Diet Plans - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Add this right after the opening <body> tag -->
    <div id="imageLightbox" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50 flex items-center justify-center">
        <div class="relative max-w-4xl w-full mx-4">
            <img id="lightboxImage" src="" alt="Meal image" class="w-full h-auto rounded-lg shadow-2xl">
            <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
    </div>

    <?php include 'components/navigation.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">My Diet Plans</h2>
        
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Calorie Tracking -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-semibold text-gray-800">Calorie Tracking</h5>
            </div>
            <div class="p-6">
                <!-- Weight Summary -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h6 class="text-sm font-medium text-gray-500">Current Weight</h6>
                        <p class="text-xl font-bold text-gray-900">
                            <?php echo $current_weight ? number_format($current_weight, 1) . ' lbs' : 'Not recorded'; ?>
                        </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h6 class="text-sm font-medium text-gray-500">Expected Change</h6>
                        <p class="text-xl font-bold <?php echo $total_expected_change > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php 
                            echo number_format($total_expected_change, 1) . ' lbs';
                            echo $total_expected_change > 0 ? ' gain' : ' loss';
                            ?>
                        </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h6 class="text-sm font-medium text-gray-500">Expected Future Weight</h6>
                        <p class="text-xl font-bold text-gray-900">
                            <?php echo $expected_future_weight ? number_format($expected_future_weight, 1) . ' lbs' : 'Not available'; ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <canvas id="calorieChart" class="w-full h-64"></canvas>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consumed</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Burned</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Change</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($calorie_tracking as $tracking): ?>
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d', strtotime($tracking['date'])); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo $tracking['calories_consumed'] ?? 0; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo $tracking['calories_burned']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo ($tracking['calories_consumed'] ?? 0) - $tracking['calories_burned']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm <?php echo $tracking['expected_weight_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php 
                                            $weight_change = $tracking['expected_weight_change'];
                                            echo number_format($weight_change, 2) . ' lbs';
                                            echo $weight_change > 0 ? ' gain' : ' loss';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Recent Meal Logs Section after the Calorie Tracking section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">Recent Meal Logs</h2>
            <?php
            // Fetch recent meal logs
            $stmt = $db->prepare("
                SELECT ml.*, dpm.meal_name, dpm.meal_type, dpm.calories, dpm.protein, dpm.carbs, dpm.fats, dpm.image_path
                FROM meal_logs ml
                JOIN diet_plan_meals dpm ON ml.meal_id = dpm.id
                WHERE ml.member_id = ?
                ORDER BY ml.meal_date DESC, ml.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$member_id]);
            $recent_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($recent_meals)): ?>
                <p class="text-gray-600">No meal logs found.</p>
            <?php else: ?>
                <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach ($recent_meals as $meal): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-start space-x-4">
                                <?php if ($meal['image_path']): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($meal['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($meal['meal_name']); ?>" 
                                         class="w-16 h-16 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                         onclick="openLightbox(this.src, '<?php echo htmlspecialchars($meal['meal_name']); ?>')">
                                <?php endif; ?>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($meal['meal_name']); ?></h3>
                                            <?php
                                            $mealTypeClass = '';
                                            $mealTypeIcon = '';
                                            switch($meal['meal_type']) {
                                                case 'breakfast':
                                                    $mealTypeClass = 'bg-yellow-100 text-yellow-800';
                                                    $mealTypeIcon = 'fa-sun';
                                                    break;
                                                case 'lunch':
                                                    $mealTypeClass = 'bg-orange-100 text-orange-800';
                                                    $mealTypeIcon = 'fa-utensils';
                                                    break;
                                                case 'dinner':
                                                    $mealTypeClass = 'bg-blue-100 text-blue-800';
                                                    $mealTypeIcon = 'fa-moon';
                                                    break;
                                                case 'snack':
                                                    $mealTypeClass = 'bg-green-100 text-green-800';
                                                    $mealTypeIcon = 'fa-cookie';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $mealTypeClass; ?> flex items-center mt-1">
                                                <i class="fas <?php echo $mealTypeIcon; ?> mr-1"></i>
                                                <?php echo ucfirst($meal['meal_type']); ?>
                                            </span>
                                        </div>
                                        <span class="text-sm font-medium text-indigo-600"><?php echo $meal['calories']; ?> kcal</span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">Date: <?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></p>
                                    <div class="grid grid-cols-3 gap-2 mt-2 text-sm">
                                        <div class="bg-gray-50 p-2 rounded text-center">
                                            <span class="text-xs text-gray-500">Protein</span>
                                            <p class="font-medium"><?php echo $meal['protein']; ?>g</p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded text-center">
                                            <span class="text-xs text-gray-500">Carbs</span>
                                            <p class="font-medium"><?php echo $meal['carbs']; ?>g</p>
                                        </div>
                                        <div class="bg-gray-50 p-2 rounded text-center">
                                            <span class="text-xs text-gray-500">Fats</span>
                                            <p class="font-medium"><?php echo $meal['fats']; ?>g</p>
                                        </div>
                                    </div>
                                    <?php if ($meal['notes']): ?>
                                        <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($meal['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Diet Plan Feedback Section -->
        <?php if (!empty($diet_plans)): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold mb-4">Diet Plan Feedback</h2>
                <?php foreach ($diet_plans as $plan): 
                    // Check if feedback already exists
                    $stmt = $db->prepare("
                        SELECT dpf.* 
                        FROM diet_plan_feedback dpf
                        JOIN member_diet_plans mdp ON dpf.member_diet_plan_id = mdp.id
                        WHERE mdp.member_id = ? AND mdp.diet_plan_id = ?
                    ");
                    $stmt->execute([$member_id, $plan['diet_plan_id']]);
                    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="border rounded-lg p-4 mb-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <p class="text-sm text-gray-600">Coach: <?php echo htmlspecialchars($plan['coach_name']); ?></p>
                            </div>
                            <?php if (!$feedback): ?>
                                <button onclick="openFeedbackModal(<?php echo $plan['diet_plan_id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')" 
                                        class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700 transition-colors duration-200">
                                    Provide Feedback
                                </button>
                            <?php else: ?>
                                <div class="flex items-center">
                                    <div class="flex items-center mr-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($feedback && $feedback['feedback']): ?>
                            <p class="text-gray-600 italic"><?php echo htmlspecialchars($feedback['feedback']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Diet Plans -->
        <?php if (empty($diet_plans)): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">You don't have any active diet plans. Please contact your coach to get started.</span>
            </div>
        <?php else: ?>
            <?php foreach ($diet_plans as $plan): ?>
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($plan['name']); ?></h5>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <p class="text-gray-600"><span class="font-semibold">Coach:</span> <?php echo htmlspecialchars($plan['coach_name']); ?></p>
                                <p class="text-gray-600"><span class="font-semibold">Specialization:</span> <?php echo ucfirst($plan['specialization']); ?></p>
                                <p class="text-gray-600"><span class="font-semibold">Duration Type:</span> <?php echo ucfirst($plan['duration_type']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600"><span class="font-semibold">Start Date:</span> <?php echo date('M d, Y', strtotime($plan['start_date'])); ?></p>
                                <p class="text-gray-600"><span class="font-semibold">End Date:</span> <?php echo date('M d, Y', strtotime($plan['end_date'])); ?></p>
                                <p class="text-gray-600"><span class="font-semibold">Status:</span> <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span></p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h6 class="text-lg font-semibold text-gray-800 mb-2">Description:</h6>
                            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                        </div>

                        <div class="mb-6">
                            <h6 class="text-lg font-semibold text-gray-800 mb-4">Daily Nutrition Goals:</h6>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4 text-center">
                                    <h6 class="text-sm font-medium text-gray-500">Calories</h6>
                                    <h4 class="text-xl font-bold text-gray-900"><?php echo $plan['calories']; ?> kcal</h4>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 text-center">
                                    <h6 class="text-sm font-medium text-gray-500">Protein</h6>
                                    <h4 class="text-xl font-bold text-gray-900"><?php echo $plan['protein']; ?>g</h4>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 text-center">
                                    <h6 class="text-sm font-medium text-gray-500">Carbs</h6>
                                    <h4 class="text-xl font-bold text-gray-900"><?php echo $plan['carbs']; ?>g</h4>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 text-center">
                                    <h6 class="text-sm font-medium text-gray-500">Fats</h6>
                                    <h4 class="text-xl font-bold text-gray-900"><?php echo $plan['fats']; ?>g</h4>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h6 class="text-lg font-semibold text-gray-800 mb-4">Meals:</h6>
                            <?php
                            try {
                                $stmt = $db->prepare("SELECT * FROM diet_plan_meals WHERE diet_plan_id = ? ORDER BY meal_order");
                                $stmt->execute([$plan['diet_plan_id']]);
                                $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Get meal completion stats
                                $meal_stats = getMealCompletionStats($db, $member_id, $plan['diet_plan_id']);
                                $completion_percentage = $meal_stats['total_meals'] > 0 
                                    ? round(($meal_stats['completed_meals'] / $meal_stats['total_meals']) * 100) 
                                    : 0;
                                
                                if (empty($meals)): ?>
                                    <p class="text-gray-500 italic">No meals added to this plan yet.</p>
                                <?php else: ?>
                                    <!-- Meal Completion Progress -->
                                    <div class="mb-6 bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <h6 class="text-sm font-medium text-gray-700">Today's Progress</h6>
                                            <span class="text-sm font-medium <?php echo $completion_percentage == 100 ? 'text-green-600' : 'text-gray-600'; ?>">
                                                <?php echo $completion_percentage; ?>% Complete
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" style="width: <?php echo $completion_percentage; ?>%"></div>
                                        </div>
                                        <div class="flex justify-between items-center mt-2">
                                            <p class="text-sm text-gray-500">
                                                <?php echo $meal_stats['completed_meals']; ?> of <?php echo $meal_stats['total_meals']; ?> meals completed today
                                            </p>
                                            <?php if ($completion_percentage == 100): ?>
                                                <span class="text-sm text-green-600 font-medium">
                                                    <i class="fas fa-check-circle mr-1"></i> All meals completed!
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Meals Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($meals as $meal): 
                                            $isCompleted = isMealCompletedToday($db, $member_id, $meal['id']);
                                            $day = ceil($meal['meal_order'] / 3); // Assuming 3 meals per day
                                            $mealNumber = $meal['meal_order'] % 3 ?: 3; // Get meal number (1, 2, or 3)
                                        ?>
                                            <div class="meal-card bg-white rounded-lg shadow-md overflow-hidden transition-transform duration-300 hover:shadow-lg <?php echo $isCompleted ? 'ring-2 ring-green-500' : ''; ?>">
                                                <!-- Meal Image -->
                                                <div class="meal-image-container relative h-48 bg-gray-200">
                                                    <?php if ($meal['image_path']): ?>
                                                        <div class="relative w-full h-full">
                                                            <img src="../uploads/<?php echo htmlspecialchars($meal['image_path']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($meal['meal_name']); ?>"
                                                                 class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                                                 onclick="openLightbox(this.src, '<?php echo htmlspecialchars($meal['meal_name']); ?>')">
                                                            <div class="meal-image-overlay">
                                                                <i class="fas fa-expand text-white text-2xl"></i>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100">
                                                            <i class="fas fa-utensils text-4xl text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <!-- Day and Meal Number Badge -->
                                                    <div class="absolute top-2 left-2">
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 flex items-center">
                                                            <i class="fas fa-calendar-day mr-1"></i>
                                                            Day <?php echo $day; ?> - Meal <?php echo $mealNumber; ?>
                                                        </span>
                                                    </div>
                                                    <!-- Status Badge -->
                                                    <div class="absolute top-2 right-2">
                                                        <?php if ($isCompleted): ?>
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 flex items-center">
                                                                <i class="fas fa-check-circle mr-1"></i>
                                                                Completed
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 flex items-center">
                                                                <i class="fas fa-clock mr-1"></i>
                                                                Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Meal Details -->
                                                <div class="p-4">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($meal['meal_name']); ?></h3>
                                                            <?php
                                                            $mealTypeClass = '';
                                                            $mealTypeIcon = '';
                                                            switch($meal['meal_type']) {
                                                                case 'breakfast':
                                                                    $mealTypeClass = 'bg-yellow-100 text-yellow-800';
                                                                    $mealTypeIcon = 'fa-sun';
                                                                    break;
                                                                case 'lunch':
                                                                    $mealTypeClass = 'bg-orange-100 text-orange-800';
                                                                    $mealTypeIcon = 'fa-utensils';
                                                                    break;
                                                                case 'dinner':
                                                                    $mealTypeClass = 'bg-blue-100 text-blue-800';
                                                                    $mealTypeIcon = 'fa-moon';
                                                                    break;
                                                                case 'snack':
                                                                    $mealTypeClass = 'bg-green-100 text-green-800';
                                                                    $mealTypeIcon = 'fa-cookie';
                                                                    break;
                                                            }
                                                            ?>
                                                            <div class="flex items-center mt-1">
                                                                <span class="meal-type-badge px-2 py-1 text-xs font-medium rounded-full <?php echo $mealTypeClass; ?> flex items-center">
                                                                    <i class="fas <?php echo $mealTypeIcon; ?> mr-1"></i>
                                                                    <?php echo ucfirst($meal['meal_type']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <span class="text-sm font-medium text-indigo-600 flex items-center">
                                                            <i class="fas fa-fire mr-1"></i>
                                                            <?php echo $meal['calories']; ?> kcal
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($meal['description'])): ?>
                                                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($meal['description']); ?></p>
                                                    <?php endif; ?>

                                                    <!-- Nutrition Info -->
                                                    <div class="grid grid-cols-3 gap-2 mb-4">
                                                        <div class="nutrition-stat text-center p-2 rounded">
                                                            <span class="text-xs text-gray-500 flex items-center justify-center">
                                                                <i class="fas fa-drumstick-bite mr-1 text-indigo-500"></i>
                                                                Protein
                                                            </span>
                                                            <p class="text-sm font-medium text-gray-900"><?php echo $meal['protein']; ?>g</p>
                                                        </div>
                                                        <div class="nutrition-stat text-center p-2 rounded">
                                                            <span class="text-xs text-gray-500 flex items-center justify-center">
                                                                <i class="fas fa-bread-slice mr-1 text-indigo-500"></i>
                                                                Carbs
                                                            </span>
                                                            <p class="text-sm font-medium text-gray-900"><?php echo $meal['carbs']; ?>g</p>
                                                        </div>
                                                        <div class="nutrition-stat text-center p-2 rounded">
                                                            <span class="text-xs text-gray-500 flex items-center justify-center">
                                                                <i class="fas fa-cheese mr-1 text-indigo-500"></i>
                                                                Fats
                                                            </span>
                                                            <p class="text-sm font-medium text-gray-900"><?php echo $meal['fats']; ?>g</p>
                                                        </div>
                                                    </div>

                                                    <!-- Action Button -->
                                                    <?php if (!$isCompleted): ?>
                                                        <?php if (canLogMeal($db, $member_id, $meal['id'])): ?>
                                                            <button type="button" 
                                                                    onclick="openLogMealModal(<?php echo $meal['id']; ?>, '<?php echo htmlspecialchars($meal['meal_name']); ?>')"
                                                                    class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 transition-colors duration-200 flex items-center justify-center">
                                                                <i class="fas fa-plus-circle mr-2"></i> Log Meal
                                                            </button>
                                                        <?php else: ?>
                                                            <div class="w-full bg-gray-100 text-gray-500 px-4 py-2 rounded-md text-sm font-medium flex items-center justify-center">
                                                                <i class="fas fa-clock mr-2"></i> Not available yet
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="w-full bg-green-50 text-green-700 px-4 py-2 rounded-md text-sm font-medium flex items-center justify-center">
                                                            <i class="fas fa-check-circle mr-2"></i> Completed
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<p class="text-red-500">Error fetching meals: ' . $e->getMessage() . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Log Meal Modal -->
    <div id="logMealModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900" id="modalTitle"></h3>
                    <button onclick="closeLogMealModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="log_meal">
                    <input type="hidden" name="meal_id" id="mealId">
                    
                    <div>
                        <label for="meal_date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" id="meal_date" name="meal_date" value="<?php echo date('Y-m-d'); ?>" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Add any notes about your meal..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeLogMealModal()"
                                class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm hover:bg-gray-300 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700 transition-colors duration-200">
                            Log Meal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Feedback Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900" id="feedbackTitle"></h3>
                    <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="submit_feedback">
                    <input type="hidden" name="diet_plan_id" id="feedbackDietPlanId">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rating</label>
                        <div class="flex items-center mt-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" onclick="setRating(<?php echo $i; ?>)" class="text-2xl text-gray-300 hover:text-yellow-400 focus:outline-none">
                                    <i class="fas fa-star rating-star" data-rating="<?php echo $i; ?>"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" required>
                    </div>

                    <div>
                        <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback</label>
                        <textarea id="feedback" name="feedback" rows="4" required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Share your experience with this diet plan..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeFeedbackModal()"
                                class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm hover:bg-gray-300 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700 transition-colors duration-200">
                            Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for calorie chart
        const dates = <?php echo json_encode(array_column(array_reverse($calorie_tracking), 'date')); ?>;
        const consumed = <?php echo json_encode(array_column(array_reverse($calorie_tracking), 'calories_consumed')); ?>;
        const burned = <?php echo json_encode(array_column(array_reverse($calorie_tracking), 'calories_burned')); ?>;
        const net = <?php echo json_encode(array_map(function($tracking) {
            return ($tracking['calories_consumed'] ?? 0) - $tracking['calories_burned'];
        }, array_reverse($calorie_tracking))); ?>;
        const weightChange = <?php echo json_encode(array_map(function($tracking) {
            return $tracking['expected_weight_change'];
        }, array_reverse($calorie_tracking))); ?>;

        // Create calorie chart
        const ctx = document.getElementById('calorieChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates.map(date => new Date(date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'Calories Consumed',
                        data: consumed,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    },
                    {
                        label: 'Calories Burned',
                        data: burned,
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    },
                    {
                        label: 'Net Calories',
                        data: net,
                        borderColor: 'rgb(54, 162, 235)',
                        tension: 0.1
                    },
                    {
                        label: 'Expected Weight Change (lbs)',
                        data: weightChange,
                        borderColor: 'rgb(153, 102, 255)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Calories'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Weight Change (lbs)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Enhanced Modal functions
        function openLogMealModal(mealId, mealName) {
            document.getElementById('modalTitle').textContent = 'Log Meal: ' + mealName;
            document.getElementById('mealId').value = mealId;
            const modal = document.getElementById('logMealModal');
            modal.classList.remove('hidden');
            // Add fade-in animation
            modal.classList.add('animate-fade-in');
        }

        function closeLogMealModal() {
            const modal = document.getElementById('logMealModal');
            // Add fade-out animation
            modal.classList.add('animate-fade-out');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('animate-fade-out');
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('logMealModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogMealModal();
            }
        });

        // Lightbox functions
        function openLightbox(imageSrc, imageAlt) {
            const lightbox = document.getElementById('imageLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            
            lightboxImage.src = imageSrc;
            lightboxImage.alt = imageAlt;
            lightbox.classList.remove('hidden');
            lightbox.classList.add('animate-fade-in');
            
            // Prevent body scrolling when lightbox is open
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightbox = document.getElementById('imageLightbox');
            
            // Add fade-out animation
            lightbox.classList.add('animate-fade-out');
            setTimeout(() => {
                lightbox.classList.add('hidden');
                lightbox.classList.remove('animate-fade-out');
                // Restore body scrolling
                document.body.style.overflow = '';
            }, 300);
        }

        // Close lightbox when clicking outside the image
        document.getElementById('imageLightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('imageLightbox').classList.contains('hidden')) {
                closeLightbox();
            }
        });

        // Feedback Modal functions
        function openFeedbackModal(dietPlanId, dietPlanName) {
            document.getElementById('feedbackTitle').textContent = 'Feedback: ' + dietPlanName;
            document.getElementById('feedbackDietPlanId').value = dietPlanId;
            document.getElementById('ratingInput').value = '';
            document.getElementById('feedback').value = '';
            resetRatingStars();
            document.getElementById('feedbackModal').classList.remove('hidden');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }

        function setRating(rating) {
            document.getElementById('ratingInput').value = rating;
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach(star => {
                const starRating = parseInt(star.dataset.rating);
                star.classList.toggle('text-yellow-400', starRating <= rating);
                star.classList.toggle('text-gray-300', starRating > rating);
            });
        }

        function resetRatingStars() {
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach(star => {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            });
        }

        // Close feedback modal when clicking outside
        document.getElementById('feedbackModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFeedbackModal();
            }
        });
    </script>

    <style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-10px); }
    }

    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }

    .animate-fade-out {
        animation: fadeOut 0.3s ease-out;
    }

    #imageLightbox {
        backdrop-filter: blur(5px);
    }

    #lightboxImage {
        max-height: 90vh;
        object-fit: contain;
    }

    /* Add smooth transition for lightbox */
    #imageLightbox {
        transition: opacity 0.3s ease-in-out;
    }

    #imageLightbox.animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }

    #imageLightbox.animate-fade-out {
        animation: fadeOut 0.3s ease-out;
    }

    .rating-star {
        transition: color 0.2s ease-in-out;
    }

    .rating-star:hover {
        transform: scale(1.1);
    }

    /* Enhanced styles for better visual appeal */
    .meal-card {
        transition: all 0.3s ease;
        border: 1px solid #e5e7eb;
        background: linear-gradient(to bottom, #ffffff, #f9fafb);
    }
    .meal-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
    }
    .meal-card.completed {
        border-color: #10b981;
        background: linear-gradient(to bottom, #f0fdf4, #ecfdf5);
    }
    .meal-type-badge {
        transition: all 0.3s ease;
    }
    .meal-type-badge:hover {
        transform: scale(1.05);
    }
    .nutrition-stat {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        transition: all 0.3s ease;
    }
    .nutrition-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .progress-bar {
        transition: width 0.5s ease-in-out;
    }
    .meal-image-container {
        position: relative;
        overflow: hidden;
        border-radius: 0.5rem;
    }
    .meal-image-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.2);
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none; /* This ensures clicks pass through to the image */
    }
    .meal-image-container:hover .meal-image-overlay {
        opacity: 1;
    }
    .stat-card {
        background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .stat-icon {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    </style>

    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html> 