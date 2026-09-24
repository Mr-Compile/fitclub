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
    header("Location: diet_plans.php");
    exit();
}

$plan_id = (int)$_GET['id'];

// Get diet plan details
$stmt = $conn->prepare("SELECT * FROM diet_plans WHERE id = ? AND coach_id = ?");
$stmt->bind_param("ii", $plan_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$plan = $result->fetch_assoc()) {
    header("Location: diet_plans.php");
    exit();
}
$stmt->close();

// Get meals for this plan
$stmt = $conn->prepare("SELECT * FROM diet_plan_meals WHERE diet_plan_id = ? ORDER BY meal_order ASC");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$meals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total nutritional values from meals
$total_calories = 0;
$total_protein = 0;
$total_carbs = 0;
$total_fats = 0;

foreach ($meals as $meal) {
    $total_calories += $meal['calories'];
    $total_protein += $meal['protein'];
    $total_carbs += $meal['carbs'];
    $total_fats += $meal['fats'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Diet Plan - <?php echo htmlspecialchars($plan['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Diet Plan Details</h1>
            <div class="flex space-x-4">
                <a href="edit_diet_plan.php?id=<?php echo $plan_id; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Plan
                </a>
                <a href="add_meals.php?plan_id=<?php echo $plan_id; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-utensils mr-2"></i> Manage Meals
                </a>
                <a href="diet_plans.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Diet Plans
                </a>
            </div>
        </div>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Plan Name -->
                    <div class="col-span-2">
                        <h2 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></h2>
                        <p class="mt-2 text-gray-600"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                    </div>
                    
                    <!-- Plan Details -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-clipboard-list text-indigo-600 mr-2"></i>
                            Plan Details
                        </h3>
                        <dl class="grid grid-cols-1 gap-4">
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-bullseye text-indigo-500 mr-2"></i>
                                    Specialization
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($plan['specialization']); ?></dd>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-clock text-indigo-500 mr-2"></i>
                                    Duration Type
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($plan['duration_type']); ?></dd>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-toggle-on text-indigo-500 mr-2"></i>
                                    Status
                                </dt>
                                <dd class="mt-1">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $plan['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas <?php echo $plan['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    
                    <!-- Nutritional Values -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-chart-pie text-indigo-600 mr-2"></i>
                            Nutritional Values
                        </h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-fire text-red-500 mr-2"></i>
                                    Total Calories
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo number_format($plan['calories']); ?> kcal</dd>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-drumstick-bite text-blue-500 mr-2"></i>
                                    Protein
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo number_format($plan['protein']); ?>g</dd>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-bread-slice text-yellow-500 mr-2"></i>
                                    Carbs
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo number_format($plan['carbs']); ?>g</dd>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
                                <dt class="text-sm font-medium text-gray-500 flex items-center">
                                    <i class="fas fa-cheese text-orange-500 mr-2"></i>
                                    Fats
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo number_format($plan['fats']); ?>g</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Meals Section -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Meals</h3>
            </div>
            
            <?php if (empty($meals)): ?>
                <div class="p-6 text-center text-gray-500">
                    No meals added to this plan yet.
                    <a href="add_meals.php?plan_id=<?php echo $plan_id; ?>" class="text-indigo-600 hover:text-indigo-800 ml-2">
                        Add meals
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
                                    <i class="fas fa-utensils mr-1"></i> Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-tag mr-1"></i> Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-align-left mr-1"></i> Description
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-fire mr-1"></i> Calories
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-drumstick-bite mr-1"></i> Protein
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-bread-slice mr-1"></i> Carbs
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-cheese mr-1"></i> Fats
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($meals as $meal): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $meal['meal_order']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                        <?php echo htmlspecialchars($meal['meal_type']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($meal['meal_name']); ?></span>
                                            <?php if ($meal['image_path']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($meal['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($meal['meal_name']); ?>"
                                                     class="mt-2 w-24 h-24 object-cover rounded-lg cursor-pointer hover:opacity-90 transition-opacity duration-200"
                                                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($meal['meal_name']); ?>')">
                                            <?php else: ?>
                                                <div class="mt-2 w-24 h-24 flex items-center justify-center bg-gray-100 rounded-lg">
                                                    <i class="fas fa-utensils text-3xl text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($meal['description']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($meal['calories']); ?> kcal
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($meal['protein']); ?>g
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($meal['carbs']); ?>g
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($meal['fats']); ?>g
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Totals Row -->
                            <tr class="bg-gray-50">
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <i class="fas fa-calculator mr-2"></i> Total
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <i class="fas fa-fire text-red-500 mr-1"></i> <?php echo number_format($total_calories); ?> kcal
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <i class="fas fa-drumstick-bite text-blue-500 mr-1"></i> <?php echo number_format($total_protein); ?>g
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <i class="fas fa-bread-slice text-yellow-500 mr-1"></i> <?php echo number_format($total_carbs); ?>g
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <i class="fas fa-cheese text-orange-500 mr-1"></i> <?php echo number_format($total_fats); ?>g
                                </td>
                            </tr>
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