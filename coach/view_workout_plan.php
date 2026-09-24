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

// Check if plan_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: workout_plans.php");
    exit();
}

$plan_id = (int)$_GET['id'];

// Get workout plan details
$stmt = $conn->prepare("SELECT * FROM workout_plans WHERE id = ? AND coach_id = ?");
$stmt->bind_param("ii", $plan_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$plan = $result->fetch_assoc()) {
    header("Location: workout_plans.php");
    exit();
}
$stmt->close();

// Get exercises for this plan
$stmt = $conn->prepare("SELECT * FROM exercises WHERE workout_plan_id = ? ORDER BY exercise_order ASC");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Workout Plan - <?php echo htmlspecialchars($plan['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
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
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Workout Plan Details</h1>
            <div class="flex space-x-4">
                <a href="edit_workout_plan.php?id=<?php echo $plan_id; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Plan
                </a>
                <a href="add_exercises.php?plan_id=<?php echo $plan_id; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-dumbbell mr-2"></i> Manage Exercises
                </a>
                <a href="workout_plans.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Plan Name -->
                    <div class="col-span-2">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($plan['name']); ?></h2>
                        <p class="text-gray-600 text-lg"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                        
                        <?php if (!empty($plan['photo_path'])): ?>
                        <div class="mt-4">
                            <img src="../<?php echo htmlspecialchars($plan['photo_path']); ?>" 
                                 alt="Workout Plan Photo" 
                                 class="max-w-2xl w-full h-auto rounded-lg shadow-lg object-cover"
                                 onclick="openLightbox(this.src, '<?php echo htmlspecialchars($plan['name']); ?>')">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Plan Details -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-clipboard-list text-indigo-600 mr-2"></i>
                            Plan Details
                        </h3>
                        <dl class="grid grid-cols-1 gap-4">
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-bullseye text-indigo-500 mr-2"></i>
                                    Specialization
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($plan['specialization']); ?></dd>
                            </div>
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-signal text-indigo-500 mr-2"></i>
                                    Difficulty Level
                                </dt>
                                <dd class="mt-1">
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php 
                                        echo match($plan['difficulty_level']) {
                                            'beginner' => 'bg-green-100 text-green-800',
                                            'intermediate' => 'bg-yellow-100 text-yellow-800',
                                            'advanced' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    ?>">
                                        <i class="fas <?php 
                                            echo match($plan['difficulty_level']) {
                                                'beginner' => 'fa-seedling',
                                                'intermediate' => 'fa-fire',
                                                'advanced' => 'fa-bolt',
                                                default => 'fa-circle'
                                            };
                                        ?> mr-1"></i>
                                        <?php echo ucfirst($plan['difficulty_level']); ?>
                                    </span>
                                </dd>
                            </div>
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i>
                                    Duration
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                    <i class="fas fa-clock text-gray-400 mr-1"></i>
                                    <?php echo $plan['duration_weeks']; ?> weeks
                                </dd>
                            </div>
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-toggle-on text-indigo-500 mr-2"></i>
                                    Status
                                </dt>
                                <dd class="mt-1">
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $plan['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas <?php echo $plan['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    
                    <!-- Additional Details -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-info-circle text-indigo-600 mr-2"></i>
                            Additional Details
                        </h3>
                        <dl class="grid grid-cols-1 gap-4">
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-calendar-plus text-indigo-500 mr-2"></i>
                                    Created At
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                    <i class="far fa-clock text-gray-400 mr-1"></i>
                                    <?php echo date('F d, Y', strtotime($plan['created_at'])); ?>
                                </dd>
                            </div>
                            <div class="bg-white p-4 rounded-md shadow-sm hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-sync-alt text-indigo-500 mr-2"></i>
                                    Last Updated
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                    <i class="far fa-clock text-gray-400 mr-1"></i>
                                    <?php echo date('F d, Y', strtotime($plan['updated_at'])); ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Exercises Section -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Exercises</h3>
                <a href="add_exercises.php?plan_id=<?php echo $plan_id; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add Exercise
                </a>
            </div>
            
            <?php if (empty($exercises)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-dumbbell text-4xl mb-4 text-gray-400"></i>
                    <p class="text-lg">No exercises added to this plan yet.</p>
                    <a href="add_exercises.php?plan_id=<?php echo $plan_id; ?>" class="mt-4 inline-block text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-plus mr-1"></i> Add your first exercise
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-sort-numeric-up mr-1"></i> Order
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-dumbbell mr-1"></i> Exercise Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-layer-group mr-1"></i> Sets
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-redo mr-1"></i> Reps
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-clock mr-1"></i> Duration
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-align-left mr-1"></i> Description
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($exercises as $exercise): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $exercise['exercise_order']; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($exercise['name']); ?></div>
                                        <?php if (!empty($exercise['gif_path'])): ?>
                                            <div class="mt-2">
                                                <img src="../<?php echo htmlspecialchars($exercise['gif_path']); ?>" 
                                                     alt="Exercise GIF" 
                                                     class="w-32 h-32 object-cover rounded shadow-md cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($exercise['name']); ?>')">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $exercise['sets']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $exercise['reps']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $exercise['duration_minutes'] ? $exercise['duration_minutes'] . ' min' : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($exercise['description']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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

    <!-- Add this before the closing </body> tag -->
    <script>
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
    </style>
</body>
</html> 