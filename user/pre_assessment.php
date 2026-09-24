<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user exists in database
$query = "SELECT * FROM users WHERE id = :user_id AND role = 'member'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    // User doesn't exist or is not a member
    session_destroy();
    header("Location: ../index.php?error=invalid_user");
    exit();
}

// Check if user already completed assessment
$query = "SELECT * FROM pre_assessments WHERE user_id = :user_id AND completed = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    header("Location: dashboard.php");
    exit();
}

// Get health conditions
$query = "SELECT * FROM health_condition_types WHERE name != 'None'";
$stmt = $db->prepare($query);
$stmt->execute();
$health_conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get common allergies
$common_allergies = [
    'Peanuts',
    'Tree Nuts',
    'Milk',
    'Eggs',
    'Soy',
    'Wheat',
    'Fish',
    'Shellfish',
    'Latex',
    'Penicillin',
    'Dust',
    'Pollen',
    'Pet Dander',
    'None'
];

// Get coach fitness goals
$coach_fitness_goals = [
    'weight_loss' => 'Weight Loss',
    'muscle_gain' => 'Muscle Gain',
    'strength' => 'Strength Training',
    'overall_fitness' => 'Overall Fitness'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Calculate BMI
        $height_m = $_POST['height'] / 100; // Convert cm to m
        $bmi = $_POST['weight'] / ($height_m * $height_m);

        // Prepare health conditions and allergies
        $health_conditions_text = isset($_POST['health_condition_ids']) ? implode(', ', $_POST['health_condition_ids']) : '';
        $allergies = isset($_POST['allergies']) ? implode(', ', $_POST['allergies']) : '';

        // Insert pre-assessment
        $query = "INSERT INTO pre_assessments (
            user_id, age, gender, height, weight, allergies, 
            health_conditions, fitness_level, fitness_goal, 
            preferred_training_time, bmi, notes, completed
        ) VALUES (
            :user_id, :age, :gender, :height, :weight, :allergies,
            :health_conditions, :fitness_level, :fitness_goal,
            :preferred_training_time, :bmi, :notes, 1
        )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":age", $_POST['age']);
        $stmt->bindParam(":gender", $_POST['gender']);
        $stmt->bindParam(":height", $_POST['height']);
        $stmt->bindParam(":weight", $_POST['weight']);
        $stmt->bindParam(":allergies", $allergies);
        $stmt->bindParam(":health_conditions", $health_conditions_text);
        $stmt->bindParam(":fitness_level", $_POST['fitness_level']);
        $stmt->bindParam(":fitness_goal", $_POST['fitness_goal']);
        $stmt->bindParam(":preferred_training_time", $_POST['preferred_training_time']);
        $stmt->bindParam(":bmi", $bmi);
        $stmt->bindParam(":notes", $_POST['notes']);
        $stmt->execute();

        $assessment_id = $db->lastInsertId();

        // Insert selected health conditions
        if (isset($_POST['health_condition_ids']) && is_array($_POST['health_condition_ids'])) {
            $query = "INSERT INTO user_health_conditions (pre_assessment_id, condition_id) VALUES (:assessment_id, :condition_id)";
            $stmt = $db->prepare($query);
            
            foreach ($_POST['health_condition_ids'] as $condition_id) {
                $stmt->bindParam(":assessment_id", $assessment_id);
                $stmt->bindParam(":condition_id", $condition_id);
                $stmt->execute();
            }
        }

        $db->commit();
        $_SESSION['assessment_completed'] = true;
        header("Location: assessment_result.php?id=" . $assessment_id);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = "An error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Assessment - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            position: relative;
            height: 50vh;
            width: 100%;
            overflow: hidden;
        }
        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            z-index: 1;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            -ms-interpolation-mode: nearest-neighbor;
            transform: translateZ(0);
            backface-visibility: hidden;
            will-change: transform;
            max-width: none;
            filter: none;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2;
        }
        .hero-content {
            position: relative;
            z-index: 3;
        }
        .text-shadow-strong {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8), 0 4px 12px rgba(0, 0, 0, 0.7);
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #4F46E5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .form-card {
            transition: all 0.3s ease;
        }
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <img src="../assets/images/men-bg.jpg" alt="Fitness training" class="hero-image" loading="eager">
            <div class="hero-overlay"></div>
            <div class="hero-content h-full flex items-center justify-center text-center">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="inline-block mb-12">
                        <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 text-shadow-strong">
                            Your Fitness Journey<br>
                            <span class="text-gray-300">Starts Here</span>
                        </h1>
                        <p class="text-xl text-white mb-8 text-shadow-strong">
                            Help us understand your fitness goals and health background to create your personalized plan.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="form-card bg-white shadow rounded-lg p-6">
                    <div class="text-center mb-8">
                        <i class="fas fa-clipboard-check text-4xl text-gray-600 mb-4"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Pre-Assessment Form</h1>
                        <p class="text-gray-600 mt-2">Help us understand your fitness journey better</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form id="assessmentForm" method="POST" class="space-y-6">
                        <!-- Personal Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-birthday-cake mr-2"></i>Age
                                </label>
                                <input type="number" name="age" required min="16" max="100"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-venus-mars mr-2"></i>Gender
                                </label>
                                <select name="gender" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-ruler-vertical mr-2"></i>Height (cm)
                                </label>
                                <input type="number" name="height" required min="100" max="250" step="0.1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-weight mr-2"></i>Weight (kg)
                                </label>
                                <input type="number" name="weight" required min="30" max="300" step="0.1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                        </div>

                        <!-- Health Information -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-heartbeat mr-2"></i>Health Conditions
                                </label>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($health_conditions as $condition): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="health_condition_ids[]" 
                                                   value="<?php echo $condition['id']; ?>"
                                                   class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                            <label class="ml-2 text-sm text-gray-700">
                                                <?php echo htmlspecialchars($condition['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-allergies mr-2"></i>Allergies
                                </label>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($common_allergies as $allergy): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="allergies[]" 
                                                   value="<?php echo $allergy; ?>"
                                                   class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                            <label class="ml-2 text-sm text-gray-700">
                                                <?php echo htmlspecialchars($allergy); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700">Other Allergies</label>
                                    <textarea name="other_allergies" rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                              placeholder="List any other allergies not mentioned above..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Fitness Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-dumbbell mr-2"></i>Fitness Level
                                </label>
                                <select name="fitness_level" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-bullseye mr-2"></i>Fitness Goal
                                </label>
                                <select name="fitness_goal" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <?php foreach ($coach_fitness_goals as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-clock mr-2"></i>Preferred Training Time
                                </label>
                                <select name="preferred_training_time" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <option value="morning">Morning</option>
                                    <option value="afternoon">Afternoon</option>
                                    <option value="evening">Evening</option>
                                    <option value="flexible">Flexible</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Additional Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                placeholder="Any additional information you'd like to share..."></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Submit Assessment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('assessmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to submit your assessment? This information will be used to create your personalized fitness plan.')) {
                document.getElementById('loadingOverlay').style.display = 'flex';
                setTimeout(() => {
                    this.submit();
                }, 500);
            }
        });
    </script>
</body>
</html> 