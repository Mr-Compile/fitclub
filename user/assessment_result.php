<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get assessment data with user information
$query = "SELECT pa.*, u.fullname 
          FROM pre_assessments pa 
          JOIN users u ON pa.user_id = u.id 
          WHERE pa.id = :assessment_id AND pa.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":assessment_id", $_GET['id']);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    header("Location: dashboard.php");
    exit();
}

// Get health conditions
$query = "SELECT hc.name 
          FROM user_health_conditions uhc 
          JOIN health_condition_types hc ON uhc.condition_id = hc.id 
          WHERE uhc.pre_assessment_id = :assessment_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":assessment_id", $_GET['id']);
$stmt->execute();
$health_conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get BMI category and color
function getBMICategory($bmi) {
    if ($bmi < 18.5) {
        return [
            'category' => 'Underweight',
            'class' => 'text-yellow-600',
            'color' => 'yellow',
            'gauge_color' => '#F59E0B',
            'gauge_percentage' => ($bmi / 18.5) * 25 // Calculate percentage within underweight range
        ];
    } elseif ($bmi < 25) {
        return [
            'category' => 'Normal weight',
            'class' => 'text-green-600',
            'color' => 'green',
            'gauge_color' => '#10B981',
            'gauge_percentage' => 25 + (($bmi - 18.5) / 6.5) * 25 // Calculate percentage within normal range
        ];
    } elseif ($bmi < 30) {
        return [
            'category' => 'Overweight',
            'class' => 'text-orange-600',
            'color' => 'orange',
            'gauge_color' => '#F97316',
            'gauge_percentage' => 50 + (($bmi - 25) / 5) * 25 // Calculate percentage within overweight range
        ];
    } else {
        return [
            'category' => 'Obese',
            'class' => 'text-red-600',
            'color' => 'red',
            'gauge_color' => '#EF4444',
            'gauge_percentage' => 75 + (min(($bmi - 30) / 10, 1) * 25) // Calculate percentage within obese range, capped at 100%
        ];
    }
}

$bmi_category = getBMICategory($assessment['bmi']);
$recommended_specialization = '';

if ($assessment['bmi'] < 18.5) {
    $recommended_specialization = 'bulk';
} elseif ($assessment['bmi'] < 25) {
    $recommended_specialization = 'strength';
} else {
    $recommended_specialization = 'weight_loss';
}

// Get recommended coach based on BMI category
$query = "SELECT c.id, u.fullname, c.specialization, u.email
          FROM coaches c
          JOIN users u ON c.user_id = u.id
          WHERE c.specialization = :specialization
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":specialization", $recommended_specialization);
$stmt->execute();
$recommended_coach = $stmt->fetch(PDO::FETCH_ASSOC);

