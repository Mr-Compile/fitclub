<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is coach
if (!isCoach()) {
    header("Location: ../index.php");
    exit();
}

// Get coach ID
$coach_id = 0;
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $coach_id = $row['id'];
}
$stmt->close();

// Handle form submission
$alert = "";
$alert_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $specialization = $_POST['specialization'] ?? '';
    $difficulty_level = $_POST['difficulty_level'] ?? '';
    $duration_weeks = (int)($_POST['duration_weeks'] ?? 0);
    $total_calories_burn = (int)($_POST['total_calories_burn'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Plan name is required";
    }
    
    if (empty($specialization)) {
        $errors[] = "Specialization is required";
    }
    
    if (empty($difficulty_level)) {
        $errors[] = "Difficulty level is required";
    }
    
    if ($duration_weeks <= 0) {
        $errors[] = "Duration must be at least 1 week";
    }
    
    if ($total_calories_burn <= 0) {
        $errors[] = "Total calories burn must be greater than 0";
    }
    
    // If no errors, insert the workout plan
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO workout_plans (coach_id, name, description, specialization, difficulty_level, duration_weeks, total_calories_burn, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssiii", $coach_id, $name, $description, $specialization, $difficulty_level, $duration_weeks, $total_calories_burn, $is_active);
            
            if ($stmt->execute()) {
                $workout_plan_id = $stmt->insert_id;
                $stmt->close();
                
                // Redirect to add exercises page
                header("Location: add_exercises.php?plan_id=" . $workout_plan_id);
                exit();
            } else {
                $alert = "Error creating workout plan: " . $stmt->error;
                $alert_type = "danger";
            }
        } catch (Exception $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    } else {
        $alert = implode("<br>", $errors);
        $alert_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Workout Plan - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Create New Workout Plan</h1>
            <div class="flex space-x-4">
                <a href="workout_plans.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
        
        <?php if (!empty($alert)): ?>
            <div class="mb-4 p-4 rounded-md <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Plan Details</h2>
            </div>
            
            <div class="p-6">
                <form method="POST" action="">
                    <div class="space-y-6">
                        <!-- Plan Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" name="description" rows="3" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Specialization -->
                        <div>
                            <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                            <select id="specialization" name="specialization" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select specialization</option>
                                <option value="strength" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'strength') ? 'selected' : ''; ?>>Strength Training</option>
                                <option value="cardio" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'cardio') ? 'selected' : ''; ?>>Cardio</option>
                                <option value="flexibility" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'flexibility') ? 'selected' : ''; ?>>Flexibility</option>
                                <option value="weight_loss" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'weight_loss') ? 'selected' : ''; ?>>Weight Loss</option>
                                <option value="muscle_gain" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'muscle_gain') ? 'selected' : ''; ?>>Muscle Gain</option>
                            </select>
                        </div>
                        
                        <!-- Difficulty Level -->
                        <div>
                            <label for="difficulty_level" class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                            <select id="difficulty_level" name="difficulty_level" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select difficulty level</option>
                                <option value="beginner" <?php echo (isset($_POST['difficulty_level']) && $_POST['difficulty_level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo (isset($_POST['difficulty_level']) && $_POST['difficulty_level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo (isset($_POST['difficulty_level']) && $_POST['difficulty_level'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        
                        <!-- Duration -->
                        <div>
                            <label for="duration_weeks" class="block text-sm font-medium text-gray-700 mb-1">Duration (weeks)</label>
                            <input type="number" id="duration_weeks" name="duration_weeks" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo htmlspecialchars($_POST['duration_weeks'] ?? ''); ?>">
                        </div>
                        
                        <!-- Total Calories Burn -->
                        <div>
                            <label for="total_calories_burn" class="block text-sm font-medium text-gray-700 mb-1">Estimated Total Calories Burn</label>
                            <input type="number" id="total_calories_burn" name="total_calories_burn" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo htmlspecialchars($_POST['total_calories_burn'] ?? ''); ?>">
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <div class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active" value="1"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                       <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label for="is_active" class="ml-2 block text-sm text-gray-700">
                                    Active
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <a href="workout_plans.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                            <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                <i class="fas fa-plus mr-2"></i> Create Plan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html> 