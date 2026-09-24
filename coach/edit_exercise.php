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
    // Validate form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sets = (int)($_POST['sets'] ?? 0);
    $reps = (int)($_POST['reps'] ?? 0);
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $exercise_order = (int)($_POST['exercise_order'] ?? 0);
    $gif_path = $exercise['gif_path']; // Keep existing GIF path by default
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Exercise name is required";
    }
    
    if ($sets <= 0) {
        $errors[] = "Number of sets must be greater than 0";
    }
    
    if ($reps <= 0) {
        $errors[] = "Number of reps must be greater than 0";
    }
    
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be greater than 0 minutes";
    }

    // Handle GIF upload
    if (isset($_FILES['gif']) && $_FILES['gif']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gif'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if file is a GIF
        if ($file_type !== 'gif') {
            $errors[] = "Only GIF files are allowed";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/exercises/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old GIF if exists
                if (!empty($exercise['gif_path'])) {
                    $old_file = '../' . $exercise['gif_path'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                $gif_path = 'uploads/exercises/' . $filename;
            } else {
                $errors[] = "Error uploading GIF file";
            }
        }
    }
    
    // If no errors, update the exercise
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE exercises SET name = ?, description = ?, sets = ?, reps = ?, duration_minutes = ?, exercise_order = ?, gif_path = ? WHERE id = ?");
            $stmt->bind_param("ssiiissi", $name, $description, $sets, $reps, $duration_minutes, $exercise_order, $gif_path, $exercise_id);
            
            if ($stmt->execute()) {
                $alert = "Exercise updated successfully!";
                $alert_type = "success";
                
                // Update the exercise data
                $exercise['name'] = $name;
                $exercise['description'] = $description;
                $exercise['sets'] = $sets;
                $exercise['reps'] = $reps;
                $exercise['duration_minutes'] = $duration_minutes;
                $exercise['exercise_order'] = $exercise_order;
                $exercise['gif_path'] = $gif_path;
            } else {
                $alert = "Error updating exercise: " . $stmt->error;
                $alert_type = "danger";
            }
            $stmt->close();
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
    <title>Edit Exercise - <?php echo htmlspecialchars($exercise['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Edit Exercise</h1>
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
                <h2 class="text-lg font-semibold text-gray-900">Workout Plan: <?php echo htmlspecialchars($exercise['plan_name']); ?></h2>
            </div>
            
            <div class="p-6">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="space-y-4">
                        <!-- Exercise Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Exercise Name</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo htmlspecialchars($exercise['name']); ?>">
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($exercise['description']); ?></textarea>
                        </div>
                        
                        <!-- Sets -->
                        <div>
                            <label for="sets" class="block text-sm font-medium text-gray-700 mb-1">Number of Sets</label>
                            <input type="number" id="sets" name="sets" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $exercise['sets']; ?>">
                        </div>
                        
                        <!-- Reps -->
                        <div>
                            <label for="reps" class="block text-sm font-medium text-gray-700 mb-1">Number of Reps</label>
                            <input type="number" id="reps" name="reps" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $exercise['reps']; ?>">
                        </div>
                        
                        <!-- Duration -->
                        <div>
                            <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $exercise['duration_minutes']; ?>">
                        </div>
                        
                        <!-- Exercise Order -->
                        <div>
                            <label for="exercise_order" class="block text-sm font-medium text-gray-700 mb-1">Exercise Order</label>
                            <input type="number" id="exercise_order" name="exercise_order" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $exercise['exercise_order']; ?>">
                        </div>

                        <!-- GIF Upload -->
                        <div>
                            <label for="gif" class="block text-sm font-medium text-gray-700 mb-1">Exercise GIF (Optional)</label>
                            <?php if (!empty($exercise['gif_path'])): ?>
                                <div class="mb-4 p-4 bg-gray-50 rounded-md">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Current GIF:</p>
                                    <img src="../<?php echo htmlspecialchars($exercise['gif_path']); ?>" alt="Current Exercise GIF" class="w-32 h-32 object-cover rounded shadow-md">
                                </div>
                            <?php endif; ?>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <i class="fas fa-upload text-gray-400 text-3xl mb-2"></i>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="gif" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Upload a new file</span>
                                            <input id="gif" name="gif" type="file" accept=".gif" class="sr-only">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">GIF files only</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <a href="add_exercises.php?plan_id=<?php echo $exercise['workout_plan_id']; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                            <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                <i class="fas fa-save mr-2"></i> Update Exercise
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