// Get coach's specialization description
$specialization_descriptions = [
    'bulk' => 'Specialized in muscle building and weight gain programs',
    'weight_loss' => 'Expert in weight management and fat loss programs',
    'strength' => 'Focused on strength training and muscle toning'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            position: relative;
            height: 40vh;
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
        .bmi-gauge {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(
                #F59E0B 0% 25%,    /* Underweight - Yellow */
                #10B981 25% 50%,   /* Normal - Green */
                #F97316 50% 75%,   /* Overweight - Orange */
                #EF4444 75% 100%   /* Obese - Red */
            );
            position: relative;
            margin: 0 auto;
        }
        .bmi-gauge::before {
            content: '';
            position: absolute;
            width: 160px;
            height: 160px;
            background: white;
            border-radius: 50%;
            top: 20px;
            left: 20px;
        }
        .bmi-pointer {
            position: absolute;
            width: 4px;
            height: 100px;
            background: #1F2937;
            bottom: 50%;
            left: 50%;
            transform-origin: bottom center;
            transform: rotate(calc(var(--bmi-rotation) * 1deg));
        }
        .bmi-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding: 0 1rem;
        }
        .bmi-label {
            text-align: center;
            font-size: 0.75rem;
            color: #6B7280;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .bmi-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-bottom: 4px;
        }
        .bmi-range {
            font-size: 0.7rem;
            color: #9CA3AF;
            margin-top: 2px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #6366F1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .result-card {
            transition: all 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="min-h-screen">
        <!-- Hero Section -->
        <div class="hero-section">
            <img src="../assets/images/men-bg.jpg" alt="Fitness training" class="hero-image" loading="eager">
            <div class="hero-overlay"></div>
            <div class="hero-content h-full flex items-center justify-center text-center">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="inline-block mb-12">
                        <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 text-shadow-strong">
                            Your Assessment Results<br>
                            <span class="text-indigo-400">Are Ready!</span>
                        </h1>
                        <p class="text-xl text-white mb-8 text-shadow-strong">
                            Discover your personalized fitness insights and recommended coach
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="result-card bg-white shadow rounded-lg p-6">
                    <div class="text-center mb-8">
                        <i class="fas fa-clipboard-check text-4xl text-indigo-600 mb-4"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Assessment Results</h1>
                        <p class="text-gray-600 mt-2">Your personalized fitness assessment</p>
                    </div>

                    <!-- BMI Gauge -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 text-center">Your BMI Analysis</h2>
                        <div class="bmi-gauge">
                            <div class="bmi-pointer" style="--bmi-rotation: <?php echo $bmi_category['gauge_percentage'] * 3.6; ?>"></div>
                        </div>
                        <div class="bmi-labels">
                            <div class="bmi-label">
                                <div class="bmi-color-dot" style="background-color: #F59E0B;"></div>
                                Underweight
                                <span class="bmi-range">&lt; 18.5</span>
                            </div>
                            <div class="bmi-label">
                                <div class="bmi-color-dot" style="background-color: #10B981;"></div>
                                Normal
                                <span class="bmi-range">18.5 - 24.9</span>
                            </div>
                            <div class="bmi-label">
                                <div class="bmi-color-dot" style="background-color: #F97316;"></div>
                                Overweight
                                <span class="bmi-range">25 - 29.9</span>
                            </div>
                            <div class="bmi-label">
                                <div class="bmi-color-dot" style="background-color: #EF4444;"></div>
                                Obese
                                <span class="bmi-range">≥ 30</span>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <p class="text-2xl font-bold <?php echo $bmi_category['class']; ?>">
                                BMI: <?php echo number_format($assessment['bmi'], 1); ?>
                            </p>
                            <p class="text-lg text-gray-600"><?php echo $bmi_category['category']; ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <?php
                                $bmi = $assessment['bmi'];
                                if ($bmi < 18.5) {
                                    echo "Your BMI indicates you are underweight. Consider consulting with a nutritionist for healthy weight gain strategies.";
                                } elseif ($bmi < 25) {
                                    echo "Your BMI is within the healthy range. Maintain your current lifestyle with regular exercise and balanced nutrition.";
                                } elseif ($bmi < 30) {
                                    echo "Your BMI indicates you are overweight. Focus on a balanced diet and regular exercise to achieve a healthy weight.";
                                } else {
                                    echo "Your BMI indicates obesity. It's recommended to consult with healthcare professionals for a personalized weight management plan.";
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-user-circle text-indigo-600 mr-2"></i>Personal Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-id-card text-gray-400 mr-1"></i>Name</p>
                                <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($assessment['fullname']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-birthday-cake text-gray-400 mr-1"></i>Age</p>
                                <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($assessment['age']); ?> years</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-venus-mars text-gray-400 mr-1"></i>Gender</p>
                                <p class="text-base font-medium text-gray-900"><?php echo ucfirst(htmlspecialchars($assessment['gender'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-weight text-gray-400 mr-1"></i>BMI</p>
                                <p class="text-base font-medium <?php echo $bmi_category['class']; ?>">
                                    <?php echo number_format($assessment['bmi'], 1); ?>
                                    (<?php echo $bmi_category['category']; ?>)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Health Information -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-heartbeat text-red-600 mr-2"></i>Health Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-ruler-vertical text-gray-400 mr-1"></i>Height</p>
                                <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($assessment['height']); ?> cm</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-weight-hanging text-gray-400 mr-1"></i>Weight</p>
                                <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($assessment['weight']); ?> kg</p>
                            </div>
                        </div>
                    </div>

                    <!-- Health Conditions -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-notes-medical text-red-600 mr-2"></i>Health Conditions
                        </h2>
                        <?php if (!empty($health_conditions)): ?>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach ($health_conditions as $condition): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                        <span class="text-gray-700"><?php echo htmlspecialchars($condition['name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600"><i class="fas fa-check-circle text-green-500 mr-2"></i>No health conditions reported</p>
                        <?php endif; ?>
                    </div>

                    <!-- Allergies -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-allergies text-yellow-600 mr-2"></i>Allergies
                        </h2>
                        <?php if (!empty($assessment['allergies'])): ?>
                            <p class="text-gray-700"><i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i><?php echo nl2br(htmlspecialchars($assessment['allergies'])); ?></p>
                        <?php else: ?>
                            <p class="text-gray-600"><i class="fas fa-check-circle text-green-500 mr-2"></i>No allergies reported</p>
                        <?php endif; ?>
                    </div>

                    <!-- Fitness Information -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-dumbbell text-blue-600 mr-2"></i>Fitness Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-chart-line text-gray-400 mr-1"></i>Fitness Level</p>
                                <p class="text-base font-medium text-gray-900"><?php echo ucfirst($assessment['fitness_level']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-bullseye text-gray-400 mr-1"></i>Fitness Goal</p>
                                <p class="text-base font-medium text-gray-900"><?php echo ucwords(str_replace('_', ' ', $assessment['fitness_goal'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><i class="fas fa-clock text-gray-400 mr-1"></i>Preferred Training Time</p>
                                <p class="text-base font-medium text-gray-900"><?php echo ucfirst($assessment['preferred_training_time']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <?php if (!empty($assessment['notes'])): ?>
                        <div class="bg-gray-50 rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                <i class="fas fa-sticky-note text-gray-600 mr-2"></i>Additional Notes
                            </h2>
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($assessment['notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Recommended Coach -->
                    <div class="bg-indigo-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-user-tie mr-2"></i>Recommended Coach
                        </h2>
                        <?php if ($recommended_coach): ?>
                            <div class="bg-white rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-circle text-3xl text-indigo-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="font-medium text-indigo-900"><?php echo htmlspecialchars($recommended_coach['fullname']); ?></h3>
                                        <p class="text-sm text-indigo-700">
                                            <?php echo ucwords(str_replace('_', ' ', $recommended_coach['specialization'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?php echo $specialization_descriptions[$recommended_coach['specialization']]; ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-2">
                                        <i class="fas fa-envelope mr-1"></i>
                                        <?php echo htmlspecialchars($recommended_coach['email']); ?>
                                    </p>
                                </div>
                                <div class="mt-4 p-3 bg-indigo-50 rounded-lg">
                                    <p class="text-sm text-gray-700">
                                        <i class="fas fa-lightbulb mr-1 text-yellow-500"></i>
                                        Recommended based on your BMI category: 
                                        <span class="font-medium <?php echo $bmi_category['class']; ?>">
                                            <?php echo $bmi_category['category']; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No specific coach recommendation available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <a href="dashboard.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors" id="continueBtn">
                            <i class="fas fa-arrow-right mr-2"></i>
                            Continue to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Show loading overlay when clicking Continue to Dashboard
        document.getElementById('continueBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('active');
            
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 500);
        });
    </script>
</body>
</html> 