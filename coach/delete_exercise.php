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

// Check if exercise_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: workout_plans.php");
    exit();
}

$exercise_id = (int)$_GET['id'];

// Get exercise details and verify it belongs to this coach's plan
$stmt = $conn->prepare("
    SELECT e.*, wp.name as plan_name 
    FROM exercises e 
    JOIN workout_plans wp ON e.workout_plan_id = wp.id 
    WHERE e.id = ? AND wp.coach_id = ?
");
$stmt->bind_param("ii", $exercise_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$exercise = $result->fetch_assoc()) {
    header("Location: workout_plans.php");
    exit();
}
$stmt->close();

// Handle form submission
$alert = "";
$alert_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Delete the exercise's GIF file if it exists
        if (!empty($exercise['gif_path'])) {
            $file_path = '../' . $exercise['gif_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete the exercise
        $stmt = $conn->prepare("DELETE FROM exercises WHERE id = ?");
        $stmt->bind_param("i", $exercise_id);
        
        if ($stmt->execute()) {
            header("Location: add_exercises.php?plan_id=" . $exercise['workout_plan_id'] . "&success=1");
            exit();
        } else {
            $alert = "Error deleting exercise: " . $stmt->error;
            $alert_type = "danger";
        }
        $stmt->close();
    } catch (Exception $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Exercise - <?php echo htmlspecialchars($exercise['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Delete Exercise</h1>
            <div class="flex space-x-4">
                <a href="add_exercises.php?plan_id=<?php echo $exercise['workout_plan_id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Exercises
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
                <h2 class="text-lg font-semibold text-gray-900">Confirm Deletion</h2>
            </div>
            
            <div class="p-6">
                <div class="mb-6">
                    <p class="text-gray-700 mb-4">Are you sure you want to delete the following exercise?</p>
                    
                    <div class="bg-gray-50 p-4 rounded-md">
                        <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($exercise['name']); ?></h3>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($exercise['description']); ?></p>
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Sets:</span> <?php echo $exercise['sets']; ?>
                            </div>
                            <div>
                                <span class="font-medium">Reps:</span> <?php echo $exercise['reps']; ?>
                            </div>
                            <div>
                                <span class="font-medium">Duration:</span> <?php echo $exercise['duration_minutes']; ?> minutes
                            </div>
                            <div>
                                <span class="font-medium">Order:</span> <?php echo $exercise['exercise_order']; ?>
                            </div>
                        </div>
                        <?php if (!empty($exercise['gif_path'])): ?>
                            <div class="mt-4">
                                <span class="font-medium text-gray-600">Current GIF:</span>
                                <img src="../<?php echo htmlspecialchars($exercise['gif_path']); ?>" 
                                     alt="Exercise GIF" 
                                     class="mt-2 w-32 h-32 object-cover rounded shadow-md">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="flex justify-end space-x-3">
                        <a href="add_exercises.php?plan_id=<?php echo $exercise['workout_plan_id']; ?>" 
                           class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" 
                                class="bg-red-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 flex items-center">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Exercise
                        </button>
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