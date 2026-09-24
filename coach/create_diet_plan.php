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
    $duration_type = $_POST['duration_type'] ?? '';
    $calories = (int)($_POST['calories'] ?? 0);
    $protein = (int)($_POST['protein'] ?? 0);
    $carbs = (int)($_POST['carbs'] ?? 0);
    $fats = (int)($_POST['fats'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Plan name is required";
    }
    
    if (empty($specialization)) {
        $errors[] = "Specialization is required";
    }
    
    if (empty($duration_type)) {
        $errors[] = "Duration type is required";
    }
    
    if ($calories <= 0) {
        $errors[] = "Calories must be greater than 0";
    }
    
    if ($protein < 0) {
        $errors[] = "Protein cannot be negative";
    }
    
    if ($carbs < 0) {
        $errors[] = "Carbs cannot be negative";
    }
    
    if ($fats < 0) {
        $errors[] = "Fats cannot be negative";
    }
    
    // If no errors, insert the diet plan
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO diet_plans (coach_id, name, description, specialization, duration_type, calories, protein, carbs, fats, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssiiiii", $coach_id, $name, $description, $specialization, $duration_type, $calories, $protein, $carbs, $fats, $is_active);
            
            if ($stmt->execute()) {
                $diet_plan_id = $stmt->insert_id;
                $stmt->close();
                
                // Redirect to add meals page
                header("Location: add_meals.php?plan_id=" . $diet_plan_id);
                exit();
            } else {
                $alert = "Error creating diet plan: " . $stmt->error;
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
    <title>Create Diet Plan - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Create Diet Plan</h1>
            <a href="diet_plans.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Diet Plans
            </a>
        </div>
        
        <?php if (!empty($alert)): ?>
            <div class="mb-4 p-4 rounded <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <form method="POST" action="" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Plan Name -->
                    <div class="col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    
                    <!-- Description -->
                    <div class="col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Specialization -->
                    <div>
                        <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                        <select id="specialization" name="specialization" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Specialization</option>
                            <option value="bulk" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'bulk') ? 'selected' : ''; ?>>Bulk</option>
                            <option value="weight_loss" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'weight_loss') ? 'selected' : ''; ?>>Weight Loss</option>
                            <option value="strength" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'strength') ? 'selected' : ''; ?>>Strength</option>
                        </select>
                    </div>
                    
                    <!-- Duration Type -->
                    <div>
                        <label for="duration_type" class="block text-sm font-medium text-gray-700 mb-1">Duration Type</label>
                        <select id="duration_type" name="duration_type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Duration Type</option>
                            <option value="daily" <?php echo (isset($_POST['duration_type']) && $_POST['duration_type'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo (isset($_POST['duration_type']) && $_POST['duration_type'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                        </select>
                    </div>
                    
                    <!-- Calories -->
                    <div>
                        <label for="calories" class="block text-sm font-medium text-gray-700 mb-1">Total Calories</label>
                        <input type="number" id="calories" name="calories" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($_POST['calories'] ?? ''); ?>">
                    </div>
                    
                    <!-- Protein -->
                    <div>
                        <label for="protein" class="block text-sm font-medium text-gray-700 mb-1">Protein (g)</label>
                        <input type="number" id="protein" name="protein" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($_POST['protein'] ?? ''); ?>">
                    </div>
                    
                    <!-- Carbs -->
                    <div>
                        <label for="carbs" class="block text-sm font-medium text-gray-700 mb-1">Carbs (g)</label>
                        <input type="number" id="carbs" name="carbs" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($_POST['carbs'] ?? ''); ?>">
                    </div>
                    
                    <!-- Fats -->
                    <div>
                        <label for="fats" class="block text-sm font-medium text-gray-700 mb-1">Fats (g)</label>
                        <input type="number" id="fats" name="fats" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($_POST['fats'] ?? ''); ?>">
                    </div>
                    
                    <!-- Active Status -->
                    <div class="col-span-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                   <?php echo (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" class="ml-2 block text-sm text-gray-700">
                                Make this plan active
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <a href="diet_plans.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-3">
                        Cancel
                    </a>
                    <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Create Plan
                    </button>
                </div>
            </form>
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