<?php
session_start();
require_once '../includes/database.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$member_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission for logging exercises
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_exercise'])) {
    try {
        $exercise_id = $_POST['exercise_id'];
        $exercise_date = $_POST['exercise_date'];
        $sets_completed = $_POST['sets_completed'];
        $reps_completed = $_POST['reps_completed'];
        $duration = $_POST['duration'];
        $calories_burned = $_POST['calories_burned'];
        $notes = $_POST['notes'];

        // Insert exercise log
        $stmt = $db->prepare("INSERT INTO exercise_logs (member_id, exercise_id, exercise_date, sets_completed, reps_completed, duration, calories_burned, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$member_id, $exercise_id, $exercise_date, $sets_completed, $reps_completed, $duration, $calories_burned, $notes]);

        // Update calorie tracking
        $stmt = $db->prepare("INSERT INTO calorie_tracking (member_id, date, calories_burned) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE calories_burned = calories_burned + ?");
        $stmt->execute([$member_id, $exercise_date, $calories_burned, $calories_burned]);

        $message = "Exercise logged successfully!";
    } catch (PDOException $e) {
        $error = "Error logging exercise: " . $e->getMessage();
    }
}

// Fetch member's active workout plans
try {
    $stmt = $db->prepare("
        SELECT wp.*, c.specialization, u.fullname as coach_name, mwp.start_date, mwp.end_date, mwp.notes as member_notes,
               mwp.status as assignment_status
        FROM workout_plans wp
        JOIN member_workout_plans mwp ON wp.id = mwp.workout_plan_id
        JOIN coaches c ON wp.coach_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE mwp.member_id = ?
        ORDER BY mwp.start_date DESC
    ");
    $stmt->execute([$member_id]);
    $workout_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching workout plans: " . $e->getMessage();
    $workout_plans = [];
}

// Fetch calorie tracking data for the chart
try {
    $stmt = $db->prepare("
        SELECT
            d.date,
            COALESCE(meals.calories_consumed, 0) AS calories_consumed,
            COALESCE(exs.calories_burned, 0) AS calories_burned,
            (COALESCE(meals.calories_consumed, 0) - COALESCE(exs.calories_burned, 0)) AS net_calories,
            ((COALESCE(meals.calories_consumed, 0) - COALESCE(exs.calories_burned, 0)) / 3500) AS expected_weight_change
        FROM (
            SELECT CURDATE() - INTERVAL n DAY AS date
            FROM (
                SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
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
        ORDER BY d.date DESC
    ");
    $stmt->execute([$member_id, $member_id]);
    $calorie_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching calorie data: " . $e->getMessage();
    $calorie_data = [];
}

// Function to check if exercise is completed for today
function isExerciseCompletedToday($db, $member_id, $exercise_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed
        FROM exercise_logs
        WHERE member_id = ? AND exercise_id = ? AND DATE(exercise_date) = CURDATE()
    ");
    $stmt->execute([$member_id, $exercise_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['completed'] > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Workout Plans - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Add this right after the opening <body> tag -->
    <div id="imageLightbox" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50 flex items-center justify-center">
        <div class="relative max-w-4xl w-full mx-4">
            <img id="lightboxImage" src="" alt="Exercise GIF" class="w-full h-auto rounded-lg shadow-2xl">
            <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
    </div>

    <?php include 'components/navigation.php'; ?>

    <div class="container mx-auto px-4 py-8">
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

        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Left Column: Calorie Tracking -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Calorie Tracking</h2>
                <div class="h-[400px]">
                    <canvas id="calorieChart"></canvas>
                </div>
            </div>

            <!-- Right Column: Exercise Logs -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Recent Exercise Logs</h2>
                <?php
                // Fetch recent exercise logs
                $stmt = $db->prepare("
                    SELECT el.*, e.name as exercise_name, e.gif_path
                    FROM exercise_logs el
                    JOIN exercises e ON el.exercise_id = e.id
                    WHERE el.member_id = ?
                    ORDER BY el.exercise_date DESC, el.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$member_id]);
                $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (empty($recent_logs)): ?>
                    <p class="text-gray-600">No exercise logs found.</p>
                <?php else: ?>
                    <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-start space-x-4">
                                    <?php if ($log['gif_path']): ?>
                                        <img src="../uploads/exercises/<?php echo basename($log['gif_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($log['exercise_name']); ?>" 
                                             class="w-16 h-16 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                             onclick="openLightbox(this.src, '<?php echo htmlspecialchars($log['exercise_name']); ?>')">
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <h3 class="font-semibold"><?php echo htmlspecialchars($log['exercise_name']); ?></h3>
                                        <p class="text-sm text-gray-600">Date: <?php echo date('M d, Y', strtotime($log['exercise_date'])); ?></p>
                                        <div class="grid grid-cols-2 gap-2 mt-2 text-sm">
                                            <div>Sets: <?php echo $log['sets_completed']; ?></div>
                                            <div>Reps: <?php echo $log['reps_completed']; ?></div>
                                            <div>Duration: <?php echo $log['duration']; ?> min</div>
                                            <div>Calories: <?php echo $log['calories_burned']; ?></div>
                                        </div>
                                        <?php if ($log['notes']): ?>
                                            <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($log['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Workout Plans Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-4">My Workout Plans</h2>
            <?php if (empty($workout_plans)): ?>
                <p class="text-gray-600">No workout plans found.</p>
            <?php else: ?>
                <?php foreach ($workout_plans as $plan): ?>
                    <?php
                    // Fetch all exercises for the plan
                    $stmt = $db->prepare("SELECT * FROM exercises WHERE workout_plan_id = ? ORDER BY exercise_order");
                    $stmt->execute([$plan['id']]);
                    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $exercisesPerDay = 4; // You can randomize 3-5 if you want
                    $totalExercises = count($exercises);

                    // Calculate day index (0-based)
                    $start = new DateTime($plan['start_date']);
                    $today = new DateTime();
                    $interval = $start->diff($today);
                    $dayIndex = $interval->days;

                    // Determine if today is a rest day (every 7th day is rest)
                    $cycleDay = $dayIndex % 7;
                    $isRestDay = ($cycleDay === 6);

                    // Helper: get exercises for a given day index
                    function getDayExercises($exercises, $exercisesPerDay, $totalExercises, $dayIndex) {
                        $cycleDay = $dayIndex % 7;
                        if ($cycleDay === 6) return [];
                        $offset = ($dayIndex - floor($dayIndex / 7)) * $exercisesPerDay;
                        $dayExercises = [];
                        for ($i = 0; $i < $exercisesPerDay; $i++) {
                            $exerciseIdx = ($offset + $i) % $totalExercises;
                            $dayExercises[] = $exercises[$exerciseIdx];
                        }
                        return $dayExercises;
                    }

                    // Get today's and next day's exercises
                    $todayExercises = $isRestDay ? [] : getDayExercises($exercises, $exercisesPerDay, $totalExercises, $dayIndex);
                    $nextDayIndex = $dayIndex + 1;
                    $nextCycleDay = $nextDayIndex % 7;
                    $nextIsRestDay = ($nextCycleDay === 6);
                    $nextDayExercises = $nextIsRestDay ? [] : getDayExercises($exercises, $exercisesPerDay, $totalExercises, $nextDayIndex);

                    // Check if all today's exercises are logged
                    $allLogged = true;
                    if (!$isRestDay) {
                        foreach ($todayExercises as $ex) {
                            $stmt = $db->prepare("SELECT COUNT(*) as completed FROM exercise_logs WHERE member_id = ? AND exercise_id = ? AND DATE(exercise_date) = CURDATE()");
                            $stmt->execute([$member_id, $ex['id']]);
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($result['completed'] == 0) {
                                $allLogged = false;
                                break;
                            }
                        }
                    }
                    ?>
                    <div class="mb-8 p-6 border rounded-lg <?php echo $plan['assignment_status'] === 'active' ? 'border-green-200 bg-green-50' : 'border-gray-200'; ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                                        echo $plan['assignment_status'] === 'active' 
                                            ? 'bg-green-100 text-green-800' 
                                            : 'bg-gray-100 text-gray-800';
                                    ?>">
                                        <?php echo ucfirst($plan['assignment_status']); ?>
                                    </span>
                                </div>
                                <p class="text-gray-600"><?php echo htmlspecialchars($plan['description']); ?></p>
                                <div class="mt-4 p-4 bg-white rounded-lg border border-gray-200">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700">Start Date</p>
                                            <p class="text-gray-900"><?php echo date('F d, Y', strtotime($plan['start_date'])); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700">End Date</p>
                                            <p class="text-gray-900"><?php echo date('F d, Y', strtotime($plan['end_date'])); ?></p>
                                        </div>
                                    </div>
                                    <?php if ($plan['member_notes']): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <p class="text-sm font-semibold text-gray-700">Notes</p>
                                            <p class="text-gray-900 italic"><?php echo htmlspecialchars($plan['member_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Coach</p>
                                <p class="font-medium"><?php echo htmlspecialchars($plan['coach_name']); ?></p>
                                <p class="text-sm text-gray-500 mt-1">Specialization</p>
                                <p class="font-medium"><?php echo htmlspecialchars($plan['specialization']); ?></p>
                            </div>
                        </div>

                        <!-- Per-Day Exercise Section -->
                        <div class="mb-6">
                        <?php if ($isRestDay): ?>
                            <div class="text-center py-8">
                                <span class="text-2xl font-bold text-blue-600">Rest Day</span>
                                <p class="text-gray-500 mt-2">Take a break and recover! No exercises scheduled for today.</p>
                            </div>
                        <?php else: ?>
                            <h4 class="text-lg font-semibold mb-2">Today's Exercises (Day <?php echo $dayIndex+1; ?>)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php foreach ($todayExercises as $exercise): 
                                    $stmt = $db->prepare("SELECT COUNT(*) as completed FROM exercise_logs WHERE member_id = ? AND exercise_id = ? AND DATE(exercise_date) = CURDATE()");
                                    $stmt->execute([$member_id, $exercise['id']]);
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $isCompleted = $result['completed'] > 0;
                                ?>
                                    <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col <?php echo $isCompleted ? 'border-2 border-green-500' : ''; ?>">
                                        <div class="relative aspect-[3/4] bg-gray-100">
                                            <?php if ($exercise['gif_path']): ?>
                                                <img src="../uploads/exercises/<?php echo basename($exercise['gif_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($exercise['name']); ?>" 
                                                     class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($exercise['name']); ?>')">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-dumbbell text-4xl text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($isCompleted): ?>
                                                <div class="absolute top-2 right-2">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        Completed
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-4 flex-grow flex flex-col">
                                            <h4 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($exercise['name']); ?></h4>
                                            <p class="text-sm text-gray-600 mb-4 flex-grow"><?php echo htmlspecialchars($exercise['description']); ?></p>
                                            <div class="grid grid-cols-2 gap-3 mb-4">
                                                <div class="bg-gray-50 p-2 rounded">
                                                    <p class="text-xs text-gray-500 flex items-center">
                                                        <i class="fas fa-dumbbell mr-1 text-gray-600"></i>
                                                        Sets
                                                    </p>
                                                    <p class="font-medium"><?php echo $exercise['sets']; ?></p>
                                                </div>
                                                <div class="bg-gray-50 p-2 rounded">
                                                    <p class="text-xs text-gray-500 flex items-center">
                                                        <i class="fas fa-repeat mr-1 text-gray-600"></i>
                                                        Reps
                                                    </p>
                                                    <p class="font-medium"><?php echo $exercise['reps']; ?></p>
                                                </div>
                                                <div class="bg-gray-50 p-2 rounded">
                                                    <p class="text-xs text-gray-500 flex items-center">
                                                        <i class="fas fa-clock mr-1 text-gray-600"></i>
                                                        Duration
                                                    </p>
                                                    <p class="font-medium"><?php echo $exercise['duration_minutes']; ?> min</p>
                                                </div>
                                                <div class="bg-gray-50 p-2 rounded">
                                                    <p class="text-xs text-gray-500 flex items-center">
                                                        <i class="fas fa-fire mr-1 text-gray-600"></i>
                                                        Calories
                                                    </p>
                                                    <p class="font-medium"><?php echo $exercise['calories_burn']; ?></p>
                                                </div>
                                            </div>
                                            <?php if (!$isCompleted && $plan['assignment_status'] === 'active'): ?>
                                                <button onclick="openLogExerciseModal(<?php echo htmlspecialchars(json_encode($exercise)); ?>)" 
                                                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                                                    <i class="fas fa-plus-circle mr-2"></i>
                                                    Log Exercise
                                                </button>
                                            <?php elseif ($isCompleted): ?>
                                                <div class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-green-700 bg-green-50">
                                                    <i class="fas fa-check-circle mr-2"></i>
                                                    Completed
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                        <!-- Next Day Preview -->
                        <?php if ($allLogged && !$isRestDay): ?>
                            <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <?php if ($nextIsRestDay): ?>
                                    <h4 class="text-lg font-semibold text-blue-700 mb-2">Tomorrow is a Rest Day!</h4>
                                    <p class="text-blue-600">Enjoy your rest and get ready for the next workout cycle.</p>
                                <?php else: ?>
                                    <h4 class="text-lg font-semibold text-blue-700 mb-2">Preview: Next Day's Exercises</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                        <?php foreach ($nextDayExercises as $exercise): ?>
                                            <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
                                                <div class="relative aspect-[3/4] bg-gray-100">
                                                    <?php if ($exercise['gif_path']): ?>
                                                        <img src="../uploads/exercises/<?php echo basename($exercise['gif_path']); ?>" 
                                                             alt="<?php echo htmlspecialchars($exercise['name']); ?>" 
                                                             class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                                             onclick="openLightbox(this.src, '<?php echo htmlspecialchars($exercise['name']); ?>')">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <i class="fas fa-dumbbell text-4xl text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="p-4 flex-grow flex flex-col">
                                                    <h4 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($exercise['name']); ?></h4>
                                                    <p class="text-sm text-gray-600 mb-4 flex-grow"><?php echo htmlspecialchars($exercise['description']); ?></p>
                                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                                        <div class="bg-gray-50 p-2 rounded">
                                                            <p class="text-xs text-gray-500 flex items-center">
                                                                <i class="fas fa-dumbbell mr-1 text-gray-600"></i>
                                                                Sets
                                                            </p>
                                                            <p class="font-medium"><?php echo $exercise['sets']; ?></p>
                                                        </div>
                                                        <div class="bg-gray-50 p-2 rounded">
                                                            <p class="text-xs text-gray-500 flex items-center">
                                                                <i class="fas fa-repeat mr-1 text-gray-600"></i>
                                                                Reps
                                                            </p>
                                                            <p class="font-medium"><?php echo $exercise['reps']; ?></p>
                                                        </div>
                                                        <div class="bg-gray-50 p-2 rounded">
                                                            <p class="text-xs text-gray-500 flex items-center">
                                                                <i class="fas fa-clock mr-1 text-gray-600"></i>
                                                                Duration
                                                            </p>
                                                            <p class="font-medium"><?php echo $exercise['duration_minutes']; ?> min</p>
                                                        </div>
                                                        <div class="bg-gray-50 p-2 rounded">
                                                            <p class="text-xs text-gray-500 flex items-center">
                                                                <i class="fas fa-fire mr-1 text-gray-600"></i>
                                                                Calories
                                                            </p>
                                                            <p class="font-medium"><?php echo $exercise['calories_burn']; ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Log Exercise Modal -->
    <div id="logExerciseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Log Exercise</h3>
                    <button onclick="closeLogExerciseModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="exercise_id" id="exercise_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Exercise</label>
                        <input type="text" id="exercise_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="exercise_date" id="exercise_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sets Completed</label>
                        <input type="number" name="sets_completed" id="sets_completed" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reps Completed</label>
                        <input type="number" name="reps_completed" id="reps_completed" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                        <input type="number" name="duration" id="duration" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Calories Burned</label>
                        <input type="number" name="calories_burned" id="calories_burned" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeLogExerciseModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <button type="submit" name="log_exercise" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Exercise
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Debug data
        console.log('Calorie Data:', <?php echo json_encode($calorie_data); ?>);

        // Initialize calorie tracking chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('calorieChart').getContext('2d');
            const calorieData = <?php echo json_encode($calorie_data); ?>;
            
            // Process data
            const labels = calorieData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }).reverse();
            
            const consumed = calorieData.map(item => {
                const value = parseInt(item.calories_consumed) || 0;
                console.log('Consumed:', value);
                return value;
            }).reverse();
            
            const burned = calorieData.map(item => {
                const value = parseInt(item.calories_burned) || 0;
                console.log('Burned:', value);
                return value;
            }).reverse();
            
            const net = calorieData.map(item => {
                const value = parseInt(item.net_calories) || 0;
                console.log('Net:', value);
                return value;
            }).reverse();
            
            const weightChange = calorieData.map(item => {
                const value = parseFloat(item.expected_weight_change) || 0;
                console.log('Weight Change:', value);
                return value;
            }).reverse();

            // Debug processed data
            console.log('Labels:', labels);
            console.log('Consumed:', consumed);
            console.log('Burned:', burned);
            console.log('Net:', net);
            console.log('Weight Change:', weightChange);

            // Create the chart
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Calories Consumed',
                            data: consumed,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Calories Burned',
                            data: burned,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Net Calories',
                            data: net,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Expected Weight Change (lbs)',
                            data: weightChange,
                            borderColor: 'rgb(153, 102, 255)',
                            tension: 0.1,
                            yAxisID: 'y1',
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Calories'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
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
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Weekly Calorie and Weight Tracking',
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.yAxisID === 'y1') {
                                        label += context.parsed.y.toFixed(2) + ' lbs';
                                    } else {
                                        label += context.parsed.y + ' calories';
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    }
                }
            });
        });

        // Modal functions
        function openLogExerciseModal(exercise) {
            document.getElementById('exercise_id').value = exercise.id;
            document.getElementById('exercise_name').value = exercise.name;
            document.getElementById('sets_completed').value = exercise.sets;
            document.getElementById('reps_completed').value = exercise.reps;
            document.getElementById('duration').value = exercise.duration_minutes;
            document.getElementById('calories_burned').value = exercise.calories_burn;
            document.getElementById('exercise_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('logExerciseModal').classList.remove('hidden');
        }

        function closeLogExerciseModal() {
            document.getElementById('logExerciseModal').classList.add('hidden');
        }

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

    /* Custom scrollbar styles */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #555;
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